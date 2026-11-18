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

    public function test_concurrent_issuance_transactions_enforce_single_slot_winner(): void
    {
        $db = $this->db();
        $dsn = config('database.connections.mongodb.dsn');
        $client1 = new Client($dsn);
        $client2 = new Client($dsn);

        $session1 = $client1->startSession();
        $session2 = $client2->startSession();

        $studentId = '123e4567-e89b-42d3-a456-' . bin2hex(random_bytes(6));
        $yearId = '123e4567-e89b-42d3-a456-' . bin2hex(random_bytes(6));
        $slotId = $studentId . ':' . $yearId;

        $doc1 = [
            '_id' => $slotId,
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'generation' => 1,
            'schema_version' => 1,
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];
        $doc2 = [
            '_id' => $slotId . '-candidate2',
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'generation' => 1,
            'schema_version' => 1,
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];

        try {
            $session1->startTransaction();
            $client1->selectDatabase($db->getDatabaseName())
                ->selectCollection('certificate_year_slots')
                ->insertOne($doc1, ['session' => $session1]);

            $session2->startTransaction();

            $session2Failed = false;
            try {
                $client2->selectDatabase($db->getDatabaseName())
                    ->selectCollection('certificate_year_slots')
                    ->insertOne($doc2, ['session' => $session2, 'maxTimeMS' => 2000]);
            } catch (\Exception $e) {
                $session2Failed = true;
            }

            $session1->commitTransaction();

            if (!$session2Failed) {
                try {
                    $session2->commitTransaction();
                    self::fail('Expected session 2 commit to fail with write conflict or duplicate key');
                } catch (\Exception $e) {
                    $session2Failed = true;
                }
            }

            self::assertTrue($session2Failed, 'Second concurrent certificate transaction was not rejected.');

            $count = $db->selectCollection('certificate_year_slots')->countDocuments([
                'student_id' => $studentId,
                'academic_year_id' => $yearId,
            ]);
            self::assertSame(1, $count);
        } finally {
            if ($session1->isInTransaction()) {
                $session1->abortTransaction();
            }
            if ($session2->isInTransaction()) {
                $session2->abortTransaction();
            }
            $session1->endSession();
            $session2->endSession();
            $db->selectCollection('certificate_year_slots')->deleteMany([
                'student_id' => $studentId,
                'academic_year_id' => $yearId,
            ]);
        }
    }
}
