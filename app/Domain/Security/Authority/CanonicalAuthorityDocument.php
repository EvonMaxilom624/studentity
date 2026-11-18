<?php

declare(strict_types=1);

namespace App\Domain\Security\Authority;

trait CanonicalAuthorityDocument
{
    public static function fromJson(string $wire): static
    {
        return new static(CanonicalAuthorityJson::decodeObject($wire));
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

    public function fingerprint(): string
    {
        return hash('sha256', $this->canonical());
    }

    public function __debugInfo(): array
    {
        return ['purpose' => $this->data['purpose'], 'schemaVersion' => 1, 'body' => 'redacted'];
    }
}
