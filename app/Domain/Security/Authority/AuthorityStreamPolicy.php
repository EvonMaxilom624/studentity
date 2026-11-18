<?php

declare(strict_types=1);

namespace App\Domain\Security\Authority;

use Closure;
use DateTimeImmutable;

/** Trusted server configuration ONLY. No binding/provider or production algorithm is selected. */
final readonly class AuthorityStreamPolicy
{
    private array $keyIds;

    public function __construct(private string $namespace, private string $generation, array $keyIds, private Closure $verifyProof, private DateTimeImmutable $earliestIssue, private DateTimeImmutable $latestIssue)
    {
        $copy = [];
        foreach ($keyIds as $id) {
            if (! is_string($id) || $id === '') {
                throw new ReceiptContractViolation('Invalid configured authority key.');
            }
            $copy[] = $id;
        }
        if ($copy === [] || $namespace === '' || $generation === '' || $earliestIssue > $latestIssue) {
            throw new ReceiptContractViolation('Invalid configured authority stream policy.');
        }
        $this->keyIds = $copy;
    }

    public function verify(AuthorityCheckpoint|AuthorityChangePage $object, string $expectedNonce): void
    {
        $d = $object->data;
        $time = ReceiptSchema::time($d['issuedAt']);
        if ($d['authorityNamespace'] !== $this->namespace || $d['issuerGeneration'] !== $this->generation || ! in_array($d['signingKeyId'], $this->keyIds, true) || ! hash_equals($expectedNonce, $d['nonce']) || $time < $this->earliestIssue || $time > $this->latestIssue) {
            throw new ReceiptContractViolation('Authority stream trust, challenge or time mismatch.');
        }
        try {
            $valid = ($this->verifyProof)($object->canonical(), $d['proof'], $d['signingKeyId']);
        } catch (\Throwable) {
            throw new ReceiptContractViolation('Authority stream proof unavailable.');
        }
        if ($valid !== true) {
            throw new ReceiptContractViolation('Invalid authority stream proof.');
        }
    }

    public function verifyReceipt(RevocationCommand $command, RevocationReceipt $receipt, DateTimeImmutable $pageTime): VerifiedRevocationReceipt
    {
        return VerifiedRevocationReceipt::verify($command, $receipt, $this->namespace, $this->generation, $this->keyIds, $this->verifyProof, min($pageTime, $this->latestIssue));
    }
}
