<?php

declare(strict_types=1);

namespace App\Domain\Documents;

/** No URL, ordinary download, overwrite, delete or release operation. No production adapter selected. */
interface PrivateQuarantineStore
{
    public function stage(QuarantineObjectReference $object, #[\SensitiveParameter] string $bytes): void;

    public function readForScan(QuarantineObjectReference $object): string;
}
