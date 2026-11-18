<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\RevocationCommand;
use App\Domain\Security\Authority\VerifiedRevocationReceipt;
use Illuminate\Support\Str;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AuthorityReceiptFixtures;
use Tests\Support\ReceiptAuthorityLab;
use Tests\Support\ReceiptJournalReplayLab;
use Tests\TestCase;

final class ReceiptJournalReplayLabTest extends TestCase
{
    use AuthorityReceiptFixtures;

    private Database $db;

    private ReceiptAuthorityLab $authority;

    private array $operations = [];

    private array $sessions = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = config('database.connections.mongodb.dsn');
        if (config('database.connections.mongodb.database') !== 'studentity_test_m0' || ! in_array(parse_url($dsn, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Exact local synthetic test database required.');
        }
        $this->db = (new Client($dsn))->selectDatabase('studentity_test_m0');
        $this->authority = new ReceiptAuthorityLab;
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->db)) {
                foreach (['security_decision_journal', 'audit_events'] as $name) {
                    if ($this->operations) {
                        $this->db->selectCollection($name)->deleteMany(['_id' => ['$in' => $this->operations]]);
                    }
                }
                if ($this->sessions) {
                    $this->db->selectCollection('user_sessions')->deleteMany(['_id' => ['$in' => $this->sessions]]);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_repeat_completes_one_revocation_and_preserves_original_immutable_records(): void
    {
        $receipt = $this->fixture();
        $writer = new ReceiptJournalReplayLab($this->db);
        self::assertSame('confirmed', $writer->apply($receipt));
        $original = $this->control($receipt)['revoked_at'];
        self::assertSame('confirmed', $writer->apply($receipt));
        self::assertSame('confirmed', (new ReceiptJournalReplayLab($this->db))->apply($receipt));
        self::assertSame((string) $original, (string) $this->control($receipt)['revoked_at']);
        $this->assertPair($receipt);
    }

    #[DataProvider('partialCollections')]
    public function test_partial_local_history_is_completed_without_rewriting_existing_evidence(string $collection, string $mapping): void
    {
        $receipt = $this->fixture();
        $this->db->selectCollection($collection)->insertOne($receipt->$mapping());
        self::assertSame('confirmed', (new ReceiptJournalReplayLab($this->db))->apply($receipt));
        $this->assertPair($receipt);
        self::assertNotNull($this->control($receipt)['revoked_at']);
    }

    public static function partialCollections(): array
    {
        return [['security_decision_journal', 'journal'], ['audit_events', 'audit']];
    }

    public function test_restored_unrevoked_control_is_reconciled_without_duplicate_history(): void
    {
        $receipt = $this->fixture();
        $writer = new ReceiptJournalReplayLab($this->db);
        $writer->apply($receipt);
        $this->db->selectCollection('user_sessions')->updateOne(['_id' => $this->control($receipt)['_id']], ['$unset' => ['revoked_at' => '']]);
        self::assertSame('confirmed', $writer->apply($receipt));
        self::assertNotNull($this->control($receipt)['revoked_at']);
        $this->assertPair($receipt);
    }

    #[DataProvider('conflictingHistory')]
    public function test_conflicting_body_even_with_the_original_hash_aborts_control_and_other_history_changes(string $collection, string $mapping, string $field): void
    {
        $receipt = $this->fixture();
        $conflict = $receipt->$mapping();
        $conflict[$field] = 'synthetic-conflict';
        $this->db->selectCollection($collection)->insertOne($conflict);
        try {
            (new ReceiptJournalReplayLab($this->db))->apply($receipt);
            self::fail('Conflicting immutable body accepted.');
        } catch (ReceiptContractViolation) {
            self::assertFalse(isset($this->control($receipt)['revoked_at']));
            $other = $collection === 'audit_events' ? 'security_decision_journal' : 'audit_events';
            self::assertSame(0, $this->db->selectCollection($other)->countDocuments(['_id' => $receipt->receipt->data['operationId']]));
            self::assertSame('synthetic-conflict', $this->db->selectCollection($collection)->findOne(['_id' => $receipt->receipt->data['operationId']])[$field]);
        }
    }

    public static function conflictingHistory(): array
    {
        return [['security_decision_journal', 'journal', 'authority_ref'], ['audit_events', 'audit', 'target_type']];
    }

    public function test_earlier_existing_revocation_time_is_not_overwritten_by_replay(): void
    {
        $receipt = $this->fixture();
        $earlier = new UTCDateTime(new \DateTimeImmutable('2026-11-17T23:59:59.000Z'));
        $this->db->selectCollection('user_sessions')->updateOne(['_id' => $this->control($receipt)['_id']], ['$set' => ['revoked_at' => $earlier]]);
        self::assertSame('confirmed', (new ReceiptJournalReplayLab($this->db))->apply($receipt));
        self::assertSame((string) $earlier, (string) $this->control($receipt)['revoked_at']);
        $this->assertPair($receipt);
    }

    public function test_same_logical_epoch_with_a_different_bson_type_is_an_immutable_conflict(): void
    {
        $receipt = $this->fixture();
        $audit = $receipt->audit();
        $audit['event_redacted']->security_epoch = new Int64(4); // Original signed projection uses string "4".
        $this->db->selectCollection('audit_events')->insertOne($audit);
        $this->expectException(ReceiptContractViolation::class);
        (new ReceiptJournalReplayLab($this->db))->apply($receipt);
    }

    #[DataProvider('controlConflicts')]
    public function test_mismatched_subject_or_epoch_refuses_reconciliation(string $field): void
    {
        $receipt = $this->fixture();
        $id = $this->control($receipt)['_id'];
        $this->db->selectCollection('user_sessions')->updateOne(['_id' => $id], ['$set' => [$field => $field === 'user_id' ? (string) Str::uuid() : new Int64(5)]]);
        $this->expectException(ReceiptContractViolation::class);
        (new ReceiptJournalReplayLab($this->db))->apply($receipt);
    }

    public static function controlConflicts(): array
    {
        return [['user_id'], ['security_epoch']];
    }

    public function test_precommit_failure_rolls_back_locally_while_independent_receipt_can_be_replayed(): void
    {
        $receipt = $this->fixture();
        try {
            (new ReceiptJournalReplayLab($this->db))->apply($receipt, static fn () => throw new \RuntimeException('Synthetic precommit failure.'));
            self::fail('Expected precommit failure.');
        } catch (\RuntimeException $e) {
            self::assertSame('Synthetic precommit failure.', $e->getMessage());
        }
        self::assertFalse(isset($this->control($receipt)['revoked_at']));
        self::assertSame(0, $this->db->selectCollection('security_decision_journal')->countDocuments(['_id' => $receipt->receipt->data['operationId']]));
        self::assertSame('committed', $this->authority->lookup($receipt->receipt->data['operationId'])['status']);
        self::assertSame('confirmed', (new ReceiptJournalReplayLab($this->db))->apply($receipt));
        $this->assertPair($receipt);
    }

    public function test_lost_commit_ack_is_completed_by_fresh_idempotent_storage_replay(): void
    {
        $receipt = $this->fixture();
        try {
            (new ReceiptJournalReplayLab($this->db))->apply($receipt, null, static fn () => throw new \RuntimeException('Synthetic lost ACK.'));
            self::fail('Expected uncertain outcome.');
        } catch (\RuntimeException $e) {
            self::assertSame('Local receipt outcome uncertain.', $e->getMessage());
        }
        self::assertSame('confirmed', (new ReceiptJournalReplayLab($this->db))->apply($receipt));
        $this->assertPair($receipt);
    }

    public function test_missing_session_retains_denial_history_without_creating_an_active_record(): void
    {
        $receipt = $this->fixture(false);
        self::assertSame('history-only', (new ReceiptJournalReplayLab($this->db))->apply($receipt));
        self::assertNull($this->control($receipt));
        $this->assertPair($receipt);
    }

    public function test_other_database_is_rejected_before_writes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ReceiptJournalReplayLab((new Client(config('database.connections.mongodb.dsn')))->selectDatabase('studentity_dev_m0'));
    }

    private function fixture(bool $withSession = true): VerifiedRevocationReceipt
    {
        $operation = $this->operations[] = (string) Str::uuid();
        $data = array_replace($this->commandData(), ['operationId' => $operation, 'subjectUserId' => (string) Str::uuid(), 'correlationId' => (string) Str::uuid(), 'sessionDigest' => hash('sha256', random_bytes(32))]);
        $command = new RevocationCommand($data);
        if ($withSession) {
            $id = $this->sessions[] = (string) Str::uuid();
            $this->db->selectCollection('user_sessions')->insertOne(['_id' => $id, 'user_id' => $data['subjectUserId'], 'session_digest' => new Binary(hex2bin($data['sessionDigest'])), 'security_epoch' => new Int64(4), 'created_at' => new UTCDateTime(new \DateTimeImmutable('2026-11-17T23:59:00Z')), 'expires_at' => new UTCDateTime(new \DateTimeImmutable('2026-11-19T00:00:00Z')), 'schema_version' => 1]);
        }

        return $this->authority->verify($command, $this->authority->revoke($command));
    }

    private function control(VerifiedRevocationReceipt $receipt): mixed
    {
        return $this->db->selectCollection('user_sessions')->findOne(['session_digest' => new Binary(hex2bin($receipt->receipt->data['sessionDigest']))]);
    }

    private function assertPair(VerifiedRevocationReceipt $receipt): void
    {
        foreach (['security_decision_journal', 'audit_events'] as $collection) {
            self::assertSame(1, $this->db->selectCollection($collection)->countDocuments(['_id' => $receipt->receipt->data['operationId']]));
        }
    }
}
