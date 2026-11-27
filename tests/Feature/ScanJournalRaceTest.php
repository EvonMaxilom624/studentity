<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Documents\FileScanCommand;
use App\Infrastructure\Documents\MongoScanAttemptJournal;
use App\Infrastructure\Documents\ScanJournalLabDatabase;
use Illuminate\Support\Str;
use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Driver\Monitoring\CommandFailedEvent;
use MongoDB\Driver\Monitoring\CommandStartedEvent;
use MongoDB\Driver\Monitoring\CommandSubscriber;
use MongoDB\Driver\Monitoring\CommandSucceededEvent;
use Symfony\Component\Process\Process;
use Tests\Support\FileSecurityFixtures;
use Tests\TestCase;

final class ScanJournalRaceTest extends TestCase
{
    use FileSecurityFixtures;

    private function db(): Database
    {
        return (new Client(config('database.connections.mongodb.dsn')))->selectDatabase(ScanJournalLabDatabase::NAME);
    }

    private function journal(?Database $database = null): MongoScanAttemptJournal
    {
        $database ??= $this->db();
        ScanJournalLabDatabase::install($database);

        return new MongoScanAttemptJournal($database, fn () => true, fn () => true);
    }

    private function command(): FileScanCommand
    {
        return new FileScanCommand((string) Str::uuid(), $this->fileReference(['objectId' => (string) Str::uuid()]), $this->filePolicy(), '2026-11-18T00:00:00.000Z');
    }

    public function test_unadmitted_nonce_cannot_replace_a_completed_result(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $n1 = bin2hex(random_bytes(32));
        $n2 = bin2hex(random_bytes(32));

        $j->begin($c, $n1);

        $r1 = $this->fileResult($c->object, $c->policy, ['scanId' => $c->scanId, 'attemptNonce' => $n1, 'status' => 'clean']);
        $r2 = $this->fileResult($c->object, $c->policy, ['scanId' => $c->scanId, 'attemptNonce' => $n2, 'status' => 'clean']);

        $j->complete($c, $r1);

        $this->fileRejects(fn () => $j->complete($c, $r2));

        $state = $j->state($c);
        self::assertSame('cleared', $state['state']);
        self::assertSame(1, $state['generation']);
        self::assertSame($n1, $state['nonce']);
        self::assertSame([1, 2, 2, 1], $this->counts($c));
    }

    public function test_stale_clean_completion_is_rejected_while_latest_unsafe_commit_is_held(): void
    {
        $j = $this->journal();
        $c = $this->command();
        $barrier = (string) Str::uuid();
        $dir = storage_path('app/private/fnd06-journal-races/'.$barrier);
        mkdir($dir, 0700, true);
        $workers = [];
        $nonces = [bin2hex(random_bytes(32)), bin2hex(random_bytes(32))];
        try {
            foreach ($nonces as $slot => $nonce) {
                $w = $this->worker($c, $nonce, $barrier, $slot, false);
                $w->start();
                $workers[] = $w;
            }
            $this->awaitSignals($dir, 'ready-0', 'ready-1');
            self::assertFileDoesNotExist($dir.'/admitted-0');
            self::assertFileDoesNotExist($dir.'/admitted-1');
            file_put_contents($dir.'/release', 'go');
            $this->awaitSignals($dir, 'staged', 'rejected');
            $pending = $j->state($c);
            self::assertSame('pending', $pending['state']);
            self::assertSame(2, $pending['sequence']);
            self::assertFalse($pending['done']);
            $winner = array_search($pending['nonce'], $nonces, true);
            self::assertIsInt($winner);
            self::assertTrue($workers[$winner]->isRunning());
            self::assertSame([1, 2, 2, 0], $this->counts($c));

            file_put_contents($dir.'/commit', 'go');
            foreach ($workers as $slot => $w) {
                $w->wait();
                self::assertSame(0, $w->getExitCode(), $w->getOutput().$w->getErrorOutput());
                self::assertSame($slot === $winner, json_decode($w->getOutput(), true, 32, JSON_THROW_ON_ERROR)['accepted']);
            }
            self::assertSame((string) $winner, file_get_contents($dir.'/staged'));
            self::assertSame((string) (1 - $winner), file_get_contents($dir.'/rejected'));
            $head = $j->state($c);
            self::assertSame($nonces[$winner], $head['nonce']);
            self::assertSame('unsafe', $head['state']);
            self::assertTrue($head['done']);
            self::assertSame(2, $head['generation']);
            self::assertSame(3, $head['sequence']);
            self::assertSame([1, 3, 3, 1], $this->counts($c));
        } finally {
            foreach ($workers as $w) {
                if ($w->isRunning()) {
                    $w->stop(1);
                }
            }
        }
    }

    public function test_conflicting_completions_compare_and_swap_the_same_pending_head(): void
    {
        $database = $this->db();
        $journal = $this->journal($database);
        $command = $this->command();
        $nonce = bin2hex(random_bytes(32));
        $journal->begin($command, $nonce);
        $objectId = $command->object->objectId;
        $originalHash = $database->selectCollection('scan_heads')->findOne(['_id' => $objectId])['hash'];

        $barrier = (string) Str::uuid();
        $dir = storage_path('app/private/fnd06-journal-races/'.$barrier);
        mkdir($dir, 0700, true);
        $worker = $this->worker($command, $nonce, $barrier, 0, true);
        $observer = new class($objectId, $originalHash, $dir.'/commit') implements CommandSubscriber
        {
            private const WRITE_CONFLICT = 112;

            private ?string $requestId = null;

            public bool $conflictObserved = false;

            public function __construct(private readonly string $objectId, private readonly string $headHash, private readonly string $release) {}

            public function commandStarted(CommandStartedEvent $event): void
            {
                if ($event->getCommandName() !== 'update' || $event->getDatabaseName() !== ScanJournalLabDatabase::NAME) {
                    return;
                }
                $command = $event->getCommand();
                $filter = $command->updates[0]->q ?? null;
                if ($command->update === 'scan_heads' && ($filter->_id ?? null) === $this->objectId && ($filter->hash ?? null) === $this->headHash) {
                    $this->requestId = $event->getRequestId();
                }
            }

            public function commandFailed(CommandFailedEvent $event): void
            {
                $this->releaseOnConflict($event->getRequestId(), $event->getError()->getCode());
            }

            public function commandSucceeded(CommandSucceededEvent $event): void
            {
                $this->releaseOnConflict($event->getRequestId(), $event->getReply()->writeErrors[0]->code ?? null);
            }

            private function releaseOnConflict(string $requestId, ?int $code): void
            {
                if ($requestId === $this->requestId && $code === self::WRITE_CONFLICT) {
                    $this->conflictObserved = file_put_contents($this->release, 'go', LOCK_EX) !== false;
                }
            }
        };
        $manager = $database->getManager();
        try {
            $worker->start();
            file_put_contents($dir.'/release', 'go', LOCK_EX);
            $this->awaitSignals($dir, 'staged');
            $pending = $journal->state($command);
            self::assertSame(['pending', 1, $nonce], [$pending['state'], $pending['generation'], $pending['nonce']]);
            self::assertSame([1, 1, 1, 0], $this->counts($command));

            $contender = $this->fileResult($command->object, $command->policy, ['scanId' => $command->scanId, 'attemptNonce' => $nonce]);
            $manager->addSubscriber($observer);
            $this->fileRejects(fn () => $journal->complete($command, $contender));
            self::assertTrue($observer->conflictObserved, 'MongoDB must report a write conflict on the original head before the staged worker can commit.');

            self::assertSame(0, $worker->wait(), $worker->getOutput().$worker->getErrorOutput());
            self::assertSame(['accepted' => true], json_decode($worker->getOutput(), true, 32, JSON_THROW_ON_ERROR));
            $head = $journal->state($command);
            self::assertSame(['unsafe', 1, 2, $nonce], [$head['state'], $head['generation'], $head['sequence'], $head['nonce']]);
            self::assertSame([1, 2, 2, 1], $this->counts($command));
        } finally {
            $manager->removeSubscriber($observer);
            if ($worker->isRunning()) {
                $worker->stop(1);
            }
        }
    }

    private function worker(FileScanCommand $command, string $nonce, string $barrier, int $slot, bool $preAdmitted): Process
    {
        $worker = new Process([PHP_BINARY, base_path('tests/Support/scan-journal-worker.php')], base_path(), ['APP_ENV' => 'testing']);
        $worker->setInput(json_encode(['command' => $command->wire(), 'nonce' => $nonce, 'barrier' => $barrier, 'slot' => $slot, 'preAdmitted' => $preAdmitted], JSON_THROW_ON_ERROR));
        $worker->setTimeout(30);

        return $worker;
    }

    private function awaitSignals(string $directory, string ...$signals): void
    {
        $deadline = hrtime(true) + 8_000_000_000;
        foreach ($signals as $signal) {
            $path = $directory.'/'.$signal;
            do {
                clearstatcache(true, $path);
                if (is_file($path)) {
                    break;
                }
                usleep(10_000);
            } while (hrtime(true) < $deadline);
            self::assertFileExists($path);
        }
    }

    private function counts(FileScanCommand $command): array
    {
        $database = $this->db();
        $filter = ['object_id' => $command->object->objectId];
        $counts = [];
        foreach (['scan_heads', 'scan_history', 'scan_audit', 'scan_outbox'] as $collection) {
            $counts[] = $database->selectCollection($collection)->countDocuments($filter);
        }

        return $counts;
    }
}
