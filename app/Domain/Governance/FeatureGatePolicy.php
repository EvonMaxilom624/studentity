<?php

declare(strict_types=1);

namespace App\Domain\Governance;

final class FeatureGatePolicy
{
    private const GATES = ['registration' => 'G-05', 'coordinator-invitations' => 'G-09'];

    public function __construct(private ApprovalRepository $repository, private CurrentApprovalAuthority $authority, private bool $realData = true) {}

    /** Inputs are server-resolved feature/version and clock, not approval claims from a request. */
    public function decide(string $feature, string $version, int $now): FeatureGateDecision
    {
        $deny = static fn ($reason) => new FeatureGateDecision(false, $reason);
        if (! isset(self::GATES[$feature]) || $version === '' || strlen($version) > 256) {
            return $deny('approval_required');
        }
        try {
            if ($this->realData && $this->authority->isSynthetic()) {
                return $deny('synthetic_evidence');
            }
            $records = $this->repository->forGate(self::GATES[$feature], $version);
            if (count($records) !== 1 || ! $records[0] instanceof GateApproval) {
                return $deny('approval_required');
            }
            $record = $records[0];
            $d = $record->data;
            if ($d['gate'] !== self::GATES[$feature] || $d['version'] !== $version || $d['status'] !== 'approved' || $d['blocks'] !== $feature || $d['approvedAt'] > $now || $d['effectiveAt'] > $now || ! $this->authority->confirms($record, $feature, $now)) {
                return $deny('approval_required');
            }

            return new FeatureGateDecision(true, 'approval_current');
        } catch (\Throwable) {
            return $deny('approval_unavailable');
        }
    }
}
