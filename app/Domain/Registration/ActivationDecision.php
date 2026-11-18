<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityDocument;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\ReceiptSchema;

/** Signed historical admission outcome; no local commit or current authorization is implied. */
final readonly class ActivationDecision
{
    use CanonicalAuthorityDocument;

    public array $data;

    public function __construct(#[\SensitiveParameter] array $data)
    {
        $d = F::scalars($data, ['purpose', 'schemaVersion', 'operationId', 'userId', 'intentFingerprint', 'claimFingerprint', 'sealedPayloadHash', 'noticeBundleFingerprint', 'requestFingerprint', 'allocationFingerprint', 'systemId', 'correlationId', 'decisionId', 'authorityNamespace', 'issuerGeneration', 'signingKeyId', 'authorityRef', 'checkpointRef', 'committedAt', 'result', 'proof']);
        F::purpose($d, 'studentity.registration.activation-decision.v1');
        F::issuer($d);
        foreach (['operationId', 'userId', 'correlationId', 'decisionId'] as $field) {
            F::uuid($d[$field]);
        }
        foreach (['intentFingerprint', 'claimFingerprint', 'sealedPayloadHash', 'noticeBundleFingerprint', 'requestFingerprint', 'allocationFingerprint'] as $field) {
            F::digest($d[$field]);
        }
        foreach (['authorityRef', 'checkpointRef'] as $field) {
            F::reference($d[$field]);
        }
        ReceiptSchema::time($d['committedAt']);
        if (! in_array($d['result'], ['allowed', 'rejected'], true) || ! preg_match('/\A[A-Za-z0-9:._-]{1,128}\z/D', $d['systemId'])) {
            throw new ReceiptContractViolation('Invalid activation decision result or ID.');
        }
        $this->data = $d;
    }
}
