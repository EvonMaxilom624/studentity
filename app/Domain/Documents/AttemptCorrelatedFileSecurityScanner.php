<?php

declare(strict_types=1);

namespace App\Domain\Documents;

/** Server adapter capability. Must bind the response nonce to this actual observation, not relabel cached output. */
interface AttemptCorrelatedFileSecurityScanner extends FileSecurityScanner
{
    public function inspectAttempt(QuarantineObjectReference $object, #[\SensitiveParameter] string $bytes, FileScanPolicy $policy, string $scanId, string $attemptNonce): FileScanResult;
}
