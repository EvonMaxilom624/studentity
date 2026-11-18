<?php

declare(strict_types=1);

namespace App\Domain\Security\Authority;

/**
 * In-memory continuity proof ONLY. Never authorizes access or confirms local replay.
 * Callers must reconcile every returned receipt before persisting any applied cursor.
 * No unsigned cursor import, trusted-baseline shortcut, durable store or provider exists.
 * The prototype retains operation/challenge sets in memory for the complete prefix.
 */
final readonly class AuthorityStreamCursor
{
    private function __construct(private AuthorityCheckpoint $barrier, private AuthorityStreamPolicy $policy, private string $position, private string $hash, private string $ref, private array $operations, private array $nonces, private array $changes = []) {}

    public static function fromCheckpoint(AuthorityCheckpoint $checkpoint, AuthorityStreamPolicy $policy, string $expectedNonce): self
    {
        $policy->verify($checkpoint, $expectedNonce);
        self::requireHistory($checkpoint);

        return new self($checkpoint, $policy, '0', AuthorityStreamSchema::ZERO_HASH, $checkpoint->data['position'] === '0' ? $checkpoint->data['checkpointRef'] : 'genesis', [], [$expectedNonce => true]);
    }

    public function advance(AuthorityChangePage $page, string $expectedNonce): self
    {
        $this->policy->verify($page, $expectedNonce);
        $d = $page->data;
        $b = $this->barrier->data;
        if (isset($this->nonces[$expectedNonce]) || $d['anchorRef'] !== $b['checkpointRef'] || $d['fromPosition'] !== $this->position || $d['fromHash'] !== $this->hash || $d['fromRef'] !== $this->ref || ReceiptSchema::time($d['issuedAt']) < ReceiptSchema::time($b['issuedAt'])) {
            throw new ReceiptContractViolation('Authority page replay, fork or checkpoint mismatch.');
        }
        $position = $this->position;
        $hash = $this->hash;
        $operations = $this->operations;
        $changes = [];
        foreach ($d['entries'] as $entry) {
            $next = AuthorityStreamSchema::next($position);
            if ($entry['position'] !== $next || $entry['previousHash'] !== $hash || AuthorityStreamSchema::compare($next, $b['position']) > 0) {
                throw new ReceiptContractViolation('Authority history gap or boundary mismatch.');
            }
            $command = RevocationCommand::fromJson($entry['commandWire']);
            $receipt = RevocationReceipt::fromJson($entry['receiptWire']);
            $verified = $this->policy->verifyReceipt($command, $receipt, ReceiptSchema::time($d['issuedAt']));
            $operation = $command->data['operationId'];
            $expectedHash = hash('sha256', CanonicalAuthorityJson::encode(['purpose' => 'studentity.authority.change-entry.v1', 'authorityNamespace' => $b['authorityNamespace'], 'issuerGeneration' => $b['issuerGeneration'], 'position' => $next, 'previousHash' => $hash, 'operationId' => $operation, 'requestFingerprint' => $command->fingerprint(), 'receiptFingerprint' => $receipt->fingerprint()]));
            if (isset($operations[$operation]) || ! hash_equals($expectedHash, $entry['entryHash'])) {
                throw new ReceiptContractViolation('Authority history duplicate or hash mismatch.');
            }
            $operations[$operation] = true;
            $position = $next;
            $hash = $expectedHash;
            $changes[] = ['position' => $position, 'command' => $command, 'receipt' => $verified];
        }
        $atBarrier = $position === $b['position'];
        if ($d['toPosition'] !== $position || $d['toHash'] !== $hash || ($atBarrier && ($d['toRef'] !== $b['checkpointRef'] || $hash !== $b['chainHash']))
            || (! $atBarrier && $d['toRef'] === $b['checkpointRef']) || ($changes === [] && (! $atBarrier || $d['toRef'] !== $this->ref)) || ($changes !== [] && $d['toRef'] === $this->ref)) {
            throw new ReceiptContractViolation('Authority page endpoint or completeness mismatch.');
        }

        return new self($this->barrier, $this->policy, $position, $hash, $d['toRef'], $operations, $this->nonces + [$expectedNonce => true], $changes);
    }

    public function refresh(AuthorityCheckpoint $checkpoint, string $expectedNonce): self
    {
        $this->policy->verify($checkpoint, $expectedNonce);
        self::requireHistory($checkpoint);
        $old = $this->barrier->data;
        $new = $checkpoint->data;
        $compare = AuthorityStreamSchema::compare($new['position'], $old['position']);
        // Finish the known barrier first: replacing an unverified barrier would
        // discard its committed hash and could hide an intermediate history fork.
        if (isset($this->nonces[$expectedNonce]) || $compare < 0 || ReceiptSchema::time($new['issuedAt']) < ReceiptSchema::time($old['issuedAt']) || ($compare === 0 && ($new['chainHash'] !== $old['chainHash'] || $new['checkpointRef'] !== $old['checkpointRef'])) || ($compare > 0 && (! $this->isFullyVerified() || $new['checkpointRef'] === $old['checkpointRef']))) {
            throw new ReceiptContractViolation('Authority checkpoint rollback, replay or fork.');
        }

        return new self($checkpoint, $this->policy, $this->position, $this->hash, $this->ref, $this->operations, $this->nonces + [$expectedNonce => true]);
    }

    public function position(): string
    {
        return $this->position;
    }

    /** Cryptographic prefix completeness, NOT local application or current positive authorization. */
    public function isFullyVerified(): bool
    {
        return $this->position === $this->barrier->data['position'] && $this->hash === $this->barrier->data['chainHash'];
    }

    /** Original commands plus verified receipts for THIS page, to reconcile idempotently. */
    public function changes(): array
    {
        return $this->changes;
    }

    private static function requireHistory(AuthorityCheckpoint $checkpoint): void
    {
        $d = $checkpoint->data;
        if ($d['minimumPosition'] !== '0' || ($d['position'] === '0' && $d['chainHash'] !== AuthorityStreamSchema::ZERO_HASH) || ($d['position'] !== '0' && $d['checkpointRef'] === 'genesis')) {
            throw new ReceiptContractViolation('Complete genesis history required; baseline approval missing.');
        }
    }
}
