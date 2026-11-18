<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Str;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\Exception\Exception as MongoDriverException;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use MongoDB\Operation\FindOneAndUpdate;
use SessionHandlerInterface;

/** Synthetic staged/encrypted candidate only; never registered as a Laravel application driver. */
final class EncryptedSessionLabStore implements SessionHandlerInterface
{
    private Collection $collection;

    private ?array $lease = null;

    public function __construct(Database $database, private Encrypter $cipher, private Closure $issueId)
    {
        EncryptedSessionLabSchema::ensure($database);
        $this->collection = $database->selectCollection('envelopes_v1', ['readConcern' => new ReadConcern(ReadConcern::MAJORITY), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY), 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY)]);
    }

    public function issue(): string
    {
        $this->idle();
        $raw = ($this->issueId)();
        $digest = $this->digest($raw);
        $operation = (string) Str::uuid();
        try {
            // Explicit issuance only. Existing digest + different issuance UUID cannot match;
            // its attempted insert fails the unique _id index instead of recycling the ID.
            $row = $this->collection->findOneAndUpdate(['_id' => $digest, 'issuance_operation_id' => $operation], [[
                '$replaceWith' => ['_id' => $digest, 'issuance_operation_id' => $operation, 'lifecycle' => 'staged', 'owner' => bin2hex(random_bytes(16)),
                    'generation' => new Int64(1), 'revision' => new Int64(0), 'schema_version' => 1,
                    'created_at' => '$$NOW', 'updated_at' => '$$NOW', 'lease_until' => ['$dateAdd' => ['startDate' => '$$NOW', 'unit' => 'second', 'amount' => 10]],
                    'expires_at' => ['$dateAdd' => ['startDate' => '$$NOW', 'unit' => 'second', 'amount' => 600]]],
            ]], ['upsert' => true, 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]);
        } catch (MongoDriverException $e) {
            if ($e->getCode() === 11000) {
                throw new SessionFenceRejected('Session ID already exists.');
            }
            throw $e;
        }
        $this->capture($row);

        return $raw;
    }

    public function read(string $id): string|false
    {
        $this->idle();
        $digest = $this->digest($id);
        $row = $this->collection->findOneAndUpdate(['_id' => $digest, 'lifecycle' => 'live', '$expr' => ['$and' => [
            ['$lte' => ['$lease_until', '$$NOW']], ['$gt' => ['$expires_at', '$$NOW']], ...self::bounds(),
        ]]], [['$set' => ['owner' => bin2hex(random_bytes(16)), 'generation' => ['$add' => ['$generation', new Int64(1)]], 'updated_at' => '$$NOW', 'lease_until' => ['$dateAdd' => ['startDate' => '$$NOW', 'unit' => 'second', 'amount' => 10]]]]], ['upsert' => false, 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]);
        if ($row === null) {
            throw new SessionFenceRejected('Session unavailable.');
        }
        $this->capture($row);
        try {
            $body = json_decode($this->cipher->decryptString($row['payload']->getData()), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($body) || count($body) !== 3 || ($body['schema_version'] ?? null) !== 1 || ! is_string($body['session_digest'] ?? null) || ! hash_equals($digest, $body['session_digest']) || ! is_string($body['payload_json'] ?? null)) {
                throw new SessionFenceRejected('Untrusted session payload.');
            }
            self::jsonPayload($body['payload_json']);

            return $body['payload_json'];
        } catch (\Throwable) {
            $this->close();
            throw new SessionFenceRejected('Untrusted session payload.');
        }
    }

    public function write(string $id, string $data): bool
    {
        $filter = $this->ownedFilter($id);
        self::jsonPayload($data);
        $ciphertext = $this->cipher->encryptString(json_encode(['schema_version' => 1, 'session_digest' => $this->digest($id), 'payload_json' => $data], JSON_THROW_ON_ERROR));
        $updated = $this->collection->updateOne($filter, ['$set' => ['payload' => new Binary($ciphertext), 'lifecycle' => 'live'], '$inc' => ['revision' => new Int64(1)], '$currentDate' => ['updated_at' => true]], ['upsert' => false]);
        if ($updated->getMatchedCount() !== 1) {
            throw new SessionFenceRejected('Obsolete session write.');
        }
        $this->lease['revision']++;
        $this->lease['lifecycle'] = 'live';

        return true;
    }

    public function retireOwned(string $id): void
    {
        $result = $this->collection->updateOne($this->ownedFilter($id), self::tombstone(), ['upsert' => false]);
        if ($result->getMatchedCount() !== 1) {
            throw new SessionFenceRejected('Obsolete session retirement.');
        }
        $this->lease = null;
    }

    public function repairStaged(string $digest, int $generation, int $revision): bool
    {
        if (! preg_match('/\A[0-9a-f]{64}\z/D', $digest) || $generation < 0 || $revision < 0) {
            throw new \InvalidArgumentException('Invalid staged repair binding.');
        }
        $result = $this->collection->updateOne(['_id' => $digest, 'lifecycle' => 'staged', 'generation' => new Int64($generation), 'revision' => new Int64($revision), '$expr' => ['$and' => [
            ['$lte' => ['$lease_until', '$$NOW']], ...self::bounds(),
        ]]], self::tombstone(), ['upsert' => false]);

        return $result->getMatchedCount() === 1;
    }

    public function close(): bool
    {
        if ($this->lease !== null) {
            $lease = $this->lease;
            $this->lease = null;
            $this->collection->updateOne(['_id' => $lease['digest'], 'owner' => $lease['owner'], 'generation' => new Int64($lease['generation'])], ['$set' => ['owner' => '', 'lease_until' => new UTCDateTime(0)], '$currentDate' => ['updated_at' => true]]);
        }

        return true;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function destroy(string $id): bool
    {
        $this->retireOwned($id);

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }

    private function digest(string $raw): string
    {
        if (! preg_match('/\A[0-9a-f]{40}\z/D', $raw)) {
            throw new SessionFenceRejected('Invalid synthetic session ID.');
        }

        return hash('sha256', $raw);
    }

    private function idle(): void
    {
        if ($this->lease !== null) {
            throw new SessionFenceRejected('Handler already owns a session.');
        }
    }

    private function capture(mixed $row): void
    {
        $this->lease = ['digest' => $row['_id'], 'owner' => $row['owner'], 'generation' => (int) (string) $row['generation'], 'revision' => (int) (string) $row['revision'], 'lifecycle' => $row['lifecycle']];
    }

    private function ownedFilter(string $raw): array
    {
        $digest = $this->digest($raw);
        if ($this->lease === null || $this->lease['digest'] !== $digest) {
            throw new SessionFenceRejected('Current session lease required.');
        }

        return ['_id' => $digest, 'owner' => $this->lease['owner'], 'generation' => new Int64($this->lease['generation']), 'revision' => new Int64($this->lease['revision']), 'lifecycle' => $this->lease['lifecycle'], '$expr' => ['$and' => [
            ['$gt' => ['$lease_until', '$$NOW']], ['$gt' => ['$expires_at', '$$NOW']], ...self::bounds(),
        ]]];
    }

    private static function bounds(): array
    {
        return [['$lt' => ['$generation', new Int64('9223372036854775807')]], ['$lt' => ['$revision', new Int64('9223372036854775807')]]];
    }

    private static function tombstone(): array
    {
        return ['$set' => ['lifecycle' => 'revoked', 'owner' => '', 'lease_until' => new UTCDateTime(0)], '$unset' => ['payload' => ''], '$inc' => ['generation' => new Int64(1), 'revision' => new Int64(1)], '$currentDate' => ['updated_at' => true]];
    }

    private static function jsonPayload(string $payload): void
    {
        try {
            $value = json_decode($payload, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new SessionFenceRejected('Invalid JSON session.');
        }
        if (! $value instanceof \stdClass) {
            throw new SessionFenceRejected('JSON session object required.');
        }
    }
}
