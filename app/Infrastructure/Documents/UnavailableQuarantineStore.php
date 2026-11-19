<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\PrivateQuarantineStore;
use App\Domain\Documents\QuarantineObjectReference;

final class UnavailableQuarantineStore implements PrivateQuarantineStore
{
    public function stage(QuarantineObjectReference $object, #[\SensitiveParameter] string $bytes): void
    {
        throw new FileSecurityUnavailable('Private quarantine storage unavailable.');
    }

    public function readForScan(QuarantineObjectReference $object): string
    {
        throw new FileSecurityUnavailable('Private quarantine storage unavailable.');
    }
}
