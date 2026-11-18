<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use App\Domain\Governance\FeatureGatePolicy;

final class RegistrationAdmissionPolicy
{
    public function __construct(private FeatureGatePolicy $gates) {}

    /** Current bundle fingerprint is server-resolved; it binds both exact notice versions/content hashes. */
    public function allows(RegistrationIntent $intent, string $currentBundleFingerprint, int $now): bool
    {
        return $intent->acknowledged() && hash_equals($currentBundleFingerprint, $intent->noticeBundleFingerprint()) && $this->gates->decide('registration', $currentBundleFingerprint, $now)->allowed;
    }
}
