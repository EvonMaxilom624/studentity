<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

/** Definitive synthetic result only. Unknown effects must never be converted to failed/succeeded. */
final readonly class JobItemOutcome
{
    public function __construct(public string $result, public string $errorCode)
    {
        if ((['succeeded' => 'none', 'failed' => 'synthetic_failure', 'skipped' => 'not_applicable'][$result] ?? null) !== $errorCode) {
            throw new \InvalidArgumentException('Unknown or incoherent synthetic outcome.');
        }
    }
}
