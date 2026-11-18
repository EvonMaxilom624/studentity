<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Exception\RuntimeException as MongoException;
use Tests\TestCase;

final class Fnd03ActiveAdminTest extends TestCase
{
    public function test_competing_transactions_and_duplicate_retry_allow_only_one_active_admin(): void
    {
        $name = config('database.connections.mongodb.database');
        $dsn = config('database.connections.mongodb.dsn');
        if ($name !== 'studentity_test_m0' || ! in_array(parse_url($dsn, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Admin uniqueness fixture requires exactly local studentity_test_m0');
        }
        $first = new Client($dsn);
        $second = new Client($dsn);
        $collection = $first->selectDatabase($name)->selectCollection('role_grants');
        self::assertSame(0, $collection->countDocuments(['role_name' => 'system_admin', 'is_active' => true]), 'Existing synthetic active admin found; refuse to replace or remove it.');
        $ids = [(string) Str::uuid(), (string) Str::uuid()];
        $documents = [];
        foreach ($ids as $id) {
            $documents[] = ['_id' => $id, 'user_id' => (string) Str::uuid(), 'role_name' => 'system_admin', 'permission_set' => (object) ['account.read' => true], 'is_active' => true, 'granted_at' => new UTCDateTime, 'schema_version' => 1];
        }
        $a = $first->startSession();
        $b = $second->startSession();
        try {
            $a->startTransaction();
            $collection->insertOne($documents[0], ['session' => $a]);
            $b->startTransaction();
            $conflict = null;
            try {
                $second->selectDatabase($name)->selectCollection('role_grants')->insertOne($documents[1], ['session' => $b, 'maxTimeMS' => 2000]);
            } catch (MongoException $exception) {
                $conflict = $exception->getCode();
            }
            self::assertContains($conflict, [24, 112, 11000], 'Expected a lock/write/unique conflict, not an unrelated error.');
            if ($b->isInTransaction()) {
                $b->abortTransaction();
            }
            $a->commitTransaction();
            try {
                $second->selectDatabase($name)->selectCollection('role_grants')->insertOne($documents[1]);
                self::fail('Unique active administrator index accepted a second admin.');
            } catch (BulkWriteException $exception) {
                self::assertSame(11000, $exception->getCode());
            }
            self::assertSame(1, $collection->countDocuments(['_id' => ['$in' => $ids], 'is_active' => true]));
        } finally {
            foreach ([$a, $b] as $session) {
                if ($session->isInTransaction()) {
                    $session->abortTransaction();
                }
                $session->endSession();
            }
            $collection->deleteMany(['_id' => ['$in' => $ids]]);
        }
    }
}
