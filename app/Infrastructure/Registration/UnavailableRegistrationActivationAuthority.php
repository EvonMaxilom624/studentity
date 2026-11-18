<?php

declare(strict_types=1);

namespace App\Infrastructure\Registration;

use App\Domain\Registration\ActivationAuthorityUnavailable;
use App\Domain\Registration\ActivationCommand;
use App\Domain\Registration\ActivationDecision;
use App\Domain\Registration\ActivationLookup;
use App\Domain\Registration\RegistrationActivationAuthority;
use App\Domain\Security\Authority\AuthorityContractFields;

final class UnavailableRegistrationActivationAuthority implements RegistrationActivationAuthority
{
    public function decide(ActivationCommand $command): ActivationDecision
    {
        throw new ActivationAuthorityUnavailable('Registration activation authority unavailable.');
    }

    public function lookup(string $operationId, string $nonce): ActivationLookup
    {
        AuthorityContractFields::uuid($operationId);
        AuthorityContractFields::digest($nonce);

        return ActivationLookup::unknown();
    }
}
