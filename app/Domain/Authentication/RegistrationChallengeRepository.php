<?php

declare(strict_types=1);

namespace App\Domain\Authentication;

use App\Domain\Registration\RegistrationIntent;

interface RegistrationChallengeRepository
{
    public function issue(string $id, RegistrationIntent $intent, #[\SensitiveParameter] string $code): void;

    public function consume(string $id, RegistrationIntent $intent, string $purpose, #[\SensitiveParameter] string $code): bool;
}
