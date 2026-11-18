<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use App\Domain\Authentication\RegistrationChallengeRepository;

/** Prerequisite only; no user creation, permanent ID allocation, mail or session issuance. */
final class RegistrationPrerequisiteService
{
    public function __construct(private RegistrationAdmissionPolicy $admission, private RegistrationChallengeRepository $challenges) {}

    public function issue(string $id, RegistrationIntent $intent, string $currentBundle, #[\SensitiveParameter] string $code, int $now): void
    {
        if (! $this->admission->allows($intent, $currentBundle, $now)) {
            throw new \RuntimeException('Registration prerequisites unavailable.');
        }
        $this->challenges->issue($id, $intent, $code);
    }

    public function verify(string $id, RegistrationIntent $intent, string $currentBundle, #[\SensitiveParameter] string $code, int $now): bool
    {
        return $this->admission->allows($intent, $currentBundle, $now) && $this->challenges->consume($id, $intent, 'registration.email', $code);
    }
}
