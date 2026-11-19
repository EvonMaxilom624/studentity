<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Domain\Jobs\JobExecutionAuthority;
use App\Domain\Jobs\JobItemCommand;
use App\Domain\Jobs\JobItemLookupQuery;
use App\Domain\Jobs\JobItemLookupResponse;
use App\Domain\Jobs\JobItemReceipt;
use App\Domain\Jobs\VerifiedJobItemReceipt;
use App\Domain\Security\Authority\AuthorityProofPolicy;
use App\Domain\Security\Authority\ReceiptSchema;
use DateTimeImmutable;
use Illuminate\Support\Str;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Operation\FindOneAndUpdate;

/** Local marker effects ONLY. Same-server namespaces are not independent recovery truth. Unbound. */
final class MongoSyntheticJobExecutionAuthority implements JobExecutionAuthority
{
    public function __construct(private Database $db, private string $namespace, private string $generation, private string $receiptKeyId, private string $lookupKeyId, private array $keys, private ?\Closure $gate = null, private ?\Closure $recoveryReady = null, private ?\Closure $beforeCommit = null, private ?\Closure $afterCommit = null)
    {
        HandlerLabDatabase::guard($db, HandlerLabDatabase::PROVIDER);
        HandlerLabDatabase::assertReady($db);
        $this->db = $db->withOptions(['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
        if ($receiptKeyId === $lookupKeyId) {
            throw new \InvalidArgumentException('Distinct synthetic proof keys required.');
        }
        foreach ([$receiptKeyId, $lookupKeyId] as $id) {
            if (! isset($keys[$id]) || ! is_string($keys[$id]) || strlen($keys[$id]) < 32) {
                throw new \InvalidArgumentException('Explicit synthetic proof key required.');
            }
        }
        $this->policy(new DateTimeImmutable);
    }

    private function allow(): void
    {
        if ($this->gate === null || $this->recoveryReady === null || ($this->gate)() !== true || ($this->recoveryReady)() !== true) {
            throw new \RuntimeException('Synthetic provider admission/recovery unavailable.');
        }
    }

    private function policy(DateTimeImmutable $now): AuthorityProofPolicy
    {
        return new AuthorityProofPolicy($this->namespace, $this->generation, [$this->receiptKeyId, $this->lookupKeyId], fn ($body, $proof, $key) => isset($this->keys[$key]) && hash_equals(hash_hmac('sha256', $body, $this->keys[$key]), $proof), $now);
    }

    private function sign(string $class, array $body): object
    {
        $raw = new $class($body + ['proof' => 'placeholder']);

        return new $class($body + ['proof' => hash_hmac('sha256', $raw->canonical(), $this->keys[$body['signingKeyId']])]);
    }

    private function binding(array $d, string $fingerprint): array
    {
        if ($d['authorityNamespace'] !== $this->namespace || $d['expectedIssuerGeneration'] !== $this->generation) {
            throw new \RuntimeException('Synthetic provider destination mismatch.');
        }

        return ['_id' => $d['itemOperationId'], 'job_operation_id' => $d['jobOperationId'], 'request_fingerprint' => $fingerprint, 'authority_namespace' => $this->namespace, 'issuer_generation' => $this->generation];
    }

    /** Upsert only the neutral control outside TX. All decisions use the real serializing TX write. */
    private function prepareControl(array $binding): void
    {
        $this->allow();
        $this->db->item_controls_v1->updateOne(['_id' => $binding['_id']], ['$setOnInsert' => $binding + ['revision' => new Int64(0), 'state' => 'observed', 'updated_at' => HandlerLabDatabase::now($this->db, [])]], ['upsert' => true]);
    }

    private function lock(array $binding, array $o): array
    {
        $this->allow();
        $now = HandlerLabDatabase::now($this->db, $o);
        $row = $this->db->item_controls_v1->findOneAndUpdate(['_id' => $binding['_id']], ['$inc' => ['revision' => new Int64(1)]], $o + ['returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]);
        if ($row === null || $row['updated_at']->toDateTimeImmutable() > $now->toDateTimeImmutable()) {
            throw new \RuntimeException('Synthetic provider control/clock unavailable.');
        }
        foreach ($binding as $k => $v) {
            if ($row[$k] !== $v) {
                throw new \RuntimeException('Synthetic provider original binding conflict.');
            }
        }
        $this->db->item_controls_v1->updateOne(['_id' => $binding['_id']], ['$set' => ['updated_at' => $now]], $o);

        return [$row, $now];
    }

    private function history(array $control, UTCDateTime $now, array $o, ?JobItemCommand $expected = null): ?JobItemReceipt
    {
        $id = $control['_id'];
        $entry = $this->db->executions_v1->findOne(['_id' => $id], $o);
        $effect = $this->db->effects_v1->findOne(['_id' => $id], $o);
        if ($entry === null) {
            if ($effect !== null || $control['state'] !== 'observed') {
                throw new \RuntimeException('Incomplete synthetic provider history.');
            }

            return null;
        }
        if ($effect === null || $control['state'] !== 'committed') {
            throw new \RuntimeException('Incomplete synthetic provider commit.');
        }
        $c = JobItemCommand::fromJson($entry['command_wire']);
        $receipt = JobItemReceipt::fromJson($entry['receipt_wire']);
        if ($c->wire() !== $entry['command_wire'] || $c->fingerprint() !== $entry['command_hash'] || OutboxLabDatabase::typed($this->binding($c->data, $c->fingerprint())) !== OutboxLabDatabase::typed(array_intersect_key($control, $this->binding($c->data, $c->fingerprint()))) || ($expected !== null && $expected->wire() !== $c->wire()) || $receipt->wire() !== $entry['receipt_wire'] || hash('sha256', $receipt->wire()) !== $entry['receipt_hash'] || $receipt->data['receiptId'] !== $entry['receipt_id'] || (string) $entry['committed_at'] !== (string) new UTCDateTime(ReceiptSchema::time($receipt->data['committedAt']))) {
            throw new \RuntimeException('Synthetic provider ledger mismatch.');
        }
        VerifiedJobItemReceipt::verify($c, $receipt, $this->policy($now->toDateTimeImmutable()), new DateTimeImmutable('1970-01-01T00:00:00Z'), $now->toDateTimeImmutable());
        $expectedEffect = ['_id' => $id, 'job_operation_id' => $c->data['jobOperationId'], 'target_id' => $c->data['targetId'], 'request_fingerprint' => $c->fingerprint(), 'marker_hash' => hash('sha256', 'synthetic-marker:'.$c->fingerprint()), 'recorded_at' => $entry['committed_at']];
        if (OutboxLabDatabase::typed($expectedEffect) !== OutboxLabDatabase::typed($effect)) {
            throw new \RuntimeException('Synthetic marker/receipt mismatch.');
        }

        return $receipt;
    }

    public function execute(JobItemCommand $command): JobItemReceipt
    {
        try {
            $binding = $this->binding($command->data, $command->fingerprint());
            $this->prepareControl($binding);
            $receipt = HandlerLabDatabase::transaction($this->db, function ($o) use ($binding, $command) {
                [$row,$now] = $this->lock($binding, $o);
                $old = $this->history($row, $now, $o, $command);
                if ($old !== null) {
                    $this->allow();

                    return $old;
                }
                $d = $command->data;
                unset($d['purpose'],$d['expectedIssuerGeneration']);
                $receipt = $this->sign(JobItemReceipt::class, $d + ['purpose' => 'studentity.job.item-receipt.v1', 'requestFingerprint' => $command->fingerprint(), 'result' => 'succeeded', 'errorCode' => 'none', 'receiptId' => (string) Str::uuid(), 'issuerGeneration' => $this->generation, 'signingKeyId' => $this->receiptKeyId, 'authorityRef' => 'urn:synthetic:durable-handler', 'checkpointRef' => 'checkpoint:'.$row['revision'], 'committedAt' => $now->toDateTimeImmutable()->format('Y-m-d\TH:i:s.v\Z')]);
                $id = $binding['_id'];
                $this->db->effects_v1->insertOne(['_id' => $id, 'job_operation_id' => $d['jobOperationId'], 'target_id' => $d['targetId'], 'request_fingerprint' => $command->fingerprint(), 'marker_hash' => hash('sha256', 'synthetic-marker:'.$command->fingerprint()), 'recorded_at' => $now], $o);
                $this->db->executions_v1->insertOne(['_id' => $id, 'command_wire' => $command->wire(), 'command_hash' => $command->fingerprint(), 'receipt_wire' => $receipt->wire(), 'receipt_hash' => hash('sha256', $receipt->wire()), 'receipt_id' => $receipt->data['receiptId'], 'committed_at' => $now], $o);
                $this->db->item_controls_v1->updateOne(['_id' => $id], ['$set' => ['state' => 'committed']], $o);
                $this->allow();
                if ($this->beforeCommit !== null) {
                    ($this->beforeCommit)();
                }

                return $receipt;
            });
            if ($this->afterCommit !== null) {
                ($this->afterCommit)();
            }
            $this->allow();

            return $receipt;
        } catch (\Throwable) {
            throw new \RuntimeException('Synthetic provider execution unresolved; lookup original command.');
        }
    }

    public function lookup(JobItemLookupQuery $query): JobItemLookupResponse
    {
        try {
            $binding = $this->binding($query->data, $query->data['requestFingerprint']);
            $this->prepareControl($binding);
            $result = HandlerLabDatabase::transaction($this->db, function ($o) use ($binding, $query) {
                [$row,$now] = $this->lock($binding, $o);
                if (ReceiptSchema::time($query->data['issuedAt']) > $now->toDateTimeImmutable()) {
                    throw new \RuntimeException('Synthetic provider challenge clock mismatch.');
                }
                $receipt = $this->history($row, $now, $o);
                $this->allow();

                return $this->sign(JobItemLookupResponse::class, ['purpose' => 'studentity.job.item-lookup-response.v1', 'schemaVersion' => 1, 'status' => $receipt === null ? 'not-committed' : 'committed', 'jobOperationId' => $binding['job_operation_id'], 'itemOperationId' => $binding['_id'], 'requestFingerprint' => $binding['request_fingerprint'], 'queryFingerprint' => $query->fingerprint(), 'nonce' => $query->data['nonce'], 'checkedAt' => $now->toDateTimeImmutable()->format('Y-m-d\TH:i:s.v\Z'), 'historyComplete' => true, 'authorityNamespace' => $this->namespace, 'issuerGeneration' => $this->generation, 'signingKeyId' => $this->lookupKeyId, 'authorityRef' => 'urn:synthetic:durable-handler', 'checkpointRef' => 'checkpoint:'.$row['revision'], 'receipt' => $receipt?->data]);
            });
            $this->allow();

            return $result;
        } catch (\Throwable) {
            return new JobItemLookupResponse(['purpose' => 'studentity.job.item-lookup-response.v1', 'schemaVersion' => 1, 'status' => 'unknown']);
        }
    }

    public function __debugInfo(): array
    {
        return ['provider' => 'synthetic-marker-only', 'configuration' => 'redacted'];
    }
}
