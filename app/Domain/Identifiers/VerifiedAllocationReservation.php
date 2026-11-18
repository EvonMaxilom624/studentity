<?php

declare(strict_types=1);

namespace App\Domain\Identifiers;

use App\Domain\Registration\RegistrationClaim;
use App\Domain\Security\Authority\AuthorityProofPolicy;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\ReceiptSchema;

/** Verified historical issuer statement, never independently current issuance permission. */
final readonly class VerifiedAllocationReservation
{
    private function __construct(public AllocationReservation $reservation) {}

    public static function verify(RegistrationClaim $claim, AllocationReservation $reservation, AuthorityProofPolicy $policy): self
    {
        $r = $reservation->data;
        $c = $claim->data;
        foreach (['operationId', 'userId', 'intentFingerprint'] as $field) {
            if (! hash_equals($r[$field], $c[$field])) {
                throw new ReceiptContractViolation('Allocation claim binding mismatch.');
            }
        }
        $policy->verify($r, $reservation->canonical(), 'committedAt');
        if (ReceiptSchema::time($r['committedAt']) < ReceiptSchema::time($c['verifiedAt'])) {
            throw new ReceiptContractViolation('Allocation precedes original verified claim.');
        }

        return new self($reservation);
    }

    public function allocation(): SystemIdAllocation
    {
        if (PHP_INT_SIZE !== 8) {
            throw new ReceiptContractViolation('Exact 64-bit allocation projection required.');
        }
        $d = $this->reservation->data;

        return new SystemIdAllocation($d['operationId'], $d['intentFingerprint'], $d['systemId'], $d['state'], (int) $d['revision']);
    }
}
