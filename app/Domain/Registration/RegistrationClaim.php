<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityDocument;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\ReceiptSchema;

/** Original durable-claim wire contract, not proof that any database transaction committed. */
final readonly class RegistrationClaim
{
    use CanonicalAuthorityDocument;

    public array $data;

    public function __construct(#[\SensitiveParameter] array $data)
    {
        $fields = ['purpose', 'schemaVersion', 'operationId', 'intentId', 'userId', 'intentFingerprint', 'sealedPayloadHash', 'noticeBundleFingerprint', 'challengeId', 'verifiedAt', 'processingDeadline', 'correlationId'];
        F::keys($data, [...$fields, 'childIds']);
        $children = F::scalars(F::object($data['childIds']), ['emailId', 'credentialId', 'termsAckId', 'privacyAckId', 'auditId', 'journalId'], ['credentialId']);
        foreach ($children as $id) {
            if ($id !== null) {
                F::uuid($id);
            }
        }
        unset($data['childIds']);
        $copy = F::scalars($data, $fields);
        F::purpose($copy, 'studentity.registration.claim.v1');
        foreach (['operationId', 'intentId', 'userId', 'challengeId', 'correlationId'] as $field) {
            F::uuid($copy[$field]);
        }
        foreach (['intentFingerprint', 'sealedPayloadHash', 'noticeBundleFingerprint'] as $field) {
            F::digest($copy[$field]);
        }
        if ($copy['operationId'] !== $copy['intentId'] || $copy['operationId'] !== $copy['userId'] || $children['termsAckId'] === $children['privacyAckId'] || ReceiptSchema::time($copy['processingDeadline']) <= ReceiptSchema::time($copy['verifiedAt'])) {
            throw new ReceiptContractViolation('Registration claim identity or ordering mismatch.');
        }
        $this->data = $copy + ['childIds' => $children];
    }
}
