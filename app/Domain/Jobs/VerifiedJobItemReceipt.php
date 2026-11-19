<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Security\Authority\AuthorityProofPolicy;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\ReceiptSchema;
use DateTimeImmutable;

/** History capability only. Consume with current caller-owned command, policy and observation bounds. */
final readonly class VerifiedJobItemReceipt
{
    private function __construct(private string $commandFingerprint, private JobItemReceipt $receipt) {}

    public static function verify(JobItemCommand $command, JobItemReceipt $receipt, AuthorityProofPolicy $policy, DateTimeImmutable $originalLower, DateTimeImmutable $observedAt): self
    {
        if (! $policy->binds($command->data['authorityNamespace'], $command->data['expectedIssuerGeneration']) || $originalLower > $observedAt) {
            throw new ReceiptContractViolation('Item receipt trust or observation window mismatch.');
        }
        $d = $receipt->data;
        foreach (['jobOperationId', 'itemOperationId', 'targetId', 'jobFingerprint', 'itemFingerprint', 'correlationId', 'requestedBy', 'jobType'] as $f) {
            if ($d[$f] !== $command->data[$f]) {
                throw new ReceiptContractViolation('Item receipt original command binding mismatch.');
            }
        }
        if ($d['requestFingerprint'] !== $command->fingerprint()) {
            throw new ReceiptContractViolation('Item receipt request fingerprint mismatch.');
        }
        $at = ReceiptSchema::time($d['committedAt']);
        if ($at < $originalLower || $at > $observedAt) {
            throw new ReceiptContractViolation('Item receipt commit outside original observation bounds.');
        }
        $policy->verify($d, $receipt->canonical(), 'committedAt');

        return new self($command->fingerprint(), $receipt);
    }

    public function outcomeFor(JobItemCommand $command, AuthorityProofPolicy $policy, DateTimeImmutable $originalLower, DateTimeImmutable $observedAt): JobItemOutcome
    {
        if ($this->commandFingerprint !== $command->fingerprint()) {
            throw new ReceiptContractViolation('Receipt capability command mismatch.');
        }
        self::verify($command, $this->receipt, $policy, $originalLower, $observedAt);

        return new JobItemOutcome($this->receipt->data['result'], $this->receipt->data['errorCode']);
    }

    public function __debugInfo(): array
    {
        return ['history' => 'redacted'];
    }
}
