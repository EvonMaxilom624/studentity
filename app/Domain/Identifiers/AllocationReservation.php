<?php

declare(strict_types=1);

namespace App\Domain\Identifiers;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityDocument;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\ReceiptSchema;

/** Signed reservation DTO; construction alone establishes no trust/currentness. */
final readonly class AllocationReservation
{
    use CanonicalAuthorityDocument;

    public array $data;

    public function __construct(#[\SensitiveParameter] array $data)
    {
        $d = F::scalars($data, ['purpose', 'schemaVersion', 'operationId', 'userId', 'intentFingerprint', 'systemId', 'state', 'revision', 'authorityNamespace', 'issuerGeneration', 'signingKeyId', 'authorityRef', 'committedAt', 'proof']);
        F::purpose($d, 'studentity.system-id.reservation.v1');
        F::uuid($d['operationId']);
        F::uuid($d['userId']);
        F::digest($d['intentFingerprint']);
        F::issuer($d);
        F::reference($d['authorityRef']);
        F::revision($d['revision']);
        ReceiptSchema::time($d['committedAt']);
        if (! preg_match('/\A[A-Za-z0-9:._-]{1,128}\z/D', $d['systemId']) || ! in_array($d['state'], ['reserved', 'issued', 'retired'], true)) {
            throw new ReceiptContractViolation('Invalid allocation reservation state or ID.');
        }
        $this->data = $d;
    }
}
