<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Identity\UserKind;
use Throwable;

final class AccessDecisionService
{
    public function __construct(private SecurityStateAuthority $authority) {}

    public function decide(AuthorizationContext $context, ResourceScope $resource, string $ability, int $now): AccessDecision
    {
        $active = $context->accountState === 'active'
            || ($context->kind === UserKind::Student && $context->accountState === 'provisional');
        if (! $active) {
            return new AccessDecision(false, 'account_inactive');
        }
        $sessionValid = $context->securityEpoch >= 0
            && $context->sessionEpoch === $context->securityEpoch
            && $context->revokedAt === null
            && $context->createdAt <= $now
            && $context->expiresAt > $now;
        if (! $sessionValid) {
            return new AccessDecision(false, 'session_invalid', 401);
        }
        try {
            $evidence = $this->authority->evidence($context->userId, $context->sessionDigest);
        } catch (Throwable) {
            $evidence = null;
        }
        if ($evidence === null || $evidence->validUntil <= $now) {
            return new AccessDecision(false, 'authority_unavailable', 503);
        }
        $authorityMatches = $evidence->userId === $context->userId
            && hash_equals($evidence->sessionDigest, $context->sessionDigest)
            && $evidence->securityEpoch === $context->securityEpoch
            && hash_equals($evidence->authorizationDigest, $context->authorizationDigest())
            && ! $evidence->revoked && ! $evidence->requiresReauthentication;
        if (! $authorityMatches || ! $evidence->credentialsSafe) {
            return new AccessDecision(false, 'security_state_mismatch', 401);
        }
        $mfa = $context->kind === UserKind::Student
            || ($context->mfaVerifiedAt !== null && $context->mfaVerifiedAt >= $context->createdAt && $context->mfaVerifiedAt <= $now);
        if (! $mfa) {
            return new AccessDecision(false, 'mfa_required');
        }
        $scoped = false;
        foreach ($context->grants as $grant) {
            if ($grant->role !== $context->kind || ($grant->permissions[$ability] ?? false) !== true || $ability === '') {
                continue;
            }
            if ($grant->programId !== null && $grant->programId !== $resource->programId) {
                continue;
            }
            if ($context->kind === UserKind::Coordinator && ($resource->programId === null || $grant->programId !== $resource->programId)) {
                continue;
            }
            if ($context->kind === UserKind::Student && $resource->ownerUserId !== $context->userId) {
                continue;
            }
            $scoped = true;
            break;
        }
        if (! AuthorizationRecoveryGate::canResume($authorityMatches, $sessionValid, $scoped, $evidence->credentialsSafe, $mfa)) {
            return new AccessDecision(false, 'permission_denied');
        }

        return new AccessDecision(true, 'allowed', 200);
    }
}
