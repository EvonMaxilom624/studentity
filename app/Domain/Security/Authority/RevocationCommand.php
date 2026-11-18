<?php

declare(strict_types=1);

namespace App\Domain\Security\Authority;

final readonly class RevocationCommand
{
    public array $data;

    public function __construct(array $data)
    {
        // Detach scalar references before validation; readonly arrays can retain aliases.
        $copy = [];
        foreach ($data as $key => $value) {
            $copy[$key] = $value;
        }
        ReceiptSchema::validate($copy, false);
        $this->data = $copy;
    }

    public static function fromJson(string $wire): self
    {
        return new self(CanonicalAuthorityJson::decodeObject($wire));
    }

    public function canonical(): string
    {
        return CanonicalAuthorityJson::encode($this->data);
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->canonical());
    }
}
