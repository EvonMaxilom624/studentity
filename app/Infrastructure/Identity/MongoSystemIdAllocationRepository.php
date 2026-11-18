<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use App\Domain\Identifiers\AllocationConflict;
use App\Domain\Identifiers\AllocationProjection;
use App\Domain\Identifiers\SystemIdAllocation;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;

/** Synthetic-only local projection. It NEVER chooses or authorizes a permanent ID. */
final class MongoSystemIdAllocationRepository implements AllocationProjection
{
    private Collection $rows;

    public function __construct(Database $db)
    {
        PrerequisiteDatabase::ensure($db, 'allocations_v1');
        $this->rows = $db->selectCollection('allocations_v1', ['readConcern' => new ReadConcern(ReadConcern::MAJORITY), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY), 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY)]);
    }

    public function find(string $operationId): ?SystemIdAllocation
    {
        $row = $this->rows->findOne(['_id' => $operationId]);

        return $row === null ? null : new SystemIdAllocation($row['_id'], $row['intent_fingerprint'], $row['system_id'], $row['state'], (int) (string) $row['revision']);
    }

    public function remember(SystemIdAllocation $a): void
    {
        try {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $old = $this->find($a->operationId);
                if ($old === null) {
                    try {
                        $this->rows->insertOne(['_id' => $a->operationId, 'intent_fingerprint' => $a->intentFingerprint, 'system_id' => $a->systemId, 'state' => $a->state, 'revision' => new Int64($a->revision), 'recorded_at' => new UTCDateTime, 'schema_version' => 1]);

                        return;
                    } catch (BulkWriteException $e) {
                        if ($e->getCode() !== 11000) {
                            throw $e;
                        }
                        if ($this->find($a->operationId) === null) {
                            $duplicate = $this->rows->findOne(['system_id' => $a->systemId]);
                            if ($duplicate !== null && $duplicate['_id'] !== $a->operationId) {
                                throw new AllocationConflict('System ID projection conflict.');
                            }
                            throw new \RuntimeException;
                        }

                        continue;
                    }
                }
                $rank = ['reserved' => 0, 'issued' => 1, 'retired' => 2];
                if ($old->intentFingerprint !== $a->intentFingerprint || $old->systemId !== $a->systemId || $a->revision < $old->revision || $rank[$a->state] < $rank[$old->state] || ($a->revision === $old->revision && $a->state !== $old->state)) {
                    throw new AllocationConflict('System ID projection conflict.');
                }
                if ($a->revision === $old->revision) {
                    return;
                }
                $result = $this->rows->updateOne(['_id' => $a->operationId, 'revision' => new Int64($old->revision), 'state' => $old->state], ['$set' => ['revision' => new Int64($a->revision), 'state' => $a->state], '$currentDate' => ['recorded_at' => true]], ['upsert' => false]);
                if ($result->getMatchedCount() === 1) {
                    return;
                }
            }
        } catch (AllocationConflict $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new \RuntimeException('System ID projection unavailable.');
        }
        throw new \RuntimeException('System ID projection unavailable.');
    }
}
