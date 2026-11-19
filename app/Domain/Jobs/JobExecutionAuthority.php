<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

/** Durable idempotent provider protocol only; no real provider or current business authority is selected. */
interface JobExecutionAuthority
{
    /** Returned raw receipt MUST pass configured current proof/original-command verification. */
    public function execute(JobItemCommand $command): JobItemReceipt;

    /** Returned raw history MUST be resolved against the caller-owned fresh challenge and current policy. */
    public function lookup(JobItemLookupQuery $query): JobItemLookupResponse;
}
