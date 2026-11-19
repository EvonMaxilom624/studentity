<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Jobs\JobItemCommand;
use App\Domain\Jobs\JobItemLookupResponse;
use App\Domain\Jobs\JobOutcomeUnknown;
use App\Infrastructure\Jobs\HandlerLabDatabase;
use App\Infrastructure\Jobs\ReceiptAwareJobHandler;
use MongoDB\Client;
use MongoDB\Driver\Exception\BulkWriteException;
use Tests\Support\HandlerReconciliationFixtures;
use Tests\TestCase;

final class JobHandlerReconciliationTest extends TestCase
{
    use HandlerReconciliationFixtures;

    public function test_original_wire_and_enqueue_time_survive_restart_and_destination_conflicts(): void
    {
        $r = $this->repo();
        $j = $this->command(1);
        $r->enqueue($j);
        $item = $j->data['items'][0];
        $t = $r->originalForHandler($j, $item);
        $s = $this->store();
        $c = $s->prepare($j, $item, 'synthetic-handler', 'handler-1', $t);
        self::assertSame($c->wire(), $this->store()->prepare($j, $item, 'synthetic-handler', 'handler-1', $t)->wire());
        $row = $this->handlerDb()->handler_items_v1->findOne(['_id' => $item['resultId']]);
        self::assertSame($j->wire(), $row['job_wire']);
        self::assertEquals($t, $row['original_created_at']->toDateTimeImmutable());
        $this->rejects(fn () => $s->prepare($j, $item, 'synthetic-handler', 'handler-2', $t));
        $this->rejects(fn () => $s->prepare($j, $item, 'synthetic-handler', 'handler-1', $t->modify('+1 second')));
        self::assertSame(1, $this->handlerDb()->handler_items_v1->countDocuments(['_id' => $item['resultId']]));
    }

    public function test_original_is_persisted_before_any_authority_io_and_exact_noncommit_executes(): void
    {
        $r = $this->repo();
        $j = $this->command(1);
        $r->enqueue($j);
        $s = $this->store();
        $called = 0;
        $a = $this->scriptedAuthority(function ($q) use ($j) {
            $row = $this->handlerDb()->handler_items_v1->findOne(['_id' => $j->data['items'][0]['resultId']]);
            self::assertSame($j->wire(), $row['job_wire']);

            return $this->handlerLookup($q);
        }, function ($c) use (&$called, $j) {
            $called++;
            self::assertSame(JobItemCommand::forItem($j, $j->data['items'][0], 'synthetic-handler', 'handler-1')->wire(), $c->wire());

            return $this->handlerReceipt($c);
        });
        self::assertSame('succeeded', $this->adapter($r, $a, $s)->handle($j, $j->data['items'][0])->result);
        self::assertSame(1, $called);
        self::assertNotNull($s->receipt(JobItemCommand::forItem($j, $j->data['items'][0], 'synthetic-handler', 'handler-1')));
    }

    public function test_cached_receipt_never_bypasses_live_unknown(): void
    {
        $r = $this->repo();
        $j = $this->command(1);
        $r->enqueue($j);
        $s = $this->store();
        $c = $s->prepare($j, $j->data['items'][0], 'synthetic-handler', 'handler-1', $r->originalForHandler($j, $j->data['items'][0]));
        $receipt = $this->handlerReceipt($c);
        $s->recordReceipt($c, $receipt, $this->handlerPolicy(), new \DateTimeImmutable);
        $a = $this->scriptedAuthority(fn () => new JobItemLookupResponse(['purpose' => 'studentity.job.item-lookup-response.v1', 'schemaVersion' => 1, 'status' => 'unknown']), function () {
            self::fail('Unknown must not execute.');
        });
        self::assertInstanceOf(JobOutcomeUnknown::class, $this->rejects(fn () => $this->adapter($r, $a, $s)->handle($j, $j->data['items'][0])));
        self::assertSame(0, $a->executions);
    }

    public function test_cached_receipt_conflicts_with_noncommit_and_receipt_fork(): void
    {
        $r = $this->repo();
        $j = $this->command(1);
        $r->enqueue($j);
        $s = $this->store();
        $item = $j->data['items'][0];
        $c = $s->prepare($j, $item, 'synthetic-handler', 'handler-1', $r->originalForHandler($j, $item));
        $receipt = $this->handlerReceipt($c);
        $s->recordReceipt($c, $receipt, $this->handlerPolicy(), new \DateTimeImmutable);
        $a = $this->scriptedAuthority(fn ($q) => $this->handlerLookup($q), function () {
            self::fail('Contradictory cache must not execute.');
        });
        self::assertInstanceOf(JobOutcomeUnknown::class, $this->rejects(fn () => $this->adapter($r, $a, $s)->handle($j, $item)));
        self::assertSame(0, $a->executions);
        $other = $this->handlerReceipt($c);
        $this->rejects(fn () => $s->recordReceipt($c, $other, $this->handlerPolicy(), new \DateTimeImmutable));
        self::assertSame($receipt->wire(), $s->receipt($c)->wire());
    }

    public function test_original_body_and_cache_tampering_fence_before_execution(): void
    {
        $r = $this->repo();
        $j = $this->command(1);
        $r->enqueue($j);
        $s = $this->store();
        $item = $j->data['items'][0];
        $s->prepare($j, $item, 'synthetic-handler', 'handler-1', $r->originalForHandler($j, $item));
        $this->handlerDb()->handler_items_v1->updateOne(['_id' => $item['resultId']], ['$set' => ['job_wire' => '{}']]);
        $a = $this->scriptedAuthority(function () {
            self::fail('Tampered history must fence.');
        }, function () {
            self::fail('Tampered history must fence.');
        });
        self::assertInstanceOf(JobOutcomeUnknown::class, $this->rejects(fn () => $this->adapter($r, $a, $s)->handle($j, $item)));
        self::assertSame(0, $a->lookups);
        self::assertSame(0, $a->executions);
    }

    public function test_admission_is_rechecked_after_lookup_and_exceptions_are_redacted(): void
    {
        $r = $this->repo();
        $j = $this->command(1);
        $r->enqueue($j);
        $active = true;
        $a = $this->scriptedAuthority(function ($q) use (&$active) {
            $active = false;

            return $this->handlerLookup($q);
        }, function () {
            self::fail('Revoked gate must fence.');
        });
        $gate = function () use (&$active) {
            return $active;
        };
        $e = $this->rejects(fn () => $this->adapter($r, $a, gate: $gate)->handle($j, $j->data['items'][0]));
        self::assertInstanceOf(JobOutcomeUnknown::class, $e);
        self::assertSame(0, $a->executions);
        $a = $this->scriptedAuthority(function () {
            throw new \RuntimeException('secret-provider-key');
        }, function () {
            self::fail('Outage must not execute.');
        });
        $e = $this->rejects(fn () => $this->adapter($r, $a)->handle($j, $j->data['items'][0]));
        self::assertStringNotContainsString('secret-provider-key', (string) $e);
        self::assertNull($e->getPrevious());
        self::assertSame(0, $a->executions);
    }

    public function test_budget_and_backward_clock_fence_and_missing_gate_defaults_deny(): void
    {
        foreach ([false, true] as $backward) {
            $r = $this->repo();
            $j = $this->command(1);
            $r->enqueue($j);
            $start = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $calls = 0;
            $a = $this->scriptedAuthority(fn ($q) => $this->handlerLookup($q), function () {
                self::fail('Bad clock must not execute.');
            });
            $clock = function () use (&$calls, $start, $backward) {
                return $calls++ === 0 ? $start : $start->modify($backward ? '-1 second' : '+10 seconds');
            };
            self::assertInstanceOf(JobOutcomeUnknown::class, $this->rejects(fn () => $this->adapter($r, $a, clock: $clock, budget: 100)->handle($j, $j->data['items'][0])));
            self::assertSame(0, $a->executions);
        }
        $a = $this->scriptedAuthority(function () {
            self::fail('Missing gate must deny.');
        }, function () {
            self::fail('Missing gate must deny.');
        });
        $h = new ReceiptAwareJobHandler($r, $this->store(), $a, fn () => $this->handlerPolicy(), 'synthetic-handler', 'handler-1', 5000);
        $this->rejects(fn () => $h->handle($j, $j->data['items'][0]));
        self::assertSame(0, $a->lookups);
        self::assertSame(0, $a->executions);
    }

    public function test_clock_must_not_roll_back_between_two_post_lookup_observations(): void
    {
        $r = $this->repo();
        $j = $this->command(1);
        $r->enqueue($j);
        $start = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $calls = 0;
        $clock = function () use (&$calls, $start) {
            $n = $calls++;

            return $n === 0 ? $start : $start->modify($n === 1 ? '+10 milliseconds' : '+5 milliseconds');
        };
        $a = $this->scriptedAuthority(fn ($q) => $this->signed(JobItemLookupResponse::class, array_replace($this->handlerLookup($q)->data, ['checkedAt' => $q->data['issuedAt']])), fn ($c) => $this->handlerReceipt($c));
        $this->rejects(fn () => $this->adapter($r, $a, clock: $clock)->handle($j, $j->data['items'][0]));
        self::assertSame(0, $a->executions);
    }

    public function test_only_exact_testing_lab_names_are_allowed_before_any_ddl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HandlerLabDatabase::install((new Client(config('database.connections.mongodb.dsn')))->selectDatabase('studentity_dev_m0'));
    }

    public function test_strict_schema_rejects_unknown_fields_without_metadata_repair(): void
    {
        $r = $this->repo();
        $j = $this->command(1);
        $r->enqueue($j);
        $s = $this->store();
        $c = $s->prepare($j, $j->data['items'][0], 'synthetic-handler', 'handler-1', $r->originalForHandler($j, $j->data['items'][0]));
        $this->expectException(BulkWriteException::class);
        $this->handlerDb()->handler_items_v1->updateOne(['_id' => $c->data['itemOperationId']], ['$set' => ['unexpected' => 'synthetic']]);
    }
}
