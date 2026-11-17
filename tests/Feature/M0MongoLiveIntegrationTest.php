<?php

declare(strict_types=1);

namespace Tests\Feature;

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Exception\BulkWriteException;
use Tests\TestCase;

/** Run ONLY in synthetic studentity_test* DB, after M0 migration has been applied there. */
final class M0MongoLiveIntegrationTest extends TestCase
{
    private function db(): \MongoDB\Database
    {
        if (!class_exists(Client::class)) {
            $this->markTestSkipped('Official mongodb/mongodb PHP library not installed');
        }
        $name = config('database.connections.mongodb.database');
        if (!is_string($name) || !str_starts_with($name, 'studentity_test')) {
            $this->markTestSkipped('Set MONGODB_DATABASE=studentity_test_m0 in .env.testing');
        }
        $dsn = config('database.connections.mongodb.dsn');
        return (new Client($dsn))->selectDatabase($name);
    }

    public function test_unique_student_year_slot_under_duplicate_write(): void
    {
        $collection = $this->db()->selectCollection('certificate_year_slots');
        $u = '123e4567-e89b-42d3-a456-' . bin2hex(random_bytes(6));
        $year = '123e4567-e89b-42d3-a456-' . bin2hex(random_bytes(6));
        $first = [
            '_id' => $u . ':' . $year,
            'student_id' => $u,
            'academic_year_id' => $year,
            'generation' => 0,
            'schema_version' => 1,
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];
        try {
            $collection->insertOne($first);
            $duplicate = $first;
            $duplicate['_id'] = 'alternative-id';
            try {
                $collection->insertOne($duplicate);
                self::fail('Compound unique student+year constraint did not reject duplicate');
            } catch (BulkWriteException $e) {
                self::assertSame(11000, $e->getCode());
            }
        } finally {
            $collection->deleteMany(['student_id' => $u, 'academic_year_id' => $year]);
        }
    }

    public function test_transaction_abort_discards_new_slot(): void
    {
        $db = $this->db();
        $client = new Client(config('database.connections.mongodb.dsn'));
        $session = $client->startSession();
        $id = 'synthetic-rollback:' . bin2hex(random_bytes(8));
        try {
            $session->startTransaction();
            $client->selectDatabase($db->getDatabaseName())->selectCollection('certificate_year_slots')->insertOne([
                '_id' => $id,
                'student_id' => '123e4567-e89b-42d3-a456-426614174000',
                'academic_year_id' => '123e4567-e89b-42d3-a456-426614174001',
                'generation' => 0,
                'schema_version' => 1,
                'created_at' => new UTCDateTime(),
                'updated_at' => new UTCDateTime(),
            ], ['session' => $session]);
            $session->abortTransaction();
            self::assertNull($db->selectCollection('certificate_year_slots')->findOne(['_id' => $id]));
        } finally {
            if ($session->isInTransaction()) {
                $session->abortTransaction();
            }
            $session->endSession();
        }
    }
}
