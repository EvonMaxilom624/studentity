<?php

declare(strict_types=1);

namespace App\Domain\Identity;

enum UserKind: string
{
    case Student = 'student';
    case Coordinator = 'coordinator';
    case SystemAdmin = 'system_admin';

    public function requiresSystemId(): bool
    {
        return $this === self::Student;
    }
}
