<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identity\UserKind;
use App\Domain\Security\AccessDecisionService;
use App\Domain\Security\AuthorizationContext;
use App\Domain\Security\ResourceScope;
use App\Domain\Security\RoleGrant;
use Illuminate\Support\Str;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Tests\Support\FencedSessionLabStore;
use Tests\Support\InMemorySecurityStateAuthority;
use Tests\Support\SessionLabHarness;
use Tests\TestCase;

final class SharedMongoSessionFenceLabTest extends TestCase
{
    use SessionLabHarness;

    private function seedFencedSession(): string
    {
        $id = $this->id();
        self::assertTrue($this->runWorker($id, 'fenced-seed', ['value' => 0])['ok']);

        return $id;
    }

    private function hold(string $id, int $value): array
    {
        $barrier = (string) Str::uuid();
        $this->barriers[] = $barrier.':held';
        $process = $this->process($id, 'fenced-held-write', ['value' => $value, 'barrier' => $barrier]);
        $process->start();
        self::assertTrue($process->waitUntil(static fn () => str_contains($process->getOutput(), '"event":"ready"')));

        return [$process, $barrier];
    }

    private function resume(string $barrier): void
    {
        $this->lab->selectCollection('barriers')->updateOne(['_id' => $barrier.':held'], ['$set' => ['release' => true]]);
    }

    public function test_fenced_json_store_works_across_laravel_processes(): void
    {
        $id = $this->seedFencedSession();
        $a = $this->runWorker($id, 'fenced-write', ['value' => 7]);
        $b = $this->runWorker($id, 'fenced-read');
        self::assertTrue($a['ok']);
        self::assertSame(7, $b['value']);
        self::assertNotSame($a['pid'], $b['pid']);
        $row = $this->lab->selectCollection('fenced_sessions')->findOne(['_id' => $id]);
        self::assertSame(1, (int) (string) $row['revision']);
        self::assertTrue((int) (string) $row['generation'] >= 2);
    }

    public function test_takeover_atomically_rejects_the_paused_old_writer_and_its_release(): void
    {
        $id = $this->seedFencedSession();
        [$old, $oldBarrier] = $this->hold($id, 1);
        $this->lab->selectCollection('fenced_sessions')->updateOne(['_id' => $id], ['$set' => ['lease_until' => new UTCDateTime((time() - 1) * 1000)]]);
        [$new, $newBarrier] = $this->hold($id, 99);
        $this->resume($oldBarrier);
        $rejected = $this->workerResult($old);
        self::assertFalse($rejected['ok']);
        self::assertSame('lab_fence_rejected', $rejected['code']);
        $row = $this->lab->selectCollection('fenced_sessions')->findOne(['_id' => $id]);
        self::assertSame(2, (int) (string) $row['generation']);
        self::assertTrue(is_string($row['owner']) && $row['owner'] !== '');
        self::assertSame(0, (int) (string) $row['revision']);
        $this->resume($newBarrier);
        self::assertTrue($this->workerResult($new)['ok']);
        self::assertSame(99, $this->runWorker($id, 'fenced-read')['value']);
    }

    public function test_revocation_tombstone_blocks_inflight_save_read_and_recreation(): void
    {
        $id = $this->seedFencedSession();
        [$held, $barrier] = $this->hold($id, 1);
        self::assertTrue($this->runWorker($id, 'fenced-invalidate')['ok']);
        $this->resume($barrier);
        self::assertSame('lab_fence_rejected', $this->workerResult($held)['code']);
        self::assertSame('lab_fence_rejected', $this->runWorker($id, 'fenced-read')['code']);
        self::assertFalse($this->runWorker($id, 'fenced-seed', ['value' => 99])['ok']);
        $row = $this->lab->selectCollection('fenced_sessions')->findOne(['_id' => $id]);
        self::assertTrue($row['revoked']);
        self::assertFalse(isset($row['payload']));
        self::assertSame(2, (int) (string) $row['generation']);
    }

    public function test_expired_lease_without_takeover_still_cannot_save(): void
    {
        $id = $this->seedFencedSession();
        [$held, $barrier] = $this->hold($id, 12);
        $this->lab->selectCollection('fenced_sessions')->updateOne(['_id' => $id], ['$set' => ['lease_until' => new UTCDateTime((time() - 1) * 1000)]]);
        $this->resume($barrier);
        self::assertSame('lab_fence_rejected', $this->workerResult($held)['code']);
        self::assertSame(0, $this->runWorker($id, 'fenced-read')['value']);
    }

    public function test_expired_session_is_rejected_at_final_save_even_with_a_valid_lease(): void
    {
        $id = $this->seedFencedSession();
        [$held, $barrier] = $this->hold($id, 12);
        $this->lab->selectCollection('fenced_sessions')->updateOne(['_id' => $id], ['$set' => ['session_expires_at' => new UTCDateTime((time() - 1) * 1000)]]);
        $this->resume($barrier);
        self::assertSame('lab_fence_rejected', $this->workerResult($held)['code']);
        self::assertSame('lab_fence_rejected', $this->runWorker($id, 'fenced-read')['code']);
        self::assertSame(0, (int) (string) $this->lab->selectCollection('fenced_sessions')->findOne(['_id' => $id])['revision']);
    }

    public function test_missing_payload_cannot_be_upserted_by_a_delayed_writer(): void
    {
        $id = $this->seedFencedSession();
        [$held, $barrier] = $this->hold($id, 12);
        $this->lab->selectCollection('fenced_sessions')->deleteOne(['_id' => $id]); // Own fixture only; simulate missing transport state.
        $this->resume($barrier);
        self::assertSame('lab_fence_rejected', $this->workerResult($held)['code']);
        self::assertNull($this->lab->selectCollection('fenced_sessions')->findOne(['_id' => $id]));
    }

    public function test_outage_and_thrown_callback_do_not_mutate_payload_or_leave_a_live_owner(): void
    {
        $id = $this->seedFencedSession();
        self::assertSame(503, $this->runWorker($id, 'fenced-read', ['unavailable' => true])['status']);
        self::assertFalse($this->runWorker($id, 'fenced-throw')['ok']);
        self::assertSame(0, $this->runWorker($id, 'fenced-read')['value']);
        $row = $this->lab->selectCollection('fenced_sessions')->findOne(['_id' => $id]);
        self::assertTrue($row['owner'] === '');
        self::assertSame(0, (int) (string) $row['revision']);
    }

    public function test_handler_refuses_other_databases_and_never_garbage_collects_tombstones(): void
    {
        $id = $this->seedFencedSession();
        self::assertTrue($this->runWorker($id, 'fenced-invalidate')['ok']);
        $handler = new FencedSessionLabStore($this->lab);
        self::assertSame(0, $handler->gc(0));
        self::assertTrue($this->lab->selectCollection('fenced_sessions')->findOne(['_id' => $id])['revoked']);
        foreach ($this->lab->selectCollection('fenced_sessions')->listIndexes() as $index) {
            self::assertFalse($index->isTtl());
        }
        try {
            new FencedSessionLabStore(new Database($this->lab->getManager(), 'studentity_test_m0'));
            self::fail('Business database was accepted by the lab handler.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
    }

    public function test_live_owner_contention_cannot_advance_generation_or_read_payload(): void
    {
        $id = $this->seedFencedSession();
        [$held, $barrier] = $this->hold($id, 7);
        $other = $this->runWorker($id, 'fenced-read');
        self::assertFalse($other['ok']);
        self::assertSame('lab_fence_rejected', $other['code']);
        self::assertSame(1, (int) (string) $this->lab->selectCollection('fenced_sessions')->findOne(['_id' => $id])['generation']);
        $this->resume($barrier);
        self::assertTrue($this->workerResult($held)['ok']);
        self::assertSame(7, $this->runWorker($id, 'fenced-read')['value']);
    }

    public function test_payload_revision_change_rejects_an_old_snapshot_with_the_same_lease(): void
    {
        $id = $this->seedFencedSession();
        [$held, $barrier] = $this->hold($id, 1);
        $this->lab->selectCollection('fenced_sessions')->updateOne(['_id' => $id], ['$inc' => ['revision' => new Int64(1)], '$set' => ['payload' => new Binary(json_encode(['lab' => ['counter' => 99]], JSON_THROW_ON_ERROR))]]);
        $this->resume($barrier);
        self::assertSame('lab_fence_rejected', $this->workerResult($held)['code']);
        self::assertSame(99, $this->runWorker($id, 'fenced-read')['value']);
    }

    public function test_restored_envelope_still_requires_independent_current_authority(): void
    {
        $id = $this->seedFencedSession();
        $snapshot = $this->lab->selectCollection('fenced_sessions')->findOne(['_id' => $id]);
        $context = new AuthorizationContext((string) Str::uuid(), UserKind::Student, 'active', 1, 1, hash('sha256', $id), time() - 1, time() + 600, null, null, [new RoleGrant('synthetic-fence-grant', UserKind::Student, null, ['profile.read' => true])]);
        $authority = new InMemorySecurityStateAuthority;
        $authority->trust($context);
        $service = new AccessDecisionService($authority);
        $scope = new ResourceScope('synthetic-profile', null, null, $context->userId);
        self::assertTrue($service->decide($context, $scope, 'profile.read', time())->allowed);
        self::assertTrue($this->runWorker($id, 'fenced-invalidate')['ok']);
        $authority->revoke($context->userId, $context->sessionDigest);
        $this->lab->selectCollection('fenced_sessions')->replaceOne(['_id' => $id], $snapshot);
        self::assertSame(0, $this->runWorker($id, 'fenced-read')['value']);
        self::assertFalse($service->decide($context, $scope, 'profile.read', time())->allowed);
    }
}
