<?php

declare(strict_types=1);
use App\Infrastructure\Verification\LocalVerificationTarget;
use Illuminate\Contracts\Console\Kernel;
use MongoDB\Client;
use MongoDB\Driver\ReadPreference;

if (PHP_SAPI !== 'cli' || PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) {
    exit(1);
}require dirname(__DIR__).'/vendor/autoload.php';
try {
    $root = dirname(__DIR__);
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (! app()->environment('testing') || config('database.connections.mongodb.database') !== 'studentity_test_m0' || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException;
    }
    $dsn = config('database.connections.mongodb.dsn');
    if (! LocalVerificationTarget::allows($dsn)) {
        throw new RuntimeException;
    }
    $client = new Client($dsn);
    $db = $client->selectDatabase('admin', ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
    $hello = iterator_to_array($db->command(['hello' => 1]), false)[0];
    $host = $client->getManager()->selectServer(new ReadPreference(ReadPreference::PRIMARY))->getHost();
    if (! LocalVerificationTarget::allowsTopology($hello, $host)) {
        throw new RuntimeException;
    }echo '{"preflight":"passed","readOnly":true}';
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Local test-target preflight unavailable.\n");
    exit(1);
}
