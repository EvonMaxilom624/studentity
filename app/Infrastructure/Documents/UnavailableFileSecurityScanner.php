<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Domain\Documents\FileScanPolicy;
use App\Domain\Documents\FileScanResult;
use App\Domain\Documents\FileSecurityScanner;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\QuarantineObjectReference;

final class UnavailableFileSecurityScanner implements FileSecurityScanner
{
    public function available(FileScanPolicy $policy): bool
    {
        return false;
    }

    public function inspect(QuarantineObjectReference $object, #[\SensitiveParameter] string $bytes, FileScanPolicy $policy, string $scanId): FileScanResult
    {
        throw new FileSecurityUnavailable('File scanner unavailable.');
    }
}
