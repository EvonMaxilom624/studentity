<?php

declare(strict_types=1);

namespace App\Domain\Documents;

/** Trusted local journal boundary, never an external scanner or permission source. */
interface ScanAttemptJournal
{
    public function begin(FileScanCommand $command, string $attemptNonce): void;

    public function complete(FileScanCommand $command, FileScanResult $result): void;
}
