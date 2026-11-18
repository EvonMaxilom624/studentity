<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identifiers\AllocationConflict;
use App\Domain\Identifiers\AllocationProjection;
use App\Domain\Identifiers\SystemIdAllocation;
use App\Domain\Identifiers\SystemIdAllocator;
use App\Infrastructure\Identity\MongoSystemIdAllocationRepository;
use App\Infrastructure\Identity\UnavailableSystemIdIssuer;
use Illuminate\Support\Str;
use MongoDB\BSON\Int64;
use MongoDB\Client;
use MongoDB\Database;
use Tests\Support\PrerequisiteProcessHarness;
use Tests\Support\SyntheticSystemIdIssuer;
use Tests\TestCase;

final class SystemIdAllocationTest extends TestCase
{
    use PrerequisiteProcessHarness;

    private array $ops = [];

    private function repo(): MongoSystemIdAllocationRepository
    {
        return new MongoSystemIdAllocationRepository($this->db());
    }

    private function db(): Database
    {
        if (config('database.connections.mongodb.database') !== 'studentity_test_m0' || ! in_array(parse_url(config('database.connections.mongodb.dsn'), PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Local synthetic environment required.');
        }

        return (new Client(config('database.connections.mongodb.dsn')))->selectDatabase('studentity_test_identity_prerequisites');
    }

    protected function tearDown(): void
    {
        try {
            $this->stopPrerequisiteWorkers();
            if ($this->ops) {
                $this->db()->selectCollection('allocations_v1')->deleteMany(['_id' => ['$in' => $this->ops]]);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function op(): string
    {
        return $this->ops[] = (string) Str::uuid();
    }

    public function test_same_operation_is_idempotent_and_retirement_is_never_recycled(): void
    {
        $issuer = new SyntheticSystemIdIssuer;
        $service = new SystemIdAllocator($issuer, $this->repo());
        $op = $this->op();
        $fp = hash('sha256', 'intent');
        $first = $service->reserve($op, $fp);
        self::assertSame($first->systemId, $service->reserve($op, $fp)->systemId);
        $issuer->retire($op);
        self::assertSame('retired', $service->reserve($op, $fp)->state);
        self::assertNotSame($first->systemId, $service->reserve($this->op(), $fp)->systemId);
        self::assertSame(1, $this->db()->selectCollection('allocations_v1')->countDocuments(['_id' => $op]));
    }

    public function test_local_projection_never_substitutes_for_unknown_issuer_and_restore_cannot_unretire(): void
    {
        $issuer = new SyntheticSystemIdIssuer;
        $repo = $this->repo();
        $service = new SystemIdAllocator($issuer, $repo);
        $op = $this->op();
        $fp = hash('sha256', 'owned-intent');
        $service->reserve($op, $fp);
        $old = $this->db()->selectCollection('allocations_v1')->findOne(['_id' => $op])->getArrayCopy();
        $old['revision'] = new Int64((string) $old['revision']);
        $issuer->retire($op);
        $service->reserve($op, $fp);
        $this->db()->selectCollection('allocations_v1')->replaceOne(['_id' => $op], $old, ['upsert' => false]);
        self::assertSame('retired', $service->reserve($op, $fp)->state);
        try {
            (new SystemIdAllocator(new UnavailableSystemIdIssuer, $repo))->reserve($op, $fp);
            self::fail('Local projection granted issuance.');
        } catch (\RuntimeException $e) {
            self::assertSame('System ID allocation unavailable.', $e->getMessage());
        }
        self::assertSame('retired', $repo->find($op)->state);
    }

    public function test_reused_operation_different_intent_and_duplicate_or_regressed_ids_are_rejected(): void
    {
        $issuer = new SyntheticSystemIdIssuer;
        $repo = $this->repo();
        $service = new SystemIdAllocator($issuer, $repo);
        $op = $this->op();
        $first = $service->reserve($op, hash('sha256', 'one'));
        $operationError = null;
        try {
            $service->reserve($op, hash('sha256', 'two'));
        } catch (\RuntimeException $error) {
            $operationError = $error;
        }
        self::assertInstanceOf(\RuntimeException::class, $operationError);
        self::assertSame('System ID allocation unavailable.', $operationError->getMessage());
        $other = $this->op();
        $duplicateError = null;
        try {
            $repo->remember(new SystemIdAllocation($other, hash('sha256', 'two'), $first->systemId, 'reserved', 1));
        } catch (\RuntimeException $error) {
            $duplicateError = $error;
        }
        self::assertInstanceOf(AllocationConflict::class, $duplicateError);
        $issuer->retire($op);
        $service->reserve($op, $first->intentFingerprint);
        $this->expectException(AllocationConflict::class);
        $repo->remember(new SystemIdAllocation($op, $first->intentFingerprint, $first->systemId, 'reserved', 3));
    }

    public function test_lost_local_ack_retries_same_independent_allocation_without_duplicate_rows(): void
    {
        $issuer = new SyntheticSystemIdIssuer;
        $repo = $this->repo();
        $op = $this->op();
        $fp = hash('sha256', 'ack');
        $original = $issuer->reserve($op, $fp);
        $losesAck = new class($repo) implements AllocationProjection
        {
            public function __construct(private AllocationProjection $inner) {}

            public function remember(SystemIdAllocation $allocation): void
            {
                $this->inner->remember($allocation);
                throw new \RuntimeException('Synthetic lost local ACK.');
            }

            public function find(string $operationId): ?SystemIdAllocation
            {
                return $this->inner->find($operationId);
            }
        };
        try {
            (new SystemIdAllocator($issuer, $losesAck))->reserve($op, $fp);
            self::fail('Lost acknowledgment reported success.');
        } catch (\RuntimeException $e) {
            self::assertSame('System ID allocation unavailable.', $e->getMessage());
        }
        $recovered = (new SystemIdAllocator($issuer, $repo))->reserve($op, $fp);
        self::assertSame($original->systemId, $recovered->systemId);
        self::assertSame(1, $this->db()->selectCollection('allocations_v1')->countDocuments(['_id' => $op]));
    }

    public function test_separate_processes_converge_on_one_operation_and_unique_id(): void
    {
        $this->repo();
        $op = $this->op();
        $id = 'synthetic-'.Str::uuid();
        $fp = hash('sha256', 'process-intent');
        $input = ['action' => 'project', 'operationId' => $op, 'fingerprint' => $fp, 'systemId' => $id];
        $a = $this->startPrerequisiteWorker($input);
        $b = $this->startPrerequisiteWorker($input);
        self::assertTrue($this->prerequisiteResult($a));
        self::assertTrue($this->prerequisiteResult($b));
        self::assertSame(1, $this->db()->selectCollection('allocations_v1')->countDocuments(['_id' => $op]));
        $other = $this->op();
        self::assertFalse($this->prerequisiteResult($this->startPrerequisiteWorker(array_replace($input, ['operationId' => $other]))));
        self::assertSame(0, $this->db()->selectCollection('allocations_v1')->countDocuments(['_id' => $other]));
    }

    public function test_synthetic_issuer_skips_collisions_with_retired_ids(): void
    {
        $candidates = ['synthetic-A', 'synthetic-A', 'synthetic-B'];
        $issuer = new SyntheticSystemIdIssuer(static function () use (&$candidates): string {
            return array_shift($candidates);
        });
        $op = $this->op();
        $first = $issuer->reserve($op, hash('sha256', 'a'));
        self::assertSame('synthetic-A', $first->systemId);
        $issuer->retire($op);
        self::assertSame('synthetic-B', $issuer->reserve($this->op(), hash('sha256', 'b'))->systemId);
        self::assertSame('retired', $issuer->lookup($op)->state);
    }
}
