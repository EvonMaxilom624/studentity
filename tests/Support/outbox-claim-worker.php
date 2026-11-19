<?php

declare(strict_types=1);

use App\Domain\Jobs\JobPolicy;
use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Infrastructure\Jobs\MongoReferenceJobRepository;
use App\Infrastructure\Jobs\OutboxLabDatabase;
use Illuminate\Contracts\Console\Kernel;
use MongoDB\Client;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') {
    exit(1);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
try {
    $input = json_decode(stream_get_contents(STDIN, 8193), true, 8, JSON_THROW_ON_ERROR);
    F::keys($input, ['operationId', 'barrierId', 'slot']);
    F::uuid($input['operationId']);
    F::uuid($input['barrierId']);
    if (! in_array($input['slot'], [0, 1], true)) {
        throw new InvalidArgumentException;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $db = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase(OutboxLabDatabase::NAME, ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
    $repo = new MongoReferenceJobRepository($db, new JobPolicy('synthetic-job-policy-v1', 3, 60000), static fn () => true);
    $barrier = storage_path('app/private/fnd04-outbox-barriers/'.$input['barrierId']);
    if (! is_dir($barrier)) {
        throw new RuntimeException;
    }
    file_put_contents($barrier.'/'.$input['slot'].'.ready', 'ready', LOCK_EX);
    $deadline = hrtime(true) + 5_000_000_000;
    $wait = static function (array $paths) use ($deadline) {
        while (hrtime(true) < $deadline) {
            clearstatcache();
            $ready = true;
            foreach ($paths as $p) {
                $ready = $ready && is_file($p);
            }if ($ready) {
                return;
            }usleep(10000);
        }throw new RuntimeException;
    };
    $wait([$barrier.'/release']);
    file_put_contents($barrier.'/'.$input['slot'].'.entered', 'entered', LOCK_EX);
    $wait([$barrier.'/0.entered', $barrier.'/1.entered']);
    echo json_encode(['claimed' => $repo->claim($input['operationId']) !== null], JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable) {
    echo '{"unavailable":true}';
    exit(2);
}
