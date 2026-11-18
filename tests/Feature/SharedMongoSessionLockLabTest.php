<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Cache\MongoLock;
use Tests\Support\SessionLabHarness;
use Tests\TestCase;

final class SharedMongoSessionLockLabTest extends TestCase
{
    use SessionLabHarness;

    private function hold(string $id, string $action = 'locked-held-increment'): array
    {
        $barrier = (string) Str::uuid();
        $this->barriers[] = $barrier.':held';
        $process = $this->process($id, $action, ['barrier' => $barrier, 'lease' => 10]);
        $process->start();
        self::assertTrue($process->waitUntil(static fn () => str_contains($process->getOutput(), '"event":"ready"')));

        return [$process, $barrier];
    }

    private function releaseBarrier(string $barrier): void
    {
        $this->lab->selectCollection('barriers')->updateOne(['_id' => $barrier.':held'], ['$set' => ['release' => true]]);
    }

    public function test_shared_lock_serializes_read_modify_save_across_processes(): void
    {
        $id = $this->id();
        $this->runWorker($id, 'write', ['value' => 0]);
        [$held, $barrier] = $this->hold($id);
        $second = $this->process($id, 'locked-increment', ['wait' => 2]);
        $second->start();
        self::assertTrue($second->waitUntil(static fn () => str_contains($second->getOutput(), '"event":"waiting"')));
        self::assertTrue($second->isRunning());
        $this->releaseBarrier($barrier);
        self::assertTrue($this->workerResult($held)['ok']);
        $result = $this->workerResult($second);
        self::assertTrue($result['ok']);
        self::assertSame(1, $result['readValue']);
        self::assertSame(MongoLock::class, $result['lockClass']);
        self::assertSame(2, $this->runWorker($id, 'read')['value']);
    }

    public function test_contending_lock_timeout_does_not_read_modify_or_save_the_payload(): void
    {
        $id = $this->id();
        $this->runWorker($id, 'write', ['value' => 0]);
        [$held, $barrier] = $this->hold($id);
        $blocked = $this->runWorker($id, 'locked-increment', ['wait' => 0]);
        self::assertFalse($blocked['ok']);
        self::assertSame('lab_lock_timeout', $blocked['code']);
        self::assertSame(0, $this->runWorker($id, 'read')['value']);
        $this->releaseBarrier($barrier);
        self::assertTrue($this->workerResult($held)['ok']);
    }

    public function test_logout_waits_for_locked_save_then_later_mutation_cannot_recreate_missing_payload(): void
    {
        $id = $this->id();
        $this->runWorker($id, 'write', ['value' => 0]);
        [$held, $barrier] = $this->hold($id);
        $logout = $this->process($id, 'locked-invalidate', ['wait' => 2]);
        $logout->start();
        self::assertTrue($logout->waitUntil(static fn () => str_contains($logout->getOutput(), '"event":"waiting"')));
        $this->releaseBarrier($barrier);
        self::assertTrue($this->workerResult($held)['ok']);
        self::assertTrue($this->workerResult($logout)['rotated']);
        self::assertNull($this->runWorker($id, 'read')['value']);
        $late = $this->runWorker($id, 'locked-increment');
        self::assertFalse($late['ok']);
        self::assertSame('lab_session_missing', $late['code']);
        self::assertNull($this->lab->selectCollection('session_payloads')->findOne(['_id' => $id]));
    }

    public function test_expired_owner_cannot_release_successor_lock_or_save_after_observed_lease_loss(): void
    {
        $id = $this->id();
        $this->runWorker($id, 'write', ['value' => 0]);
        [$held, $barrier] = $this->hold($id);
        $locks = $this->lab->selectCollection('locks');
        $locks->updateOne(['_id' => 'session:'.$id], ['$set' => ['expires_at' => new UTCDateTime((time() - 1) * 1000)]]);
        $successor = new MongoLock($locks, 'session:'.$id, 10, lottery: [0, 0]);
        self::assertTrue($successor->get());
        try {
            $this->releaseBarrier($barrier);
            $stale = $this->workerResult($held);
            self::assertFalse($stale['ok']);
            self::assertSame('lab_lock_lost', $stale['code']);
            self::assertTrue($successor->isOwnedByCurrentProcess());
            self::assertSame(0, $this->runWorker($id, 'read')['value']);
        } finally {
            $successor->release();
        }
    }

    public function test_lease_expiring_after_last_owner_check_still_demonstrates_need_for_fencing(): void
    {
        $id = $this->id();
        $this->runWorker($id, 'write', ['value' => 0]);
        [$held, $barrier] = $this->hold($id, 'locked-held-after-check');
        $locks = $this->lab->selectCollection('locks');
        $locks->updateOne(['_id' => 'session:'.$id], ['$set' => ['expires_at' => new UTCDateTime((time() - 1) * 1000)]]);
        $successor = new MongoLock($locks, 'session:'.$id, 10, lottery: [0, 0]);
        self::assertTrue($successor->get());
        try {
            $this->runWorker($id, 'write', ['value' => 99]); // Successor work represented by this owned synthetic payload update.
            $this->releaseBarrier($barrier);
            self::assertTrue($this->workerResult($held)['ok']);
            self::assertTrue($successor->isOwnedByCurrentProcess());
            self::assertSame(1, $this->runWorker($id, 'read')['value']); // A stale writer overwrote the successor after its last owner check.
        } finally {
            $successor->release();
        }
    }

    public function test_lock_backend_outage_and_exception_release_fail_safely(): void
    {
        $id = $this->id();
        $this->runWorker($id, 'write', ['value' => 5]);
        $outage = $this->runWorker($id, 'locked-increment', ['lockUnavailable' => true]);
        self::assertFalse($outage['ok']);
        self::assertSame(503, $outage['status']);
        self::assertSame(5, $this->runWorker($id, 'read')['value']);
        $failure = $this->runWorker($id, 'locked-throw');
        self::assertFalse($failure['ok']);
        self::assertTrue($this->runWorker($id, 'locked-increment')['ok']);
        self::assertNull($this->lab->selectCollection('locks')->findOne(['_id' => 'session:'.$id]));
        foreach ($this->lab->selectCollection('locks')->listIndexes() as $index) {
            self::assertFalse($index->isTtl());
        }
    }

    public function test_installed_lock_provider_does_not_support_lease_refresh(): void
    {
        $id = $this->id();
        $lock = new MongoLock($this->lab->selectCollection('locks'), 'session:'.$id, 10, lottery: [0, 0]);
        self::assertTrue($lock->get());
        try {
            try {
                $lock->refresh(15);
                self::fail('Do not assume lease renewal exists in the installed driver.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('does not support refreshing', $exception->getMessage());
            }
        } finally {
            $lock->release();
        }
    }
}
