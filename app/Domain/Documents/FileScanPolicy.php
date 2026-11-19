<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityJson;

/** Server-owned pins; signatureVersion is the malware definition version, not a proof signature. */
final readonly class FileScanPolicy
{
    public function __construct(public string $reference, public string $scannerId, public string $signatureVersion, public int $roundTripMilliseconds)
    {
        foreach ([$reference, $scannerId, $signatureVersion] as $ref) {
            F::reference($ref);
        }
        if ($roundTripMilliseconds < 1 || $roundTripMilliseconds > 60000) {
            throw new \InvalidArgumentException('Bounded scan observation required.');
        }
    }

    public function fingerprint(): string
    {
        return hash('sha256', CanonicalAuthorityJson::encode(['purpose' => 'studentity.file-scan.policy.v1'] + get_object_vars($this)));
    }

    public function __debugInfo(): array
    {
        return ['policy' => 'redacted'];
    }
}
