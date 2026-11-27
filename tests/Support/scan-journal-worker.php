<?php

declare(strict_types=1);

use App\Domain\Documents\FileScanCommand;
use App\Domain\Documents\FileScanResult;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Infrastructure\Documents\MongoScanAttemptJournal;
use App\Infrastructure\Documents\ScanJournalLabDatabase;
use Illuminate\Contracts\Console\Kernel;
use MongoDB\Client;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') {
    exit(1);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
try {
    $i = json_decode(stream_get_contents(STDIN, 32769), true, 16, JSON_THROW_ON_ERROR);
    F::keys($i, ['command', 'nonce', 'barrier', 'slot', 'preAdmitted']);
    F::uuid($i['barrier']);
    F::digest($i['nonce']);
    if (! in_array($i['slot'], [0, 1], true) || ! is_bool($i['preAdmitted'])) {
        throw new RuntimeException;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $c = FileScanCommand::fromWire($i['command']);
    $db = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase(ScanJournalLabDatabase::NAME);
    $dir = storage_path('app/private/fnd06-journal-races/'.$i['barrier']);
    if (! is_dir($dir)) {
        throw new RuntimeException;
    }
    $wait = static function (array $paths) {
        $until = hrtime(true) + 10_000_000_000;
        while (hrtime(true) < $until) {
            clearstatcache();
            $ready = true;
            foreach ($paths as $p) {
                $ready = $ready && is_file($p);
            }if ($ready) {
                return;
            }usleep(10000);
        }throw new RuntimeException;
    };
    $j = new MongoScanAttemptJournal($db, fn () => true, fn () => true, beforeCommit: static function (string $stage) use ($dir, $i, $wait): void {
        if ($stage === 'complete') {
            file_put_contents($dir.'/staged', (string) $i['slot'], LOCK_EX);
            $wait([$dir.'/commit']);
        }
    });
    file_put_contents($dir.'/ready-'.$i['slot'], 'ready', LOCK_EX);
    $wait([$dir.'/release']);
    if (! $i['preAdmitted']) {
        $j->begin($c, $i['nonce']);
        file_put_contents($dir.'/admitted-'.$i['slot'], 'admitted', LOCK_EX);
        $wait([$dir.'/admitted-0', $dir.'/admitted-1']);
    }
    $latest = $j->state($c)['nonce'] === $i['nonce'];
    if (! $latest) {
        if ($i['preAdmitted']) {
            throw new RuntimeException('The pre-admitted scan is no longer current.');
        }
        $wait([$dir.'/staged']);
    }
    $r = new FileScanResult($c->object->fingerprint(), $c->policy->fingerprint(), $c->scanId, $c->policy->scannerId, $c->policy->signatureVersion, $latest ? 'unsafe' : 'clean', $c->object->mediaType, new DateTimeImmutable, true, true, ! $latest, true, $i['nonce']);
    $accepted = true;
    try {
        $j->complete($c, $r);
    } catch (FileSecurityUnavailable) {
        $accepted = false;
        if (! $latest) {
            file_put_contents($dir.'/rejected', (string) $i['slot'], LOCK_EX);
        }
    }
    echo json_encode(['accepted' => $accepted], JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable) {
    echo '{"unavailable":true}';
    exit(2);
}
