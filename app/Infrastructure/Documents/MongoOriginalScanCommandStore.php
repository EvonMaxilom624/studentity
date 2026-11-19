<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Domain\Documents\FileScanCommand;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Security\Authority\AuthorityContractFields as F;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;

/** Unbound insert-only local lab; no scanner invocation, scan result or release state. */
final class MongoOriginalScanCommandStore
{
    public function __construct(private Database $db, private ?\Closure $gate = null)
    {
        ScanCommandLabDatabase::assertReady($db);
        $this->db = $db->withOptions(['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'], 'readPreference' => new ReadPreference(ReadPreference::PRIMARY), 'readConcern' => new ReadConcern(ReadConcern::MAJORITY), 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY)]);
    }

    private function allow(): void
    {
        if ($this->gate === null || ($this->gate)() !== true) {
            throw new \RuntimeException;
        }ScanCommandLabDatabase::assertReady($this->db);
    }

    private function read(string $id): ?FileScanCommand
    {
        $r = $this->db->scan_commands->findOne(['_id' => $id]);
        if ($r === null) {
            return null;
        }$c = FileScanCommand::fromWire($r['wire']);
        if ($c->scanId !== $id || $c->wire() !== $r['wire'] || $c->fingerprint() !== $r['fingerprint']) {
            throw new \RuntimeException;
        }

return $c;
    }

    public function prepare(FileScanCommand $c): void
    {
        try {
            $this->allow();
            $this->db->scan_commands->updateOne(['_id' => $c->scanId], ['$setOnInsert' => ['_id' => $c->scanId, 'wire' => $c->wire(), 'fingerprint' => $c->fingerprint()]], ['upsert' => true]);
            $stored = $this->read($c->scanId);
            if ($stored === null || $stored->wire() !== $c->wire()) {
                throw new \RuntimeException;
            }$this->allow();
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('Original scan command unresolved; evidence retained.');
        }
    }

    public function lookup(string $scanId): ?FileScanCommand
    {
        try {
            $this->allow();
            F::uuid($scanId);
            $r = $this->read($scanId);
            $this->allow();

            return $r;
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('Original scan command unavailable.');
        }
    }
}
