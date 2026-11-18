<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Registration\ActivationAuthorityUnavailable;
use App\Domain\Registration\RegistrationActivationAuthority;
use App\Infrastructure\Registration\UnavailableRegistrationActivationAuthority;
use Tests\Support\RegistrationActivationFixtures;
use Tests\TestCase;

final class RegistrationActivationUnavailableTest extends TestCase
{
    use RegistrationActivationFixtures;

    public function test_runtime_default_is_unavailable_and_lookup_is_unknown_not_noncommit(): void
    {
        $authority = app(RegistrationActivationAuthority::class);
        self::assertInstanceOf(UnavailableRegistrationActivationAuthority::class, $authority);
        $operationId = $this->claimData()['operationId'];
        $nonce = hash('sha256', 'nonce');
        $result = $authority->lookup($operationId, $nonce)->resolveFor($operationId, $nonce, new \DateTimeImmutable('2026-11-18T00:00:03.000Z'), new \DateTimeImmutable('2026-11-18T00:10:00.000Z'));
        self::assertSame('unknown', $result['status']);
        self::assertNull($result['decision']);
        self::assertNull($result['absence']);
        $this->expectException(ActivationAuthorityUnavailable::class);
        $authority->decide($this->command());
    }
}
