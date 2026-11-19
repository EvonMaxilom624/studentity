<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Jobs\JobExecutionAuthority;
use App\Domain\Jobs\JobHandlerUnavailable;
use App\Domain\Jobs\VerifiedJobItemLookup;
use App\Infrastructure\Jobs\UnavailableJobExecutionAuthority;
use Tests\Support\HandlerProtocolFixtures;
use Tests\TestCase;

final class JobExecutionUnavailableTest extends TestCase
{
    use HandlerProtocolFixtures;

    public function test_runtime_authority_default_is_unavailable_and_cannot_invent_noncommit(): void
    {
        $authority = app(JobExecutionAuthority::class);
        self::assertInstanceOf(UnavailableJobExecutionAuthority::class, $authority);
        $command = $this->itemCommand();
        $query = $this->protocolQuery();
        $response = $authority->lookup($query);
        $cap = VerifiedJobItemLookup::verify($command, $query, $response, $this->policy(), $this->at('00:00:00.000'), $this->at('00:00:59.000'), $this->at('00:02:00.000'));
        $r = $cap->resolveFor($command, $query, $this->policy(), $this->at('00:00:00.000'), $this->at('00:00:59.000'), $this->at('00:02:00.000'));
        self::assertSame('unknown', $r['status']);
        self::assertNull($r['receipt']);
        $this->expectException(JobHandlerUnavailable::class);
        $authority->execute($command);
    }
}
