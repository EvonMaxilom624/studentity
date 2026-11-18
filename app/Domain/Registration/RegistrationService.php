<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use App\Domain\Identifiers\VerifiedAllocationReservation;
use App\Domain\Security\Authority\AuthorityProofPolicy;
use App\Infrastructure\Registration\MongoRegistrationActivationRepository;

/** Unbound lab coordinator. Persist original command before I/O; never hold a Mongo transaction across I/O. */
final readonly class RegistrationService
{
    public function __construct(private RegistrationActivationAuthority $authority, private MongoRegistrationActivationRepository $repository, private AuthorityProofPolicy $admissionPolicy, private AuthorityProofPolicy $allocationPolicy) {}

    public function complete(string $operationId, VerifiedAllocationReservation $current): string
    {
        $command = $this->repository->originalCommand($operationId);
        if (! $this->admissionPolicy->binds($command->data['authorityNamespace'], $command->data['expectedIssuerGeneration'])) {
            throw new ActivationAuthorityUnavailable('Current activation destination mismatch.');
        }
        $this->repository->assertCanSubmit($command);
        $decision = $this->authority->decide($command);

        return $this->repository->apply(VerifiedActivationDecision::verify($command, $decision, $this->admissionPolicy, $this->allocationPolicy), $current);
    }

    public function reconcile(string $operationId, string $nonce, \DateTimeImmutable $earliestCheck, \DateTimeImmutable $latestCheck, VerifiedAllocationReservation $current): string
    {
        $result = $this->authority->lookup($operationId, $nonce)->resolveFor($operationId, $nonce, $earliestCheck, $latestCheck);
        if ($result['status'] === 'unknown') {
            throw new ActivationAuthorityUnavailable('Activation outcome remains unknown.');
        }
        if ($result['status'] === 'committed') {
            return $this->repository->apply($result['decision'], $current);
        }

        VerifiedActivationAbsence::verify($result['absence']->data, $operationId, $nonce, $this->admissionPolicy, $earliestCheck);

        return $this->complete($operationId, $current);
    }
}
