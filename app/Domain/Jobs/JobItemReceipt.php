<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityDocument;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\ReceiptSchema;

/** Signed historical handler outcome; raw DTO until verified against caller-owned original command. */
final readonly class JobItemReceipt
{
    use CanonicalAuthorityDocument;

    public array $data;

    public function __construct(array $data)
    {
        $d = F::scalars($data, ['purpose', 'schemaVersion', 'jobOperationId', 'itemOperationId', 'targetId', 'jobFingerprint', 'itemFingerprint', 'requestFingerprint', 'correlationId', 'requestedBy', 'jobType', 'result', 'errorCode', 'receiptId', 'authorityNamespace', 'issuerGeneration', 'signingKeyId', 'authorityRef', 'checkpointRef', 'committedAt', 'proof'], ['requestedBy']);
        F::purpose($d, 'studentity.job.item-receipt.v1');
        F::issuer($d);
        foreach (['jobOperationId', 'itemOperationId', 'targetId', 'correlationId', 'receiptId'] as $f) {
            F::uuid($d[$f]);
        }if ($d['requestedBy'] !== null) {
            F::uuid($d['requestedBy']);
        }
        foreach (['jobFingerprint', 'itemFingerprint', 'requestFingerprint'] as $f) {
            F::digest($d[$f]);
        }foreach (['authorityRef', 'checkpointRef'] as $f) {
            F::reference($d[$f]);
        }
        ReceiptSchema::time($d['committedAt']);
        new JobItemOutcome($d['result'], $d['errorCode']);
        if ($d['jobType'] !== 'synthetic.reference-check.v1') {
            throw new ReceiptContractViolation('Unsupported receipt handler type.');
        }$this->data = $d;
    }
}
