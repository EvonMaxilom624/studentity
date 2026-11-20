<?php

namespace Tests\Feature;

use App\Infrastructure\Verification\SourceArtifactManifest;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ArtifactManifestCommandTest extends TestCase
{
    public function test_cli_captures_only_source_inputs_and_never_claims_deployment_acceptance(): void
    {
        self::assertSame(0, Artisan::call('studentity:artifact-manifest', ['--json' => true]));
        $m = json_decode(Artisan::output(), true, 64, JSON_THROW_ON_ERROR);
        self::assertFalse($m['deployable']);
        self::assertArrayHasKey('composer.lock', $m['files']);
        self::assertArrayHasKey('app/Providers/AppServiceProvider.php', $m['files']);
        self::assertArrayNotHasKey('.env', $m['files']);
        self::assertArrayNotHasKey('.env.example', $m['files']);
        self::assertTrue(SourceArtifactManifest::matches($m, SourceArtifactManifest::capture(base_path(), array_keys($m['files']))));
    }
}
