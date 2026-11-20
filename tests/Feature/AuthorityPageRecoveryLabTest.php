<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Security\Authority\AuthorityStreamCursor;
use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\RevocationCommand;
use App\Domain\Security\Authority\RevocationReceipt;
use Illuminate\Support\Str;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AuthorityPageRecoveryLab;
use Tests\Support\AuthorityStreamFixtures;
use Tests\Support\ReceiptAuthorityLab;
use Tests\Support\ReceiptJournalReplayLab;
use Tests\TestCase;

final class AuthorityPageRecoveryLabTest extends TestCase
{
    use AuthorityStreamFixtures;

    private Database $database;

    private array $operations = [];

    private array $controls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = config('database.connections.mongodb.dsn');
        if (config('database.connections.mongodb.database') !== 'studentity_test_m0' || ! in_array(parse_url($dsn, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Exact local synthetic environment required.');
        }
        $this->database = (new Client($dsn))->selectDatabase('studentity_test_m0');
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->database)) {
                foreach (['security_decision_journal', 'audit_events'] as $name) {
                    if ($this->operations) {
                        $this->database->selectCollection($name)->deleteMany(['_id' => ['$in' => $this->operations]]);
                    }
                }
                if ($this->controls) {
                    $this->database->selectCollection('user_sessions')->deleteMany(['_id' => ['$in' => $this->controls]]);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_applied_position_advances_only_after_each_verified_page_is_locally_confirmed(): void
    {
        $entries = $this->entries();
        $recovery = $this->recovery($entries);
        self::assertSame('0', $recovery->position());
        self::assertFalse($recovery->isFullyReconciled());
        self::assertSame('page-applied', $recovery->apply($this->page([$entries[0]], ['toRef' => 'page-1']), $this->nonce('page')));
        self::assertSame('1', $recovery->position());
        self::assertFalse($recovery->isFullyReconciled());
        $this->assertReceiptState($entries[0], true, 1);
        $this->assertReceiptState($entries[1], false, 0);
        self::assertSame('page-applied', $recovery->apply($this->page([$entries[1]], ['fromRef' => 'page-1', 'fromPosition' => '1', 'fromHash' => $entries[0]['entryHash'], 'nonce' => $this->nonce('second')]), $this->nonce('second')));
        self::assertSame('2', $recovery->position());
        self::assertTrue($recovery->isFullyReconciled());
        $this->assertReceiptState($entries[1], true, 1);
    }

    #[DataProvider('commitFailures')]
    public function test_partial_page_keeps_prior_position_and_retries_the_same_originals(string $failure): void
    {
        $entries = $this->entries();
        $recovery = $this->recovery($entries);
        $page = $this->page($entries);
        $failSecond = static function (string $position): void {
            if ($position === '2') {
                throw new \RuntimeException('Synthetic local acknowledgment fault.');
            }
        };
        self::assertSame('local-incomplete', $recovery->apply($page, $this->nonce('page'), $failure === 'abort' ? $failSecond : null, $failure === 'lost-ack' ? $failSecond : null));
        self::assertSame('0', $recovery->position());
        self::assertFalse($recovery->isFullyReconciled());
        $this->assertReceiptState($entries[0], true, 1);
        $this->assertReceiptState($entries[1], $failure === 'lost-ack', $failure === 'lost-ack' ? 1 : 0);
        self::assertSame('page-applied', $recovery->apply($page, $this->nonce('page')));
        self::assertSame('2', $recovery->position());
        self::assertTrue($recovery->isFullyReconciled());
        foreach ($entries as $entry) {
            $this->assertReceiptState($entry, true, 1);
        }
        $this->expectException(ReceiptContractViolation::class);
        $recovery->apply($page, $this->nonce('page'));
    }

    public static function commitFailures(): array
    {
        return [['abort'], ['lost-ack']];
    }

    public function test_losing_coordinator_context_restarts_at_genesis_and_reconciles_existing_history(): void
    {
        $entries = $this->entries();
        $first = $this->recovery($entries);
        self::assertSame('page-applied', $first->apply($this->page($entries), $this->nonce('page')));
        $fresh = new AuthorityPageRecoveryLab($this->checkpoint($entries, ['nonce' => $this->nonce('fresh-checkpoint')]), $this->policy(), $this->nonce('fresh-checkpoint'), new ReceiptJournalReplayLab($this->database));
        self::assertSame('0', $fresh->position());
        self::assertSame('page-applied', $fresh->apply($this->page($entries, ['nonce' => $this->nonce('fresh-page')]), $this->nonce('fresh-page')));
        foreach ($entries as $entry) {
            $this->assertReceiptState($entry, true, 1);
        }
    }

    #[DataProvider('commitFailures')]
    public function test_later_page_uncertainty_preserves_the_previously_applied_position(string $failure): void
    {
        $entries = $this->entries();
        $recovery = $this->recovery($entries);
        self::assertSame('page-applied', $recovery->apply($this->page([$entries[0]], ['toRef' => 'page-1']), $this->nonce('page')));
        $second = $this->page([$entries[1]], ['fromRef' => 'page-1', 'fromPosition' => '1', 'fromHash' => $entries[0]['entryHash'], 'nonce' => $this->nonce('second')]);
        $fault = static fn () => throw new \RuntimeException('Synthetic page fault.');
        self::assertSame('local-incomplete', $recovery->apply($second, $this->nonce('second'), $failure === 'abort' ? $fault : null, $failure === 'lost-ack' ? $fault : null));
        self::assertSame('1', $recovery->position());
        self::assertFalse($recovery->isFullyReconciled());
        $this->assertReceiptState($entries[0], true, 1);
        self::assertSame('page-applied', $recovery->apply($second, $this->nonce('second')));
        self::assertSame('2', $recovery->position());
        self::assertTrue($recovery->isFullyReconciled());
        $this->assertReceiptState($entries[1], true, 1);
    }

    public function test_callback_cannot_reenter_and_publish_a_cursor_during_pending_replay(): void
    {
        $entries = $this->entries();
        $recovery = $this->recovery($entries);
        $page = $this->page($entries);
        self::assertSame('local-incomplete', $recovery->apply($page, $this->nonce('page'), fn () => $recovery->apply($page, $this->nonce('page'))));
        self::assertSame('0', $recovery->position());
        foreach ($entries as $entry) {
            $this->assertReceiptState($entry, false, 0);
        }
        self::assertSame('page-applied', $recovery->apply($page, $this->nonce('page')));
    }

    public function test_missing_control_records_history_but_holds_page_until_late_control_is_reconciled(): void
    {
        $entries = $this->entries(false);
        $this->insertControl($entries[0]);
        $recovery = $this->recovery($entries);
        self::assertSame('history-only', $recovery->apply($this->page($entries), $this->nonce('page')));
        self::assertSame('0', $recovery->position());
        self::assertFalse($recovery->isFullyReconciled());
        foreach ($entries as $entry) {
            $command = RevocationCommand::fromJson($entry['commandWire']);
            self::assertSame(1, $this->database->selectCollection('security_decision_journal')->countDocuments(['_id' => $command->data['operationId']]));
        }
        $this->insertControl($entries[1]);
        self::assertSame('page-applied', $recovery->apply($this->page($entries), $this->nonce('page')));
        foreach ($entries as $entry) {
            $this->assertReceiptState($entry, true, 1);
        }
    }

    public function test_invalid_second_entry_is_rejected_before_any_first_entry_write(): void
    {
        $entries = $this->entries();
        $recovery = $this->recovery($entries);
        $command = RevocationCommand::fromJson($entries[1]['commandWire']);
        $entries[1]['commandWire'] = (new RevocationCommand(array_replace($command->data, ['securityEpoch' => '5'])))->canonical();
        try {
            $recovery->apply($this->page($entries), $this->nonce('page'));
            self::fail('Invalid original binding accepted.');
        } catch (ReceiptContractViolation) {
            self::assertSame('0', $recovery->position());
        }
        foreach ($entries as $entry) {
            $this->assertReceiptState($entry, false, 0);
        }
    }

    public function test_conflicting_immutable_history_blocks_position_without_replacing_the_conflict(): void
    {
        $entries = $this->entries();
        $verified = AuthorityStreamCursor::fromCheckpoint($this->checkpoint($entries), $this->policy(), $this->nonce('checkpoint'))->advance($this->page($entries), $this->nonce('page'))->changes()[1]['receipt'];
        $conflict = $verified->journal();
        $conflict['authority_ref'] = 'urn:synthetic:conflict';
        $this->database->selectCollection('security_decision_journal')->insertOne($conflict);
        $recovery = $this->recovery($entries);
        self::assertSame('local-incomplete', $recovery->apply($this->page($entries), $this->nonce('page')));
        self::assertSame('0', $recovery->position());
        $this->assertReceiptState($entries[0], true, 1);
        self::assertSame('urn:synthetic:conflict', $this->database->selectCollection('security_decision_journal')->findOne(['_id' => $conflict['_id']])['authority_ref']);
        self::assertSame(0, $this->database->selectCollection('audit_events')->countDocuments(['_id' => $conflict['_id']]));
    }

    public function test_empty_genesis_history_never_creates_control_or_history(): void
    {
        $recovery = $this->recovery([]);
        self::assertTrue($recovery->isFullyReconciled());
        $journalCountBefore = $this->database->selectCollection('security_decision_journal')->countDocuments();
        $sessionsCountBefore = $this->database->selectCollection('user_sessions')->countDocuments();
        $auditCountBefore = $this->database->selectCollection('audit_events')->countDocuments();
        self::assertSame('page-applied', $recovery->apply($this->page([], ['anchorRef' => 'barrier-0', 'fromRef' => 'barrier-0', 'toRef' => 'barrier-0']), $this->nonce('page')));
        self::assertSame('0', $recovery->position());
        self::assertSame([], $this->operations);
        self::assertSame($journalCountBefore, $this->database->selectCollection('security_decision_journal')->countDocuments());
        self::assertSame($sessionsCountBefore, $this->database->selectCollection('user_sessions')->countDocuments());
        self::assertSame($auditCountBefore, $this->database->selectCollection('audit_events')->countDocuments());
    }

    public function test_uncertain_page_cannot_be_replaced_with_a_different_signed_response(): void
    {
        $entries = $this->entries();
        $recovery = $this->recovery($entries);
        self::assertSame('local-incomplete', $recovery->apply($this->page($entries), $this->nonce('page'), static fn () => throw new \RuntimeException('Synthetic abort.')));
        try {
            $recovery->apply($this->page($entries, ['nonce' => $this->nonce('replacement')]), $this->nonce('replacement'));
            self::fail('Pending original page was replaced.');
        } catch (ReceiptContractViolation) {
            self::assertSame('0', $recovery->position());
        }
        foreach ($entries as $entry) {
            $this->assertReceiptState($entry, false, 0);
        }
        self::assertSame('page-applied', $recovery->apply($this->page($entries), $this->nonce('page')));
    }

    private function recovery(array $entries): AuthorityPageRecoveryLab
    {
        return new AuthorityPageRecoveryLab($this->checkpoint($entries), $this->policy(), $this->nonce('checkpoint'), new ReceiptJournalReplayLab($this->database));
    }

    private function entries(bool $controls = true): array
    {
        $authority = new ReceiptAuthorityLab;
        $entries = [];
        $previous = str_repeat('0', 64);
        for ($i = 1; $i <= 2; $i++) {
            $operation = $this->operations[] = (string) Str::uuid();
            $command = new RevocationCommand(array_replace($this->commandData(), ['operationId' => $operation, 'subjectUserId' => (string) Str::uuid(), 'sessionDigest' => bin2hex(random_bytes(32)), 'correlationId' => (string) Str::uuid()]));
            $receipt = new RevocationReceipt($this->signed($authority->revoke($command)->data));
            $hash = hash('sha256', CanonicalAuthorityJson::encode(['purpose' => 'studentity.authority.change-entry.v1', 'authorityNamespace' => 'synthetic-authority', 'issuerGeneration' => 'generation-1', 'position' => (string) $i, 'previousHash' => $previous, 'operationId' => $operation, 'requestFingerprint' => $command->fingerprint(), 'receiptFingerprint' => $receipt->fingerprint()]));
            $entry = ['position' => (string) $i, 'previousHash' => $previous, 'entryHash' => $hash, 'commandWire' => $command->canonical(), 'receiptWire' => $receipt->wire()];
            if ($controls) {
                $this->insertControl($entry);
            }
            $entries[] = $entry;
            $previous = $hash;
        }

        return $entries;
    }

    private function insertControl(array $entry): void
    {
        $command = RevocationCommand::fromJson($entry['commandWire']);
        $id = $this->controls[] = (string) Str::uuid();
        $this->database->selectCollection('user_sessions')->insertOne(['_id' => $id, 'user_id' => $command->data['subjectUserId'], 'session_digest' => new Binary(hex2bin($command->data['sessionDigest'])), 'security_epoch' => new Int64(4), 'created_at' => new UTCDateTime(0), 'expires_at' => new UTCDateTime(new \DateTimeImmutable('2026-11-19T00:00:00Z')), 'schema_version' => 1]);
    }

    private function assertReceiptState(array $entry, bool $revoked, int $historyCount): void
    {
        $command = RevocationCommand::fromJson($entry['commandWire']);
        $row = $this->database->selectCollection('user_sessions')->findOne(['session_digest' => new Binary(hex2bin($command->data['sessionDigest']))]);
        self::assertSame($revoked, isset($row['revoked_at']));
        foreach (['security_decision_journal', 'audit_events'] as $name) {
            self::assertSame($historyCount, $this->database->selectCollection($name)->countDocuments(['_id' => $command->data['operationId']]));
        }
    }
}
