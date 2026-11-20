<?php

declare(strict_types=1);

namespace App\Domain\Documents;

/** Internal buffered read only; no URL, output, promotion or permission grant. */
interface PrivateDeliveryReader
{
    public function read(QuarantineObjectReference $object): string;
}
