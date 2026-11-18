<?php

declare(strict_types=1);

namespace App\Domain\Security\Authority;

use Closure;
use DateTimeImmutable;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;

/** Isolated receipt contract. No live service binds it and it never supplies current positive authority. */
final readonly class VerifiedRevocationReceipt
{
    private function __construct(public RevocationReceipt $receipt) {}

    /** Trust inputs/proof callback/deadline MUST be server configuration, never request/receipt choices. */
    public static function verify(RevocationCommand $command, RevocationReceipt $receipt, string $namespace, string $generation, array $keyIds, Closure $verifyProof, DateTimeImmutable $latestCommit): self
    {
        $c = $command->data;
        $r = $receipt->data;
        if ($namespace !== $c['authorityNamespace'] || $namespace !== $r['authorityNamespace'] || $generation !== $c['expectedIssuerGeneration'] || $generation !== $r['issuerGeneration'] || ! in_array($r['signingKeyId'], $keyIds, true)) {
            throw new ReceiptContractViolation('Untrusted authority issuer.');
        }
        foreach (['operationId', 'subjectUserId', 'sessionDigest', 'securityEpoch', 'action', 'correlationId'] as $field) {
            if (! hash_equals($c[$field], $r[$field])) {
                throw new ReceiptContractViolation('Authority receipt binding mismatch.');
            }
        }
        if (! hash_equals($command->fingerprint(), $r['requestFingerprint']) || ReceiptSchema::time($r['committedAt']) > $latestCommit) {
            throw new ReceiptContractViolation('Authority receipt binding or time mismatch.');
        }
        try {
            $valid = $verifyProof($receipt->canonical(), $r['proof'], $r['signingKeyId']);
        } catch (\Throwable) {
            throw new ReceiptContractViolation('Authority receipt proof unavailable.');
        }
        if ($valid !== true) {
            throw new ReceiptContractViolation('Invalid authority receipt proof.');
        }

        return new self($receipt);
    }

    public function journal(): array
    {
        $r = $this->receipt->data;

        return ['_id' => $r['operationId'], 'subject_user_id' => $r['subjectUserId'], 'event_kind' => 'session.revoked',
            'authority_ref' => 'urn:studentity:authority:'.rawurlencode($r['authorityNamespace']).':'.rawurlencode($r['issuerGeneration']).':'.$r['receiptId'],
            'recovery_anchor_ref' => $r['checkpointRef'], 'event_hash' => new Binary(hex2bin($this->receipt->fingerprint())),
            'occurred_at' => new UTCDateTime(ReceiptSchema::time($r['committedAt'])), 'schema_version' => 1, 'external_anchor_status' => 'anchored'];
    }

    public function audit(): array
    {
        $r = $this->receipt->data;
        $doc = ['_id' => $r['operationId'], 'action' => 'session.revocation.confirmed', 'target_type' => 'user_session',
            'correlation_id' => $r['correlationId'], 'occurred_at' => $r['committedAt'], 'schema_version' => 1,
            'event_redacted' => (object) ['subject_user_id' => $r['subjectUserId'], 'hash_scheme' => 'jcs-receipt-audit-v1', 'ability' => 'session.revoke', 'allowed' => true, 'reason' => 'revoked', 'journal_id' => $r['operationId'], 'receipt_hash' => $this->receipt->fingerprint(), 'security_epoch' => $r['securityEpoch']]];
        $doc['event_hash'] = new Binary(hash('sha256', CanonicalAuthorityJson::encode($doc), true));
        $doc['occurred_at'] = new UTCDateTime(ReceiptSchema::time($r['committedAt']));

        return $doc;
    }
}
