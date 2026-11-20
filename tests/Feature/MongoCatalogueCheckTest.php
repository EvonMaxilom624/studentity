<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class MongoCatalogueCheckTest extends TestCase
{
    public function test_live_metadata_check_is_read_only_and_matches_both_accepted_schemas(): void
    {
        $p = new Process([PHP_BINARY, base_path('scripts/check-mongo-catalogue.php')], base_path(), ['APP_ENV' => 'testing', 'MONGODB_URI' => config('database.connections.mongodb.dsn')]);
        $p->setTimeout(20);
        $p->run();
        self::assertSame(0, $p->getExitCode(), $p->getOutput().$p->getErrorOutput());
        $report = json_decode($p->getOutput(), true, 64, JSON_THROW_ON_ERROR);
        self::assertTrue($report['readOnly']);
        self::assertSame(['studentity_dev_m0', 'studentity_test_m0'], array_column($report['databases'], 'database'));
        foreach ($report['databases'] as $db) {
            self::assertSame(62, $db['collections']);
            self::assertSame(128, $db['indexes']);
        }
    }

    public function test_wrong_environment_and_remote_uri_are_rejected_without_reflecting_secrets(): void
    {
        foreach ([['APP_ENV' => 'production'], ['APP_ENV' => 'testing', 'MONGODB_URI' => 'mongodb://synthetic-secret@remote.invalid/?replicaSet=rs0']] as $env) {
            $p = new Process([PHP_BINARY, base_path('scripts/check-mongo-catalogue.php')], base_path(), $env);
            $p->setTimeout(10);
            $p->run();
            self::assertSame(1, $p->getExitCode());
            self::assertStringNotContainsString('synthetic-secret', $p->getOutput().$p->getErrorOutput());
        }
    }
}
