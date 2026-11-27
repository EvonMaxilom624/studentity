<?php

declare(strict_types=1);

namespace App\Domain\Certificates;

use RuntimeException;

/**
 * Thrown when certificate slot operation fails closed due to conflict,
 * unsatisfied eligibility gate, or unavailable dependencies.
 */
final class CertificateSlotUnavailable extends RuntimeException {}
