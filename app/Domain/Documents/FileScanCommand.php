<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\ReceiptSchema;

/** Original immutable scan identity; historical command, never permission or current scan evidence. */
final readonly class FileScanCommand
{
    public function __construct(public string $scanId, public QuarantineObjectReference $object, public FileScanPolicy $policy, public string $issuedAt)
    {
        F::uuid($scanId);
        ReceiptSchema::time($issuedAt);
    }

    public function wire(): string
    {
        return CanonicalAuthorityJson::encode(['purpose' => 'studentity.file-scan.command.v1', 'scanId' => $this->scanId, 'object' => get_object_vars($this->object), 'policy' => get_object_vars($this->policy), 'issuedAt' => $this->issuedAt]);
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->wire());
    }

    public static function fromWire(string $wire): self
    {
        $d = CanonicalAuthorityJson::decodeObject($wire);
        F::keys($d, ['purpose', 'scanId', 'object', 'policy', 'issuedAt']);
        if ($d['purpose'] !== 'studentity.file-scan.command.v1') {
            throw new \InvalidArgumentException;
        }
        $o = F::object($d['object']);
        $p = F::object($d['policy']);
        F::keys($o, ['objectId', 'documentVersionId', 'ownerUserId', 'sha256', 'byteCount', 'mediaType']);
        F::keys($p, ['reference', 'scannerId', 'signatureVersion', 'roundTripMilliseconds']);

        return new self($d['scanId'], new QuarantineObjectReference(...$o), new FileScanPolicy(...$p), $d['issuedAt']);
    }

    public function __debugInfo(): array
    {
        return ['command' => 'redacted'];
    }
}
