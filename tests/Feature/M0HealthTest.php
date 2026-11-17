<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class M0HealthTest extends TestCase
{
    public function test_health_route_is_synthetic_only(): void
    {
        $this->getJson('/m0/health')->assertOk()->assertExactJson([
            'status' => 'ok',
            'phase' => 'M0',
            'mode' => 'synthetic-only',
        ]);
    }
}
