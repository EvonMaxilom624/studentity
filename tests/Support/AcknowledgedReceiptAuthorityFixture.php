<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Security\Authority\RevocationCommand;
use App\Domain\Security\Authority\RevocationReceipt;
use App\Domain\Security\Authority\VerifiedRevocationReceipt;

/** A supplied, already-acknowledged synthetic operation, NOT durable authority storage. */
final class AcknowledgedReceiptAuthorityFixture implements ReceiptAuthorityLabPort
{
    public function __construct(private RevocationCommand $original, private RevocationReceipt $receipt, private string $key)
    {
        if (! app()->environment('testing') || strlen($key) !== 32) {
            throw new \InvalidArgumentException('Synthetic fixture only.');
        }
        $this->verify($original, $receipt);
    }

    public function revoke(RevocationCommand $command): RevocationReceipt
    {
        throw new \LogicException('Replay must not submit a new authority command.');
    }

    public function verify(RevocationCommand $command, RevocationReceipt $receipt): VerifiedRevocationReceipt
    {
        return VerifiedRevocationReceipt::verify($command, $receipt, 'synthetic-authority', 'generation-1', ['lab-key'],
            fn (string $body, string $proof, string $keyId) => $keyId === 'lab-key' && hash_equals(hash_hmac('sha256', $body, $this->key), $proof),
            new \DateTimeImmutable('2026-11-18T00:00:01.000Z'));
    }

    public function lookup(string $operationId): array
    {
        // Partial fixture knowledge can never establish definitive absence.
        return $operationId === $this->original->data['operationId'] ? ['status' => 'committed', 'receipt' => $this->receipt] : ['status' => 'unknown'];
    }
}
