<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Domain\Jobs\JobItemCommand;
use App\Domain\Jobs\JobItemReceipt;
use App\Domain\Jobs\ReferenceJobCommand;
use App\Domain\Jobs\VerifiedJobItemReceipt;
use App\Domain\Security\Authority\AuthorityProofPolicy;
use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;

/** Immutable original references and historical ACK cache; never current provider permission. */
final class MongoJobItemStore
{
    public function __construct(private Database $db, private ?\Closure $gate = null)
    {
        HandlerLabDatabase::guard($db, HandlerLabDatabase::CONSUMER);
        HandlerLabDatabase::assertReady($db);
        $this->db = $db->withOptions(['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
    }

    private function allow(): void
    {
        if ($this->gate === null || ($this->gate)() !== true) {
            throw new \RuntimeException('Handler store admission unavailable.');
        }
    }

    public function prepare(ReferenceJobCommand $job, array $item, string $namespace, string $generation, DateTimeImmutable $originalCreatedAt): JobItemCommand
    {
        $this->allow();
        $c = JobItemCommand::forItem($job, $item, $namespace, $generation);
        $row = ['_id' => $c->data['itemOperationId'], 'schema_version' => 1, 'job_wire' => $job->wire(), 'job_hash' => $job->fingerprint(), 'command_wire' => $c->wire(), 'command_hash' => $c->fingerprint(), 'original_created_at' => new UTCDateTime($originalCreatedAt), 'receipt_wire' => null, 'receipt_hash' => null];
        HandlerLabDatabase::transaction($this->db, function ($o) use ($c, $row) {
            $this->allow();
            $old = $this->db->handler_items_v1->findOne(['_id' => $row['_id']], $o);
            if ($old === null) {
                $this->db->handler_items_v1->insertOne($row, $o);
            }
            $stored = $this->read($c, $o);
            foreach (['job_wire', 'job_hash', 'command_wire', 'command_hash'] as $k) {
                if ($stored[$k] !== $row[$k]) {
                    throw new \RuntimeException('Original handler command conflict.');
                }
            }
            if ((string) $stored['original_created_at'] !== (string) $row['original_created_at']) {
                throw new \RuntimeException('Original handler lower bound conflict.');
            }
        });

        return $c;
    }

    private function read(JobItemCommand $c, array $o): array
    {
        $r = $this->db->handler_items_v1->findOne(['_id' => $c->data['itemOperationId']], $o);
        if ($r === null) {
            throw new \RuntimeException('Original handler command missing.');
        }
        $job = ReferenceJobCommand::fromJson($r['job_wire']);
        $stored = JobItemCommand::fromJson($r['command_wire']);
        $items = array_values(array_filter($job->data['items'], fn ($i) => $i['resultId'] === $r['_id']));
        if (count($items) !== 1 || $r['schema_version'] !== 1 || $job->wire() !== $r['job_wire'] || $job->fingerprint() !== $r['job_hash'] || $stored->wire() !== $r['command_wire'] || $stored->fingerprint() !== $r['command_hash'] || $stored->wire() !== $c->wire() || JobItemCommand::forItem($job, $items[0], $c->data['authorityNamespace'], $c->data['expectedIssuerGeneration'])->wire() !== $c->wire()) {
            throw new \RuntimeException('Original handler reference mismatch.');
        }
        if ($r['receipt_wire'] !== null) {
            $receipt = JobItemReceipt::fromJson($r['receipt_wire']);
            if ($receipt->wire() !== $r['receipt_wire'] || hash('sha256', $r['receipt_wire']) !== $r['receipt_hash']) {
                throw new \RuntimeException('Handler receipt cache mismatch.');
            }
        } elseif ($r['receipt_hash'] !== null) {
            throw new \RuntimeException('Incomplete handler cache.');
        }

        return $r;
    }

    public function receipt(JobItemCommand $c): ?JobItemReceipt
    {
        $this->allow();

        return HandlerLabDatabase::transaction($this->db, function ($o) use ($c) {
            $r = $this->read($c, $o);

            return $r['receipt_wire'] === null ? null : JobItemReceipt::fromJson($r['receipt_wire']);
        });
    }

    public function recordReceipt(JobItemCommand $c, JobItemReceipt $receipt, AuthorityProofPolicy $policy, DateTimeImmutable $observedAt): void
    {
        $this->allow();
        HandlerLabDatabase::transaction($this->db, function ($o) use ($c, $receipt, $policy, $observedAt) {
            $this->allow();
            $r = $this->read($c, $o);
            VerifiedJobItemReceipt::verify($c, $receipt, $policy, $r['original_created_at']->toDateTimeImmutable(), $observedAt);
            if ($r['receipt_wire'] !== null) {
                if ($r['receipt_wire'] !== $receipt->wire()) {
                    throw new \RuntimeException('Conflicting original handler receipt.');
                }

                return;
            }
            if ($this->db->handler_items_v1->updateOne(['_id' => $r['_id'], 'command_hash' => $c->fingerprint(), 'receipt_wire' => null], ['$set' => ['receipt_wire' => $receipt->wire(), 'receipt_hash' => hash('sha256', $receipt->wire())]], $o)->getModifiedCount() !== 1) {
                throw new \RuntimeException('Handler receipt CAS failed.');
            }
        });
    }
}
