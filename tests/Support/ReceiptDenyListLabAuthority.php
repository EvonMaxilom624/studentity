<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Security\Authority\VerifiedRevocationReceipt;
use App\Domain\Security\AuthorityEvidence;
use App\Domain\Security\SecurityStateAuthority;

/**
 * Synthetic negative-evidence overlay, never selected by application providers.
 * Historical receipts can only restrict current evidence, never create it.
 * Keeping receipts in PHP memory models independence, NOT crash durability.
 */
final class ReceiptDenyListLabAuthority implements SecurityStateAuthority
{
    private array $denied = [];

    public function __construct(private SecurityStateAuthority $current, array $receipts)
    {
        if (! app()->environment('testing')) {
            throw new \InvalidArgumentException('Synthetic receipt overlay only.');
        }
        foreach ($receipts as $receipt) {
            if (! $receipt instanceof VerifiedRevocationReceipt) {
                throw new \InvalidArgumentException('Verified negative receipt required.');
            }
            $data = $receipt->receipt->data;
            $this->denied[$data['subjectUserId'].':'.$data['sessionDigest']] = true;
        }
    }

    public function evidence(string $userId, string $sessionDigest): ?AuthorityEvidence
    {
        $evidence = $this->current->evidence($userId, $sessionDigest);
        if ($evidence === null || ! isset($this->denied[$userId.':'.$sessionDigest])) {
            return $evidence;
        }

        return new AuthorityEvidence($evidence->userId, $evidence->sessionDigest, $evidence->securityEpoch, $evidence->authorizationDigest, $evidence->validUntil, revoked: true, credentialsSafe: $evidence->credentialsSafe, requiresReauthentication: $evidence->requiresReauthentication);
    }

    public function revoke(string $userId, string $sessionDigest): void
    {
        $this->current->revoke($userId, $sessionDigest);
    }
}
