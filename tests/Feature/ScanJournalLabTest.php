<?php

namespace Tests\Feature;

use App\Domain\Documents\FileScanCommand;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\QuarantineInspectionService;
use App\Infrastructure\Documents\MongoScanAttemptJournal;
use App\Infrastructure\Documents\ScanJournalLabDatabase;
use Illuminate\Support\Str;
use MongoDB\Client;
use MongoDB\Driver\Exception\BulkWriteException;
use Symfony\Component\Process\Process;
use Tests\Support\FileSecurityFixtures;
use Tests\TestCase;

final class ScanJournalLabTest extends TestCase
{
    use FileSecurityFixtures;

    private function db()
    {
        return (new Client(config('database.connections.mongodb.dsn')))->selectDatabase(ScanJournalLabDatabase::NAME);
    }

    private function journal(?\Closure $before = null, ?\Closure $after = null, ?\Closure $gate = null, ?\Closure $recovery = null)
    {
        ScanJournalLabDatabase::install($this->db());

        return new MongoScanAttemptJournal($this->db(), $gate ?? fn () => true, $recovery ?? fn () => true, $before, $after);
    }

    private function command(): FileScanCommand
    {
        return new FileScanCommand((string) Str::uuid(), $this->fileReference(['objectId' => (string) Str::uuid()]), $this->filePolicy(), '2026-11-18T00:00:00.000Z');
    }

    private function nonce(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function scanResult($c, $n, $status = 'clean')
    {
        return $this->fileResult($c->object, $c->policy, ['scanId' => $c->scanId, 'attemptNonce' => $n, 'status' => $status, 'malwareClean' => $status === 'clean']);
    }

    private function counts($c): array
    {
        $a = [];
        foreach (['scan_heads', 'scan_history', 'scan_audit', 'scan_outbox'] as $n) {
            $a[] = $this->db()->selectCollection($n)->countDocuments(['object_id' => $c->object->objectId]);
        }

        return $a;
    }

    public function test_history_head_audit_outbox_are_durable_and_exact_replays_do_not_duplicate(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $n = $this->nonce();
        $j->begin($c, $n);
        $j->begin($c, $n);
        self::assertSame([1, 1, 1, 0], $this->counts($c));
        self::assertSame('pending', $j->state($c)['state']);
        $r = $this->scanResult($c, $n);
        $j->complete($c, $r);
        self::assertSame('cleared', $this->journal()->state($c)['state']);
        $j->complete($c, $r);
        self::assertSame([1, 2, 2, 1], $this->counts($c));
    }

    public function test_new_pending_and_unsafe_head_prevent_old_clean_completion(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $a = $this->nonce();
        $j->begin($c, $a);
        $old = $this->scanResult($c, $a);
        $b = $this->nonce();
        $j->begin($c, $b);
        self::assertSame('pending', $j->state($c)['state']);
        $this->fileRejects(fn () => $j->complete($c, $old));
        $j->complete($c, $this->scanResult($c, $b, 'unsafe'));
        self::assertSame('unsafe', $j->state($c)['state']);
        self::assertSame(2, $j->state($c)['generation']);
        self::assertSame([1, 3, 3, 1], $this->counts($c));
    }

    public function test_older_head_restore_cannot_hide_newer_history(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $a = $this->nonce();
        $j->begin($c, $a);
        $j->complete($c, $this->scanResult($c, $a));
        $old = $this->db()->scan_heads->findOne(['_id' => $c->object->objectId]);
        $b = $this->nonce();
        $j->begin($c, $b);
        $j->complete($c, $this->scanResult($c, $b, 'unsafe'));
        $this->db()->scan_heads->replaceOne(['_id' => $c->object->objectId], $old);
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $j->state($c)));
    }

    public function test_state_audit_history_and_outbox_abort_together(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $n = $this->nonce();
        $j->begin($c, $n);
        $fault = $this->journal(before: function ($stage) {
            if ($stage === 'complete') {
                throw new \RuntimeException('synthetic abort');
            }
        });
        $r = $this->scanResult($c, $n);
        $this->fileRejects(fn () => $fault->complete($c, $r));
        self::assertSame([1, 1, 1, 0], $this->counts($c));
        self::assertSame('pending', $j->state($c)['state']);
        $j->complete($c, $r);
        self::assertSame([1, 2, 2, 1], $this->counts($c));
    }

    public function test_lost_commit_ack_replays_original_result_without_duplicate_events(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $n = $this->nonce();
        $j->begin($c, $n);
        $r = $this->scanResult($c, $n);
        $fault = $this->journal(after: function ($stage) {
            if ($stage === 'complete') {
                throw new \RuntimeException('lost ACK');
            }
        });
        $this->fileRejects(fn () => $fault->complete($c, $r));
        $this->journal()->complete($c, $r);
        self::assertSame([1, 2, 2, 1], $this->counts($c));
    }

    public function test_default_current_and_recovery_gates_deny_without_writes(): void
    {
        $this->journal();
        $c = $this->command();
        foreach ([new MongoScanAttemptJournal($this->db()), $this->journal(gate: fn () => false), $this->journal(recovery: fn () => false)] as $j) {
            $this->fileRejects(fn () => $j->begin($c, $this->nonce()));
            $this->fileRejects(fn () => $j->state($c));
        }self::assertSame([0, 0, 0, 0], $this->counts($c));
    }

    public function test_object_binding_and_result_tuple_cannot_be_changed(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $n = $this->nonce();
        $j->begin($c, $n);
        $bad = new FileScanCommand((string) Str::uuid(), $this->fileReference(['objectId' => $c->object->objectId, 'ownerUserId' => (string) Str::uuid()]), $c->policy, $c->issuedAt);
        $this->fileRejects(fn () => $j->begin($bad, $this->nonce()));
        $r = $this->fileResult($c->object, $c->policy, ['scanId' => $c->scanId, 'attemptNonce' => $this->nonce()]);
        $this->fileRejects(fn () => $j->complete($c, $r));
        self::assertSame([1, 1, 1, 0], $this->counts($c));
    }

    public function test_reverse_orphan_outbox_and_corrupt_history_fence_without_repair(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $n = $this->nonce();
        $j->begin($c, $n);
        $this->db()->scan_outbox->insertOne(['_id' => (string) Str::uuid(), 'object_id' => $c->object->objectId, 'wire' => '{}', 'hash' => str_repeat('0', 64)]);
        $this->fileRejects(fn () => $j->state($c));
        self::assertSame(1, $this->db()->scan_outbox->countDocuments(['object_id' => $c->object->objectId]));
        $c = $this->command();
        $j->begin($c, $this->nonce());
        $this->db()->scan_history->updateOne(['object_id' => $c->object->objectId], ['$set' => ['hash' => str_repeat('0', 64)]]);
        $this->fileRejects(fn () => $j->state($c));
    }

    public function test_service_admission_precedes_even_scanner_health_and_completion_is_journaled(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $calls = 0;
        $scanner = $this->fileScanner(health: function () use ($j, $c, &$calls) {
            $calls++;
            self::assertNotNull($j->state($c));

            return true;
        });
        $s = new QuarantineInspectionService($this->fileStore(), $scanner, fn () => $c->policy, fn () => true, fn () => new \DateTimeImmutable, $j);
        $s->inspectCommand($c);
        self::assertGreaterThan(0, $calls);
        self::assertSame('cleared', $j->state($c)['state']);
    }

    public function test_scanner_failure_leaves_pending_admission_without_result_or_promotion(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $s = new QuarantineInspectionService($this->fileStore(), $this->fileScanner(health: fn () => false), fn () => $c->policy, fn () => true, fn () => new \DateTimeImmutable, $j);
        $this->fileRejects(fn () => $s->inspectCommand($c));
        self::assertSame('pending', $j->state($c)['state']);
        self::assertSame([1, 1, 1, 0], $this->counts($c));
    }

    public function test_reentrant_newer_attempt_wins_and_older_call_cannot_promote(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $outer = true;
        $s = null;
        $scanner = $this->fileScanner(function ($o, $b, $p, $id) use (&$outer, &$s, $c) {
            if ($outer) {
                $outer = false;
                $s->inspectCommand($c);

                return $this->fileResult($o, $p, ['scanId' => $id]);
            }

            return $this->fileResult($o, $p, ['scanId' => $id, 'status' => 'unsafe', 'malwareClean' => false]);
        });
        $s = new QuarantineInspectionService($this->fileStore(), $scanner, fn () => $c->policy, fn () => true, fn () => new \DateTimeImmutable, $j);
        $this->fileRejects(fn () => $s->inspectCommand($c));
        self::assertSame('unsafe', $j->state($c)['state']);
        self::assertSame([1, 3, 3, 1], $this->counts($c));
    }

    public function test_recovery_revocation_blocks_existing_clean_state_and_completion(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $n = $this->nonce();
        $j->begin($c, $n);
        $r = $this->scanResult($c, $n);
        $j->complete($c, $r);
        $closed = $this->journal(recovery: fn () => false);
        $this->fileRejects(fn () => $closed->state($c));
        $this->fileRejects(fn () => $closed->complete($c, $r));
        self::assertSame([1, 2, 2, 1], $this->counts($c));
    }

    public function test_completed_result_replay_conflict_and_outbox_tamper_are_fenced(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $n = $this->nonce();
        $j->begin($c, $n);
        $r = $this->scanResult($c, $n);
        $j->complete($c, $r);
        $this->fileRejects(fn () => $j->complete($c, $this->scanResult($c, $n, 'unsafe')));
        self::assertSame('cleared', $j->state($c)['state']);
        $this->db()->scan_outbox->updateOne(['object_id' => $c->object->objectId], ['$set' => ['hash' => str_repeat('0', 64)]]);
        $this->fileRejects(fn () => $j->state($c));
        self::assertSame([1, 2, 2, 1], $this->counts($c));
    }

    public function test_strict_schema_rejects_payload_fields(): void
    {
        $this->journal();
        $this->expectException(BulkWriteException::class);
        $this->db()->scan_heads->insertOne(['_id' => (string) Str::uuid(), 'object_id' => (string) Str::uuid(), 'wire' => '{}', 'hash' => str_repeat('0', 64), 'bytes' => 'forbidden']);
    }

    public function test_two_processes_admit_before_completion_and_only_latest_attempt_finishes(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $barrier = (string) Str::uuid();
        $dir = storage_path('app/private/fnd06-journal-races/'.$barrier);
        mkdir($dir, 0700, true);
        $workers = [];
        try {
            foreach ([0, 1] as $slot) {
                $w = new Process([PHP_BINARY, base_path('tests/Support/scan-journal-worker.php')], base_path(), ['APP_ENV' => 'testing']);
                $w->setInput(json_encode(['command' => $c->wire(), 'nonce' => $this->nonce(), 'barrier' => $barrier, 'slot' => $slot], JSON_THROW_ON_ERROR));
                $w->setTimeout(20);
                $w->start();
                $workers[] = $w;
            }
            $deadline = microtime(true) + 15;
            while ((! is_file($dir.'/ready-0') || ! is_file($dir.'/ready-1')) && microtime(true) < $deadline) {
                clearstatcache();
                usleep(10000);
            }self::assertFileExists($dir.'/ready-0');
            self::assertFileExists($dir.'/ready-1');
            self::assertFileDoesNotExist($dir.'/admitted-0');
            self::assertFileDoesNotExist($dir.'/admitted-1');
            file_put_contents($dir.'/release', 'go');
            $accepted = 0;
            foreach ($workers as $w) {
                $w->wait();
                self::assertSame(0, $w->getExitCode(), $w->getErrorOutput());
                $accepted += json_decode($w->getOutput(), true, 32, JSON_THROW_ON_ERROR)['accepted'] ? 1 : 0;
            }self::assertSame(1, $accepted);
            self::assertSame('unsafe', $j->state($c)['state']);
            self::assertSame(2, $j->state($c)['generation']);
            self::assertSame([1, 3, 3, 1], $this->counts($c));
        } finally {
            foreach ($workers as $w) {
                if ($w->isRunning()) {
                    $w->stop(1);
                }
            }
        }
    }

    public function test_orphan_duplicate_head_is_detected_in_reverse_evidence_check(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $j->begin($c, $this->nonce());
        $this->db()->scan_heads->insertOne(['_id' => (string) Str::uuid(), 'object_id' => $c->object->objectId, 'wire' => '{}', 'hash' => str_repeat('0', 64)]);
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $j->state($c)));
    }

    public function test_same_scan_id_cannot_change_its_command_within_object_history(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $j->begin($c, $this->nonce());
        $changed = new FileScanCommand($c->scanId, $c->object, $this->filePolicy(['signatureVersion' => 'new-definitions']), $c->issuedAt);
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $j->begin($changed, $this->nonce())));
    }

    public function test_accepted_database_rejected_before_ddl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ScanJournalLabDatabase::install((new Client(config('database.connections.mongodb.dsn')))->selectDatabase('studentity_dev_m0'));
    }
}
