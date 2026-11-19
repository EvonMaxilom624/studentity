<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityDocument;
use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\ReceiptContractViolation;

/** Deterministic original item command; structural parsing does not authorize execution. */
final readonly class JobItemCommand
{
    use CanonicalAuthorityDocument;

    public array $data;

    public function __construct(array $data)
    {
        $d = F::scalars($data, ['purpose', 'schemaVersion', 'authorityNamespace', 'expectedIssuerGeneration', 'jobOperationId', 'itemOperationId', 'targetId', 'jobFingerprint', 'itemFingerprint', 'correlationId', 'requestedBy', 'jobType'], ['requestedBy']);
        F::purpose($d, 'studentity.job.item-command.v1');
        F::issuer($d, true);
        foreach (['jobOperationId', 'itemOperationId', 'targetId', 'correlationId'] as $f) {
            F::uuid($d[$f]);
        }if ($d['requestedBy'] !== null) {
            F::uuid($d['requestedBy']);
        }
        foreach (['jobFingerprint', 'itemFingerprint'] as $f) {
            F::digest($d[$f]);
        }
        if ($d['jobType'] !== 'synthetic.reference-check.v1') {
            throw new ReceiptContractViolation('Unsupported item handler type.');
        }$this->data = $d;
    }

    public static function forItem(ReferenceJobCommand $job, array $item, string $namespace, string $generation): self
    {
        $i = F::scalars($item, ['targetId', 'resultId', 'auditId']);
        $found = false;
        foreach ($job->data['items'] as $original) {
            if (CanonicalAuthorityJson::encode($original) === CanonicalAuthorityJson::encode($i)) {
                $found = true;
                break;
            }
        }
        if (! $found) {
            throw new ReceiptContractViolation('Item is not an exact original job member.');
        }

        return new self(['purpose' => 'studentity.job.item-command.v1', 'schemaVersion' => 1, 'authorityNamespace' => $namespace, 'expectedIssuerGeneration' => $generation, 'jobOperationId' => $job->data['operationId'], 'itemOperationId' => $i['resultId'], 'targetId' => $i['targetId'], 'jobFingerprint' => $job->fingerprint(), 'itemFingerprint' => hash('sha256', CanonicalAuthorityJson::encode($i)), 'correlationId' => $job->data['correlationId'], 'requestedBy' => $job->data['requestedBy'], 'jobType' => $job->data['jobType']]);
    }
}
