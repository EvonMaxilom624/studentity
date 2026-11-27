<?php

declare(strict_types=1);

namespace App\Domain\Certificates;

use DateTimeImmutable;
use DateTimeZone;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * Manages certificate year slot allocation, optimistic concurrency, and operative certificate binding.
 * Enforces M0-07 uniqueness and ClearanceClaimGate eligibility invariants.
 */
final readonly class CertificateYearSlotManager
{
    public function __construct(
        private Database $db,
        private string $collectionName = 'certificate_year_slots',
        private ?\Closure $clock = null
    ) {}

    public function getSlot(string $studentId, string $academicYearId): ?CertificateYearSlotRecord
    {
        $slotId = CertificateYearSlotKey::fromIds($studentId, $academicYearId);
        $doc = $this->db->selectCollection($this->collectionName)->findOne(['_id' => $slotId]);

        return $doc ? $this->mapRecord($doc) : null;
    }

    public function getOrCreateSlot(string $studentId, string $academicYearId): CertificateYearSlotRecord
    {
        $slotId = CertificateYearSlotKey::fromIds($studentId, $academicYearId);
        $col = $this->db->selectCollection($this->collectionName);

        $now = $this->nowUtc();
        $record = [
            '_id' => $slotId,
            'student_id' => $studentId,
            'academic_year_id' => $academicYearId,
            'generation' => 0,
            'created_at' => new UTCDateTime($now->getTimestamp() * 1000),
            'updated_at' => new UTCDateTime($now->getTimestamp() * 1000),
            'schema_version' => 1,
        ];

        try {
            $col->insertOne($record);
        } catch (BulkWriteException $e) {
            if ($e->getCode() !== 11000) {
                throw new CertificateSlotUnavailable('Database error during slot allocation.', 0, $e);
            }
        }

        $existing = $col->findOne(['_id' => $slotId]);
        if (! $existing) {
            throw new CertificateSlotUnavailable('Failed to retrieve certificate year slot.');
        }

        return $this->mapRecord($existing);
    }

    public function bindOperativeCertificate(
        string $studentId,
        string $academicYearId,
        string $certificateId,
        int $expectedGeneration,
        array $eligibilityWitnesses
    ): CertificateYearSlotRecord {
        $eligible = ClearanceClaimGate::mayClaimValid(
            $eligibilityWitnesses['authenticIssuance'] ?? false,
            $eligibilityWitnesses['issuedLifecycle'] ?? false,
            $eligibilityWitnesses['currentAcademicYear'] ?? false,
            $eligibilityWitnesses['officialStatusEligible'] ?? false,
            $eligibilityWitnesses['identityAndEnrollmentVerified'] ?? false,
            $eligibilityWitnesses['applicableRequirementsApproved'] ?? false,
            $eligibilityWitnesses['authoritativeDependenciesConsistent'] ?? false,
            $eligibilityWitnesses['recoveryReconciliationCurrent'] ?? false
        );

        if (! $eligible) {
            throw new CertificateSlotUnavailable('Clearance eligibility requirements not satisfied.');
        }

        $slotId = CertificateYearSlotKey::fromIds($studentId, $academicYearId);
        $col = $this->db->selectCollection($this->collectionName);
        $now = $this->nowUtc();

        $result = $col->updateOne(
            ['_id' => $slotId, 'generation' => $expectedGeneration],
            [
                '$set' => [
                    'operative_certificate_id' => $certificateId,
                    'generation' => $expectedGeneration + 1,
                    'updated_at' => new UTCDateTime($now->getTimestamp() * 1000),
                ],
            ]
        );

        if ($result->getMatchedCount() === 0) {
            throw new CertificateSlotUnavailable('Concurrent slot modification or stale generation conflict.');
        }

        $updated = $col->findOne(['_id' => $slotId]);

        return $this->mapRecord($updated);
    }

    public function revokeOperativeCertificate(
        string $studentId,
        string $academicYearId,
        int $expectedGeneration
    ): CertificateYearSlotRecord {
        $slotId = CertificateYearSlotKey::fromIds($studentId, $academicYearId);
        $col = $this->db->selectCollection($this->collectionName);
        $now = $this->nowUtc();

        $result = $col->updateOne(
            ['_id' => $slotId, 'generation' => $expectedGeneration],
            [
                '$unset' => ['operative_certificate_id' => ''],
                '$set' => [
                    'generation' => $expectedGeneration + 1,
                    'updated_at' => new UTCDateTime($now->getTimestamp() * 1000),
                ],
            ]
        );

        if ($result->getMatchedCount() === 0) {
            throw new CertificateSlotUnavailable('Concurrent slot modification or stale generation conflict.');
        }

        $updated = $col->findOne(['_id' => $slotId]);

        return $this->mapRecord($updated);
    }

    private function mapRecord(array|object $doc): CertificateYearSlotRecord
    {
        $arr = (array) $doc;

        $createdAt = isset($arr['created_at']) && $arr['created_at'] instanceof UTCDateTime
            ? $arr['created_at']->toDateTime()->setTimezone(new DateTimeZone('UTC'))
            : new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $updatedAt = isset($arr['updated_at']) && $arr['updated_at'] instanceof UTCDateTime
            ? $arr['updated_at']->toDateTime()->setTimezone(new DateTimeZone('UTC'))
            : new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return new CertificateYearSlotRecord(
            (string) $arr['_id'],
            (string) $arr['student_id'],
            (string) $arr['academic_year_id'],
            isset($arr['operative_certificate_id']) && $arr['operative_certificate_id'] !== null ? (string) $arr['operative_certificate_id'] : null,
            (int) $arr['generation'],
            DateTimeImmutable::createFromInterface($createdAt),
            DateTimeImmutable::createFromInterface($updatedAt),
            (int) ($arr['schema_version'] ?? 1)
        );
    }

    private function nowUtc(): DateTimeImmutable
    {
        return $this->clock !== null
            ? ($this->clock)()
            : new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
