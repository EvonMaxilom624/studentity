<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Domain\Jobs\JobExecutionAuthority;
use App\Domain\Jobs\JobHandlerUnavailable;
use App\Domain\Jobs\JobItemCommand;
use App\Domain\Jobs\JobItemLookupQuery;
use App\Domain\Jobs\JobItemLookupResponse;
use App\Domain\Jobs\JobItemReceipt;

final class UnavailableJobExecutionAuthority implements JobExecutionAuthority
{
    public function execute(JobItemCommand $command): JobItemReceipt
    {
        throw new JobHandlerUnavailable('Job execution authority unavailable.');
    }

    public function lookup(JobItemLookupQuery $query): JobItemLookupResponse
    {
        return new JobItemLookupResponse(['purpose' => 'studentity.job.item-lookup-response.v1', 'schemaVersion' => 1, 'status' => 'unknown']);
    }
}
