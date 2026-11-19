<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Security\Authority\AuthorityProofPolicy;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\ReceiptSchema;
use DateTimeImmutable;

/** No public positive status. Every consumption rechecks current context, freshness and both proof layers. */
final readonly class VerifiedJobItemLookup
{
    private function __construct(private string $commandFingerprint, private string $queryFingerprint, private JobItemLookupResponse $response, private ?VerifiedJobItemReceipt $receipt) {}

    public static function verify(JobItemCommand $command, JobItemLookupQuery $query, JobItemLookupResponse $response, AuthorityProofPolicy $policy, DateTimeImmutable $originalLower, DateTimeImmutable $earliestLookup, DateTimeImmutable $observedAt): self
    {
        $query->assertFor($command);
        $issued = ReceiptSchema::time($query->data['issuedAt']);
        if (! $policy->binds($command->data['authorityNamespace'], $command->data['expectedIssuerGeneration']) || $originalLower > $observedAt || $earliestLookup > $observedAt || $issued < $originalLower || $issued < $earliestLookup || $issued > $observedAt) {
            throw new ReceiptContractViolation('Lookup trust or active challenge window mismatch.');
        }
        $d = $response->data;
        $receipt = null;
        if ($d['status'] !== 'unknown') {
            foreach (['jobOperationId', 'itemOperationId', 'requestFingerprint', 'nonce'] as $f) {
                if ($d[$f] !== $query->data[$f]) {
                    throw new ReceiptContractViolation('Lookup statement query binding mismatch.');
                }
            }
            $checked = ReceiptSchema::time($d['checkedAt']);
            if ($d['queryFingerprint'] !== $query->fingerprint() || $d['historyComplete'] !== true || $checked < $issued || $checked > $observedAt) {
                throw new ReceiptContractViolation('Lookup statement freshness or complete history mismatch.');
            }
            $policy->verify($d, $response->canonical(), 'checkedAt');
            if ($d['status'] === 'committed') {
                $receipt = VerifiedJobItemReceipt::verify($command, new JobItemReceipt($d['receipt']), $policy, $originalLower, $checked);
            }
        }

        return new self($command->fingerprint(), $query->fingerprint(), $response, $receipt);
    }

    public function resolveFor(JobItemCommand $command, JobItemLookupQuery $query, AuthorityProofPolicy $policy, DateTimeImmutable $originalLower, DateTimeImmutable $earliestLookup, DateTimeImmutable $observedAt): array
    {
        if ($this->commandFingerprint !== $command->fingerprint() || $this->queryFingerprint !== $query->fingerprint()) {
            throw new ReceiptContractViolation('Lookup capability active query mismatch.');
        }
        $fresh = self::verify($command, $query, $this->response, $policy, $originalLower, $earliestLookup, $observedAt);

        return ['status' => $fresh->response->data['status'], 'receipt' => $fresh->receipt];
    }

    public function __debugInfo(): array
    {
        return ['history' => 'redacted'];
    }
}
