<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\RevocationCommand;
use App\Domain\Security\Authority\RevocationReceipt;
use App\Domain\Security\Authority\VerifiedRevocationReceipt;
use Illuminate\Support\Str;

/** In-memory synthetic protocol model, NOT durable storage, recovery infrastructure or a production adapter. */
final class ReceiptAuthorityLab implements ReceiptAuthorityLabPort
{
    public bool $loseNextAck = false;

    public bool $unavailable = false;

    private string $key;

    private array $operations = [];

    public function __construct()
    {
        $this->key = random_bytes(32);
    }

    public function revoke(RevocationCommand $command): RevocationReceipt
    {
        if ($this->unavailable) {
            throw new \RuntimeException('Synthetic authority unavailable.');
        }
        $c = $command->data;
        if ($c['authorityNamespace'] !== 'synthetic-authority' || $c['expectedIssuerGeneration'] !== 'generation-1') {
            throw new ReceiptContractViolation('Synthetic authority binding mismatch.');
        }
        $existing = $this->operations[$c['operationId']] ?? null;
        if ($existing !== null) {
            if (! hash_equals($existing->data['requestFingerprint'], $command->fingerprint())) {
                throw new ReceiptContractViolation('Conflicting authority operation.');
            }

            return $existing;
        }
        $receipt = $this->sign(['purpose' => 'studentity.session-revocation.receipt.v1', 'schemaVersion' => 1,
            'operationId' => $c['operationId'], 'receiptId' => (string) Str::uuid(), 'authorityNamespace' => $c['authorityNamespace'],
            'issuerGeneration' => 'generation-1', 'signingKeyId' => 'lab-key', 'subjectUserId' => $c['subjectUserId'],
            'sessionDigest' => $c['sessionDigest'], 'action' => 'session.revoke', 'requestFingerprint' => $command->fingerprint(),
            'correlationId' => $c['correlationId'], 'securityEpoch' => $c['securityEpoch'], 'subjectVersion' => (string) (count($this->operations) + 1),
            'checkpointRef' => 'checkpoint-'.(count($this->operations) + 1), 'committedAt' => '2026-11-18T00:00:00.123Z', 'result' => 'revoked']);
        $this->operations[$c['operationId']] = $receipt;
        if ($this->loseNextAck) {
            $this->loseNextAck = false;
            throw new \RuntimeException('Synthetic acknowledgment lost.');
        }

        return $receipt;
    }

    /** Test signing hook also constructs valid-proof/wrong-binding negative fixtures. */
    public function sign(array $data): RevocationReceipt
    {
        unset($data['proof']);
        $unsigned = new RevocationReceipt($data + ['proof' => 'placeholder']);

        return new RevocationReceipt($data + ['proof' => hash_hmac('sha256', $unsigned->canonical(), $this->key)]);
    }

    public function verify(RevocationCommand $command, RevocationReceipt $receipt): VerifiedRevocationReceipt
    {
        return VerifiedRevocationReceipt::verify($command, $receipt, 'synthetic-authority', 'generation-1', ['lab-key'],
            fn (string $body, string $proof, string $keyId) => $keyId === 'lab-key' && hash_equals(hash_hmac('sha256', $body, $this->key), $proof),
            new \DateTimeImmutable('2026-11-18T00:00:01.000Z'));
    }

    public function lookup(string $operationId): array
    {
        if ($this->unavailable) {
            return ['status' => 'unknown'];
        }

        return isset($this->operations[$operationId]) ? ['status' => 'committed', 'receipt' => $this->operations[$operationId]] : ['status' => 'definitivelyNotCommitted'];
    }
}
