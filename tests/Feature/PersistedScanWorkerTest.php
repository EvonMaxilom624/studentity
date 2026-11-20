<?php

namespace Tests\Feature;

use App\Domain\Documents\FileScanCommand;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Infrastructure\Documents\MongoOriginalScanCommandStore;
use App\Infrastructure\Documents\PersistedScanWorker;
use App\Infrastructure\Documents\ScanCommandLabDatabase;
use Illuminate\Support\Str;
use MongoDB\Client;
use Tests\Support\FileSecurityFixtures;
use Tests\TestCase;

final class PersistedScanWorkerTest extends TestCase
{
    use FileSecurityFixtures;

    private function commands()
    {
        $db = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase(ScanCommandLabDatabase::NAME);
        ScanCommandLabDatabase::install($db);

        return new MongoOriginalScanCommandStore($db, fn () => true);
    }

    private function command(string $at = '2026-11-18T00:00:00.000Z'): FileScanCommand
    {
        return new FileScanCommand((string) Str::uuid(), $this->fileReference(), $this->filePolicy(), $at);
    }

    public function test_original_is_durable_before_any_scanner_call(): void
    {
        $s = $this->commands();
        $c = $this->command();
        $calls = 0;
        $scanner = $this->fileScanner(function ($o, $b, $p, $id) use ($c) {
            self::assertSame($c->scanId, $id);

            return $this->fileResult($o, $p, ['scanId' => $id]);
        }, function () use ($s, $c, &$calls) {
            $calls++;
            self::assertSame($c->wire(), $s->lookup($c->scanId)->wire());

            return true;
        });
        $w = new PersistedScanWorker($s, $this->inspectionService(scanner: $scanner));
        self::assertSame($c->scanId, $w->run($c)->scanId);
        self::assertGreaterThan(0, $calls);
    }

    public function test_restart_after_scanner_uncertainty_reuses_original_id_and_body(): void
    {
        $c = $this->command();
        $ids = [];
        $scanner = $this->fileScanner(function ($o, $b, $p, $id) use (&$ids) {
            $ids[] = $id;
            throw new \RuntimeException('synthetic lost scanner acknowledgement');
        });
        $w = new PersistedScanWorker($this->commands(), $this->inspectionService(scanner: $scanner));
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $w->run($c)));
        $scanner = $this->fileScanner(function ($o, $b, $p, $id) use (&$ids) {
            $ids[] = $id;

            return $this->fileResult($o, $p, ['scanId' => $id]);
        });
        $fresh = new PersistedScanWorker($this->commands(), $this->inspectionService(scanner: $scanner));
        self::assertSame($c->scanId, $fresh->resume($c->scanId)->scanId);
        self::assertSame([$c->scanId, $c->scanId], $ids);
        self::assertSame($c->wire(), $this->commands()->lookup($c->scanId)->wire());
    }

    public function test_changed_policy_or_future_original_time_fence_before_storage_and_scanner(): void
    {
        foreach ([false, true] as $future) {
            $c = $this->command($future ? '2099-01-01T00:00:00.000Z' : '2026-11-18T00:00:00.000Z');
            $store = $this->fileStore();
            $scanner = $this->fileScanner();
            $service = $this->inspectionService($store, $scanner, policy: fn () => $this->filePolicy($future ? [] : ['signatureVersion' => 'new-definitions']));
            $w = new PersistedScanWorker($this->commands(), $service);
            self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $w->run($c)));
            self::assertSame(0, $store->reads);
            self::assertSame(0, $scanner->scans);
            self::assertSame(0, $scanner->healthChecks);
        }
    }

    public function test_missing_command_never_generates_a_replacement_or_contacts_scanner(): void
    {
        $scanner = $this->fileScanner();
        $w = new PersistedScanWorker($this->commands(), $this->inspectionService(scanner: $scanner));
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $w->resume((string) Str::uuid())));
        self::assertSame(0, $scanner->scans);
        self::assertSame(0, $scanner->healthChecks);
    }
}
