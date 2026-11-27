<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Certificates\CertificateSlotUnavailable;
use App\Domain\Certificates\CertificateYearSlotKey;
use App\Domain\Certificates\CertificateYearSlotManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use MongoDB\Client;
use MongoDB\Database;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class CertificateYearSlotTransactionTest extends TestCase
{
    private Database $db;

    private CertificateYearSlotManager $manager;

    private string $collectionName = 'certificate_year_slots';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase('studentity_test_m0');
        $this->manager = new CertificateYearSlotManager($this->db, $this->collectionName);
    }

    private function validEligibility(): array
    {
        return [
            'authenticIssuance' => true,
            'issuedLifecycle' => true,
            'currentAcademicYear' => true,
            'officialStatusEligible' => true,
            'identityAndEnrollmentVerified' => true,
            'applicableRequirementsApproved' => true,
            'authoritativeDependenciesConsistent' => true,
            'recoveryReconciliationCurrent' => true,
        ];
    }

    public function test_slot_allocation_uses_deterministic_id_and_initializes_generation_zero(): void
    {
        $studentId = (string) Str::uuid();
        $academicYearId = (string) Str::uuid();

        $slot = $this->manager->getOrCreateSlot($studentId, $academicYearId);

        $expectedSlotId = CertificateYearSlotKey::fromIds($studentId, $academicYearId);
        $this->assertSame($expectedSlotId, $slot->slotId);
        $this->assertSame($studentId, $slot->studentId);
        $this->assertSame($academicYearId, $slot->academicYearId);
        $this->assertNull($slot->operativeCertificateId);
        $this->assertSame(0, $slot->generation);
        $this->assertSame(1, $slot->schemaVersion);
    }

    public function test_duplicate_slot_creation_is_idempotent_and_returns_same_slot(): void
    {
        $studentId = (string) Str::uuid();
        $academicYearId = (string) Str::uuid();

        $slot1 = $this->manager->getOrCreateSlot($studentId, $academicYearId);
        $slot2 = $this->manager->getOrCreateSlot($studentId, $academicYearId);

        $this->assertSame($slot1->slotId, $slot2->slotId);
        $this->assertSame($slot1->generation, $slot2->generation);
    }

    public function test_binding_operative_certificate_requires_full_eligibility_and_increments_generation(): void
    {
        $studentId = (string) Str::uuid();
        $academicYearId = (string) Str::uuid();
        $certificateId = (string) Str::uuid();

        $slot = $this->manager->getOrCreateSlot($studentId, $academicYearId);
        $this->assertSame(0, $slot->generation);

        $updated = $this->manager->bindOperativeCertificate(
            $studentId,
            $academicYearId,
            $certificateId,
            0,
            $this->validEligibility()
        );

        $this->assertSame($certificateId, $updated->operativeCertificateId);
        $this->assertSame(1, $updated->generation);

        $stored = $this->manager->getSlot($studentId, $academicYearId);
        $this->assertNotNull($stored);
        $this->assertSame($certificateId, $stored->operativeCertificateId);
        $this->assertSame(1, $stored->generation);
    }

    public function test_unsatisfied_clearance_gate_fails_closed_without_modifying_slot(): void
    {
        $studentId = (string) Str::uuid();
        $academicYearId = (string) Str::uuid();
        $certificateId = (string) Str::uuid();

        $slot = $this->manager->getOrCreateSlot($studentId, $academicYearId);

        $invalidEligibility = $this->validEligibility();
        $invalidEligibility['applicableRequirementsApproved'] = false; // Requirement missing/unapproved

        $this->expectException(CertificateSlotUnavailable::class);
        $this->expectExceptionMessage('Clearance eligibility requirements not satisfied.');

        try {
            $this->manager->bindOperativeCertificate(
                $studentId,
                $academicYearId,
                $certificateId,
                0,
                $invalidEligibility
            );
        } finally {
            $stored = $this->manager->getSlot($studentId, $academicYearId);
            $this->assertNotNull($stored);
            $this->assertNull($stored->operativeCertificateId);
            $this->assertSame(0, $stored->generation);
        }
    }

    public function test_optimistic_concurrency_rejects_stale_generation_update(): void
    {
        $studentId = (string) Str::uuid();
        $academicYearId = (string) Str::uuid();
        $cert1 = (string) Str::uuid();
        $cert2 = (string) Str::uuid();

        $this->manager->getOrCreateSlot($studentId, $academicYearId);

        // Advance to generation 1
        $this->manager->bindOperativeCertificate(
            $studentId,
            $academicYearId,
            $cert1,
            0,
            $this->validEligibility()
        );

        // Concurrent update attempts to bind cert2 using stale expectedGeneration = 0
        $this->expectException(CertificateSlotUnavailable::class);
        $this->expectExceptionMessage('Concurrent slot modification or stale generation conflict.');

        $this->manager->bindOperativeCertificate(
            $studentId,
            $academicYearId,
            $cert2,
            0,
            $this->validEligibility()
        );
    }

    public function test_revocation_clears_operative_certificate_and_increments_generation(): void
    {
        $studentId = (string) Str::uuid();
        $academicYearId = (string) Str::uuid();
        $cert = (string) Str::uuid();

        $this->manager->getOrCreateSlot($studentId, $academicYearId);
        $this->manager->bindOperativeCertificate($studentId, $academicYearId, $cert, 0, $this->validEligibility());

        $revoked = $this->manager->revokeOperativeCertificate($studentId, $academicYearId, 1);

        $this->assertNull($revoked->operativeCertificateId);
        $this->assertSame(2, $revoked->generation);
    }

    public function test_two_workers_binding_the_same_generation_leave_one_operative_certificate(): void
    {
        $studentId = (string) Str::uuid();
        $academicYearId = (string) Str::uuid();
        $certificateIds = [(string) Str::uuid(), (string) Str::uuid()];
        $slotId = CertificateYearSlotKey::fromIds($studentId, $academicYearId);
        $barrier = storage_path('app/private/certificate-slot-races/'.$studentId);
        File::ensureDirectoryExists($barrier, 0700);
        $workers = [];

        try {
            $initial = $this->manager->getOrCreateSlot($studentId, $academicYearId);

            foreach ($certificateIds as $workerId => $certificateId) {
                $worker = new Process(
                    [PHP_BINARY, base_path('tests/Support/certificate-slot-bind-worker.php')],
                    base_path(),
                    ['APP_ENV' => 'testing'],
                );
                $worker->setTimeout(30);
                $worker->setInput(json_encode([
                    'studentId' => $studentId,
                    'academicYearId' => $academicYearId,
                    'certificateId' => $certificateId,
                    'expectedGeneration' => $initial->generation,
                    'eligibility' => $this->validEligibility(),
                    'workerId' => $workerId,
                ], JSON_THROW_ON_ERROR));
                $workers[$workerId] = $worker;
                $worker->start();
            }

            $deadline = hrtime(true) + 12_000_000_000;
            do {
                clearstatcache();
                if (is_file($barrier.'/0.ready') && is_file($barrier.'/1.ready')) {
                    break;
                }
                usleep(10_000);
            } while (hrtime(true) < $deadline && $workers[0]->isRunning() && $workers[1]->isRunning());

            self::assertFileExists($barrier.'/0.ready');
            self::assertFileExists($barrier.'/1.ready');
            $beforeRelease = $this->manager->getSlot($studentId, $academicYearId);
            self::assertNotNull($beforeRelease);
            self::assertSame($initial->generation, $beforeRelease->generation);
            self::assertNull($beforeRelease->operativeCertificateId);

            self::assertNotFalse(file_put_contents($barrier.'/release', 'release', LOCK_EX));
            $outcomes = [];
            foreach ($workers as $workerId => $worker) {
                self::assertSame(0, $worker->wait(), $worker->getOutput());
                self::assertSame('', $worker->getErrorOutput());
                $outcomes[$certificateIds[$workerId]] = $worker->getOutput();
            }

            self::assertEqualsCanonicalizing(['bound', 'conflict'], array_values($outcomes));
            $stored = $this->manager->getSlot($studentId, $academicYearId);
            self::assertNotNull($stored);
            self::assertSame($initial->generation + 1, $stored->generation);
            self::assertSame(array_search('bound', $outcomes, true), $stored->operativeCertificateId);
            self::assertSame(1, $this->db->selectCollection($this->collectionName)->countDocuments([
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId,
            ]));
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(0);
                }
            }
            File::delete([$barrier.'/0.ready', $barrier.'/1.ready', $barrier.'/release']);
            rmdir($barrier);
            $this->db->selectCollection($this->collectionName)->deleteOne(['_id' => $slotId]);
        }
    }
}
