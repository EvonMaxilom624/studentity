<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Encryption\Encrypter;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Model\IndexInfo;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\EncryptedSessionLabSchema;
use Tests\Support\EncryptedSessionLabStore;
use Tests\Support\SessionFenceRejected;
use Tests\TestCase;

final class EncryptedSessionTransportLabTest extends TestCase
{
    private Database $lab;

    private Encrypter $cipher;

    private array $digests = [];

    private array $handlers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = config('database.connections.mongodb.dsn');
        if (config('database.connections.mongodb.database') !== 'studentity_test_m0' || ! in_array(parse_url($dsn, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Exact local synthetic environment required.');
        }
        $this->lab = (new Client($dsn))->selectDatabase('studentity_test_session_activation');
        EncryptedSessionLabSchema::ensure($this->lab);
        $this->cipher = new Encrypter(random_bytes(32), 'AES-256-CBC');
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->handlers as $handler) {
                $handler->close();
            }
            if (isset($this->lab) && $this->digests) {
                $this->rows()->deleteMany(['_id' => ['$in' => $this->digests]]);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_explicit_issuance_is_staged_under_digest_without_payload_and_only_issuer_can_publish(): void
    {
        $issuer = $this->handler();
        $raw = $issuer->issue();
        self::assertMatchesRegularExpression('/\A[0-9a-f]{40}\z/', $raw);
        $row = $this->row($raw);
        self::assertSame(hash('sha256', $raw), $row['_id']);
        self::assertSame('staged', $row['lifecycle']);
        self::assertArrayNotHasKey('payload', $row->getArrayCopy());
        $this->reject(fn () => $this->handler()->read($raw));
        $issuer->write($raw, '{"counter":7,"marker":"synthetic-secret"}');
        $issuer->close();
        self::assertSame('live', $this->row($raw)['lifecycle']);
        self::assertStringNotContainsString('synthetic-secret', $this->row($raw)['payload']->getData());
        self::assertSame('{"counter":7,"marker":"synthetic-secret"}', $this->handler()->read($raw));
    }

    public function test_unknown_cookie_read_and_final_save_never_upsert(): void
    {
        $raw = bin2hex(random_bytes(20));
        $handler = $this->handler();
        $this->reject(fn () => $handler->read($raw));
        $this->reject(fn () => $handler->write($raw, '{}'));
        self::assertNull($this->row($raw));
    }

    public function test_payload_copied_to_a_different_live_digest_is_rejected_and_lease_released(): void
    {
        $a = $this->handler();
        $rawA = $a->issue();
        $a->write($rawA, '{"counter":1}');
        $a->close();
        $b = $this->handler();
        $rawB = $b->issue();
        $b->write($rawB, '{"counter":2}');
        $b->close();
        $this->rows()->updateOne(['_id' => hash('sha256', $rawB)], ['$set' => ['payload' => $this->row($rawA)['payload']]]);
        $reader = $this->handler();
        $this->reject(fn () => $reader->read($rawB));
        $reader->close();
        self::assertSame('', $this->row($rawB)['owner']);
    }

    public function test_wrong_encryption_key_fails_closed_without_php_unserialization(): void
    {
        $issuer = $this->handler();
        $raw = $issuer->issue();
        $issuer->write($raw, '{"literal":"O:8:\"stdClass\":0:{}"}');
        $issuer->close();
        $wrong = $this->handler(new Encrypter(random_bytes(32), 'AES-256-CBC'));
        $this->reject(fn () => $wrong->read($raw));
        $wrong->close();
        self::assertSame('', $this->row($raw)['owner']);
        self::assertSame('{"literal":"O:8:\"stdClass\":0:{}"}', $this->handler()->read($raw));
    }

    public function test_expired_writer_cannot_save_retire_or_release_the_successor(): void
    {
        $old = $this->handler();
        $raw = $old->issue();
        $old->write($raw, '{"counter":0}');
        $this->expireLease($raw);
        $new = $this->handler();
        $new->read($raw);
        $new->write($raw, '{"counter":99}');
        $owner = $this->row($raw)['owner'];
        $this->reject(fn () => $old->write($raw, '{"counter":1}'));
        $this->reject(fn () => $old->retireOwned($raw));
        $old->close();
        self::assertSame($owner, $this->row($raw)['owner']);
        self::assertSame('live', $this->row($raw)['lifecycle']);
        $new->close();
        self::assertSame('{"counter":99}', $this->handler()->read($raw));
    }

    public function test_staged_repair_requires_expired_lease_and_exact_generation_revision(): void
    {
        $issuer = $this->handler();
        $raw = $issuer->issue();
        $row = $this->row($raw);
        self::assertFalse($issuer->repairStaged($row['_id'], 1, 0));
        $this->expireLease($raw);
        self::assertFalse($issuer->repairStaged($row['_id'], 2, 0));
        self::assertTrue($issuer->repairStaged($row['_id'], 1, 0));
        $this->reject(fn () => $issuer->write($raw, '{}'));
        $this->reject(fn () => $this->handler()->read($raw));
        self::assertSame('revoked', $this->row($raw)['lifecycle']);
        self::assertArrayNotHasKey('payload', $this->row($raw)->getArrayCopy());
    }

    public function test_staged_snapshot_cannot_repair_a_published_live_envelope(): void
    {
        $issuer = $this->handler();
        $raw = $issuer->issue();
        $row = $this->row($raw);
        $issuer->write($raw, '{"counter":3}');
        $this->expireLease($raw);
        self::assertFalse($issuer->repairStaged($row['_id'], 1, 0));
        self::assertSame('live', $this->row($raw)['lifecycle']);
    }

    public function test_current_owner_retirement_is_a_tombstone_and_id_cannot_be_reissued(): void
    {
        $issuer = $this->handler();
        $raw = $issuer->issue();
        $issuer->write($raw, '{}');
        $issuer->retireOwned($raw);
        self::assertSame('revoked', $this->row($raw)['lifecycle']);
        self::assertSame('', $this->row($raw)['owner']);
        $collision = new EncryptedSessionLabStore($this->lab, $this->cipher, fn () => $raw);
        $this->handlers[] = $collision;
        $this->reject(fn () => $collision->issue());
        self::assertSame('revoked', $this->row($raw)['lifecycle']);
    }

    public function test_session_expiry_and_counter_overflow_prevent_save_or_acquisition(): void
    {
        $issuer = $this->handler();
        $raw = $issuer->issue();
        $this->rows()->updateOne(['_id' => hash('sha256', $raw)], ['$set' => ['expires_at' => new UTCDateTime(0)]]);
        $this->reject(fn () => $issuer->write($raw, '{}'));
        $live = $this->handler();
        $other = $live->issue();
        $live->write($other, '{}');
        $live->close();
        $this->rows()->updateOne(['_id' => hash('sha256', $other)], ['$set' => ['generation' => new Int64('9223372036854775807')]]);
        $this->reject(fn () => $this->handler()->read($other));
        self::assertSame('9223372036854775807', (string) $this->row($other)['generation']);
    }

    public function test_strict_validator_rejects_payload_on_staged_and_missing_payload_on_live(): void
    {
        $issuer = $this->handler();
        $raw = $issuer->issue();
        foreach ([['payload' => new Binary('untrusted')], ['lifecycle' => 'live'], ['unexpected' => true]] as $invalid) {
            try {
                $this->rows()->updateOne(['_id' => hash('sha256', $raw)], ['$set' => $invalid]);
                self::fail('Invalid envelope accepted.');
            } catch (BulkWriteException $e) {
                self::assertSame(121, $e->getCode());
            }
        }
        self::assertSame('staged', $this->row($raw)['lifecycle']);
    }

    public function test_schema_has_only_non_ttl_indexes_gc_is_noop_and_other_database_is_rejected(): void
    {
        $indexes = iterator_to_array($this->rows()->listIndexes());
        self::assertCount(3, $indexes);
        foreach ($indexes as $index) {
            self::assertFalse(isset($index['expireAfterSeconds']));
        }
        self::assertSame(0, $this->handler()->gc(1));
        $this->expectException(\InvalidArgumentException::class);
        EncryptedSessionLabSchema::ensure((new Client(config('database.connections.mongodb.dsn')))->selectDatabase('studentity_dev_m0'));
    }

    #[DataProvider('incompatibleCollectionOptions')]
    public function test_incompatible_existing_collection_options_are_refused_without_metadata_mutation(string $option, mixed $value): void
    {
        $options = ['validator' => EncryptedSessionLabSchema::validator(), 'validationLevel' => 'strict', 'validationAction' => 'error', $option => $value];
        $this->expectException(\RuntimeException::class);
        EncryptedSessionLabSchema::assertOptions($options);
    }

    public static function incompatibleCollectionOptions(): array
    {
        return [['capped', true], ['collation', ['locale' => 'en', 'strength' => 2]], ['timeseries', ['timeField' => 'created_at']]];
    }

    #[DataProvider('incompatibleIndexOptions')]
    public function test_incompatible_existing_index_options_are_refused_without_metadata_mutation(string $option, mixed $value): void
    {
        $index = new IndexInfo(['v' => 2, 'key' => ['expires_at' => 1], 'name' => 'ix_transport_expiry', $option => $value]);
        $this->expectException(\RuntimeException::class);
        EncryptedSessionLabSchema::assertIndex($index);
    }

    public static function incompatibleIndexOptions(): array
    {
        return [['hidden', true], ['collation', ['locale' => 'en']], ['storageEngine', ['wiredTiger' => ['configString' => 'synthetic']]]];
    }

    private function handler(?Encrypter $cipher = null): EncryptedSessionLabStore
    {
        $handler = new EncryptedSessionLabStore($this->lab, $cipher ?? $this->cipher, function (): string {
            $raw = bin2hex(random_bytes(20));
            $this->digests[] = hash('sha256', $raw);

            return $raw;
        });
        $this->handlers[] = $handler;

        return $handler;
    }

    private function row(string $raw): mixed
    {
        return $this->rows()->findOne(['_id' => hash('sha256', $raw)]);
    }

    private function rows(): Collection
    {
        return $this->lab->selectCollection('envelopes_v1');
    }

    private function expireLease(string $raw): void
    {
        $this->rows()->updateOne(['_id' => hash('sha256', $raw)], ['$set' => ['lease_until' => new UTCDateTime(0)]]);
    }

    private function reject(\Closure $action): void
    {
        try {
            $action();
            self::fail('Unsafe transport operation accepted.');
        } catch (SessionFenceRejected) {
            self::assertTrue(true);
        }
    }
}
