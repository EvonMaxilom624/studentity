<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use App\Domain\Identifiers\AllocationReservation;
use App\Domain\Identifiers\VerifiedAllocationReservation;
use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityDocument;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\ReceiptSchema;

/** Proposed original command. Parsing is structural; verify nested issuer proof at the trust boundary. */
final readonly class ActivationCommand
{
    use CanonicalAuthorityDocument;

    public array $data;

    public function __construct(#[\SensitiveParameter] array $data)
    {
        $fields = ['purpose', 'schemaVersion', 'authorityNamespace', 'expectedIssuerGeneration', 'preparedAt'];
        F::keys($data, [...$fields, 'claim', 'allocation']);
        $claim = new RegistrationClaim(F::object($data['claim']));
        $allocation = new AllocationReservation(F::object($data['allocation']));
        unset($data['claim'],$data['allocation']);
        $d = F::scalars($data, $fields);
        F::purpose($d, 'studentity.registration.activation-command.v1');
        F::issuer($d, true);
        foreach (['operationId', 'userId', 'intentFingerprint'] as $field) {
            if ($claim->data[$field] !== $allocation->data[$field]) {
                throw new ReceiptContractViolation('Activation allocation binding mismatch.');
            }
        }
        $prepared = ReceiptSchema::time($d['preparedAt']);
        if ($allocation->data['state'] === 'retired' || ReceiptSchema::time($allocation->data['committedAt']) < ReceiptSchema::time($claim->data['verifiedAt']) || $prepared < ReceiptSchema::time($allocation->data['committedAt']) || $prepared < ReceiptSchema::time($claim->data['verifiedAt']) || $prepared >= ReceiptSchema::time($claim->data['processingDeadline'])) {
            throw new ReceiptContractViolation('Activation preparation state or time mismatch.');
        }
        $this->data = $d + ['claim' => $claim->data, 'allocation' => $allocation->data];
    }

    public static function prepare(RegistrationClaim $claim, VerifiedAllocationReservation $allocation, string $namespace, string $generation, string $preparedAt): self
    {
        return new self(['purpose' => 'studentity.registration.activation-command.v1', 'schemaVersion' => 1, 'authorityNamespace' => $namespace, 'expectedIssuerGeneration' => $generation, 'preparedAt' => $preparedAt, 'claim' => $claim->data, 'allocation' => $allocation->reservation->data]);
    }
}
