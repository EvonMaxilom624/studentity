<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\AuthorityProofPolicy;
use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\ReceiptSchema;
use DateTimeImmutable;

/** Authenticated absence at a challenged complete-ledger observation, NOT future absence or a grant. */
final readonly class VerifiedActivationAbsence
{
    private function __construct(public array $data) {}

    public static function verify(#[\SensitiveParameter] array $data, string $operationId, string $expectedNonce, AuthorityProofPolicy $policy, DateTimeImmutable $earliestCheck): self
    {
        F::uuid($operationId);
        F::digest($expectedNonce);
        $d = F::scalars($data, ['purpose', 'schemaVersion', 'operationId', 'authorityNamespace', 'issuerGeneration', 'signingKeyId', 'nonce', 'checkedAt', 'checkpointRef', 'historyComplete', 'result', 'proof'], [], ['historyComplete']);
        F::purpose($d, 'studentity.registration.activation-absence.v1');
        F::uuid($d['operationId']);
        F::digest($d['nonce']);
        F::issuer($d);
        F::reference($d['checkpointRef']);
        if ($d['operationId'] !== $operationId || ! hash_equals($d['nonce'], $expectedNonce) || $d['historyComplete'] !== true || $d['result'] !== 'not-committed' || ReceiptSchema::time($d['checkedAt']) < $earliestCheck) {
            throw new ReceiptContractViolation('Noncommit observation scope, challenge or time mismatch.');
        }
        $body = $d;
        unset($body['proof']);
        $policy->verify($d, CanonicalAuthorityJson::encode($body), 'checkedAt');

        return new self($d);
    }

    public static function verifyJson(string $wire, string $operationId, string $expectedNonce, AuthorityProofPolicy $policy, DateTimeImmutable $earliestCheck): self
    {
        return self::verify(CanonicalAuthorityJson::decodeObject($wire), $operationId, $expectedNonce, $policy, $earliestCheck);
    }

    public function wire(): string
    {
        return CanonicalAuthorityJson::encode($this->data);
    }

    public function __debugInfo(): array
    {
        return ['purpose' => $this->data['purpose'], 'body' => 'redacted'];
    }
}
