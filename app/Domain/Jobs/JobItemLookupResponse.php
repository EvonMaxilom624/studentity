<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityDocument;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\ReceiptSchema;

/** Raw tagged lookup: unknown carries no proof; evidenced states must be configured-verifier checked. */
final readonly class JobItemLookupResponse
{
    use CanonicalAuthorityDocument;

    public array $data;

    public function __construct(array $data)
    {
        if (($data['status'] ?? null) === 'unknown') {
            $d = F::scalars($data, ['purpose', 'schemaVersion', 'status']);
            F::purpose($d, 'studentity.job.item-lookup-response.v1');
            $this->data = $d;

            return;
        }
        $fields = ['purpose', 'schemaVersion', 'status', 'jobOperationId', 'itemOperationId', 'requestFingerprint', 'queryFingerprint', 'nonce', 'checkedAt', 'historyComplete', 'authorityNamespace', 'issuerGeneration', 'signingKeyId', 'authorityRef', 'checkpointRef', 'proof'];
        F::keys($data, [...$fields, 'receipt']);
        $receipt = $data['receipt'];
        unset($data['receipt']);
        $d = F::scalars($data, $fields, [], ['historyComplete']);
        F::purpose($d, 'studentity.job.item-lookup-response.v1');
        F::issuer($d);
        if (! in_array($d['status'], ['committed', 'not-committed'], true) || ($d['status'] === 'committed') !== ($receipt !== null)) {
            throw new ReceiptContractViolation('Contradictory lookup status and receipt.');
        }
        foreach (['jobOperationId', 'itemOperationId'] as $f) {
            F::uuid($d[$f]);
        }foreach (['requestFingerprint', 'queryFingerprint', 'nonce'] as $f) {
            F::digest($d[$f]);
        }foreach (['authorityRef', 'checkpointRef'] as $f) {
            F::reference($d[$f]);
        }ReceiptSchema::time($d['checkedAt']);
        $this->data = $d + ['receipt' => $receipt === null ? null : (new JobItemReceipt(F::object($receipt)))->data];
    }
}
