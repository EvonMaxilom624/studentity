<?php

declare(strict_types=1);

namespace App\Domain\Registration;

/** Independently authenticated/durable server port; no concrete positive provider is selected. */
interface RegistrationActivationAuthority
{
    /** Idempotent original command; returned DTO must pass configured decision AND reservation verification. */
    public function decide(ActivationCommand $command): ActivationDecision;

    /** Resolve every result with the current request context; never infer noncommit from local/offline state. */
    public function lookup(string $operationId, string $nonce): ActivationLookup;
}
