<?php

declare(strict_types=1);

namespace App\Domain\Security\Authority;

/** Bounded original commands/receipts for consecutive negative-state reconciliation. */
final readonly class AuthorityChangePage
{
    public array $data;

    public function __construct(array $data)
    {
        $this->data = AuthorityStreamSchema::snapshot($data, true);
    }

    public static function fromJson(string $wire): self
    {
        return new self(AuthorityStreamSchema::decode($wire));
    }

    public function canonical(): string
    {
        $data = $this->data;
        unset($data['proof']);

        return CanonicalAuthorityJson::encode($data);
    }

    public function wire(): string
    {
        return CanonicalAuthorityJson::encode($this->data);
    }
}
