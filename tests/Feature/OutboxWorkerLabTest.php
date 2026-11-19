<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Jobs\JobHandlerUnavailable;
use App\Domain\Jobs\JobItemOutcome;
use App\Domain\Jobs\JobLease;
use App\Domain\Jobs\JobOutcomeUnknown;
use App\Domain\Jobs\JobPolicy;
use App\Domain\Jobs\ReferenceJobCommand;
use App\Domain\Jobs\ReferenceJobHandler;
use App\Infrastructure\Jobs\JobAuditTrail;
use App\Infrastructure\Jobs\MongoReferenceJobRepository;
use App\Infrastructure\Jobs\OutboxLabDatabase;
use App\Infrastructure\Jobs\ReferenceJobWorker;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\OutboxFixtures;
use Tests\TestCase;

final class OutboxWorkerLabTest extends TestCase
{
    use OutboxFixtures;

    public function test_enqueue_is_atomic_correlated_and_same_body_idempotent(): void
    {
        $r = $this->repo();
        $c = $this->command();
        $id = $c->data['operationId'];
        $r->enqueue($c);
        $r->enqueue(ReferenceJobCommand::fromJson($c->wire()));
        self::assertSame('queued', $r->status($id));
        self::assertSame(1, $this->db()->audit_events->countDocuments(['target_id' => $id]));
        $d = $c->data;
        $d['correlationId'] = $this->uuid();
        try {
            $r->enqueue(new ReferenceJobCommand($d));
            self::fail('Conflicting body accepted');
        } catch (\RuntimeException) {
            self::assertSame(0, $this->db()->background_jobs->findOne(['_id' => $id])['attempt_count']);
        }
        $next = $this->command();
        try {
            $this->repo(fault: static function ($stage) {
                if ($stage === 'enqueue') {
                    throw new \RuntimeException('synthetic abort');
                }
            })->enqueue($next);
            self::fail('Abort ignored');
        } catch (\RuntimeException) {
            self::assertNull($this->db()->background_jobs->findOne(['_id' => $next->data['operationId']]));
            self::assertSame(0, $this->db()->audit_events->countDocuments(['target_id' => $next->data['operationId']]));
        }
    }

    public function test_item_id_reservations_prevent_cross_job_handler_key_reuse(): void
    {
        $r = $this->repo();
        $c = $this->command(1);
        $r->enqueue($c);
        $d = $this->command(1)->data;
        $d['items'][0]['resultId'] = $c->data['items'][0]['resultId'];
        $this->expectException(\RuntimeException::class);
        $r->enqueue(new ReferenceJobCommand($d));
    }

    public function test_worker_commits_mixed_outcomes_and_final_item_aggregate_atomically(): void
    {
        $r = $this->repo();
        $c = $this->command();
        $id = $c->data['operationId'];
        $r->enqueue($c);
        $handler = new class implements ReferenceJobHandler
        {
            public int $calls = 0;

            public function handle(ReferenceJobCommand $command, array $item): JobItemOutcome
            {
                return ++$this->calls === 1 ? new JobItemOutcome('succeeded', 'none') : new JobItemOutcome('failed', 'synthetic_failure');
            }
        };
        self::assertSame('partial_failure', (new ReferenceJobWorker($r, $handler))->run($id));
        self::assertSame(2, $handler->calls);
        self::assertSame('partial_failure', $r->status($id));
        self::assertSame(2, $this->db()->job_results->countDocuments(['job_id' => $id]));
        self::assertSame(5, $this->db()->audit_events->countDocuments(['target_id' => $id]));
        self::assertNull((new ReferenceJobWorker($r, $handler))->run($id));
        self::assertSame(2, $handler->calls);
    }

    public function test_partial_restart_skips_original_result_and_uses_original_item_operation(): void
    {
        $r = $this->repo(lease: 100);
        $c = $this->command();
        $id = $c->data['operationId'];
        $r->enqueue($c);
        $lease = $r->claim($id);
        $r->record($lease, $c->data['items'][0]['targetId'], new JobItemOutcome('succeeded', 'none'));
        usleep(150000);
        $handler = new class implements ReferenceJobHandler
        {
            public array $seen = [];

            public function handle(ReferenceJobCommand $command, array $item): JobItemOutcome
            {
                $this->seen[] = $item['resultId'];

                return new JobItemOutcome('skipped', 'not_applicable');
            }
        };
        self::assertSame('succeeded', (new ReferenceJobWorker($this->repo(lease: 100), $handler))->run($id));
        self::assertSame([$c->data['items'][1]['resultId']], $handler->seen);
        self::assertSame(2, $this->db()->background_jobs->findOne(['_id' => $id])['attempt_count']);
    }

    public function test_expired_and_forged_owners_cannot_write_after_reclaim(): void
    {
        $r = $this->repo(lease: 100);
        $c = $this->command(1);
        $id = $c->data['operationId'];
        $r->enqueue($c);
        $first = $r->claim($id);
        usleep(150000);
        $second = $r->claim($id);
        self::assertNotNull($second);
        try {
            $r->record($first, $c->data['items'][0]['targetId'], new JobItemOutcome('succeeded', 'none'));
            self::fail('Stale owner accepted');
        } catch (\RuntimeException) {
            self::assertSame(0, $this->db()->job_results->countDocuments(['job_id' => $id]));
        }
        $forged = new JobLease($second->command, $this->uuid(), $second->generation, $second->expiresAt);
        $this->expectException(\RuntimeException::class);
        $r->record($forged, $c->data['items'][0]['targetId'], new JobItemOutcome('succeeded', 'none'));
    }

    public function test_unknown_last_attempt_never_fabricates_terminal_outcome_or_leaks_exception(): void
    {
        $r = $this->repo(lease: 100, attempts: 1);
        $c = $this->command(1);
        $id = $c->data['operationId'];
        $r->enqueue($c);
        $handler = new class implements ReferenceJobHandler
        {
            public function handle(ReferenceJobCommand $command, array $item): JobItemOutcome
            {
                throw new \RuntimeException('synthetic-secret@example.invalid');
            }
        };
        try {
            (new ReferenceJobWorker($r, $handler))->run($id);
            self::fail('Unknown accepted');
        } catch (JobOutcomeUnknown $e) {
            self::assertStringNotContainsString('synthetic-secret', $e->getMessage());
            self::assertNull($e->getPrevious());
        }
        usleep(150000);
        self::assertNull($r->claim($id));
        self::assertSame('running', $r->status($id));
        self::assertSame(0, $this->db()->job_results->countDocuments(['job_id' => $id]));
    }

    public function test_result_ack_retry_is_exact_and_aborted_result_has_no_audit_or_head_change(): void
    {
        $r = $this->repo();
        $c = $this->command();
        $id = $c->data['operationId'];
        $r->enqueue($c);
        $lease = $r->claim($id);
        $item = $c->data['items'][0]['targetId'];
        $outcome = new JobItemOutcome('succeeded', 'none');
        $before = $this->db()->background_jobs->findOne(['_id' => $id]);
        try {
            $this->repo(fault: static function ($stage) {
                if ($stage === 'record') {
                    throw new \RuntimeException('synthetic abort');
                }
            })->record($lease, $item, $outcome);
            self::fail('Abort ignored');
        } catch (\RuntimeException) {
            self::assertSame($before['audit_head_hash'], $this->db()->background_jobs->findOne(['_id' => $id])['audit_head_hash']);
            self::assertSame(0, $this->db()->job_results->countDocuments(['job_id' => $id]));
        }
        $r->record($lease, $item, $outcome);
        $n = $this->db()->audit_events->countDocuments(['target_id' => $id]);
        $r->record($lease, $item, $outcome);
        self::assertSame($n, $this->db()->audit_events->countDocuments(['target_id' => $id]));
        $this->expectException(\RuntimeException::class);
        $r->record($lease, $item, new JobItemOutcome('failed', 'synthetic_failure'));
    }

    #[DataProvider('tampering')]
    public function test_schema_valid_command_policy_lease_result_and_audit_edits_fence_processing(string $attack): void
    {
        $r = $this->repo();
        $c = $this->command();
        $id = $c->data['operationId'];
        $r->enqueue($c);
        $lease = $r->claim($id);
        $r->record($lease, $c->data['items'][0]['targetId'], new JobItemOutcome('succeeded', 'none'));
        $db = $this->db();
        switch ($attack) {
            case 'command':$db->background_jobs->updateOne(['_id' => $id], ['$set' => ['payload_redacted.correlationId' => $this->uuid()]]);
                break;
            case 'policy':$db->background_jobs->updateOne(['_id' => $id], ['$set' => ['max_attempts' => 10]]);
                break;
            case 'lease':$db->background_jobs->updateOne(['_id' => $id], ['$set' => ['lease_until' => new UTCDateTime(new \DateTimeImmutable('+1 hour'))]]);
                break;
            case 'result':$db->job_results->updateOne(['job_id' => $id], ['$set' => ['attempted_at' => new UTCDateTime(0)]]);
                break;
            case 'audit':$db->audit_events->updateOne(['target_id' => $id, 'action' => 'job.item-completed'], ['$set' => ['event_redacted.outcome' => 'failed']]);
                break;
        }
        $this->expectException(\RuntimeException::class);
        $r->status($id);
    }

    public static function tampering(): array
    {
        return [['command'], ['policy'], ['lease'], ['result'], ['audit']];
    }

    public function test_default_gate_and_default_handler_deny_without_job_execution(): void
    {
        $r = $this->repo();
        $c = $this->command(1);
        $r->enqueue($c);
        $id = $c->data['operationId'];
        try {
            (new ReferenceJobWorker($r))->run($id);
            self::fail('Missing handler accepted');
        } catch (JobHandlerUnavailable) {
            self::assertSame(0, $this->db()->background_jobs->findOne(['_id' => $id])['attempt_count']);
        }
        $this->expectException(\RuntimeException::class);
        $this->repo(gate: static fn () => false)->claim($id);
    }

    #[DataProvider('entryPoints')]
    public function test_accepted_database_is_rejected_before_install(string $entry): void
    {
        $db = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase('studentity_test_m0');
        $this->expectException(\InvalidArgumentException::class);
        if ($entry === 'install') {
            OutboxLabDatabase::install($db);
        } else {
            new JobAuditTrail($db, new JobPolicy('synthetic-job-policy-v1', 3, 60000));
        }
    }

    public static function entryPoints(): array
    {
        return [['install'], ['audit']];
    }

    #[DataProvider('terminalOutcomes')]
    public function test_all_known_last_attempt_outcomes_finalize_without_an_extra_worker_claim(string $result, string $code, string $expected): void
    {
        $r = $this->repo(attempts: 1);
        $c = $this->command();
        $r->enqueue($c);
        $handler = new class($result, $code) implements ReferenceJobHandler
        {
            public function __construct(private string $result, private string $code) {}

            public function handle(ReferenceJobCommand $command, array $item): JobItemOutcome
            {
                return new JobItemOutcome($this->result, $this->code);
            }
        };
        self::assertSame($expected, (new ReferenceJobWorker($r, $handler))->run($c->data['operationId']));
        self::assertSame($expected, $r->status($c->data['operationId']));
        self::assertNull($r->claim($c->data['operationId']));
    }

    public static function terminalOutcomes(): array
    {
        return [['succeeded', 'none', 'succeeded'], ['failed', 'synthetic_failure', 'failed'], ['skipped', 'not_applicable', 'succeeded']];
    }

    public function test_lost_handler_ack_reuses_same_original_item_receipt_on_retry(): void
    {
        $r = $this->repo(lease: 100);
        $c = $this->command(1);
        $r->enqueue($c);
        $handler = new class implements ReferenceJobHandler
        {
            public int $effects = 0;

            public array $keys = [];

            private array $receipts = [];

            public function handle(ReferenceJobCommand $command, array $item): JobItemOutcome
            {
                $key = $item['resultId'];
                $this->keys[] = $key;
                if (! isset($this->receipts[$key])) {
                    $this->effects++;
                    $this->receipts[$key] = new JobItemOutcome('succeeded', 'none');
                    throw new \RuntimeException('synthetic lost acknowledgment');
                }

                return $this->receipts[$key];
            }
        };
        try {
            (new ReferenceJobWorker($r, $handler))->run($c->data['operationId']);
            self::fail('Expected unknown');
        } catch (JobOutcomeUnknown) {
        }
        usleep(150000);
        self::assertSame('succeeded', (new ReferenceJobWorker($this->repo(lease: 100), $handler))->run($c->data['operationId']));
        self::assertSame(1, $handler->effects);
        self::assertSame([$c->data['items'][0]['resultId'], $c->data['items'][0]['resultId']], $handler->keys);
    }

    public function test_backward_database_observation_cannot_append_before_last_event(): void
    {
        $r = $this->repo();
        $c = $this->command(1);
        $r->enqueue($c);
        $row = $this->db()->background_jobs->findOne(['_id' => $c->data['operationId']]);
        $rejected = false;
        try {
            OutboxLabDatabase::transaction($this->db(), function ($o) use ($c, $row, &$rejected) {
                try {
                    (new JobAuditTrail($this->db(), new JobPolicy('synthetic-job-policy-v1', 3, 60000)))->append($row, $c, $this->uuid(), 'job.queued', 'queued', new UTCDateTime(0), $o);
                } catch (\RuntimeException) {
                    $rejected = true;
                }
                throw new \RuntimeException('Abort this synthetic clock probe regardless.');
            });
        } catch (\RuntimeException) {
        }
        self::assertTrue($rejected);
        self::assertSame('queued', $r->status($c->data['operationId']));
    }

    public function test_barrier_coordinated_claims_have_one_live_owner(): void
    {
        $r = $this->repo();
        $c = $this->command(1);
        $id = $c->data['operationId'];
        $r->enqueue($c);
        $barrierId = $this->uuid();
        $barrier = storage_path('app/private/fnd04-outbox-barriers/'.$barrierId);
        mkdir($barrier, 0700, true);
        $workers = [];
        try {
            foreach ([0, 1] as $slot) {
                $w = new Process([PHP_BINARY, base_path('tests/Support/outbox-claim-worker.php')], base_path(), ['APP_ENV' => 'testing']);
                $w->setInput(json_encode(['operationId' => $id, 'barrierId' => $barrierId, 'slot' => $slot], JSON_THROW_ON_ERROR));
                $w->setTimeout(20);
                $w->start();
                $workers[] = $w;
            }
            $deadline = hrtime(true) + 5_000_000_000;
            while (hrtime(true) < $deadline) {
                clearstatcache();
                if (is_file($barrier.'/0.ready') && is_file($barrier.'/1.ready')) {
                    break;
                }if (! $workers[0]->isRunning() || ! $workers[1]->isRunning()) {
                    break;
                }usleep(10000);
            }
            foreach ([0, 1] as $slot) {
                self::assertFileExists($barrier.'/'.$slot.'.ready');
                self::assertTrue($workers[$slot]->isRunning());
                self::assertFileDoesNotExist($barrier.'/'.$slot.'.entered');
            }
            self::assertSame(0, $this->db()->background_jobs->findOne(['_id' => $id])['attempt_count']);
            file_put_contents($barrier.'/release', 'release', LOCK_EX);
            $wins = 0;
            foreach ($workers as $w) {
                $w->wait();
                self::assertSame(0, $w->getExitCode(), $w->getOutput());
                self::assertSame('', $w->getErrorOutput());
                $wins += (int) json_decode($w->getOutput(), true, 8, JSON_THROW_ON_ERROR)['claimed'];
            }
            self::assertSame(1, $wins);
            self::assertSame(1, $this->db()->background_jobs->findOne(['_id' => $id])['attempt_count']);
            self::assertSame('running', $r->status($id));
        } finally {
            foreach ($workers as $w) {
                if ($w->isRunning()) {
                    $w->stop(0);
                }
            }
        }
    }

    public function test_other_job_claim_audit_identifier_cannot_be_reserved_by_a_new_command(): void
    {
        $r = $this->repo();
        $a = $this->command(1);
        $r->enqueue($a);
        $r->claim($a->data['operationId']);
        $audit = $this->db()->audit_events->findOne(['target_id' => $a->data['operationId'], 'action' => 'job.claimed']);
        $b = $this->command(1)->data;
        $b['items'][0]['auditId'] = $audit['_id'];
        $error = null;
        try {
            $r->enqueue(new ReferenceJobCommand($b));
        } catch (\RuntimeException $e) {
            $error = $e;
        }
        self::assertNotNull($error);
        self::assertNull($this->db()->background_jobs->findOne(['_id' => $b['operationId']]));
    }

    public function test_foreign_orphan_result_uuid_is_rejected_before_new_job_or_handler(): void
    {
        $r = $this->repo();
        $c = $this->command(1);
        $row = ['_id' => $c->data['items'][0]['resultId'], 'schema_version' => 1, 'job_id' => $this->uuid(), 'target_ref' => $this->uuid(), 'command_fingerprint' => str_repeat('a', 64), 'result' => 'succeeded', 'error_code' => 'none', 'attempted_at' => new UTCDateTime];
        $row['outcome_fingerprint'] = JobAuditTrail::resultHash($row);
        $this->db()->job_results->insertOne($row);
        $error = null;
        try {
            $r->enqueue($c);
        } catch (\RuntimeException $e) {
            $error = $e;
        }self::assertNotNull($error);
        self::assertNull($this->db()->background_jobs->findOne(['_id' => $c->data['operationId']]));
    }

    public function test_restart_with_trailing_completed_item_returns_atomic_terminal_result(): void
    {
        $r = $this->repo(lease: 100);
        $c = $this->command();
        $id = $c->data['operationId'];
        $r->enqueue($c);
        $lease = $r->claim($id);
        $r->record($lease, $c->data['items'][1]['targetId'], new JobItemOutcome('succeeded', 'none'));
        usleep(150000);
        $handler = new class implements ReferenceJobHandler
        {
            public array $seen = [];

            public function handle(ReferenceJobCommand $command, array $item): JobItemOutcome
            {
                $this->seen[] = $item['resultId'];

                return new JobItemOutcome('succeeded', 'none');
            }
        };
        self::assertSame('succeeded', (new ReferenceJobWorker($this->repo(lease: 100), $handler))->run($id));
        self::assertSame([$c->data['items'][0]['resultId']], $handler->seen);
    }

    public function test_stock_mongodb_type_map_supports_enqueue_replay_claim_and_results(): void
    {
        $this->repo();
        $db = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase(OutboxLabDatabase::NAME);
        $r = new MongoReferenceJobRepository($db, new JobPolicy('synthetic-job-policy-v1', 3, 60000), static fn () => true);
        $c = $this->command(1);
        $r->enqueue($c);
        $r->enqueue($c);
        self::assertSame('queued', $r->status($c->data['operationId']));
        $lease = $r->claim($c->data['operationId']);
        $r->record($lease, $c->data['items'][0]['targetId'], new JobItemOutcome('succeeded', 'none'));
        self::assertSame('succeeded', $r->status($c->data['operationId']));
    }

    public function test_untransactional_audit_append_cannot_leave_orphan_event_on_head_conflict(): void
    {
        $r = $this->repo();
        $c = $this->command(1);
        $id = $c->data['operationId'];
        $r->enqueue($c);
        $row = $this->db()->background_jobs->findOne(['_id' => $id]);
        $head = $row['audit_head_hash'];
        $row['audit_head_hash'] = str_repeat('e', 64);
        $error = null;
        try {
            (new JobAuditTrail($this->db(), new JobPolicy('synthetic-job-policy-v1', 3, 60000)))->append($row, $c, $this->uuid(), 'job.queued', 'queued', new UTCDateTime, []);
        } catch (\RuntimeException $e) {
            $error = $e;
        }
        self::assertNotNull($error);
        self::assertSame(1, $this->db()->audit_events->countDocuments(['target_id' => $id]));
        self::assertSame($head, $this->db()->background_jobs->findOne(['_id' => $id])['audit_head_hash']);
    }

    public function test_dispatch_rejects_clock_before_the_latest_verified_item_observation(): void
    {
        $r = $this->repo();
        $c = $this->command();
        $id = $c->data['operationId'];
        $r->enqueue($c);
        $lease = $r->claim($id);
        $first = $c->data['items'][0];
        $r->record($lease, $first['targetId'], new JobItemOutcome('succeeded', 'none'));
        // Coherent synthetic future evidence models a DB wall-clock rollback without changing the system clock.
        $row = $this->db()->job_results->findOne(['_id' => $first['resultId']]);
        $future = new UTCDateTime(new \DateTimeImmutable('+30 seconds'));
        $row['attempted_at'] = $future;
        $row['outcome_fingerprint'] = JobAuditTrail::resultHash($row);
        $this->db()->job_results->replaceOne(['_id' => $row['_id']], $row);
        $event = $this->db()->audit_events->findOne(['_id' => $first['auditId']]);
        $event['job_sequence'] = new Int64((string) $event['job_sequence']);
        $event['occurred_at'] = $future;
        $event['event_redacted']['outcomeFingerprint'] = $row['outcome_fingerprint'];
        unset($event['event_hash']);
        $hash = hash('sha256', OutboxLabDatabase::typed($event));
        $event['event_hash'] = new Binary(hex2bin($hash));
        $this->db()->audit_events->replaceOne(['_id' => $event['_id']], $event);
        $this->db()->background_jobs->updateOne(['_id' => $id], ['$set' => ['audit_head_hash' => $hash]]);
        $error = null;
        try {
            $r->result($lease, $c->data['items'][1]['targetId']);
        } catch (\RuntimeException $e) {
            $error = $e;
        }self::assertNotNull($error);
    }

    public function test_expired_lease_denies_dispatch_even_without_reclaim(): void
    {
        $r = $this->repo(lease: 100);
        $c = $this->command(1);
        $r->enqueue($c);
        $lease = $r->claim($c->data['operationId']);
        usleep(150000);
        $this->expectException(\RuntimeException::class);
        $r->result($lease, $c->data['items'][0]['targetId']);
    }

    public function test_full_resource_bound_reserves_all_ten_future_claim_audit_ids(): void
    {
        $r = $this->repo(attempts: 10);
        $c = $this->command(100);
        $r->enqueue($c);
        $row = $this->db()->background_jobs->findOne(['_id' => $c->data['operationId']]);
        self::assertCount(112, $row['reserved_audit_ids']);
        self::assertCount(112,array_unique($row['reserved_audit_ids']));
        self::assertSame('queued',$r->status($c->data['operationId']));
    }
}
