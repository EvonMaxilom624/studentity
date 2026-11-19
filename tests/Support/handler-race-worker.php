<?php

declare(strict_types=1);

use App\Domain\Jobs\JobItemCommand;
use App\Domain\Jobs\JobItemLookupQuery;
use App\Domain\Jobs\JobPolicy;
use App\Domain\Jobs\ReferenceJobCommand;
use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\AuthorityProofPolicy;
use App\Infrastructure\Jobs\HandlerLabDatabase;
use App\Infrastructure\Jobs\MongoJobItemStore;
use App\Infrastructure\Jobs\MongoReferenceJobRepository;
use App\Infrastructure\Jobs\MongoSyntheticJobExecutionAuthority;
use App\Infrastructure\Jobs\OutboxLabDatabase;
use App\Infrastructure\Jobs\ReceiptAwareJobHandler;
use App\Infrastructure\Jobs\ReferenceJobWorker;
use Illuminate\Contracts\Console\Kernel;
use MongoDB\Client;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') {
    exit(1);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
try {
    $input = json_decode(stream_get_contents(STDIN, 32769), true, 16, JSON_THROW_ON_ERROR);
    F::keys($input, ['mode', 'command', 'directory', 'number']);
    if (! in_array($input['mode'], ['provider', 'lookup-execute', 'worker'], true) || ! in_array($input['number'], [0, 1], true)) {
        throw new RuntimeException;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $root = realpath(storage_path('app/private/fnd04-handler-races'));
    $dir = realpath($input['directory']);
    if ($root === false || $dir === false || ! str_starts_with($dir, $root.DIRECTORY_SEPARATOR) || dirname($dir) !== $root) {
        throw new RuntimeException;
    }
    F::uuid(basename($dir));
    $job = new ReferenceJobCommand($input['command']);
    $client = new Client(config('database.connections.mongodb.dsn'));
    $p = new MongoSyntheticJobExecutionAuthority($client->selectDatabase(HandlerLabDatabase::PROVIDER), 'synthetic-handler', 'handler-1', 'receipt-key', 'lookup-key', ['receipt-key' => str_repeat('H', 32), 'lookup-key' => str_repeat('L', 32)], fn () => true, fn () => true);
    $s = new MongoJobItemStore($client->selectDatabase(HandlerLabDatabase::CONSUMER), fn () => true);
    $r = new MongoReferenceJobRepository($client->selectDatabase(OutboxLabDatabase::NAME), new JobPolicy('synthetic-job-policy-v1', 3, 60000), fn () => true);
    $policy = fn () => new AuthorityProofPolicy('synthetic-handler', 'handler-1', ['receipt-key', 'lookup-key'], fn ($b, $proof, $k) => hash_equals(hash_hmac('sha256', $b, str_repeat($k === 'receipt-key' ? 'H' : 'L', 32)), $proof), new DateTimeImmutable('+1 hour'));
    $h = new ReceiptAwareJobHandler($r, $s, $p, $policy, 'synthetic-handler', 'handler-1', 5000, fn () => true, fn () => new DateTimeImmutable('now', new DateTimeZone('UTC')));
    file_put_contents($dir.'/ready-'.$input['number'], 'ready', LOCK_EX);
    $deadline = hrtime(true) + 8_000_000_000;
    $wait = static function (array $paths) use ($deadline) {
        while (hrtime(true) < $deadline) {
            clearstatcache();
            $ready = true;
            foreach ($paths as $path) {
                $ready = $ready && is_file($path);
            } if ($ready) {
                return;
            } usleep(10000);
        } throw new RuntimeException;
    };
    $wait([$dir.'/release']);
    file_put_contents($dir.'/entered-'.$input['number'], 'entered', LOCK_EX);
    $wait([$dir.'/entered-0', $dir.'/entered-1']);
    if ($input['mode'] === 'lookup-execute' && $input['number'] === 0) {
        $c = JobItemCommand::forItem($job, $job->data['items'][0], 'synthetic-handler', 'handler-1');
        $q = JobItemLookupQuery::forCommand($c, bin2hex(random_bytes(32)), (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'));
        $out = ['query' => $q->wire(), 'lookup' => $p->lookup($q)->wire()];
    } elseif (in_array($input['mode'], ['provider', 'lookup-execute'], true)) {
        $receipt = $p->execute(JobItemCommand::forItem($job, $job->data['items'][0], 'synthetic-handler', 'handler-1'));
        $out = ['receiptHash' => hash('sha256', $receipt->wire())];
    } else {
        $out = ['status' => (new ReferenceJobWorker($r,$h))->run($job->data['operationId'])];
    }
    echo json_encode($out,JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable) {
    echo '{"unavailable":true}';
    exit(2);
}
