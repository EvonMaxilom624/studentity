<?php

declare(strict_types=1);

// CLI-only synthetic test worker. No application binding, route, or production-provider adapter.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Session\Store;
use Illuminate\Support\Str;
use Tests\Support\FencedSessionLabStore;
use Tests\Support\SessionFenceRejected;

function emitLabResult(array $result): void
{
    echo json_encode($result, JSON_THROW_ON_ERROR), PHP_EOL;
}

final class LabLeaseLost extends RuntimeException {}

function waitForLockLabRelease($app, array $input): void
{
    $barrier = $input['barrier'] ?? '';
    if (! is_string($barrier) || ! Str::isUuid($barrier)) {
        throw new InvalidArgumentException('Invalid synthetic barrier');
    }
    $barriers = $app['db']->connection('session_lab')->getDatabase()->selectCollection('barriers');
    $barriers->insertOne(['_id' => $barrier.':held', 'ready' => true]);
    emitLabResult(['event' => 'ready']);
    flush();
    $deadline = microtime(true) + 5;
    while (! ($barriers->findOne(['_id' => $barrier.':held'])['release'] ?? false)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Synthetic barrier timed out');
        }
        usleep(20000);
    }
}

function runLockedLab($app, array $input, string $id, string $action): array
{
    $lease = $input['lease'] ?? 10;
    $wait = $input['wait'] ?? 2;
    if (! is_int($lease) || $lease < 1 || $lease > 15 || ! is_int($wait) || $wait < 0 || $wait > 2) {
        throw new InvalidArgumentException('Invalid synthetic lock timings');
    }
    $lockConnection = 'session_lab';
    if ($input['lockUnavailable'] ?? false) {
        $connection = $app['config']->get('database.connections.session_lab');
        $connection['dsn'] = 'mongodb://127.0.0.1:1/?serverSelectionTimeoutMS=100&connectTimeoutMS=100';
        $connection['options']['serverSelectionTimeoutMS'] = 100;
        $app['config']->set('database.connections.unavailable_lab_lock', $connection);
        $lockConnection = 'unavailable_lab_lock';
    }
    $app['config']->set('cache.stores.session_lab_lock', ['driver' => 'mongodb', 'connection' => $lockConnection, 'collection' => 'lock_cache_unused', 'lock_collection' => 'locks', 'lock_lottery' => [0, 0], 'prefix' => '']);
    $lock = $app['cache']->store('session_lab_lock')->lock('session:'.$id, $lease)->betweenBlockedAttemptsSleepFor(50);
    emitLabResult(['event' => 'waiting']);
    flush();

    // Laravel's block callback holds the shared lock until this callback's final save/invalidation finishes.
    return $lock->block($wait, static function () use ($app, $input, $id, $action, $lock): array {
        $store = $app['session']->driver();
        $store->setId($id);
        $store->start(); // Crucially, acquire BEFORE reading the session, not just before the final write.
        $before = $store->get('lab.counter');
        if ($before === null && $action !== 'locked-invalidate') {
            return ['ok' => false, 'status' => 401, 'code' => 'lab_session_missing'];
        }
        if ($action === 'locked-held-increment') {
            waitForLockLabRelease($app, $input);
        }
        if (! $lock->isOwnedByCurrentProcess()) {
            throw new LabLeaseLost('Synthetic lock ownership lost');
        }
        if ($action === 'locked-held-after-check') {
            // Deliberate evaluation seam: a process can pause after its last owner check and before save.
            waitForLockLabRelease($app, $input);
        }
        if ($action === 'locked-throw') {
            throw new RuntimeException('Synthetic request failure');
        }
        $result = ['ok' => true, 'pid' => getmypid(), 'readValue' => $before, 'lockClass' => get_class($lock)];
        if ($action === 'locked-invalidate') {
            $store->invalidate();
            $store->regenerateToken();
            $result['rotated'] = $store->getId() !== $id;
        } else {
            $store->put('lab.counter', (int) $before + 1);
            $store->save();
        }

        return $result;
    });
}

function runFencedLab($app, array $input, string $id, string $action): array
{
    $handler = new FencedSessionLabStore($app['db']->connection('session_lab')->getDatabase());
    try {
        if ($action === 'fenced-seed') {
            $handler->create($id, json_encode(['lab' => ['counter' => (int) ($input['value'] ?? 0)], '_token' => Str::random(40)], JSON_THROW_ON_ERROR));

            return ['ok' => true];
        }
        if ($action === 'fenced-invalidate') {
            return ['ok' => $handler->destroy($id)];
        }
        $store = new Store('synthetic_fenced_session', $handler, $id, 'json');
        $store->start();
        if ($action === 'fenced-held-write') {
            waitForLockLabRelease($app, $input); // Pause AFTER a valid lease/payload read, before atomic final save.
        }
        if ($action === 'fenced-throw') {
            throw new RuntimeException('Synthetic fenced request failure');
        }
        if ($action !== 'fenced-read') {
            $store->put('lab.counter', (int) ($input['value'] ?? 0));
            $store->save(); // Handler throws on rejected CAS; Laravel must not silently accept a failed write.
        }

        return ['ok' => true, 'pid' => getmypid(), 'value' => $store->get('lab.counter')];
    } finally {
        $handler->close();
    }
}

try {
    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $id = $input['id'] ?? '';
    if (getenv('APP_ENV') !== 'testing'
        || ($input['database'] ?? 'studentity_test_session_lab') !== 'studentity_test_session_lab'
        || ($input['collection'] ?? 'session_payloads') !== 'session_payloads'
        || ! is_string($id) || ! preg_match('/^synlab[A-Za-z0-9]{34}$/D', $id)) {
        emitLabResult(['ok' => false, 'status' => 400, 'code' => 'lab_target_rejected']);
        exit(0);
    }
    $action = $input['action'] ?? '';
    if (! in_array($action, ['write', 'read', 'invalidate', 'concurrent-write', 'held-write', 'locked-increment', 'locked-held-increment', 'locked-invalidate', 'locked-held-after-check', 'locked-throw', 'fenced-seed', 'fenced-read', 'fenced-write', 'fenced-held-write', 'fenced-invalidate', 'fenced-throw'], true)) {
        throw new InvalidArgumentException('Unknown synthetic action');
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $connection = $app['config']->get('database.connections.mongodb');
    if (! in_array(parse_url($connection['dsn'], PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
        emitLabResult(['ok' => false, 'status' => 400, 'code' => 'lab_target_rejected']);
        exit(0);
    }
    $connection['database'] = 'studentity_test_session_lab';
    $connection['options']['serverSelectionTimeoutMS'] = 5000;
    if ($input['unavailable'] ?? false) {
        $connection['dsn'] = 'mongodb://127.0.0.1:1/?serverSelectionTimeoutMS=100&connectTimeoutMS=100';
        $connection['options']['serverSelectionTimeoutMS'] = 100;
    }
    $app['config']->set('database.connections.session_lab', $connection);
    $app['config']->set('session.driver', 'mongodb');
    $app['config']->set('session.connection', 'session_lab');
    $app['config']->set('session.table', 'session_payloads');
    $app['config']->set('session.serialization', 'json');
    $app['config']->set('session.lifetime', 10); // Test lifetime only, not institutional policy.
    $app['config']->set('session.lottery', [0, 100]); // Never run unscoped GC or create a TTL index.
    if (str_starts_with($action, 'fenced-')) {
        emitLabResult(runFencedLab($app, $input, $id, $action));
        exit(0);
    }
    if (str_starts_with($action, 'locked-')) {
        emitLabResult(runLockedLab($app, $input, $id, $action));
        exit(0);
    }
    $store = $app['session']->driver();
    $store->setId($id);
    $store->start();
    $before = $store->get('lab.counter');
    $result = ['ok' => true, 'pid' => getmypid(), 'php' => PHP_VERSION, 'driver' => $app['session']->getDefaultDriver(), 'serialization' => $app['config']->get('session.serialization')];
    if ($action === 'read') {
        $result['value'] = $before;
    } elseif ($action === 'invalidate') {
        $token = $store->token();
        $store->invalidate();
        $store->regenerateToken();
        $result['rotated'] = $store->getId() !== $id;
        $result['csrfRotated'] = $store->token() !== $token;
        // Do not persist the rotated ID: cleanup owns only the supplied synthetic fixture ID.
    } else {
        if ($action === 'concurrent-write' || $action === 'held-write') {
            $barrier = $input['barrier'] ?? '';
            $role = $action === 'held-write' ? 'held' : ($input['role'] ?? '');
            if (! is_string($barrier) || ! Str::isUuid($barrier) || ! in_array($role, ['a', 'b', 'held'], true)) {
                throw new InvalidArgumentException('Invalid synthetic barrier');
            }
            $barriers = $app['db']->connection('session_lab')->getDatabase()->selectCollection('barriers');
            $barriers->insertOne(['_id' => $barrier.':'.$role, 'run' => $barrier, 'ready' => true]);
            if ($action === 'held-write') {
                emitLabResult(['event' => 'ready']);
                flush();
            }
            $deadline = microtime(true) + 5;
            do {
                $ready = $action === 'held-write'
                    ? ($barriers->findOne(['_id' => $barrier.':held'])['release'] ?? false)
                    : $barriers->countDocuments(['run' => $barrier, 'ready' => true]) === 2;
                if ($ready) {
                    break;
                }
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Synthetic barrier timed out');
                }
                usleep(20000);
            } while (true);
            $result['readValue'] = $before;
        }
        $store->put('lab.counter', $action === 'write' ? (int) ($input['value'] ?? 0) : (int) $before + 1);
        $store->put('lab.synthetic', true);
        $store->save();
        $result['value'] = $store->get('lab.counter');
    }
    emitLabResult($result);
} catch (SessionFenceRejected) {
    emitLabResult(['ok' => false, 'status' => 409, 'code' => 'lab_fence_rejected']);
} catch (LockTimeoutException) {
    emitLabResult(['ok' => false, 'status' => 503, 'code' => 'lab_lock_timeout']);
} catch (LabLeaseLost) {
    emitLabResult(['ok' => false, 'status' => 503, 'code' => 'lab_lock_lost']);
} catch (Throwable $exception) {
    // Never echo connection strings, session IDs/payloads, credentials, or arbitrary exception messages.
    emitLabResult(['ok' => false, 'status' => 503, 'code' => 'lab_backend_unavailable', 'exceptionType' => $exception::class]);
}
