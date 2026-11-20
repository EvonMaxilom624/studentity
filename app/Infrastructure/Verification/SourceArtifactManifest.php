<?php

declare(strict_types=1);

namespace App\Infrastructure\Verification;

use App\Domain\Security\Authority\CanonicalAuthorityJson;

/** Deterministic runtime/test/build input hashes only; never a signed or deployable artifact. */
final class SourceArtifactManifest
{
    private static function validPath(string $p): bool
    {
        return $p !== '' && ! str_contains($p, "\0") && ! str_contains($p, '\\') && ! str_starts_with($p, '/') && ! preg_match('/\A[A-Za-z]:/', $p) && ! in_array('..', explode('/', $p), true) && ! in_array('.', explode('/', $p), true) && ! str_contains($p, '//');
    }

    private static function allowed(string $p): bool
    {
        if ($p === '.github/workflows/ci-strict-candidate.yml') {
            return true; // Explicit public execution input; no other hidden files are admitted.
        }
        foreach (explode('/', $p) as $segment) {
            if (str_starts_with($segment, '.')) {
                return false;
            }
        }
        if (in_array($p, ['artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'phpunit.xml', 'vite.config.ts', 'vite.config.js', 'tsconfig.json', 'public/index.php'], true)) {
            return true;
        }

        return preg_match('/\A(?:app|bootstrap|config|routes|resources|tests|database|scripts)\/(?!.*(?:^|\/)\.)(?:[A-Za-z0-9_. -]+\/)*[A-Za-z0-9_. -]+\.(?:php|json|ts|tsx|js|jsx|css|xml|sql)\z/D', $p) === 1;
    }

    public static function capture(string $root, array $paths): array
    {
        $base = realpath($root);
        if ($base === false) {
            throw new \InvalidArgumentException('Source root unavailable.');
        }$files = [];
        foreach ($paths as $p) {
            if (! is_string($p) || ! self::validPath($p)) {
                throw new \InvalidArgumentException('Unsafe source path.');
            }if (! self::allowed($p)) {
                continue;
            }$want = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $p);
            $real = realpath($want);
            $normalize = fn ($v) => PHP_OS_FAMILY === 'Windows' ? strtolower(str_replace('\\', '/', $v)) : str_replace('\\', '/', $v);
            if ($real === false || ! is_file($real) || is_link($want) || $normalize($real) !== $normalize($want)) {
                throw new \InvalidArgumentException('Source path unavailable or aliased.');
            }$hash = @hash_file('sha256', $real);
            if ($hash === false) {
                throw new \InvalidArgumentException('Source digest unavailable.');
            }$files[$p] = $hash;
        }
        ksort($files);

        return ['purpose' => 'studentity.source-inputs.v1', 'deployable' => false, 'files' => $files, 'digest' => hash('sha256', CanonicalAuthorityJson::encode($files))];
    }

    public static function matches(array $expected, array $actual): bool
    {
        foreach ([$expected, $actual] as $m) {
            $keys = array_keys($m);
            sort($keys);
            if ($keys !== ['deployable', 'digest', 'files', 'purpose'] || $m['purpose'] !== 'studentity.source-inputs.v1' || $m['deployable'] !== false || ! is_array($m['files'])) {
                return false;
            }foreach ($m['files'] as $p => $h) {
                if (! is_string($p) || ! self::validPath($p) || ! self::allowed($p) || ! is_string($h) || ! preg_match('/\A[a-f0-9]{64}\z/D', $h)) {
                    return false;
                }
            }if (! is_string($m['digest']) || $m['digest'] !== hash('sha256', CanonicalAuthorityJson::encode($m['files']))) {
                return false;
            }
        }

        return CanonicalAuthorityJson::encode($expected) === CanonicalAuthorityJson::encode($actual);
    }
}
