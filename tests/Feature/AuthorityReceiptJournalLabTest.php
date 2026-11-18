<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\RevocationCommand;
use Illuminate\Support\Str;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use Tests\Support\AuthorityReceiptFixtures;
use Tests\Support\ReceiptAuthorityLab;
use Tests\TestCase;

final class AuthorityReceiptJournalLabTest extends TestCase
{
    use AuthorityReceiptFixtures;

    private Database $database;

    private array $operations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = config('database.connections.mongodb.dsn');
        if (config('database.connections.mongodb.database') !== 'studentity_test_m0' || ! in_array(parse_url($dsn, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Receipt journal fixtures require the exact local synthetic database.');
        }
        $this->database = (new Client($dsn))->selectDatabase('studentity_test_m0');
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->database) && $this->operations) {
                foreach (['security_decision_journal', 'audit_events'] as $collection) {
                    $this->database->selectCollection($collection)->deleteMany(['_id' => ['$in' => $this->operations]]);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_verified_mappings_commit_together_under_existing_strict_validators_and_hash_stably_after_read(): void
    {
        $authority = new ReceiptAuthorityLab;
        $operation = $this->operations[] = (string) Str::uuid();
        $command = new RevocationCommand(array_replace($this->commandData(), ['operationId' => $operation, 'subjectUserId' => (string) Str::uuid(), 'correlationId' => (string) Str::uuid()]));
        $receipt = $authority->revoke($command);
        $verified = $authority->verify($command, $receipt);
        $transaction = $this->database->getManager()->startSession();
        try {
            $transaction->startTransaction(['readConcern' => new ReadConcern(ReadConcern::SNAPSHOT), 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY)]);
            $this->database->selectCollection('security_decision_journal')->insertOne($verified->journal(), ['session' => $transaction]);
            $this->database->selectCollection('audit_events')->insertOne($verified->audit(), ['session' => $transaction]);
            $transaction->commitTransaction();
        } finally {
            if ($transaction->isInTransaction()) {
                $transaction->abortTransaction();
            } $transaction->endSession();
        }
        $journal = $this->database->selectCollection('security_decision_journal')->findOne(['_id' => $operation]);
        $audit = $this->database->selectCollection('audit_events')->findOne(['_id' => $operation]);
        self::assertInstanceOf(Binary::class, $journal['event_hash']);
        self::assertInstanceOf(UTCDateTime::class, $journal['occurred_at']);
        self::assertSame('1794960000123', (string) $journal['occurred_at']);
        self::assertSame($command->data['subjectUserId'], $journal['subject_user_id']);
        self::assertSame($command->data['correlationId'], $audit['correlation_id']);
        self::assertArrayNotHasKey('actor_user_id', $audit->getArrayCopy());
        $projection = $audit->getArrayCopy();
        unset($projection['event_hash']);
        $projection['occurred_at'] = $audit['occurred_at']->toDateTime()->format('Y-m-d\TH:i:s.v\Z');
        $projection['event_redacted'] = (object) $audit['event_redacted']->getArrayCopy();
        self::assertSame(hash('sha256', CanonicalAuthorityJson::encode($projection), true), $audit['event_hash']->getData());
        self::assertSame($verified->audit()['event_hash']->getData(), $authority->verify($command, $authority->lookup($operation)['receipt'])->audit()['event_hash']->getData());
        try {
            $this->database->selectCollection('security_decision_journal')->insertOne($verified->journal());
            self::fail('Duplicate operation accepted.');
        } catch (BulkWriteException $exception) {
            self::assertSame(11000, $exception->getCode());
        }
        self::assertSame(1, $this->database->selectCollection('security_decision_journal')->countDocuments(['_id' => $operation]));
    }

    public function test_invalid_audit_aborts_transaction_without_leaving_a_journal_receipt(): void
    {
        $authority = new ReceiptAuthorityLab;
        $operation = $this->operations[] = (string) Str::uuid();
        $command = new RevocationCommand(array_replace($this->commandData(), ['operationId' => $operation, 'subjectUserId' => (string) Str::uuid(), 'correlationId' => (string) Str::uuid()]));
        $verified = $authority->verify($command, $authority->revoke($command));
        $invalid = $verified->audit();
        unset($invalid['event_hash']);
        $transaction = $this->database->getManager()->startSession();
        try {
            $transaction->startTransaction(['readConcern' => new ReadConcern(ReadConcern::SNAPSHOT), 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY)]);
            $this->database->selectCollection('security_decision_journal')->insertOne($verified->journal(), ['session' => $transaction]);
            try {
                $this->database->selectCollection('audit_events')->insertOne($invalid, ['session' => $transaction]);
                self::fail('Invalid audit accepted.');
            } catch (BulkWriteException $exception) {
                self::assertSame(121, $exception->getCode());
            }
        } finally {
            if ($transaction->isInTransaction()) {
                $transaction->abortTransaction();
            } $transaction->endSession();
        }
        self::assertSame(0, $this->database->selectCollection('security_decision_journal')->countDocuments(['_id' => $operation]));
        self::assertSame('committed', $authority->lookup($operation)['status']);
    }
}
