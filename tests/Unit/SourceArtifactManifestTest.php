<?php

namespace Tests\Unit;

use App\Infrastructure\Verification\SourceArtifactManifest;
use PHPUnit\Framework\TestCase;

final class SourceArtifactManifestTest extends TestCase
{
    public function test_explicit_ci_workflow_is_hashed_without_admitting_other_hidden_files(): void
    {
        $r = $this->root();
        mkdir($r.'/.github/workflows', 0700, true);
        file_put_contents($r.'/.github/workflows/ci-strict-candidate.yml', 'name: synthetic');
        file_put_contents($r.'/.github/private.json', 'synthetic-secret');
        $paths = ['.github/workflows/ci-strict-candidate.yml', '.github/private.json'];
        $before = SourceArtifactManifest::capture($r, $paths);
        self::assertSame(['.github/workflows/ci-strict-candidate.yml'], array_keys($before['files']));
        file_put_contents($r.'/.github/workflows/ci-strict-candidate.yml', 'name: changed');
        self::assertFalse(SourceArtifactManifest::matches($before, SourceArtifactManifest::capture($r, $paths)));
    }

    private function root(): string
    {
        $r = dirname(__DIR__, 2).'/storage/app/private/artifact-fixtures/'.bin2hex(random_bytes(8));
        mkdir($r.'/app', 0700, true);
        file_put_contents($r.'/app/fixture.php', '<?php // synthetic');
        file_put_contents($r.'/.env', 'synthetic-secret');
        file_put_contents($r.'/composer.lock', '{}');

        return $r;
    }

    public function test_deterministic_source_hashes_exclude_env_and_never_contain_file_contents(): void
    {
        $r = $this->root();
        $m = SourceArtifactManifest::capture($r, ['composer.lock', '.env', 'app/fixture.php']);
        self::assertFalse($m['deployable']);
        self::assertSame(['app/fixture.php', 'composer.lock'], array_keys($m['files']));
        self::assertSame($m, SourceArtifactManifest::capture($r, ['app/fixture.php', 'composer.lock', '.env']));
        self::assertStringNotContainsString('synthetic-secret', json_encode($m));
        self::assertStringNotContainsString('<?php', json_encode($m));
    }

    public function test_modified_added_or_removed_sources_fail_exact_manifest_comparison(): void
    {
        $r = $this->root();
        $m = SourceArtifactManifest::capture($r, ['app/fixture.php']);
        file_put_contents($r.'/app/fixture.php', '<?php // changed');
        self::assertFalse(SourceArtifactManifest::matches($m, SourceArtifactManifest::capture($r, ['app/fixture.php'])));
        file_put_contents($r.'/app/extra.php', '<?php');
        self::assertFalse(SourceArtifactManifest::matches($m, SourceArtifactManifest::capture($r, ['app/fixture.php', 'app/extra.php'])));
        self::assertFalse(SourceArtifactManifest::matches($m, SourceArtifactManifest::capture($r, [])));
    }

    public function test_hidden_source_named_json_cannot_export_secret_hashes(): void
    {
        $r = $this->root();
        file_put_contents($r.'/app/.private.json', 'synthetic-secret-hidden');
        $m = SourceArtifactManifest::capture($r, ['app/.private.json', 'app/fixture.php']);
        self::assertSame(['app/fixture.php'], array_keys($m['files']));
    }

    public function test_artisan_entrypoint_is_hashed_and_changes_invalidate_manifest(): void
    {
        $r = $this->root();
        file_put_contents($r.'/artisan', '<?php // synthetic entrypoint');
        $before = SourceArtifactManifest::capture($r, ['artisan', '.env']);
        self::assertSame(['artisan'], array_keys($before['files']));
        self::assertSame(hash_file('sha256', $r.'/artisan'), $before['files']['artisan']);
        file_put_contents($r.'/artisan', '<?php // changed entrypoint');
        self::assertFalse(SourceArtifactManifest::matches($before, SourceArtifactManifest::capture($r, ['artisan', '.env'])));
        self::assertFalse(SourceArtifactManifest::matches($before, SourceArtifactManifest::capture($r, [])));
        self::assertFalse($before['deployable']);
    }

    public function test_traversal_absolute_and_ambiguous_paths_are_rejected(): void
    {
        $r = $this->root();
        foreach (['../.env', 'app/../.env', '/etc/passwd', 'C:/tools/php84/php.ini', 'app\\fixture.php'] as $p) {
            try {
                SourceArtifactManifest::capture($r, [$p]);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);

                continue;
            }self::fail('Unsafe path accepted.');
        }
    }
}
