<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Identity\UserKind;
use App\Domain\Security\AccessDecisionService;
use App\Domain\Security\AuthorityEvidence;
use App\Domain\Security\AuthorizationContext;
use App\Domain\Security\ResourceScope;
use App\Domain\Security\RoleGrant;
use App\Domain\Security\SecurityStateAuthority;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Fnd03AccessDecisionTest extends TestCase
{
    private const NOW = 1800000000;

    private function context(array $overrides = []): AuthorizationContext
    {
        return new AuthorizationContext(...array_replace([
            'userId' => '123e4567-e89b-42d3-a456-426614174001',
            'kind' => UserKind::Coordinator,
            'accountState' => 'active',
            'securityEpoch' => 4,
            'sessionEpoch' => 4,
            'sessionDigest' => str_repeat('a', 64),
            'createdAt' => self::NOW - 100,
            'expiresAt' => self::NOW + 100,
            'revokedAt' => null,
            'mfaVerifiedAt' => self::NOW - 50,
            'grants' => [new RoleGrant('grant-a', UserKind::Coordinator, 'program-a', ['profile.read' => true])],
        ], $overrides));
    }

    private function service(AuthorizationContext $context, array $evidenceOverrides = []): AccessDecisionService
    {
        $evidence = new AuthorityEvidence(...array_replace([
            'userId' => $context->userId,
            'sessionDigest' => $context->sessionDigest,
            'securityEpoch' => $context->securityEpoch,
            'authorizationDigest' => $context->authorizationDigest(),
            'validUntil' => self::NOW + 30,
            'revoked' => false,
            'credentialsSafe' => true,
            'requiresReauthentication' => false,
        ], $evidenceOverrides));
        $authority = new class($evidence) implements SecurityStateAuthority
        {
            public function __construct(private AuthorityEvidence $evidence) {}

            public function evidence(string $userId, string $sessionDigest): ?AuthorityEvidence
            {
                return $this->evidence;
            }

            public function revoke(string $userId, string $sessionDigest): void {}
        };

        return new AccessDecisionService($authority);
    }

    public function test_explicit_permission_and_exact_program_allow_access(): void
    {
        $context = $this->context();
        $decision = $this->service($context)->decide($context, new ResourceScope('profile', 'resource-a', 'program-a'), 'profile.read', self::NOW);
        self::assertTrue($decision->allowed);
        self::assertSame('allowed', $decision->reason);
    }

    public static function deniedContexts(): array
    {
        return [
            'disabled' => [['accountState' => 'disabled']],
            'inactive' => [['accountState' => 'inactive']],
            'provisional staff' => [['accountState' => 'provisional']],
            'expired' => [['expiresAt' => self::NOW]],
            'future session' => [['createdAt' => self::NOW + 1]],
            'revoked' => [['revokedAt' => self::NOW - 1]],
            'old session epoch' => [['sessionEpoch' => 3]],
            'missing MFA' => [['mfaVerifiedAt' => null]],
            'future MFA' => [['mfaVerifiedAt' => self::NOW + 1]],
            'MFA before session' => [['mfaVerifiedAt' => self::NOW - 101]],
            'missing grants' => [['grants' => []]],
        ];
    }

    #[DataProvider('deniedContexts')]
    public function test_invalid_security_context_is_denied(array $overrides): void
    {
        $context = $this->context($overrides);
        self::assertFalse($this->service($context)->decide($context, new ResourceScope('profile', 'resource-a', 'program-a'), 'profile.read', self::NOW)->allowed);
    }

    public static function badEvidence(): array
    {
        return [
            'other subject' => [['userId' => 'someone-else']],
            'other session' => [['sessionDigest' => str_repeat('b', 64)]],
            'older backup epoch' => [['securityEpoch' => 5]],
            'restored grant' => [['authorizationDigest' => str_repeat('b', 64)]],
            'expired authority' => [['validUntil' => self::NOW]],
            'authority revocation' => [['revoked' => true]],
            'compromised credentials' => [['credentialsSafe' => false]],
            'recovery requires reauth' => [['requiresReauthentication' => true]],
        ];
    }

    #[DataProvider('badEvidence')]
    public function test_independent_authority_must_match_current_security_state(array $overrides): void
    {
        $context = $this->context();
        self::assertFalse($this->service($context, $overrides)->decide($context, new ResourceScope('profile', 'resource-a', 'program-a'), 'profile.read', self::NOW)->allowed);
    }

    public function test_cross_program_unknown_action_and_truthy_permission_are_denied(): void
    {
        $context = $this->context();
        $service = $this->service($context);
        self::assertFalse($service->decide($context, new ResourceScope('profile', 'x', 'program-b'), 'profile.read', self::NOW)->allowed);
        self::assertFalse($service->decide($context, new ResourceScope('profile', 'x'), 'profile.read', self::NOW)->allowed);
        self::assertFalse($service->decide($context, new ResourceScope('profile', 'x', 'program-a'), 'profile.export', self::NOW)->allowed);
        $context = $this->context(['grants' => [new RoleGrant('grant-a', UserKind::Coordinator, 'program-a', ['profile.read' => 'true'])]]);
        self::assertFalse($this->service($context)->decide($context, new ResourceScope('profile', 'x', 'program-a'), 'profile.read', self::NOW)->allowed);
    }

    public function test_student_ownership_requires_an_explicit_matching_grant(): void
    {
        $context = $this->context(['kind' => UserKind::Student, 'accountState' => 'provisional', 'mfaVerifiedAt' => null, 'grants' => [new RoleGrant('student-grant', UserKind::Student, null, ['profile.read' => true])]]);
        $service = $this->service($context);
        self::assertTrue($service->decide($context, new ResourceScope('profile', 'x', null, $context->userId), 'profile.read', self::NOW)->allowed);
        self::assertFalse($service->decide($context, new ResourceScope('profile', 'x', null, 'other-student'), 'profile.read', self::NOW)->allowed);
        self::assertFalse($service->decide($context, new ResourceScope('profile', 'x'), 'profile.read', self::NOW)->allowed);
        self::assertFalse($service->decide($context, new ResourceScope('profile', 'x', null, $context->userId), 'profile.write', self::NOW)->allowed);
    }

    public function test_system_admin_has_no_implicit_superuser_bypass(): void
    {
        $context = $this->context(['kind' => UserKind::SystemAdmin, 'grants' => [new RoleGrant('admin-grant', UserKind::SystemAdmin, null, ['profile.read' => true])]]);
        $service = $this->service($context);
        self::assertTrue($service->decide($context, new ResourceScope('profile', 'x', 'program-a'), 'profile.read', self::NOW)->allowed);
        self::assertFalse($service->decide($context, new ResourceScope('profile', 'x', 'program-a'), 'profile.delete', self::NOW)->allowed);
        $wrongRole = $this->context(['grants' => $context->grants]);
        self::assertFalse($this->service($wrongRole)->decide($wrongRole, new ResourceScope('profile', 'x', 'program-a'), 'profile.read', self::NOW)->allowed);
    }

    public function test_missing_or_unavailable_authority_never_allows_access(): void
    {
        $authority = new class implements SecurityStateAuthority
        {
            public bool $throw = false;

            public function evidence(string $userId, string $sessionDigest): ?AuthorityEvidence
            {
                if ($this->throw) {
                    throw new \RuntimeException('Synthetic authority outage');
                }

                return null;
            }

            public function revoke(string $userId, string $sessionDigest): void {}
        };
        $context = $this->context();
        foreach ([false, true] as $throw) {
            $authority->throw = $throw;
            $decision = (new AccessDecisionService($authority))->decide($context, new ResourceScope('profile', 'x', 'program-a'), 'profile.read', self::NOW);
            self::assertFalse($decision->allowed);
            self::assertSame(503, $decision->status);
        }
    }

    public function test_authorization_fingerprint_ignores_query_order_but_detects_permission_changes(): void
    {
        $a = new RoleGrant('grant-a', UserKind::Coordinator, 'program-a', ['profile.read' => true, 'profile.write' => false]);
        $b = new RoleGrant('grant-b', UserKind::Coordinator, 'program-b', ['profile.read' => true]);
        $original = $this->context(['grants' => [$a, $b]]);
        $reordered = $this->context(['grants' => [$b, new RoleGrant('grant-a', UserKind::Coordinator, 'program-a', ['profile.write' => false, 'profile.read' => true])]]);
        self::assertSame($original->authorizationDigest(), $reordered->authorizationDigest());
        $changed = $this->context(['grants' => [new RoleGrant('grant-a', UserKind::Coordinator, 'program-a', ['profile.read' => true, 'profile.write' => true]), $b]]);
        self::assertNotSame($original->authorizationDigest(), $changed->authorizationDigest());
    }
}
