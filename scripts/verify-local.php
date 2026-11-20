<?php

declare(strict_types=1);
/** Local verification only: no installs, migrations, cleanup, credentials or deployment. */
if (PHP_SAPI !== 'cli' || PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) {
    fwrite(STDERR, "Explicit PHP8.4 CLI required.\n");
    exit(1);
}
require dirname(__DIR__).'/vendor/autoload.php';
use App\Infrastructure\Verification\SourceArtifactManifest;
use App\Infrastructure\Verification\VerificationProfile;
use Symfony\Component\Process\Process;

$stage = 'preflight';
try {
    $profile = VerificationProfile::fromArguments(array_slice($argv, 1));
    $root = dirname(__DIR__);
    $testEnv = ['APP_ENV' => 'testing', 'MONGODB_DATABASE' => 'studentity_test_m0'];
    $xml = simplexml_load_file($root.'/phpunit.xml', SimpleXMLElement::class, LIBXML_NONET);
    if ($xml === false) {
        throw new RuntimeException('PHPUnit configuration unavailable.');
    }
    foreach ($xml->php->env as $entry) {
        $testEnv[(string) $entry['name']] = (string) $entry['value'];
    }
    $testEnv['APP_ENV'] = 'testing';
    $testEnv['MONGODB_DATABASE'] = 'studentity_test_m0';
    $preflight = new Process([PHP_BINARY, $root.'/scripts/verification-preflight.php'], $root, $testEnv);
    $preflight->setTimeout(20);
    $preflight->mustRun();
    if ($profile->preflightOnly) {
        echo "Local read-only test-target preflight passed.\n";
        exit(0);
    }

    $evidence = $root.'/storage/app/private/local-verification/'.bin2hex(random_bytes(16));
    if (! mkdir($evidence, 0700, true)) {
        throw new RuntimeException('Cannot create verification evidence.');
    }
    $stage = 'initial-input-manifest';
    $initial = new Process([PHP_BINARY, 'artisan', 'studentity:artifact-manifest', '--json'], $root, $testEnv);
    $initial->setTimeout(20);
    $initial->mustRun();
    $beforeManifest = json_decode($initial->getOutput(), true, 64, JSON_THROW_ON_ERROR);
    file_put_contents($evidence.'/source-inputs-before.json', $initial->getOutput(), LOCK_EX);
    $run = static function (array $cmd, string $log) use ($root, $testEnv) {
        $p = new Process($cmd, $root, basename($cmd[1] ?? '') === 'phpunit' ? $testEnv : null);
        $p->setTimeout(600);
        $p->run(static function ($type, $chunk) use ($log) {
            file_put_contents($log, $chunk, FILE_APPEND | LOCK_EX);
            echo $chunk;
        });
        if (! $p->isSuccessful()) {
            throw new RuntimeException('Verification stage failed; retained evidence.');
        }
    };
    $stage = 'tests';
    $run([PHP_BINARY, 'vendor/bin/phpunit', ...$profile->phpunitArguments()], $evidence.'/tests.log');
    $build = 'not_requested';
    if ($profile->build) {
        $stage = 'frontend-build';
        $run([PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm', 'run', 'build'], $evidence.'/build.log');
        $build = 'passed';
    }
    $stage = 'input-manifest';
    $p = new Process([PHP_BINARY, 'artisan', 'studentity:artifact-manifest', '--json'], $root);
    $p->setTimeout(20);
    $p->mustRun();
    $manifest = json_decode($p->getOutput(), true, 64, JSON_THROW_ON_ERROR);
    if (! SourceArtifactManifest::matches($beforeManifest, $manifest)) {
        throw new RuntimeException('Source inputs changed during verification.');
    }
    if ($manifest['deployable'] !== false) {
        throw new RuntimeException('Unexpected artifact claim.');
    }file_put_contents($evidence.'/source-inputs.json', $p->getOutput(), LOCK_EX);
    $summary = ['purpose' => 'studentity.local-verification.v1', 'profile' => $profile->strict ? 'strict-ci-candidate' : 'local', 'strictTests' => $profile->strict, 'tests' => 'passed', 'frontendBuild' => $build, 'inputDigest' => $manifest['digest'], 'sourceInputsUnchanged' => true, 'deployable' => false];
    file_put_contents($evidence.'/summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
    echo 'Local verification passed; deployable=false. Evidence: '.$evidence."\n";
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Local verification failed during {$stage}; inspect retained evidence if created.\n");
    exit(1);
}
