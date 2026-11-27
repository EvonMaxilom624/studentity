<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Governance\ApprovalRepository;
use App\Domain\Governance\CurrentApprovalAuthority;
use App\Domain\Governance\FeatureGatePolicy;
use App\Domain\Governance\GateApproval;
use App\Infrastructure\Readiness\OperationalReadinessInspector;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

final class InstitutionalGateTraceabilityTest extends TestCase
{
    public function test_historical_lab_gate_alias_cannot_grant_real_data_admission_even_with_confirmation(): void
    {
        $record = new GateApproval(['id' => 'synthetic', 'gate' => 'G-05', 'version' => 'synthetic-v1', 'status' => 'approved', 'decisionRef' => 'synthetic', 'authority' => 'synthetic', 'evidence' => 'synthetic', 'effectiveAt' => 1, 'approvedAt' => 1, 'createdAt' => 1, 'schemaVersion' => 1, 'blocks' => 'registration']);
        $repository = $this->createStub(ApprovalRepository::class);
        $repository->method('forGate')->willReturn([$record]);
        $authority = $this->createStub(CurrentApprovalAuthority::class);
        $authority->method('isSynthetic')->willReturn(false);
        $authority->method('confirms')->willReturn(true);
        self::assertFalse((new FeatureGatePolicy($repository, $authority))->decide('registration', 'synthetic-v1', 2)->allowed);
        self::assertTrue((new FeatureGatePolicy($repository, $authority, false))->decide('registration', 'synthetic-v1', 2)->allowed);
    }

    public function test_notice_readiness_refers_to_finalized_privacy_gate_without_aliasing_retention_or_technical_acceptance(): void
    {
        $report = (new OperationalReadinessInspector(new Container, new Repository))->report();
        $notices = array_values(array_filter($report['blockers'], fn ($b) => $b['id'] === 'approved-notices'))[0];
        self::assertContains('G-04', $notices['basis']);
        self::assertNotContains('G-05', $notices['basis']);
        self::assertNotContains('G-09', $notices['basis']);
        self::assertStringNotContainsString('G-05 registration', implode(' ', $notices['requiredEvidence']));
        self::assertFalse($report['activationAllowed']);
    }
}
