<?php

declare(strict_types=1);

namespace App\Domain\Governance;

interface ApprovalRepository
{
    /** @return list<GateApproval> */
    public function forGate(string $gate, string $version): array;
}
