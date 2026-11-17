<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Domain\Certificates\CertificateYearSlotKey;
use App\Domain\Certificates\ClearanceClaimGate;
use App\Domain\Documents\QuarantineReleaseGate;
use App\Domain\Security\AuthorizationRecoveryGate;

final class M0DomainGatesTest extends TestCase
{
    public function test_slot_is_stable(): void
    {
        self::assertSame('123e4567-e89b-42d3-a456-426614174000:123e4567-e89b-42d3-a456-426614174001',
            CertificateYearSlotKey::fromIds('123e4567-e89b-42d3-a456-426614174000','123e4567-e89b-42d3-a456-426614174001'));
    }

    public function test_positive_clearance_fails_closed(): void
    {
        self::assertFalse(ClearanceClaimGate::mayClaimValid(true, true, true, true, true, true, false, true));
    }

    public function test_recovered_permission_without_revocation_evidence_is_denied(): void
    {
        self::assertFalse(AuthorizationRecoveryGate::canResume(true, false, true, true, true));
    }

    public function test_unscanned_upload_is_not_reviewable(): void
    {
        self::assertFalse(QuarantineReleaseGate::canEnterReview('quarantined', 'pending', false, false, true));
    }
}
