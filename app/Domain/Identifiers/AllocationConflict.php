<?php

declare(strict_types=1);

namespace App\Domain\Identifiers;

/** Observed operation/identity/state conflict, distinct from unavailable storage. */
final class AllocationConflict extends \RuntimeException {}
