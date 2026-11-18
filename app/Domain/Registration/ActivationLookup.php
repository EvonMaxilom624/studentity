<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\ReceiptSchema;
use DateTimeImmutable;

/** Lookup history only. Noncommit permits at most retry of the SAME original idempotent command. */
final readonly class ActivationLookup
{
    private function __construct(private string $status, private ?VerifiedActivationDecision $decision, private ?VerifiedActivationAbsence $absence) {}

    public static function unknown(): self
    {
        return new self('unknown', null, null);
    }

    public static function committed(string $operationId, VerifiedActivationDecision $decision): self
    {
        F::uuid($operationId);
        if ($decision->command->data['claim']['operationId'] !== $operationId) {
            throw new ReceiptContractViolation('Committed lookup operation mismatch.');
        }

        return new self('committed', $decision, null);
    }

    public static function notCommitted(string $operationId, string $expectedNonce, VerifiedActivationAbsence $absence): self
    {
        F::uuid($operationId);
        F::digest($expectedNonce);
        if ($absence->data['operationId'] !== $operationId || $absence->data['nonce'] !== $expectedNonce) {
            throw new ReceiptContractViolation('Noncommit lookup request mismatch.');
        }

        return new self('not-committed', null, $absence);
    }

    /** Consume only in the current server-owned query context, including cached result objects. */
    public function resolveFor(string $operationId, string $expectedNonce, DateTimeImmutable $earliestCheck, DateTimeImmutable $latestCheck): array
    {
        F::uuid($operationId);
        F::digest($expectedNonce);
        if ($earliestCheck > $latestCheck) {
            throw new ReceiptContractViolation('Lookup observation bounds mismatch.');
        }
        if ($this->decision !== null && $this->decision->command->data['claim']['operationId'] !== $operationId) {
            throw new ReceiptContractViolation('Committed lookup operation mismatch.');
        }
        if ($this->absence !== null) {
            $data = $this->absence->data;
            $checkedAt = ReceiptSchema::time($data['checkedAt']);
            if ($data['operationId'] !== $operationId || $data['nonce'] !== $expectedNonce || $checkedAt < $earliestCheck || $checkedAt > $latestCheck) {
                throw new ReceiptContractViolation('Noncommit lookup request or observation mismatch.');
            }
        }

        return ['status' => $this->status, 'decision' => $this->decision, 'absence' => $this->absence];
    }
}
