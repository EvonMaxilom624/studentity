<?php

declare(strict_types=1);

namespace App\Domain\Security\Authority;

/** Valid shape does not imply authentic proof; only VerifiedRevocationReceipt exposes journal mapping. */
final readonly class RevocationReceipt
{
    public array $data;

    public function __construct(array $data)
    {
        $copy = [];
        foreach ($data as $key => $value) {
            $copy[$key] = $value;
        }
        ReceiptSchema::validate($copy, true);
        $this->data = $copy;
    }

    public static function fromJson(string $wire): self
    {
        return new self(CanonicalAuthorityJson::decodeObject($wire));
    }

    public function canonical(): string
    {
        $body = $this->data;
        unset($body['proof']);

        return CanonicalAuthorityJson::encode($body);
    }

    public function wire(): string
    {
        return CanonicalAuthorityJson::encode($this->data);
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->canonical());
    }
}
