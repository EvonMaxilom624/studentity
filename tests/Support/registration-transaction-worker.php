<?php

declare(strict_types=1);

use App\Domain\Authentication\ChallengePolicy;
use App\Domain\Authentication\ChallengeVerifier;
use App\Domain\Registration\RegistrationRecipientKey;
use App\Domain\Security\Authority\AuthorityContractFields;
use App\Infrastructure\Registration\MongoRegistrationIntentRepository;
use App\Infrastructure\Registration\RegistrationLabDatabase;
use App\Infrastructure\Registration\RegistrationPayloadCipher;
use Illuminate\Contracts\Console\Kernel;
use MongoDB\Client;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') {
    exit(1);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
try {
    $input = json_decode(stream_get_contents(STDIN, 8193), true, 8, JSON_THROW_ON_ERROR);
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $db = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase(RegistrationLabDatabase::NAME, ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
    $repo = new MongoRegistrationIntentRepository($db, new RegistrationPayloadCipher('synthetic-payload-v1', ['synthetic-payload-v1' => str_repeat('K', 32)]), new ChallengeVerifier(str_repeat('V', 32)), new ChallengePolicy(3, 60000), 'synthetic-policy-v1', 120000, static fn () => true, recipientKey: new RegistrationRecipientKey('synthetic-recipient-v1', str_repeat('R', 32)), verificationKeyId: 'synthetic-verification-v1');
    AuthorityContractFields::uuid($input['barrierId']);
    if (! in_array($input['slot'], [0, 1], true)) {
        throw new InvalidArgumentException;
    }
    $barrier = storage_path('app/private/registration-transaction-barriers/'.$input['barrierId']);
    if (! is_dir($barrier)) {
        throw new RuntimeException;
    }
    file_put_contents($barrier.'/'.$input['slot'].'.ready', 'ready', LOCK_EX);
    $deadline = hrtime(true) + 5_000_000_000;
    $wait = static function (array $paths) use ($deadline): void {
        while (hrtime(true) < $deadline) {
            clearstatcache();
            $all = true;
            foreach ($paths as $path) {
                $all = $all && is_file($path);
            }
            if ($all) {
                return;
            }
            usleep(10000);
        }
        throw new RuntimeException('Synthetic worker barrier timed out.');
    };
    $wait([$barrier.'/release']);
    file_put_contents($barrier.'/'.$input['slot'].'.entered', 'entered', LOCK_EX);
    $wait([$barrier.'/0.entered', $barrier.'/1.entered']);
    if ($input['action'] === 'claim') {
        $ok = $repo->claim($input['id'], $input['challenge'], $input['code']) !== null;
    } elseif ($input['action'] === 'resend') {
        $ok = $repo->resend($input['id'], 0, $input['challenge'], $input['code']);
    } else {
        throw new InvalidArgumentException;
    }
    echo json_encode(['ok' => $ok], JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable) {
    echo '{"unavailable":true}';
    exit(2);
}
