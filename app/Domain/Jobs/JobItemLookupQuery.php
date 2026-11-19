<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityDocument;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\ReceiptSchema;

/** Fresh server-owned query; never take the expected command/nonce/clock from a response. */
final readonly class JobItemLookupQuery
{
    use CanonicalAuthorityDocument;

    public array $data;

    public function __construct(array $data)
    {
        $d = F::scalars($data, ['purpose', 'schemaVersion', 'authorityNamespace', 'expectedIssuerGeneration', 'jobOperationId', 'itemOperationId', 'requestFingerprint', 'nonce', 'issuedAt']);
        F::purpose($d, 'studentity.job.item-lookup-query.v1');
        F::issuer($d, true);
        foreach (['jobOperationId', 'itemOperationId'] as $f) {
            F::uuid($d[$f]);
        }foreach (['requestFingerprint', 'nonce'] as $f) {
            F::digest($d[$f]);
        }ReceiptSchema::time($d['issuedAt']);
        $this->data = $d;
    }

    public static function forCommand(JobItemCommand $command, string $nonce, string $issuedAt): self
    {
        $d = $command->data;

        return new self(['purpose' => 'studentity.job.item-lookup-query.v1', 'schemaVersion' => 1, 'authorityNamespace' => $d['authorityNamespace'], 'expectedIssuerGeneration' => $d['expectedIssuerGeneration'], 'jobOperationId' => $d['jobOperationId'], 'itemOperationId' => $d['itemOperationId'], 'requestFingerprint' => $command->fingerprint(), 'nonce' => $nonce, 'issuedAt' => $issuedAt]);
    }

    public function assertFor(JobItemCommand $command): void
    {
        if (self::forCommand($command, $this->data['nonce'], $this->data['issuedAt'])->wire() !== $this->wire()) {
            throw new ReceiptContractViolation('Lookup query original command mismatch.');
        }
    }
}
