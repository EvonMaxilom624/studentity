<?php

declare(strict_types=1);

namespace App\Domain\Governance;

/** Trusted server port, never a client-selected callback or restored approval row. */
interface CurrentApprovalAuthority
{
    public function isSynthetic(): bool;

    public function confirms(GateApproval $approval, string $feature, int $now): bool;
}
