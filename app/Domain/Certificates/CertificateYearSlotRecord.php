<?php

declare(strict_types=1);

namespace App\Domain\Certificates;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityJson;
use DateTimeImmutable;

/**
 * Immutable representation of a certificate_year_slots record.
 */
final readonly class CertificateYearSlotRecord
{
    public function __construct(
        public string $slotId,
        public string $studentId,
        public string $academicYearId,
        public ?string $operativeCertificateId,
        public int $generation,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public int $schemaVersion = 1
    ) {
        F::uuid($studentId);
        F::uuid($academicYearId);
        if ($operativeCertificateId !== null) {
            F::uuid($operativeCertificateId);
        }
        if ($generation < 0) {
            throw new \InvalidArgumentException('Generation must be non-negative.');
        }
        if ($schemaVersion < 1) {
            throw new \InvalidArgumentException('Schema version must be positive.');
        }
    }

    public function fingerprint(): string
    {
        return hash('sha256', CanonicalAuthorityJson::encode([
            'purpose' => 'studentity.certificate.slot.record.v1',
            'slotId' => $this->slotId,
            'studentId' => $this->studentId,
            'academicYearId' => $this->academicYearId,
            'operativeCertificateId' => $this->operativeCertificateId,
            'generation' => $this->generation,
            'schemaVersion' => $this->schemaVersion,
        ]));
    }
}
