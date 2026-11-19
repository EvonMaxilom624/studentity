<?php

declare(strict_types=1);

namespace App\Domain\Documents;

/** Trusted server adapter port; actual structure/malware/active-content implementation remains unaccepted. */
interface FileSecurityScanner
{
    public function available(FileScanPolicy $policy): bool;

    public function inspect(QuarantineObjectReference $object, #[\SensitiveParameter] string $bytes, FileScanPolicy $policy, string $scanId): FileScanResult;
}
