<?php

declare(strict_types=1);
use App\Domain\Authentication\ChallengePolicy;
use App\Domain\Authentication\ChallengeVerifier;
use App\Domain\Identifiers\AllocationConflict;
use App\Domain\Identifiers\SystemIdAllocation;
use App\Domain\Registration\RegistrationIntent;
use App\Infrastructure\Identity\MongoSystemIdAllocationRepository;
use App\Infrastructure\Registration\MongoRegistrationChallengeRepository;
use Illuminate\Contracts\Console\Kernel;
use MongoDB\Client;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') {
    exit(1);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$ok = false;
$exitCode = 0;
$code = null;
try {
    $wire = stream_get_contents(STDIN, 65537);
    if (strlen($wire) > 65536) {
        throw new InvalidArgumentException;
    }
    $input = json_decode($wire, true, 8, JSON_THROW_ON_ERROR);
    if (! is_array($input)) {
        throw new InvalidArgumentException;
    }
    $action = $input['action'] ?? null;
    $allowed = match ($action) {
        'project' => ['action', 'operationId', 'fingerprint', 'systemId'],'consume' => ['action', 'intent', 'challengeId', 'key', 'code'],default => throw new InvalidArgumentException
    };
    if (count($input) !== count($allowed) || array_diff(array_keys($input), $allowed)) {
        throw new InvalidArgumentException;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $dsn = config('database.connections.mongodb.dsn');
    if (! app()->environment('testing') || config('database.connections.mongodb.database') !== 'studentity_test_m0' || ! in_array(parse_url($dsn, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
        throw new InvalidArgumentException;
    }
    $db = (new Client($dsn))->selectDatabase('studentity_test_identity_prerequisites');
    if ($action === 'project') {
        (new MongoSystemIdAllocationRepository($db))->remember(new SystemIdAllocation($input['operationId'], $input['fingerprint'], $input['systemId'], 'reserved', 1));
        $ok = true;
    } else {
        $key = base64_decode($input['key'], true);
        if ($key === false || strlen($key) !== 32) {
            throw new InvalidArgumentException;
        }
        $repo = new MongoRegistrationChallengeRepository($db, new ChallengePolicy(3, 60000), new ChallengeVerifier($key));
        $ok = $repo->consume($input['challengeId'], new RegistrationIntent($input['intent']), 'registration.email', $input['code']);
    }
} catch (AllocationConflict) {
    $ok = false;
    $code = 'allocation_conflict';
} catch (Throwable) {
    $ok = false;
    $code = 'worker_unavailable';
    $exitCode = 2;
}
// Keys, codes, identifiers, exception objects and input never go to stdout/logs.
echo json_encode(['ok' => $ok] + ($code === null ? [] : ['code' => $code]), JSON_THROW_ON_ERROR), PHP_EOL;
exit($exitCode);
