<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\RevocationCommand;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AuthorityReceiptFixtures;
use Tests\Support\EncryptedSessionLabStore;
use Tests\Support\ReceiptAuthorityLab;
use Tests\Support\ReceiptJournalReplayLab;
use Tests\Support\StagedSessionRepairLab;
use Tests\TestCase;

final class StagedSessionRepairLabTest extends TestCase
{
    use AuthorityReceiptFixtures;

    private Database $transportDb;

    private Database $securityDb;

    private Encrypter $cipher;

    private ReceiptAuthorityLab $authority;

    private array $digests = [];

    private array $operations = [];

    private array $controls = [];

    private array $handlers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = config('database.connections.mongodb.dsn');
        if (config('database.connections.mongodb.database') !== 'studentity_test_m0' || ! in_array(parse_url($dsn, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Exact local synthetic environment required.');
        }
        $client = new Client($dsn);
        $this->transportDb = $client->selectDatabase('studentity_test_session_activation');
        $this->securityDb = $client->selectDatabase('studentity_test_m0');
        $this->cipher = new Encrypter(random_bytes(32), 'AES-256-CBC');
        $this->authority = new ReceiptAuthorityLab;
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->handlers as $handler) {
                $handler->close();
            }
            if (isset($this->transportDb) && $this->digests) {
                $this->rows()->deleteMany(['_id' => ['$in' => $this->digests]]);
            }
            if (isset($this->securityDb)) {
                foreach (['security_decision_journal', 'audit_events'] as $name) {
                    if ($this->operations) {
                        $this->securityDb->selectCollection($name)->deleteMany(['_id' => ['$in' => $this->operations]]);
                    }
                }
                if ($this->controls) {
                    $this->securityDb->selectCollection('user_sessions')->deleteMany(['_id' => ['$in' => $this->controls]]);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_expired_stage_is_tombstoned_then_acknowledged_and_locally_reconciled_idempotently(): void
    {
        [$snapshot, $command] = $this->stage();
        self::assertSame('repaired', $this->coordinator()->repair($snapshot, $command));
        self::assertSame('revoked', $this->row($snapshot)['lifecycle']);
        self::assertNotNull($this->control($command)['revoked_at']);
        $receipt = $this->authority->lookup($command->data['operationId'])['receipt'];
        self::assertSame('repaired', $this->coordinator()->repair($snapshot, $command));
        self::assertSame($receipt->wire(), $this->authority->lookup($command->data['operationId'])['receipt']->wire());
        $this->assertHistory($command, 1);
    }

    #[DataProvider('ineligibleStages')]
    public function test_busy_live_and_stale_snapshots_never_trigger_independent_revocation(string $mode): void
    {
        [$snapshot, $command, $handler, $raw] = $this->stage(true, false);
        if ($mode === 'live') {
            $handler->write($raw, '{}');
            $handler->close();
        }
        if ($mode === 'stale') {
            $handler->close();
            $snapshot['revision'] = 9;
        }
        self::assertSame('not-eligible', $this->coordinator()->repair($snapshot, $command));
        self::assertSame('definitivelyNotCommitted', $this->authority->lookup($command->data['operationId'])['status']);
        self::assertFalse(isset($this->control($command)['revoked_at']));
        $this->assertHistory($command, 0);
    }

    public static function ineligibleStages(): array
    {
        return [['busy'], ['live'], ['stale']];
    }

    public function test_unknown_authority_keeps_transport_denied_without_claiming_local_confirmation(): void
    {
        [$snapshot, $command] = $this->stage();
        $this->authority->unavailable = true;
        self::assertSame('authority-unknown', $this->coordinator()->repair($snapshot, $command));
        self::assertSame('revoked', $this->row($snapshot)['lifecycle']);
        self::assertFalse(isset($this->control($command)['revoked_at']));
        $this->assertHistory($command, 0);
        $this->authority->unavailable = false;
        self::assertSame('repaired', $this->coordinator()->repair($snapshot, $command));
        $this->assertHistory($command, 1);
    }

    public function test_lost_independent_ack_is_resumed_by_original_operation_lookup(): void
    {
        [$snapshot, $command] = $this->stage();
        $this->authority->loseNextAck = true;
        self::assertSame('authority-unknown', $this->coordinator()->repair($snapshot, $command));
        self::assertSame('committed', $this->authority->lookup($command->data['operationId'])['status']);
        self::assertFalse(isset($this->control($command)['revoked_at']));
        self::assertSame('repaired', $this->coordinator()->repair($snapshot, $command));
        $this->assertHistory($command, 1);
    }

    public function test_local_abort_after_ack_leaves_denial_and_can_be_replayed_without_duplicate_history(): void
    {
        [$snapshot, $command] = $this->stage();
        self::assertSame('local-incomplete', $this->coordinator()->repair($snapshot, $command, static fn () => throw new \RuntimeException('Synthetic local failure.')));
        self::assertSame('committed', $this->authority->lookup($command->data['operationId'])['status']);
        self::assertSame('revoked', $this->row($snapshot)['lifecycle']);
        self::assertFalse(isset($this->control($command)['revoked_at']));
        $this->assertHistory($command, 0);
        self::assertSame('repaired', $this->coordinator()->repair($snapshot, $command));
        $this->assertHistory($command, 1);
    }

    public function test_lost_local_commit_ack_is_reconciled_from_the_same_receipt(): void
    {
        [$snapshot, $command] = $this->stage();
        self::assertSame('local-incomplete', $this->coordinator()->repair($snapshot, $command, null, static fn () => throw new \RuntimeException('Synthetic lost local ACK.')));
        self::assertNotNull($this->control($command)['revoked_at']);
        self::assertSame('repaired', $this->coordinator()->repair($snapshot, $command));
        $this->assertHistory($command, 1);
    }

    public function test_missing_command_or_late_control_never_invents_authority_and_can_be_resumed(): void
    {
        [$snapshot, $command] = $this->stage(false);
        self::assertSame('transport-only', $this->coordinator()->repair($snapshot));
        self::assertSame('definitivelyNotCommitted', $this->authority->lookup($command->data['operationId'])['status']);
        $this->insertControl($command);
        self::assertSame('authority-unknown', $this->coordinator()->repair($snapshot));
        self::assertFalse(isset($this->control($command)['revoked_at']));
        self::assertSame('repaired', $this->coordinator()->repair($snapshot, $command));
    }

    public function test_known_command_without_local_control_retains_verified_history_without_upsert(): void
    {
        [$snapshot, $command] = $this->stage(false);
        self::assertSame('repaired-history-only', $this->coordinator()->repair($snapshot, $command));
        self::assertNull($this->control($command));
        $this->assertHistory($command, 1);
    }

    public function test_partial_receipt_history_is_completed_by_coordinated_replay(): void
    {
        [$snapshot, $command] = $this->stage();
        $receipt = $this->authority->revoke($command);
        $verified = $this->authority->verify($command, $receipt);
        $this->securityDb->selectCollection('security_decision_journal')->insertOne($verified->journal());
        self::assertSame('repaired', $this->coordinator()->repair($snapshot, $command));
        $this->assertHistory($command, 1);
    }

    public function test_wrong_command_binding_is_rejected_before_tombstone_or_authority_change(): void
    {
        [$snapshot, $command] = $this->stage();
        $wrong = new RevocationCommand(array_replace($command->data, ['sessionDigest' => str_repeat('f', 64)]));
        try {
            $this->coordinator()->repair($snapshot, $wrong);
            self::fail('Wrong binding accepted.');
        } catch (ReceiptContractViolation) {
            self::assertSame('staged', $this->row($snapshot)['lifecycle']);
            self::assertSame('definitivelyNotCommitted', $this->authority->lookup($command->data['operationId'])['status']);
        }
    }

    public function test_control_epoch_mismatch_never_requests_independent_revocation(): void
    {
        [$snapshot, $command] = $this->stage();
        $this->securityDb->selectCollection('user_sessions')->updateOne(['_id' => $this->control($command)['_id']], ['$set' => ['security_epoch' => new Int64(5)]]);
        try {
            $this->coordinator()->repair($snapshot, $command);
            self::fail('Wrong control epoch accepted.');
        } catch (ReceiptContractViolation) {
            self::assertSame('definitivelyNotCommitted', $this->authority->lookup($command->data['operationId'])['status']);
        }
    }

    private function stage(bool $withControl = true, bool $release = true): array
    {
        $handler = $this->handler();
        $raw = $handler->issue();
        $row = $this->rows()->findOne(['_id' => hash('sha256', $raw)]);
        $snapshot = ['_id' => $row['_id'], 'issuance_operation_id' => $row['issuance_operation_id'], 'generation' => (int) (string) $row['generation'], 'revision' => (int) (string) $row['revision']];
        $this->operations[] = $row['issuance_operation_id'];
        $command = new RevocationCommand(array_replace($this->commandData(), ['operationId' => $row['issuance_operation_id'], 'subjectUserId' => (string) Str::uuid(), 'sessionDigest' => $row['_id'], 'correlationId' => (string) Str::uuid()]));
        if ($withControl) {
            $this->insertControl($command);
        }
        if ($release) {
            $handler->close();
        }

        return [$snapshot, $command, $handler, $raw];
    }

    private function handler(): EncryptedSessionLabStore
    {
        $handler = new EncryptedSessionLabStore($this->transportDb, $this->cipher, function (): string {
            $raw = bin2hex(random_bytes(20));
            $this->digests[] = hash('sha256', $raw);

            return $raw;
        });
        $this->handlers[] = $handler;

        return $handler;
    }

    private function coordinator(): StagedSessionRepairLab
    {
        return new StagedSessionRepairLab($this->transportDb, $this->securityDb, $this->handler(), new ReceiptJournalReplayLab($this->securityDb), $this->authority);
    }

    private function insertControl(RevocationCommand $command): void
    {
        $id = $this->controls[] = (string) Str::uuid();
        $this->securityDb->selectCollection('user_sessions')->insertOne(['_id' => $id, 'user_id' => $command->data['subjectUserId'], 'session_digest' => new Binary(hex2bin($command->data['sessionDigest'])), 'security_epoch' => new Int64(4), 'created_at' => new UTCDateTime(new \DateTimeImmutable('2026-11-17T23:59:00Z')), 'expires_at' => new UTCDateTime(new \DateTimeImmutable('2026-11-19T00:00:00Z')), 'schema_version' => 1]);
    }

    private function control(RevocationCommand $command): mixed
    {
        return $this->securityDb->selectCollection('user_sessions')->findOne(['session_digest' => new Binary(hex2bin($command->data['sessionDigest']))]);
    }

    private function row(array $snapshot): mixed
    {
        return $this->rows()->findOne(['_id' => $snapshot['_id']]);
    }

    private function rows(): Collection
    {
        return $this->transportDb->selectCollection('envelopes_v1');
    }

    private function assertHistory(RevocationCommand $command, int $count): void
    {
        foreach (['security_decision_journal', 'audit_events'] as $name) {
            self::assertSame($count, $this->securityDb->selectCollection($name)->countDocuments(['_id' => $command->data['operationId']]));
        }
    }
}
