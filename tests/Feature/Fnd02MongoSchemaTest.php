<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Driver\Exception\BulkWriteException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Existing schema regression evidence only: never migrates, drops, or repairs collections. */
final class Fnd02MongoSchemaTest extends TestCase
{
    private function client(): Client
    {
        $dsn = config('database.connections.mongodb.dsn');
        if (config('database.connections.mongodb.database') !== 'studentity_test_m0'
            || ! in_array(parse_url($dsn, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Schema fixtures require exactly local studentity_test_m0');
        }

        return new Client($dsn, ['serverSelectionTimeoutMS' => 5000]);
    }

    private function database(): Database
    {
        return $this->client()->selectDatabase('studentity_test_m0');
    }

    private function user(): array
    {
        return ['_id' => (string) Str::uuid(), 'kind' => 'student', 'account_state' => 'provisional', 'security_epoch' => new Int64(1), 'created_at' => new UTCDateTime, 'updated_at' => new UTCDateTime, 'schema_version' => 1];
    }

    public function test_both_synthetic_environments_match_the_accepted_schema_catalogue(): void
    {
        $catalogue = json_decode(file_get_contents(base_path('database/mongodb/collection_catalogue.json')), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(62, $catalogue['collections']);
        foreach (['studentity_dev_m0', 'studentity_test_m0'] as $name) {
            $db = $this->client()->selectDatabase($name); // Development database inspection is read-only.
            foreach ($catalogue['collections'] as $spec) {
                $info = iterator_to_array($db->listCollections(['filter' => ['name' => $spec['name']]]), false);
                self::assertCount(1, $info, $name.'.'.$spec['name']);
                $options = json_decode(json_encode($info[0]->getOptions(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                self::assertEquals($spec['validator'], $options['validator'], $name.'.'.$spec['name'].' validator');
                self::assertSame('strict', $options['validationLevel']);
                self::assertSame('error', $options['validationAction']);
                $indexes = iterator_to_array($db->selectCollection($spec['name'])->listIndexes(), false);
                $seen = [];
                foreach ($spec['indexes'] as $expected) {
                    $key = json_encode($expected['keys']);
                    if (isset($seen[$key])) {
                        continue; // The accepted migration deduplicates catalogue indexes with identical keys.
                    }
                    $seen[$key] = true;
                    $matches = array_values(array_filter($indexes, static fn ($index) => $index->getKey() === $expected['keys']));
                    self::assertNotEmpty($matches, $name.'.'.$spec['name'].' '.$expected['name']);
                    self::assertSame($expected['unique'] ?? false, $matches[0]->isUnique());
                    foreach (['partialFilterExpression', 'expireAfterSeconds'] as $option) {
                        $actual = isset($matches[0][$option]) ? json_decode(json_encode($matches[0][$option], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR) : null;
                        self::assertEquals($expected[$option] ?? null, $actual, $name.'.'.$spec['name'].' '.$expected['name'].' '.$option);
                    }
                }
            }
            self::assertSame(1, $db->selectCollection('migrations')->countDocuments(['migration' => '2026_11_16_000000_create_studentity_collections']));
        }
    }

    public static function invalidUsers(): array
    {
        return ['missing required field' => ['missing'], 'unrecognized role' => ['role'], 'wrong BSON epoch type' => ['epoch']];
    }

    #[DataProvider('invalidUsers')]
    public function test_invalid_identity_documents_are_rejected_by_the_live_validator(string $case): void
    {
        $document = $this->user();
        if ($case === 'missing') {
            unset($document['security_epoch']);
        } elseif ($case === 'role') {
            $document['kind'] = 'synthetic_unsupported_role';
        } else {
            $document['security_epoch'] = '1';
        }
        $users = $this->database()->selectCollection('users');
        try {
            $users->insertOne($document);
            self::fail('Live MongoDB validator accepted an invalid user.');
        } catch (BulkWriteException $exception) {
            self::assertSame(121, $exception->getCode());
            self::assertNull($users->findOne(['_id' => $document['_id']]));
        } finally {
            $users->deleteOne(['_id' => $document['_id']]); // Own randomly generated fixture only.
        }
    }

    public function test_valid_identity_write_rolls_back_in_a_real_transaction(): void
    {
        $client = $this->client();
        $users = $client->selectDatabase('studentity_test_m0')->selectCollection('users');
        $session = $client->startSession();
        $document = $this->user();
        try {
            $session->startTransaction();
            $users->insertOne($document, ['session' => $session]);
            self::assertSame(1, $users->countDocuments(['_id' => $document['_id']], ['session' => $session]));
            $session->abortTransaction();
            self::assertNull($users->findOne(['_id' => $document['_id']]));
        } finally {
            if ($session->isInTransaction()) {
                $session->abortTransaction();
            }
            $session->endSession();
        }
    }

    public function test_system_id_partial_unique_index_rejects_another_identity(): void
    {
        $first = $this->user();
        $first['system_id'] = 'SYN-FND02-'.Str::uuid(); // Synthetic index fixture, not an official ID format.
        $second = $this->user();
        $second['system_id'] = $first['system_id'];
        $users = $this->database()->selectCollection('users');
        try {
            $users->insertOne($first);
            try {
                $users->insertOne($second);
                self::fail('Duplicate System ID was accepted.');
            } catch (BulkWriteException $exception) {
                self::assertSame(11000, $exception->getCode());
            }
            self::assertSame(1, $users->countDocuments(['system_id' => $first['system_id']]));
        } finally {
            $users->deleteMany(['_id' => ['$in' => [$first['_id'], $second['_id']]]]);
        }
    }
}
