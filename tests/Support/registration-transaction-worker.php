<?php

declare(strict_types=1);

use App\Domain\Authentication\ChallengePolicy;
use App\Domain\Authentication\ChallengeVerifier;
use App\Domain\Registration\RegistrationRecipientKey;
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
