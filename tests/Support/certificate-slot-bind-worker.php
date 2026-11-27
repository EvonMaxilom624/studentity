<?php

declare(strict_types=1);

use App\Domain\Certificates\CertificateSlotUnavailable;
use App\Domain\Certificates\CertificateYearSlotManager;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;
use MongoDB\Client;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') {
    exit(1);
}

require dirname(__DIR__, 2).'/vendor/autoload.php';

try {
    $input = json_decode(stream_get_contents(STDIN, 8193), true, 8, JSON_THROW_ON_ERROR);
    if (! Str::isUuid($input['studentId']) || ! in_array($input['workerId'], [0, 1], true)) {
        throw new InvalidArgumentException('Invalid synthetic worker input.');
    }

    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $db = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase('studentity_test_m0');
    $barrier = storage_path('app/private/certificate-slot-races/'.$input['studentId']);
    $manager = new CertificateYearSlotManager($db, clock: static function () use ($barrier, $input): DateTimeImmutable {
        // The manager calls its clock after eligibility checks and immediately before the update.
        if (! is_dir($barrier) || file_put_contents($barrier.'/'.$input['workerId'].'.ready', 'ready', LOCK_EX) === false) {
            throw new RuntimeException('Synthetic certificate worker could not signal readiness.');
        }

        $deadline = hrtime(true) + 15_000_000_000;
        do {
            clearstatcache();
            if (is_file($barrier.'/release')) {
                return new DateTimeImmutable('now', new DateTimeZone('UTC'));
            }
            usleep(10_000);
        } while (hrtime(true) < $deadline);

        throw new RuntimeException('Synthetic certificate worker timed out at the write barrier.');
    });
    $slot = $manager->getSlot($input['studentId'], $input['academicYearId']);
    if ($slot === null || $slot->generation !== $input['expectedGeneration'] || $slot->operativeCertificateId !== null) {
        throw new RuntimeException('Synthetic certificate worker did not read the initial slot.');
    }

    $bound = $manager->bindOperativeCertificate(
        $input['studentId'],
        $input['academicYearId'],
        $input['certificateId'],
        $slot->generation,
        $input['eligibility'],
    );
    if ($bound->operativeCertificateId !== $input['certificateId'] || $bound->generation !== $slot->generation + 1) {
        throw new RuntimeException('Synthetic certificate worker returned an unexpected slot.');
    }
    echo 'bound';
} catch (CertificateSlotUnavailable $exception) {
    if ($exception->getMessage() !== 'Concurrent slot modification or stale generation conflict.') {
        echo 'Unexpected certificate slot rejection.';
        exit(2);
    }
    echo 'conflict';
} catch (Throwable $exception) {
    echo 'Synthetic certificate worker failed ('.$exception::class.').';
    exit(2);
}
