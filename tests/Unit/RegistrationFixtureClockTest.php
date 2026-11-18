<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\RegistrationTransactionFixtures;

final class RegistrationFixtureClockTest extends TestCase
{
    use RegistrationTransactionFixtures;

    public function test_payload_acknowledgment_uses_supplied_clock_in_utc_before_former_fixed_date(): void
    {
        $observation = new \DateTimeImmutable('2024-02-03T04:05:06.789+08:00');
        $payload = $this->payload(observedAt: $observation);
        self::assertSame('2024-02-02T20:04:06.789Z', $payload->fields()['acknowledged_at']);
        self::assertSame('2024-02-03T04:05:06.789+08:00', $observation->format('Y-m-d\TH:i:s.vP'));
    }

    public function test_default_fixture_acknowledgment_is_relative_to_current_observation(): void
    {
        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $payload = $this->payload();
        $after = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $ack = new \DateTimeImmutable($payload->fields()['acknowledged_at']);
        self::assertGreaterThanOrEqual($before->modify('-1 minute')->format('Y-m-d\TH:i:s.v\Z'), $ack->format('Y-m-d\TH:i:s.v\Z'));
        self::assertLessThanOrEqual($after->modify('-1 minute')->format('Y-m-d\TH:i:s.v\Z'), $ack->format('Y-m-d\TH:i:s.v\Z'));
    }
}
