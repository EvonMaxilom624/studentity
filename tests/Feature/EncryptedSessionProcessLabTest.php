<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Security\Authority\RevocationCommand;
use App\Domain\Security\Authority\RevocationReceipt;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Database;
use Symfony\Component\Process\Process;
use Tests\Support\AuthorityReceiptFixtures;
use Tests\TestCase;

/** Independently bootstrapped CLI Store requests, not two HTTP deployments. */
final class EncryptedSessionProcessLabTest extends TestCase
{
    use AuthorityReceiptFixtures;

    private Database $transport;

    private Database $security;

    private Database $lab;

    private string $key;

    private array $ids = [];

    private array $operations = [];

    private array $controls = [];

    private array $barriers = [];

    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = config('database.connections.mongodb.dsn');
        if (config('database.connections.mongodb.database') !== 'studentity_test_m0' || ! in_array(parse_url($dsn, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Exact local synthetic environment required.');
        }
        $client = new Client($dsn);
        $this->transport = $client->selectDatabase('studentity_test_session_activation');
        $this->security = $client->selectDatabase('studentity_test_m0');
        $this->lab = $client->selectDatabase('studentity_test_session_lab');
        $this->key = base64_encode(random_bytes(32));
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
            if ($this->ids) {
                $this->transport->selectCollection('envelopes_v1')->deleteMany(['_id' => ['$in' => array_map(fn ($id) => hash('sha256', $id), $this->ids)]]);
            }
            foreach (['security_decision_journal', 'audit_events'] as $name) {
                if ($this->operations) {
                    $this->security->selectCollection($name)->deleteMany(['_id' => ['$in' => $this->operations]]);
                }
            }
            if ($this->controls) {
                $this->security->selectCollection('user_sessions')->deleteMany(['_id' => ['$in' => $this->controls]]);
            }
            if ($this->barriers) {
                $this->lab->selectCollection('barriers')->deleteMany(['_id' => ['$in' => $this->barriers]]);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_store_payload_survives_independent_php_boots(): void
    {
        $id = $this->id();
        self::assertSame(200, $this->runWorker('issue', $id)['status']);
        self::assertSame(0, $this->runWorker('read', $id)['counter']);
        self::assertSame(1, $this->runWorker('increment', $id)['counter']);
        self::assertSame(1, $this->runWorker('read', $id)['counter']);
    }

    public function test_rotation_and_logout_retire_old_ids_across_boots(): void
    {
        $old = $this->id();
        $next = $this->id();
        $anonymous = $this->id();
        $this->runWorker('issue', $old);
        $this->runWorker('increment', $old);
        $before = $this->payload($old);
        self::assertSame(200, $this->runWorker('rotate', $old, ['newId' => $next])['status']);
        self::assertSame(409, $this->runWorker('read', $old)['status']);
        self::assertSame(1, $this->runWorker('read', $next)['counter']);
        self::assertNotSame(hash('sha256', $before['_token']), hash('sha256', $this->payload($next)['_token']));
        self::assertSame(200, $this->runWorker('logout', $next, ['newId' => $anonymous])['status']);
        self::assertSame(409, $this->runWorker('read', $next)['status']);
        self::assertNull($this->runWorker('read', $anonymous)['counter']);
        self::assertNotSame(hash('sha256', $before['_token']), hash('sha256', $this->payload($anonymous)['_token']));
        foreach ([$old, $next] as $id) {
            $row = $this->row($id);
            self::assertSame('revoked', $row['lifecycle']);
            self::assertFalse(isset($row['payload']));
        }
    }

    public function test_expired_owner_cannot_overwrite_successor_in_another_process(): void
    {
        $id = $this->id();
        $this->runWorker('issue', $id);
        $barrier = $this->barriers[] = (string) Str::uuid();
        $held = $this->process('held-write', $id, ['barrier' => $barrier]);
        $held->start();
        $deadline = microtime(true) + 5;
        while (! str_contains($held->getOutput(), 'ready')) {
            if (! $held->isRunning() || microtime(true) > $deadline) {
                self::fail('Owned worker failed to reach barrier.');
            }
            usleep(20000);
        }
        $this->transport->selectCollection('envelopes_v1')->updateOne(['_id' => hash('sha256', $id)], ['$set' => ['lease_until' => new UTCDateTime(0)]]);
        self::assertSame(99, $this->runWorker('set', $id)['counter']);
        $this->lab->selectCollection('barriers')->updateOne(['_id' => $barrier], ['$set' => ['release' => true]]);
        self::assertSame(409, $this->workerResult($held)['status']);
        self::assertSame(99, $this->runWorker('read', $id)['counter']);
    }

    public function test_fresh_process_reconciles_original_receipt_after_lost_local_commit_ack(): void
    {
        $id = $this->id();
        self::assertSame(200, $this->runWorker('stage', $id)['status']);
        $row = $this->row($id);
        $op = $this->operations[] = $row['issuance_operation_id'];
        $snapshot = ['_id' => $row['_id'], 'issuance_operation_id' => $op, 'generation' => (int) (string) $row['generation'], 'revision' => (int) (string) $row['revision']];
        $command = new RevocationCommand(array_replace($this->commandData(), ['operationId' => $op, 'sessionDigest' => $row['_id'], 'subjectUserId' => (string) Str::uuid(), 'correlationId' => (string) Str::uuid()]));
        $control = $this->controls[] = (string) Str::uuid();
        $this->security->selectCollection('user_sessions')->insertOne(['_id' => $control, 'user_id' => $command->data['subjectUserId'], 'session_digest' => new Binary(hex2bin($row['_id'])), 'security_epoch' => new Int64(4), 'created_at' => new UTCDateTime(0), 'expires_at' => new UTCDateTime(new \DateTimeImmutable('2026-11-19T00:00:00Z')), 'schema_version' => 1]);
        $key = random_bytes(32);
        $data = ['purpose' => 'studentity.session-revocation.receipt.v1', 'schemaVersion' => 1, 'operationId' => $op, 'receiptId' => (string) Str::uuid(), 'authorityNamespace' => 'synthetic-authority', 'issuerGeneration' => 'generation-1', 'signingKeyId' => 'lab-key', 'subjectUserId' => $command->data['subjectUserId'], 'sessionDigest' => $row['_id'], 'action' => 'session.revoke', 'requestFingerprint' => $command->fingerprint(), 'correlationId' => $command->data['correlationId'], 'securityEpoch' => '4', 'subjectVersion' => '1', 'checkpointRef' => 'checkpoint-1', 'committedAt' => '2026-11-18T00:00:00.123Z', 'result' => 'revoked'];
        $unsigned = new RevocationReceipt($data + ['proof' => 'placeholder']);
        $receipt = new RevocationReceipt($data + ['proof' => hash_hmac('sha256', $unsigned->canonical(), $key)]);
        $extra = ['snapshot' => $snapshot, 'commandWire' => $command->canonical(), 'receiptWire' => $receipt->wire(), 'proofKey' => base64_encode($key)];
        self::assertSame('local-incomplete', $this->runWorker('repair-lost-ack', $id, $extra)['repairStatus']);
        self::assertSame('repaired', $this->runWorker('repair', $id, $extra)['repairStatus']);
        self::assertSame('repaired', $this->runWorker('repair', $id, $extra)['repairStatus']);
        self::assertSame('revoked', $this->row($id)['lifecycle']);
        self::assertNotNull($this->security->selectCollection('user_sessions')->findOne(['_id' => $control])['revoked_at']);
        foreach (['security_decision_journal', 'audit_events'] as $name) {
            self::assertSame(1, $this->security->selectCollection($name)->countDocuments(['_id' => $op]));
        }
        self::assertSame(409, $this->runWorker('read', $id)['status']);
    }

    public function test_wrong_key_and_target_override_fail_without_leaking_input(): void
    {
        $id = $this->id();
        $this->runWorker('issue', $id);
        self::assertSame(409, $this->runWorker('read', $id, ['cipherKey' => base64_encode(random_bytes(32))])['status']);
        self::assertSame(400, $this->runWorker('read', $id, ['database' => 'studentity_dev_m0'])['status']);
        self::assertSame(0, $this->runWorker('read', $id)['counter']);
    }

    private function id(): string
    {
        return $this->ids[] = bin2hex(random_bytes(20));
    }

    private function row(string $id): mixed
    {
        return $this->transport->selectCollection('envelopes_v1')->findOne(['_id' => hash('sha256', $id)]);
    }

    private function payload(string $id): array
    {
        $wrapper = json_decode((new Encrypter(base64_decode($this->key), 'AES-256-CBC'))->decryptString($this->row($id)['payload']->getData()), true, 32, JSON_THROW_ON_ERROR);

        return json_decode($wrapper['payload_json'], true, 32, JSON_THROW_ON_ERROR);
    }

    private function process(string $action, string $id, array $extra): Process
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/encrypted-process-worker.php')], base_path(), ['APP_ENV' => 'testing']);
        $process->setInput(json_encode($extra + ['action' => $action, 'rawId' => $id, 'newId' => $id, 'cipherKey' => $this->key], JSON_THROW_ON_ERROR));
        $process->setTimeout(15);

        return $this->processes[] = $process;
    }

    private function runWorker(string $action, string $id, array $extra = []): array
    {
        $process = $this->process($action, $id, $extra);
        $process->start();

        return $this->workerResult($process);
    }

    private function workerResult(Process $process): array
    {
        $process->wait();
        self::assertSame(0, $process->getExitCode());
        self::assertSame('', $process->getErrorOutput());
        $output = $process->getOutput();
        foreach ([$this->key, ...$this->ids] as $secret) {
            self::assertFalse(str_contains($output, $secret), 'Worker output exposed synthetic input.');
        }
        $lines = explode("\n", trim($output));
        $result = json_decode(end($lines), true, 32, JSON_THROW_ON_ERROR);
        self::assertSame(PHP_VERSION, $result['phpVersion']);

        return $result;
    }
}
