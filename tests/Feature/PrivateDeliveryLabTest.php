<?php

namespace Tests\Feature;

use App\Domain\Documents\FileScanCommand;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\PrivateDeliveryAuthority;
use App\Domain\Documents\PrivateDeliveryReader;
use App\Domain\Documents\PrivateFileDelivery;
use App\Domain\Documents\QuarantineObjectReference;
use App\Infrastructure\Documents\JournalPrivateDeliveryAuthority;
use App\Infrastructure\Documents\LocalPrivateDeliveryLabReader;
use App\Infrastructure\Documents\LocalQuarantineLabStore;
use App\Infrastructure\Documents\MongoScanAttemptJournal;
use App\Infrastructure\Documents\ScanJournalLabDatabase;
use Illuminate\Support\Str;
use MongoDB\Client;
use Tests\Support\FileSecurityFixtures;
use Tests\TestCase;

final class PrivateDeliveryLabTest extends TestCase
{
    use FileSecurityFixtures;

    private function fixture(): array
    {
        $db = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase(ScanJournalLabDatabase::NAME);
        ScanJournalLabDatabase::install($db);
        $journal = new MongoScanAttemptJournal($db, fn () => true, fn () => true);
        $command = new FileScanCommand((string) Str::uuid(), $this->fileReference(['objectId' => (string) Str::uuid()]), $this->filePolicy(), '2026-11-18T00:00:00.000Z');
        $nonce = bin2hex(random_bytes(32));
        $journal->begin($command, $nonce);
        $journal->complete($command, $this->fileResult($command->object, $command->policy, ['scanId' => $command->scanId, 'attemptNonce' => $nonce]));
        $namespace = (string) Str::uuid();
        LocalQuarantineLabStore::install($namespace);
        $store = new LocalQuarantineLabStore($namespace, fn () => true);
        $store->stage($command->object, $this->fileBytes());

        return [$command, $journal, $store];
    }

    private function authority($c, $j, ?\Closure $access = null, ?\Closure $recovery = null, ?\Closure $policy = null, ?\Closure $health = null): JournalPrivateDeliveryAuthority
    {
        return new JournalPrivateDeliveryAuthority($j, fn () => $c, $access ?? fn ($actor, $o) => $actor === $o->ownerUserId ? hash('sha256', 'current-role-record') : null, $recovery ?? fn () => hash('sha256', 'independent-synthetic-generation'), $policy ?? fn () => $c->policy, $health ?? fn () => true);
    }

    public function test_exact_private_bytes_return_only_after_current_server_checks(): void
    {
        [$c, $j, $store] = $this->fixture();
        $service = new PrivateFileDelivery(new LocalPrivateDeliveryLabReader($store), $this->authority($c, $j), 65536, 5000);
        self::assertSame($this->fileBytes(), $service->read($c->object->ownerUserId, $c->object, 'download'));
        self::assertSame('cleared', $j->state($c)['state']);
    }

    public function test_wrong_actor_and_unavailable_authorities_never_read_bytes(): void
    {
        [$c, $j] = $this->fixture();
        $reader = new class implements PrivateDeliveryReader
        {
            public int $reads = 0;

            public function read(QuarantineObjectReference $object): string
            {
                $this->reads++;

                return 'must-not-read';
            }
        };
        $cases = [[$this->authority($c, $j), (string) Str::uuid()], [new JournalPrivateDeliveryAuthority($j), $c->object->ownerUserId], [$this->authority($c, $j, recovery: fn () => null), $c->object->ownerUserId], [$this->authority($c, $j, health: fn () => false), $c->object->ownerUserId], [$this->authority($c, $j, policy: fn () => $this->filePolicy(['reference' => 'changed'])), $c->object->ownerUserId]];
        foreach ($cases as [$authority, $actor]) {
            self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => (new PrivateFileDelivery($reader, $authority, 65536, 5000))->read($actor, $c->object, 'download')));
        }
        self::assertSame(0, $reader->reads);
    }

    public function test_new_pending_scan_during_read_prevents_release_of_old_clean_bytes(): void
    {
        [$c, $j, $store] = $this->fixture();
        $reader = new class($store, $j, $c) implements PrivateDeliveryReader
        {
            public function __construct(private $store, private $journal, private $command) {}

            public function read(QuarantineObjectReference $object): string
            {
                $bytes = $this->store->readForScan($object);
                $next = new FileScanCommand((string) Str::uuid(), $object, $this->command->policy, $this->command->issuedAt);
                $this->journal->begin($next, bin2hex(random_bytes(32)));

                return $bytes;
            }
        };
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => (new PrivateFileDelivery($reader, $this->authority($c, $j), 65536, 5000))->read($c->object->ownerUserId, $c->object, 'download')));
        self::assertSame('pending', $j->state($c)['state']);
    }

    public function test_revocation_or_recovery_change_during_storage_read_prevents_release(): void
    {
        foreach (['role-revocation', 'role-revision', 'recovery'] as $change) {
            [$c, $j, $store] = $this->fixture();
            $revision = 'before';
            $reader = new class($store, function () use (&$revision) {
                $revision = 'after';
            }) implements PrivateDeliveryReader {

                public function __construct(private $store, private \Closure $change) {}

                public function read(QuarantineObjectReference $object): string
                {
                    $bytes = $this->store->readForScan($object);
                    ($this->change)();

                    return $bytes;
                }
            };
            $access = match ($change) {
                'role-revocation' => function () use (&$revision) {
                    return $revision === 'before' ? hash('sha256', 'role-before') : null;
                },
                'role-revision' => function () use (&$revision) {
                    return hash('sha256', 'role-'.$revision);
                },
                default => fn () => hash('sha256', 'role'),
            };
            $recovery = $change === 'recovery' ? function () use (&$revision) {
                return hash('sha256', $revision);
            } : fn () => hash('sha256', 'recovery');
            self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => (new PrivateFileDelivery($reader, $this->authority($c, $j, $access, $recovery), 65536, 5000))->read($c->object->ownerUserId, $c->object, 'download')));
        }
    }

    public function test_view_permission_does_not_grant_download_or_export(): void
    {
        [$c, $j, $store] = $this->fixture();
        $access = fn ($actor, $object, $operation) => $actor === $object->ownerUserId && $operation === 'view' ? hash('sha256', 'view-only') : null;
        $service = new PrivateFileDelivery(new LocalPrivateDeliveryLabReader($store), $this->authority($c, $j, $access), 65536, 5000);
        self::assertSame($this->fileBytes(), $service->read($c->object->ownerUserId, $c->object, 'view'));
        foreach (['download', 'export', 'approve'] as $operation) {
            self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $service->read($c->object->ownerUserId, $c->object, $operation)));
        }
    }

    public function test_policy_change_or_scanner_outage_during_read_prevents_release(): void
    {
        foreach (['policy', 'scanner'] as $change) {
            [$c, $j, $store] = $this->fixture();
            $changed = false;
            $reader = new class($store, function () use (&$changed) {
                $changed = true;
            }) implements PrivateDeliveryReader {

                public function __construct(private $store, private \Closure $change) {}

                public function read(QuarantineObjectReference $object): string
                {
                    $bytes = $this->store->readForScan($object);
                    ($this->change)();

                    return $bytes;
                }
            };
            $policy = function () use (&$changed, $change, $c) {
                return $change === 'policy' && $changed ? $this->filePolicy(['reference' => 'new-policy']) : $c->policy;
            };
            $health = function () use (&$changed, $change) {
                return $change !== 'scanner' || ! $changed;
            };
            self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => (new PrivateFileDelivery($reader, $this->authority($c, $j, policy: $policy, health: $health), 65536, 5000))->read($c->object->ownerUserId, $c->object, 'download')));
        }
    }

    public function test_unsafe_latest_head_or_different_object_metadata_deny(): void
    {
        [$c, $j, $store] = $this->fixture();
        $reader = new LocalPrivateDeliveryLabReader($store);
        $service = new PrivateFileDelivery($reader, $this->authority($c, $j), 65536, 5000);
        $other = $this->fileReference(['objectId' => $c->object->objectId, 'documentVersionId' => (string) Str::uuid()]);
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $service->read($c->object->ownerUserId, $other, 'download')));
        $nonce = bin2hex(random_bytes(32));
        $next = new FileScanCommand((string) Str::uuid(), $c->object, $c->policy, $c->issuedAt);
        $j->begin($next, $nonce);
        $j->complete($next, $this->fileResult($next->object, $next->policy, ['scanId' => $next->scanId, 'attemptNonce' => $nonce, 'status' => 'unsafe', 'malwareClean' => false]));
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => (new PrivateFileDelivery($reader, $this->authority($next, $j), 65536, 5000))->read($next->object->ownerUserId, $next->object, 'download')));
    }

    public function test_expired_authority_budget_denies_before_storage_read(): void
    {
        $reader = new class implements PrivateDeliveryReader
        {
            public int $reads = 0;

            public function read(QuarantineObjectReference $object): string
            {
                $this->reads++;

                return 'not-read';
            }
        };
        $authority = new class implements PrivateDeliveryAuthority
        {
            public function witness(string $actorUserId, QuarantineObjectReference $object, string $operation): string
            {
                usleep(10000);

                return hash('sha256', 'current');
            }
        };
        $o = $this->fileReference();
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => (new PrivateFileDelivery($reader, $authority, 65536, 1))->read($o->ownerUserId, $o, 'view')));
        self::assertSame(0, $reader->reads);
    }

    public function test_expired_storage_budget_denies_and_lab_adapter_rejects_non_testing_environment(): void
    {
        [$c, $j, $store] = $this->fixture();
        $reader = new class($this->fileBytes()) implements PrivateDeliveryReader
        {
            public function __construct(private string $bytes) {}

            public function read(QuarantineObjectReference $object): string
            {
                usleep(20000);

                return $this->bytes;
            }
        };
        $authority = new class implements PrivateDeliveryAuthority
        {
            public function witness(string $actorUserId, QuarantineObjectReference $object, string $operation): string
            {
                return hash('sha256', 'current');
            }
        };
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => (new PrivateFileDelivery($reader, $authority, 65536, 5))->read($c->object->ownerUserId, $c->object, 'download')));
        $this->app->instance('env', 'production');
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => (new LocalPrivateDeliveryLabReader($store))->read($c->object)));
    }

    public function test_corrupt_bytes_and_size_limit_fail_closed(): void
    {
        [$c, $j, $store] = $this->fixture();
        $reader = new class implements PrivateDeliveryReader
        {
            public function read(QuarantineObjectReference $object): string
            {
                return str_repeat('x', $object->byteCount);
            }
        };
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => (new PrivateFileDelivery($reader, $this->authority($c, $j), 65536, 5000))->read($c->object->ownerUserId, $c->object, 'download')));
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => (new PrivateFileDelivery(new LocalPrivateDeliveryLabReader($store), $this->authority($c, $j), 1, 5000))->read($c->object->ownerUserId, $c->object, 'download')));
    }
}
