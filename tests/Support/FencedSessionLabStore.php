<?php

declare(strict_types=1);

namespace Tests\Support;

use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use MongoDB\Operation\FindOneAndUpdate;
use SessionHandlerInterface;

/** Lab-only, pre-provisioned authenticated-session prototype. Never registered as an app driver. */
final class FencedSessionLabStore implements SessionHandlerInterface
{
    private Collection $collection;

    private ?array $lease = null;

    public function __construct(Database $database)
    {
        if ($database->getDatabaseName() !== 'studentity_test_session_lab') {
            throw new \InvalidArgumentException('Fenced session prototype requires the isolated lab database.');
        }
        $this->collection = $database->selectCollection('fenced_sessions', ['readConcern' => new ReadConcern(ReadConcern::MAJORITY), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY), 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY)]);
    }

    private function assertId(string $id): void
    {
        if (! preg_match('/^synlab[A-Za-z0-9]{34}$/D', $id)) {
            throw new \InvalidArgumentException('Only synthetic lab session IDs are accepted.');
        }
    }

    /** Explicit fixture issuance only. A missing/invalidated session is NEVER implicitly upserted. */
    public function create(string $id, string $payload): void
    {
        $this->assertId($id);
        json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $this->collection->insertOne(['_id' => $id, 'payload' => new Binary($payload), 'generation' => new Int64(0), 'revision' => new Int64(0), 'owner' => '', 'lease_until' => new UTCDateTime(0), 'session_expires_at' => new UTCDateTime((time() + 600) * 1000), 'revoked' => false]);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $this->assertId($id);
        if ($this->lease !== null) {
            throw new SessionFenceRejected('A live handler cannot claim another session.');
        }
        $owner = bin2hex(random_bytes(16));
        // Claim the next generation and capture its payload in ONE server-side operation.
        $row = $this->collection->findOneAndUpdate([
            '_id' => $id, 'revoked' => false,
            '$expr' => ['$and' => [['$lte' => ['$lease_until', '$$NOW']], ['$gt' => ['$session_expires_at', '$$NOW']]]],
        ], [[
            '$set' => ['owner' => ['$literal' => $owner], 'generation' => ['$add' => ['$generation', new Int64(1)]], 'lease_until' => ['$dateAdd' => ['startDate' => '$$NOW', 'unit' => 'second', 'amount' => 10]]],
        ]], ['upsert' => false, 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]);
        if ($row === null) {
            throw new SessionFenceRejected('Session is missing, revoked, expired, or busy.');
        }
        $this->lease = ['id' => $id, 'owner' => $owner, 'generation' => (int) (string) $row['generation'], 'revision' => (int) (string) $row['revision']];
        if (! ($row['payload'] ?? null) instanceof Binary) {
            throw new SessionFenceRejected('Session payload is unavailable.');
        }

        return $row['payload']->getData();
    }

    public function write(string $id, string $data): bool
    {
        $this->assertId($id);
        if ($this->lease === null || $this->lease['id'] !== $id) {
            throw new SessionFenceRejected('A current lease is required to save.');
        }
        json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        $result = $this->collection->updateOne([
            '_id' => $id, 'owner' => $this->lease['owner'], 'generation' => new Int64($this->lease['generation']), 'revision' => new Int64($this->lease['revision']), 'revoked' => false,
            '$expr' => ['$and' => [['$gt' => ['$lease_until', '$$NOW']], ['$gt' => ['$session_expires_at', '$$NOW']]]],
        ], ['$set' => ['payload' => new Binary($data)], '$inc' => ['revision' => new Int64(1)]], ['upsert' => false]);
        if ($result->getMatchedCount() !== 1) {
            throw new SessionFenceRejected('Stale or revoked session write rejected.');
        }
        $this->lease['revision']++;

        return true;
    }

    /** Trusted synthetic invalidation primitive; caller authentication/authority is outside this lab handler. */
    public function destroy(string $id): bool
    {
        $this->assertId($id);
        $result = $this->collection->updateOne(['_id' => $id, 'revoked' => false], [
            '$set' => ['revoked' => true, 'owner' => '', 'lease_until' => new UTCDateTime(0)],
            '$unset' => ['payload' => ''], '$inc' => ['generation' => new Int64(1), 'revision' => new Int64(1)],
        ], ['upsert' => false]);

        return $result->getMatchedCount() === 1 || $this->collection->findOne(['_id' => $id, 'revoked' => true], ['projection' => ['_id' => 1]]) !== null;
    }

    /** HTTP lifecycle retirement must be fenced too: an obsolete request cannot revoke its successor. */
    public function retireOwned(string $id): void
    {
        $this->assertId($id);
        if ($this->lease === null || $this->lease['id'] !== $id) {
            throw new SessionFenceRejected('A current lease is required to retire.');
        }
        $result = $this->collection->updateOne([
            '_id' => $id, 'owner' => $this->lease['owner'], 'generation' => new Int64($this->lease['generation']), 'revision' => new Int64($this->lease['revision']), 'revoked' => false,
            '$expr' => ['$and' => [['$gt' => ['$lease_until', '$$NOW']], ['$gt' => ['$session_expires_at', '$$NOW']]]],
        ], [
            '$set' => ['revoked' => true, 'owner' => '', 'lease_until' => new UTCDateTime(0)],
            '$unset' => ['payload' => ''], '$inc' => ['generation' => new Int64(1), 'revision' => new Int64(1)],
        ], ['upsert' => false]);
        if ($result->getMatchedCount() !== 1) {
            throw new SessionFenceRejected('Stale session retirement rejected.');
        }
        $this->lease = null;
    }

    public function close(): bool
    {
        if ($this->lease !== null) {
            $lease = $this->lease;
            $this->lease = null;
            // Releasing an obsolete generation cannot clear the successor or remove its tombstone.
            $this->collection->updateOne(['_id' => $lease['id'], 'owner' => $lease['owner'], 'generation' => new Int64($lease['generation'])], ['$set' => ['owner' => '', 'lease_until' => new UTCDateTime(0)]]);
        }

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0; // Tombstone disposal is not part of this experiment; no TTL or broad deletion.
    }
}
