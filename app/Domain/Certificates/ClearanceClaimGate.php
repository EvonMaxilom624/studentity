<?php

declare(strict_types=1);

namespace App\Domain\Certificates;

/** AUD-12: no positive clearance without trustworthy live eligibility. */
final class ClearanceClaimGate
{
    public static function mayClaimValid(
        bool $authenticIssuance,
        bool $issuedLifecycle,
        bool $currentAcademicYear,
        bool $officialStatusEligible,
        bool $identityAndEnrollmentVerified,
        bool $applicableRequirementsApproved,
        bool $authoritativeDependenciesConsistent,
        bool $recoveryReconciliationCurrent,
    ): bool {
        return $authenticIssuance
            && $issuedLifecycle
            && $currentAcademicYear
            && $officialStatusEligible
            && $identityAndEnrollmentVerified
            && $applicableRequirementsApproved
            && $authoritativeDependenciesConsistent
            && $recoveryReconciliationCurrent;
    }
}
