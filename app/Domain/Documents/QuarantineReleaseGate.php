<?php

declare(strict_types=1);

namespace App\Domain\Documents;

/** Fail closed; authorization to view/download is a separate mandatory gate. */
final class QuarantineReleaseGate
{
    public static function canEnterReview(
        string $objectState,
        string $versionSecurityState,
        bool $scannerAvailable,
        bool $mostRecentScanClean,
        bool $storedChecksumMatches,
    ): bool {
        return $objectState === 'cleared'
            && $versionSecurityState === 'cleared'
            && $scannerAvailable
            && $mostRecentScanClean
            && $storedChecksumMatches;
    }
}
