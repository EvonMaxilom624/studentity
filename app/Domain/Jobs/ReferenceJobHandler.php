<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

interface ReferenceJobHandler
{
    /** Must reconcile/reuse item.resultId with this exact command/target on replay; no new operation ID. */
    public function handle(ReferenceJobCommand $command, array $item): JobItemOutcome;
}
