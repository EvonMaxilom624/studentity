<?php

declare(strict_types=1);

// CLI-only Store laboratory. Secrets and original wire objects arrive only via stdin.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'testing') {
    exit(1);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Domain\Security\Authority\RevocationCommand;
use App\Domain\Security\Authority\RevocationReceipt;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use MongoDB\Client;
use Tests\Support\AcknowledgedReceiptAuthorityFixture;
use Tests\Support\EncryptedHttpLabSession;
use Tests\Support\EncryptedSessionLabStore;
use Tests\Support\ReceiptJournalReplayLab;
use Tests\Support\SessionFenceRejected;
use Tests\Support\StagedSessionRepairLab;

$handler = null;
$result = ['status' => 400];
try {
    $wire = stream_get_contents(STDIN, 65537);
    if (strlen($wire) > 65536) {
        throw new InvalidArgumentException;
    }
    $input = json_decode($wire, true, 16, JSON_THROW_ON_ERROR);
    if (! is_array($input) || array_diff(array_keys($input), ['action', 'rawId', 'newId', 'cipherKey', 'barrier', 'snapshot', 'commandWire', 'receiptWire', 'proofKey'])) {
        throw new InvalidArgumentException;
    }
    foreach (['rawId', 'newId'] as $field) {
        if (! is_string($input[$field] ?? null) || ! preg_match('/\A[0-9a-f]{40}\z/D', $input[$field])) {
            throw new InvalidArgumentException;
        }
    }
    $key = is_string($input['cipherKey'] ?? null) ? base64_decode($input['cipherKey'], true) : false;
    if ($key === false || strlen($key) !== 32 || ! in_array($input['action'] ?? null, ['issue', 'read', 'increment', 'set', 'rotate', 'logout', 'held-write', 'stage', 'repair', 'repair-lost-ack'], true)) {
        throw new InvalidArgumentException;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $dsn = $app['config']->get('database.connections.mongodb.dsn');
    if (! $app->environment('testing') || $app['config']->get('database.connections.mongodb.database') !== 'studentity_test_m0' || ! in_array(parse_url($dsn, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
        throw new InvalidArgumentException;
    }
    $client = new Client($dsn);
    $transport = $client->selectDatabase('studentity_test_session_activation');
    $handler = new EncryptedSessionLabStore($transport, new Encrypter($key, 'AES-256-CBC'), fn () => $input['newId']);
    $action = $input['action'];
    if (in_array($action, ['repair', 'repair-lost-ack'], true)) {
        $command = RevocationCommand::fromJson($input['commandWire']);
        $receipt = RevocationReceipt::fromJson($input['receiptWire']);
        $authority = new AcknowledgedReceiptAuthorityFixture($command, $receipt, base64_decode($input['proofKey'], true));
        $security = $client->selectDatabase('studentity_test_m0');
        $repair = new StagedSessionRepairLab($transport, $security, $handler, new ReceiptJournalReplayLab($security), $authority);
        $status = $repair->repair($input['snapshot'], $command, null, $action === 'repair-lost-ack' ? static fn () => throw new RuntimeException('Synthetic lost local acknowledgment.') : null);
        $result = ['status' => 200, 'repairStatus' => $status];
    } else {
        $fresh = in_array($action, ['issue', 'stage'], true);
        $id = $fresh ? $handler->issue() : $input['rawId'];
        $store = new EncryptedHttpLabSession('process_lab', $handler, $id, $fresh);
        $store->start();
        if ($action === 'held-write') {
            if (! is_string($input['barrier'] ?? null) || ! Str::isUuid($input['barrier'])) {
                throw new InvalidArgumentException;
            }
            $barriers = $client->selectDatabase('studentity_test_session_lab')->selectCollection('barriers');
            $barriers->insertOne(['_id' => $input['barrier'], 'ready' => true]);
            echo '{"event":"ready"}', PHP_EOL;
            flush();
            $deadline = microtime(true) + 5;
            while (! ($barriers->findOne(['_id' => $input['barrier']])['release'] ?? false)) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException;
                }
                usleep(20000);
            }
        }
        if ($action === 'issue') {
            $store->put('counter', 0);
        }
        if (in_array($action, ['increment', 'held-write'], true)) {
            $store->put('counter', $store->get('counter') + 1);
        }
        if ($action === 'set') {
            $store->put('counter', 99);
        }
        if ($action === 'rotate') {
            $store->regenerate(true);
        }
        if ($action === 'logout') {
            $store->invalidate();
            $store->regenerateToken();
        }
        if ($action !== 'stage') {
            $store->save();
        }
        $result = ['status' => 200, 'counter' => $store->get('counter')];
    }
} catch (SessionFenceRejected) {
    $result = ['status' => 409];
} catch (InvalidArgumentException|TypeError|JsonException) {
    $result = ['status' => 400];
} catch (Throwable) {
    $result = ['status' => 503];
} finally {
    try {
        $handler?->close();
    } catch (Throwable) {
        $result = ['status' => 503];
    }
}
// Do not log/report exception objects, input, raw IDs, payloads, proof material or keys.
echo json_encode($result + ['phpVersion' => PHP_VERSION], JSON_THROW_ON_ERROR), PHP_EOL;
