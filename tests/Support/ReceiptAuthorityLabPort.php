<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Security\Authority\RevocationCommand;
use App\Domain\Security\Authority\RevocationReceipt;
use App\Domain\Security\Authority\VerifiedRevocationReceipt;

/** Test-only seam; not the application's authority interface. */
interface ReceiptAuthorityLabPort
{
    public function revoke(RevocationCommand $command): RevocationReceipt;

    public function verify(RevocationCommand $command, RevocationReceipt $receipt): VerifiedRevocationReceipt;

    public function lookup(string $operationId): array;
}
