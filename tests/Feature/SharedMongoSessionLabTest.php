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
use MongoDB\BSON\UTCDateTime;
use Tests\Support\InMemorySecurityStateAuthority;
use Tests\Support\SessionLabHarness;
use Tests\TestCase;

/** Shared transport experiment only; it never changes the application's session driver. */
final class SharedMongoSessionLabTest extends TestCase
{
    use SessionLabHarness;

    public function test_two_laravel_processes_share_json_session_transport(): void
    {
        $id = $this->id();
        $written = $this->runWorker($id, 'write', ['value' => 7]);
        $read = $this->runWorker($id, 'read');
        self::assertTrue($written['ok']);
        self::assertSame(7, $read['value']);
        self::assertSame('json', $read['serialization']);
        self::assertSame('mongodb', $read['driver']);
        self::assertNotSame($written['pid'], $read['pid']);
        self::assertSame(PHP_VERSION, $read['php']);
        $document = $this->lab->selectCollection('session_payloads')->findOne(['_id' => $id]);
        self::assertInstanceOf(Binary::class, $document['payload']);
        $payload = json_decode($document['payload']->getData(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(7, $payload['lab']['counter']);
    }

    public function test_invalidation_and_expiry_are_observed_by_another_process(): void
    {
        $id = $this->id();
        $this->runWorker($id, 'write', ['value' => 3]);
        $invalidated = $this->runWorker($id, 'invalidate');
        self::assertTrue($invalidated['rotated']);
        self::assertTrue($invalidated['csrfRotated']);
        self::assertNull($this->runWorker($id, 'read')['value']);
        $this->runWorker($id, 'write', ['value' => 4]);
        $this->lab->selectCollection('session_payloads')->updateOne(['_id' => $id], ['$set' => ['expires_at' => new UTCDateTime((time() - 1) * 1000)]]);
        self::assertNull($this->runWorker($id, 'read')['value']);
    }

    public function test_backend_outage_and_non_lab_targets_are_rejected(): void
    {
        $id = $this->id();
        $outage = $this->runWorker($id, 'read', ['unavailable' => true]);
        self::assertFalse($outage['ok']);
        self::assertSame(503, $outage['status']);
        $unsafe = $this->runWorker($id, 'write', ['database' => 'studentity_dev_m0']);
        self::assertFalse($unsafe['ok']);
        self::assertSame('lab_target_rejected', $unsafe['code']);
        $unsafeCollection = $this->runWorker($id, 'write', ['collection' => 'user_sessions']);
        self::assertSame('lab_target_rejected', $unsafeCollection['code']);
    }

    public function test_unlocked_concurrent_writers_demonstrate_lost_updates(): void
    {
        $id = $this->id();
        $this->runWorker($id, 'write', ['value' => 0]);
        $barrier = (string) Str::uuid();
        foreach (['a', 'b'] as $role) {
            $this->barriers[] = $barrier.':'.$role;
        }
        $a = $this->process($id, 'concurrent-write', ['barrier' => $barrier, 'role' => 'a']);
        $b = $this->process($id, 'concurrent-write', ['barrier' => $barrier, 'role' => 'b']);
        $a->start();
        $b->start();
        $first = $this->workerResult($a);
        $second = $this->workerResult($b);
        self::assertTrue($first['ok']);
        self::assertTrue($second['ok']);
        self::assertSame(0, $first['readValue']);
        self::assertSame(0, $second['readValue']);
        // Evaluation finding: without a shared lock/CAS, two increments retain only one.
        self::assertSame(1, $this->runWorker($id, 'read')['value']);
    }

    public function test_stale_inflight_writer_can_recreate_payload_but_cannot_reverse_independent_revocation(): void
    {
        $id = $this->id();
        $this->runWorker($id, 'write', ['value' => 1]);
        $context = new AuthorizationContext((string) Str::uuid(), UserKind::Student, 'active', 1, 1, hash('sha256', $id), time() - 1, time() + 600, null, null, [new RoleGrant('synthetic-student-grant', UserKind::Student, null, ['profile.read' => true])]);
        $authority = new InMemorySecurityStateAuthority;
        $authority->trust($context);
        $policy = new AccessDecisionService($authority);
        $scope = new ResourceScope('synthetic-profile', null, null, $context->userId);
        self::assertTrue($policy->decide($context, $scope, 'profile.read', time())->allowed);
        $barrier = (string) Str::uuid();
        $this->barriers[] = $barrier.':held';
        $held = $this->process($id, 'held-write', ['barrier' => $barrier]);
        $held->start();
        self::assertTrue($held->waitUntil(static fn ($type, $output) => str_contains($held->getOutput(), '"event":"ready"')));
        $this->runWorker($id, 'invalidate');
        $authority->revoke($context->userId, $context->sessionDigest);
        self::assertNull($this->runWorker($id, 'read')['value']);
        $this->lab->selectCollection('barriers')->updateOne(['_id' => $barrier.':held'], ['$set' => ['release' => true]]);
        self::assertTrue($this->workerResult($held)['ok']);
        self::assertSame(2, $this->runWorker($id, 'read')['value']);
        self::assertFalse($policy->decide($context, $scope, 'profile.read', time())->allowed);
    }

    public function test_restoring_only_owned_payload_does_not_restore_authority(): void
    {
        $id = $this->id();
        $this->runWorker($id, 'write', ['value' => 8]);
        $snapshot = $this->lab->selectCollection('session_payloads')->findOne(['_id' => $id]);
        $context = new AuthorizationContext((string) Str::uuid(), UserKind::Student, 'active', 1, 1, hash('sha256', $id), time() - 1, time() + 600, null, null, [new RoleGrant('synthetic-grant', UserKind::Student, null, ['profile.read' => true])]);
        $authority = new InMemorySecurityStateAuthority;
        $authority->trust($context);
        $this->runWorker($id, 'invalidate');
        $authority->revoke($context->userId, $context->sessionDigest);
        $this->lab->selectCollection('session_payloads')->replaceOne(['_id' => $id], $snapshot, ['upsert' => true]);
        self::assertSame(8, $this->runWorker($id, 'read')['value']);
        self::assertFalse((new AccessDecisionService($authority))->decide($context, new ResourceScope('synthetic-profile', null, null, $context->userId), 'profile.read', time())->allowed);
    }

    public function test_lab_never_creates_ttl_indexes_or_accepts_non_lab_session_ids(): void
    {
        $id = $this->id();
        $this->runWorker($id, 'write', ['value' => 1]);
        foreach ($this->lab->selectCollection('session_payloads')->listIndexes() as $index) {
            self::assertFalse($index->isTtl());
        }
        $rejected = $this->runWorker(str_repeat('a', 40), 'read');
        self::assertFalse($rejected['ok']);
        self::assertSame('lab_target_rejected', $rejected['code']);
    }
}
