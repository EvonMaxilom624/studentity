<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class LocalVerificationRunnerTest extends TestCase
{
    public function test_preflight_child_isolated_from_parent_dev_database_settings(): void
    {
        $p = new Process([PHP_BINARY, base_path('scripts/verify-local.php'), '--preflight-only'], base_path(), ['APP_ENV' => 'local', 'MONGODB_DATABASE' => 'studentity_dev_m0', 'DB_CONNECTION' => 'mongodb', 'DB_DATABASE' => 'synthetic-unsafe-parent-db']);
        $p->setTimeout(25);
        $p->run();
        self::assertSame(0, $p->getExitCode(), $p->getErrorOutput());
        self::assertStringContainsString('preflight passed', $p->getOutput());
    }

    public function test_remote_uri_is_rejected_before_tests_and_never_reflected(): void
    {
        $p = new Process([PHP_BINARY, base_path('scripts/verify-local.php'), '--preflight-only'], base_path(), ['MONGODB_URI' => 'mongodb://synthetic-secret@remote.invalid/?replicaSet=rs0']);
        $p->setTimeout(25);
        $p->run();
        self::assertSame(1, $p->getExitCode());
        self::assertStringNotContainsString('synthetic-secret', $p->getErrorOutput().$p->getOutput());
        self::assertStringContainsString('preflight', $p->getErrorOutput());
    }

    public function test_strict_candidate_cannot_report_success_from_preflight_only(): void
    {
        $p = new Process([PHP_BINARY, base_path('scripts/verify-local.php'), '--ci-candidate', '--preflight-only'], base_path());
        $p->setTimeout(10);
        $p->run();
        self::assertSame(1, $p->getExitCode());
        self::assertStringNotContainsString('passed', $p->getOutput());
    }
}
