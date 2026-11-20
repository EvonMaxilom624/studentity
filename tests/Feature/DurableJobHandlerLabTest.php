<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Jobs\JobItemCommand;
use App\Domain\Jobs\JobItemLookupQuery;
use App\Domain\Jobs\JobItemLookupResponse;
use App\Domain\Jobs\JobItemReceipt;
use App\Domain\Jobs\JobOutcomeUnknown;
use App\Domain\Jobs\VerifiedJobItemLookup;
use App\Domain\Security\Authority\ReceiptSchema;
use App\Infrastructure\Jobs\MongoSyntheticJobExecutionAuthority;
use App\Infrastructure\Jobs\ReceiptAwareJobHandler;
use App\Infrastructure\Jobs\ReferenceJobWorker;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use Symfony\Component\Process\Process;
use Tests\Support\HandlerReconciliationFixtures;
use Tests\TestCase;

final class DurableJobHandlerLabTest extends TestCase
{
    use HandlerReconciliationFixtures;

    private function original(int $lease = 60000): array
    {
        $r = $this->repo($lease);
        $j = $this->command(1);
        $r->enqueue($j);
        $c = JobItemCommand::forItem($j, $j->data['items'][0], 'synthetic-handler', 'handler-1');

        return [$r, $j, $c];
    }

    private function itemQuery(JobItemCommand $c): JobItemLookupQuery
    {
        return JobItemLookupQuery::forCommand($c, bin2hex(random_bytes(32)), (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'));
    }

    public function test_durable_marker_and_same_original_signed_receipt_are_atomic_and_idempotent(): void
    {
        [$r,$j,$c] = $this->original();
        $p = $this->provider();
        $first = $p->execute($c);
        $second = $this->provider()->execute($c);
        self::assertSame($first->wire(), $second->wire());
        $this->assertOneEffect($c);
        $q = $this->itemQuery($c);
        $raw = $p->lookup($q);
        $now = new \DateTimeImmutable;
        $resolved = VerifiedJobItemLookup::verify($c, $q, $raw, $this->handlerPolicy(), $r->originalForHandler($j, $j->data['items'][0]), ReceiptSchema::time($q->data['issuedAt']), $now)->resolveFor($c, $q, $this->handlerPolicy(), $r->originalForHandler($j, $j->data['items'][0]), ReceiptSchema::time($q->data['issuedAt']), $now);
        self::assertSame('committed', $resolved['status']);
        self::assertSame($first->wire(), (new JobItemReceipt($raw->data['receipt']))->wire());
    }

    public function test_worker_runs_only_after_verified_lookup_and_leaves_one_result(): void
    {
        $r = $this->repo();
        $j = $this->command(1);
        $r->enqueue($j);
        self::assertSame('succeeded', (new ReferenceJobWorker($r, $this->adapter($r, $this->provider())))->run($j->data['operationId']));
        self::assertSame(1, $this->db()->job_results->countDocuments(['job_id' => $j->data['operationId']]));
        $this->assertOneEffect(JobItemCommand::forItem($j, $j->data['items'][0], 'synthetic-handler', 'handler-1'));
    }

    public function test_lost_ack_then_new_worker_recovers_exact_original_receipt_without_repeating_effect(): void
    {
        [$r,$j,$c] = $this->original(1000);
        $p = $this->provider(after: function () {
            throw new \RuntimeException('synthetic lost ACK');
        });
        self::assertInstanceOf(JobOutcomeUnknown::class, $this->rejects(fn () => (new ReferenceJobWorker($r, $this->adapter($r, $p)))->run($j->data['operationId'])));
        $this->assertOneEffect($c);
        self::assertSame(0, $this->db()->job_results->countDocuments(['job_id' => $j->data['operationId']]));
        $old = $this->handlerDb(true)->executions_v1->findOne(['_id' => $c->data['itemOperationId']])['receipt_wire'];
        usleep(1100000);
        $provider = $this->provider();
        $slow = $this->scriptedAuthority(function ($query) use ($provider) {
            usleep(200000); // Valid provider latency must not share the expired fixture's tiny lease.

            return $provider->lookup($query);
        }, fn ($command) => $provider->execute($command));
        $r = $this->repo(1000);
        self::assertSame('succeeded', (new ReferenceJobWorker($r, $this->adapter($r, $slow, $this->store())))->run($j->data['operationId']));
        $this->assertOneEffect($c);
        self::assertSame($old, $this->store()->receipt($c)->wire());
    }

    public function test_local_result_abort_then_restart_keeps_provider_receipt_and_single_effect(): void
    {
        [$r,$j,$c] = $this->original(1000);
        // Complete external reconciliation before starting the short local write lease.
        $outcome = $this->adapter($r, $this->provider())->handle($j, $j->data['items'][0]);
        $faults = 0;
        $r = $this->repo(1000, fault: function ($stage) use (&$faults) {
            if ($stage === 'record') {
                $faults++;
                throw new \RuntimeException('synthetic result abort');
            }
        });
        $lease = $r->claim($j->data['operationId']);
        self::assertNotNull($lease);
        $this->rejects(fn () => $r->record($lease, $c->data['targetId'], $outcome));
        self::assertSame(1, $faults, 'The intended transaction fault must be reached.');
        $cached = $this->store()->receipt($c)->wire();
        $this->assertOneEffect($c);
        self::assertSame(0, $this->db()->job_results->countDocuments(['job_id' => $j->data['operationId']]));
        usleep(1100000);
        $r = $this->repo(1000);
        self::assertSame('succeeded', (new ReferenceJobWorker($r, $this->adapter($r, $this->provider())))->run($j->data['operationId']));
        self::assertSame($cached, $this->store()->receipt($c)->wire());
        $this->assertOneEffect($c);
    }

    public function test_provider_abort_creates_neither_marker_nor_receipt_then_same_command_recovers(): void
    {
        [$r,$j,$c] = $this->original();
        $reached = 0;
        $p = $this->provider(before: function () use (&$reached) {
            $reached++;
            throw new \RuntimeException('synthetic precommit abort');
        });
        $this->rejects(fn () => $p->execute($c));
        self::assertSame(1, $reached);
        self::assertSame(0, $this->handlerDb(true)->effects_v1->countDocuments(['_id' => $c->data['itemOperationId']]));
        self::assertSame(0, $this->handlerDb(true)->executions_v1->countDocuments(['_id' => $c->data['itemOperationId']]));
        self::assertSame('not-committed', $this->provider()->lookup($this->itemQuery($c))->data['status']);
        $this->provider()->execute($c);
        $this->assertOneEffect($c);
    }

    public function test_unavailable_recovery_admission_defaults_and_epoch_conflicts_cannot_execute(): void
    {
        [$r,$j,$c] = $this->original();
        foreach ([$this->provider(gate: fn () => false), $this->provider(ready: fn () => false), new MongoSyntheticJobExecutionAuthority($this->handlerDb(true), 'synthetic-handler', 'handler-1', 'receipt-key', 'lookup-key', ['receipt-key' => str_repeat('H', 32), 'lookup-key' => str_repeat('L', 32)])] as $p) {
            self::assertSame('unknown', $p->lookup($this->itemQuery($c))->data['status']);
            $this->rejects(fn () => $p->execute($c));
        }
        $p = $this->provider();
        $p->execute($c);
        $changed = JobItemCommand::forItem($j, $j->data['items'][0], 'synthetic-handler', 'handler-2');
        self::assertSame('unknown', $p->lookup($this->itemQuery($changed))->data['status']);
        $this->rejects(fn () => $p->execute($changed));
        $this->assertOneEffect($c);
    }

    public function test_incomplete_restore_pairs_and_reverse_orphan_evidence_always_fence(): void
    {
        $p = $this->provider();
        foreach (['control', 'effect', 'receipt', 'receipt-effect', 'control-effect'] as $state) {
            [$r,$j,$c] = $this->original();
            $id = $c->data['itemOperationId'];
            $now = new UTCDateTime;
            if (str_contains($state, 'control')) {
                $this->handlerDb(true)->item_controls_v1->insertOne(['_id' => $id, 'job_operation_id' => $c->data['jobOperationId'], 'request_fingerprint' => $c->fingerprint(), 'authority_namespace' => 'synthetic-handler', 'issuer_generation' => 'handler-1', 'revision' => new Int64(1), 'state' => 'committed', 'updated_at' => $now]);
            }
            if (str_contains($state, 'effect')) {
                $this->handlerDb(true)->effects_v1->insertOne(['_id' => $id, 'job_operation_id' => $c->data['jobOperationId'], 'target_id' => $c->data['targetId'], 'request_fingerprint' => $c->fingerprint(), 'marker_hash' => hash('sha256', 'synthetic-marker:'.$c->fingerprint()), 'recorded_at' => $now]);
            }
            if (str_contains($state, 'receipt')) {
                $receipt = $this->handlerReceipt($c);
                $this->handlerDb(true)->executions_v1->insertOne(['_id' => $id, 'command_wire' => $c->wire(), 'command_hash' => $c->fingerprint(), 'receipt_wire' => $receipt->wire(), 'receipt_hash' => hash('sha256', $receipt->wire()), 'receipt_id' => $receipt->data['receiptId'], 'committed_at' => new UTCDateTime(ReceiptSchema::time($receipt->data['committedAt']))]);
            }
            self::assertSame('unknown', $p->lookup($this->itemQuery($c))->data['status'], $state);
            $this->rejects(fn () => $p->execute($c));
        }
    }

    public function test_stale_local_lease_cannot_write_result_after_provider_effect_finishes(): void
    {
        [$r,$j,$c] = $this->original(1000);
        $old = $r->claim($j->data['operationId']);
        $outcome = $this->adapter($r, $this->provider())->handle($j, $j->data['items'][0]);
        usleep(1100000);
        // Reconcile before claiming the write lease; keep the original policy on restart.
        $resolved = $this->adapter($r, $this->provider())->handle($j, $j->data['items'][0]);
        $writer = $this->repo(1000);
        $fresh = $writer->claim($j->data['operationId']);
        self::assertNotNull($fresh);
        $this->rejects(fn () => $r->record($old, $c->data['targetId'], $outcome));
        self::assertSame('succeeded', $writer->record($fresh, $c->data['targetId'], $resolved));
        $this->assertOneEffect($c);
    }

    public function test_current_key_revocation_between_lookup_and_execution_fences(): void
    {
        [$r,$j,$c] = $this->original();
        $p = $this->provider();
        $calls = 0;
        $a = $this->scriptedAuthority(fn ($q) => $p->lookup($q), fn ($c) => $p->execute($c));
        $policy = function () use (&$calls) {
            return $this->handlerPolicy(++$calls < 3 ? ['receipt-key', 'lookup-key'] : ['receipt-key']);
        };
        $h = new ReceiptAwareJobHandler($r, $this->store(), $a, $policy, 'synthetic-handler', 'handler-1', 5000, fn () => true, fn () => new \DateTimeImmutable);
        self::assertInstanceOf(JobOutcomeUnknown::class, $this->rejects(fn () => $h->handle($j, $j->data['items'][0])));
        self::assertSame(0, $a->executions);
        self::assertSame(0, $this->handlerDb(true)->effects_v1->countDocuments(['_id' => $c->data['itemOperationId']]));
    }

    public function test_provider_tamper_and_clock_rollback_cannot_be_reported_as_current_history(): void
    {
        [$r,$j,$c] = $this->original();
        $p = $this->provider();
        $p->execute($c);
        $this->handlerDb(true)->item_controls_v1->updateOne(['_id' => $c->data['itemOperationId']], ['$set' => ['updated_at' => new UTCDateTime(new \DateTimeImmutable('+1 day'))]]);
        self::assertSame('unknown', $p->lookup($this->itemQuery($c))->data['status']);
        $this->rejects(fn () => $p->execute($c));
        [$r,$j,$c] = $this->original();
        $p->execute($c);
        $this->handlerDb(true)->effects_v1->updateOne(['_id' => $c->data['itemOperationId']], ['$set' => ['marker_hash' => str_repeat('0', 64)]]);
        self::assertSame('unknown', $p->lookup($this->itemQuery($c))->data['status']);
        $this->rejects(fn () => $p->execute($c));
    }

    public function test_changed_body_with_same_item_id_cannot_replace_original_provider_evidence(): void
    {
        [$r,$j,$c] = $this->original();
        $p = $this->provider();
        $first = $p->execute($c);
        $changed = new JobItemCommand(array_replace($c->data, ['targetId' => $this->uuid()]));
        self::assertSame('unknown', $p->lookup($this->itemQuery($changed))->data['status']);
        $this->rejects(fn () => $p->execute($changed));
        self::assertSame($first->wire(), $p->execute($c)->wire());
        $this->assertOneEffect($c);
    }

    public function test_receipt_key_revocation_blocks_committed_lookup_without_local_result(): void
    {
        [$r,$j,$c] = $this->original();
        $p = $this->provider();
        $p->execute($c);
        $a = $this->scriptedAuthority(fn ($q) => $p->lookup($q), fn ($c) => $p->execute($c));
        $this->rejects(fn () => (new ReferenceJobWorker($r, $this->adapter($r, $a, policy: $this->handlerPolicy(['lookup-key']))))->run($j->data['operationId']));
        self::assertSame(0, $a->executions);
        self::assertSame(0, $this->db()->job_results->countDocuments(['job_id' => $j->data['operationId']]));
        $this->assertOneEffect($c);
    }

    public function test_two_process_provider_and_shared_worker_recovery_use_one_original_effect(): void
    {
        foreach (['provider', 'lookup-execute', 'worker'] as $mode) {
            [$r,$j,$c] = $this->original(60000);
            $this->provider()->lookup($this->itemQuery($c));
            $this->store();
            $dir = storage_path('app/private/fnd04-handler-races/'.$this->uuid());
            mkdir($dir, 0700, true);
            $workers = [];
            try {
                foreach ([0, 1] as $n) {
                    $process = new Process([PHP_BINARY, base_path('tests/Support/handler-race-worker.php')], base_path(), ['APP_ENV' => 'testing']);
                    $process->setInput(json_encode(['mode' => $mode, 'command' => $j->data, 'directory' => $dir, 'number' => $n], JSON_THROW_ON_ERROR));
                    $process->setTimeout(12);
                    $process->start();
                    $workers[] = $process;
                }
                $deadline = microtime(true) + 8;
                while ((! is_file($dir.'/ready-0') || ! is_file($dir.'/ready-1')) && microtime(true) < $deadline) {
                    usleep(10000);
                }
                self::assertFileExists($dir.'/ready-0');
                self::assertFileExists($dir.'/ready-1');
                self::assertFileDoesNotExist($dir.'/entered-0');
                self::assertFileDoesNotExist($dir.'/entered-1');
                file_put_contents($dir.'/release', 'go');
                $results = [];
                foreach ($workers as $w) {
                    $w->wait();
                    self::assertSame(0, $w->getExitCode(), $w->getErrorOutput());
                    $results[] = json_decode($w->getOutput(), true, 32, JSON_THROW_ON_ERROR);
                }
                if ($mode === 'provider') {
                    self::assertSame($results[0]['receiptHash'], $results[1]['receiptHash']);
                } elseif ($mode === 'lookup-execute') {
                    $query = JobItemLookupQuery::fromJson($results[0]['query']);
                    $response = JobItemLookupResponse::fromJson($results[0]['lookup']);
                    $at = new \DateTimeImmutable;
                    $lower = $r->originalForHandler($j, $j->data['items'][0]);
                    $v = VerifiedJobItemLookup::verify($c, $query, $response, $this->handlerPolicy(), $lower, ReceiptSchema::time($query->data['issuedAt']), $at);
                    self::assertContains($v->resolveFor($c, $query, $this->handlerPolicy(), $lower, ReceiptSchema::time($query->data['issuedAt']), $at)['status'], ['committed', 'not-committed']);
                    if ($response->data['status'] === 'committed') {
                        self::assertSame($results[1]['receiptHash'], hash('sha256', (new JobItemReceipt($response->data['receipt']))->wire()));
                    } else {
                        $entry = $this->handlerDb(true)->executions_v1->findOne(['_id' => $c->data['itemOperationId']]);
                        self::assertLessThanOrEqual($entry['committed_at']->toDateTimeImmutable(), ReceiptSchema::time($response->data['checkedAt']));
                    }
                } else {
                    self::assertContains('succeeded', array_column($results, 'status'));
                    self::assertSame(1, $this->db()->job_results->countDocuments(['job_id' => $j->data['operationId']]));
                }
                $this->assertOneEffect($c);
            } finally {
                foreach ($workers as $w) {
                    if ($w->isRunning()) {
                        $w->stop(1);
                    }
                }
            }
        }
    }
}
