<?php

declare(strict_types=1);

namespace App\Domain\Security\Authority;

use Closure;
use DateTimeImmutable;

/** Server-owned proof policy ONLY; authenticity/history is never current positive authorization. */
final readonly class AuthorityProofPolicy
{
    private array $keyIds;

    public function __construct(private string $namespace, private string $generation, array $keyIds, private Closure $verifyProof, private DateTimeImmutable $latestObservation)
    {
        if (! preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $namespace)) {
            throw new ReceiptContractViolation('Invalid configured issuer.');
        }
        AuthorityContractFields::reference($generation);
        $copy = [];
        foreach ($keyIds as $id) {
            if (! is_string($id)) {
                throw new ReceiptContractViolation('Invalid configured proof key.');
            }AuthorityContractFields::reference($id);
            $copy[] = $id;
        }
        if ($copy === []) {
            throw new ReceiptContractViolation('Configured proof keys required.');
        }$this->keyIds = $copy;
    }

    public function verify(array $data, string $canonical, string $timeField): void
    {
        $time = $data[$timeField] ?? null;
        $proof = $data['proof'] ?? null;
        $key = $data['signingKeyId'] ?? null;
        if (($data['authorityNamespace'] ?? null) !== $this->namespace || ($data['issuerGeneration'] ?? null) !== $this->generation || ! is_string($key) || ! in_array($key, $this->keyIds, true) || ! is_string($proof) || ! is_string($time) || ReceiptSchema::time($time) > $this->latestObservation) {
            throw new ReceiptContractViolation('Untrusted authority proof binding or time.');
        }
        try {
            $valid = ($this->verifyProof)($canonical, $proof, $key);
        } catch (\Throwable) {
            throw new ReceiptContractViolation('Authority proof unavailable.');
        }
        if ($valid !== true) {
            throw new ReceiptContractViolation('Invalid authority proof.');
        }
    }

    public function binds(string $namespace, string $generation): bool
    {
        return $namespace === $this->namespace && $generation === $this->generation;
    }

    public function __debugInfo(): array
    {
        return ['authorityNamespace' => $this->namespace, 'issuerGeneration' => $this->generation, 'proofConfiguration' => 'redacted'];
    }
}
