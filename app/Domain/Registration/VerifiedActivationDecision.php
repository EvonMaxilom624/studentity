<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use App\Domain\Identifiers\AllocationReservation;
use App\Domain\Identifiers\VerifiedAllocationReservation;
use App\Domain\Security\Authority\AuthorityProofPolicy;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\ReceiptSchema;

/** Authenticated original outcome only. Runtime apply additionally needs trustworthy CURRENT state. */
final readonly class VerifiedActivationDecision
{
    private function __construct(public ActivationCommand $command, public ActivationDecision $decision, public VerifiedAllocationReservation $allocation) {}

    public static function verify(ActivationCommand $command, ActivationDecision $decision, AuthorityProofPolicy $admissionPolicy, AuthorityProofPolicy $allocationPolicy): self
    {
        $c = $command->data;
        $r = $decision->data;
        $claim = new RegistrationClaim($c['claim']);
        $raw = new AllocationReservation($c['allocation']);
        if (! $admissionPolicy->binds($c['authorityNamespace'], $c['expectedIssuerGeneration'])) {
            throw new ReceiptContractViolation('Untrusted activation command destination.');
        }
        $allocation = VerifiedAllocationReservation::verify($claim, $raw, $allocationPolicy);
        foreach (['operationId', 'userId', 'intentFingerprint', 'sealedPayloadHash', 'noticeBundleFingerprint', 'correlationId'] as $field) {
            if (! hash_equals($r[$field], $claim->data[$field])) {
                throw new ReceiptContractViolation('Activation decision claim mismatch.');
            }
        }
        foreach (['claimFingerprint' => $claim->fingerprint(), 'requestFingerprint' => $command->fingerprint(), 'allocationFingerprint' => $raw->fingerprint(), 'systemId' => $raw->data['systemId']] as $field => $expected) {
            if (! hash_equals($r[$field], $expected)) {
                throw new ReceiptContractViolation('Activation decision body mismatch.');
            }
        }
        $admissionPolicy->verify($r, $decision->canonical(), 'committedAt');
        $time = ReceiptSchema::time($r['committedAt']);
        if ($time < ReceiptSchema::time($c['preparedAt']) || ($r['result'] === 'allowed' && $time >= ReceiptSchema::time($claim->data['processingDeadline']))) {
            throw new ReceiptContractViolation('Activation decision ordering mismatch.');
        }

        return new self($command, $decision, $allocation);
    }
}
