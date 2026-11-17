<?php

declare(strict_types=1);

namespace App\Domain\Certificates;

use InvalidArgumentException;

final class CertificateYearSlotKey
{
    public static function fromIds(string $studentId, string $academicYearId): string
    {
        $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        if (!preg_match($uuid, $studentId) || !preg_match($uuid, $academicYearId)) {
            throw new InvalidArgumentException('Student and year IDs must be UUID strings.');
        }
        return strtolower($studentId) . ':' . strtolower($academicYearId);
    }
}
