<?php

declare(strict_types=1);

namespace App\Infrastructure\Governance;

use App\Domain\Governance\CurrentApprovalAuthority;
use App\Domain\Governance\GateApproval;

final class UnavailableApprovalAuthority implements CurrentApprovalAuthority
{
    public function isSynthetic(): bool
    {
        return false;
    }

    public function confirms(GateApproval $approval, string $feature, int $now): bool
    {
        return false;
    }
}
