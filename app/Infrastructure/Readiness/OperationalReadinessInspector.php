<?php

declare(strict_types=1);

namespace App\Infrastructure\Readiness;

use App\Domain\Governance\CurrentApprovalAuthority;
use App\Domain\Identifiers\SystemIdIssuer;
use App\Domain\Jobs\JobExecutionAuthority;
use App\Domain\Registration\RegistrationActivationAuthority;
use App\Domain\Security\SecurityStateAuthority;
use App\Models\AuthenticatedUser;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;

/** Read-only operator diagnostic. This report is never a permission or acceptance capability. */
final readonly class OperationalReadinessInspector
{
    public function __construct(private Container $container, private Repository $config) {}

    public function report(): array
    {
        $ports = [];
        foreach (['security-authority' => SecurityStateAuthority::class, 'approval-authority' => CurrentApprovalAuthority::class, 'system-id-issuer' => SystemIdIssuer::class, 'registration-authority' => RegistrationActivationAuthority::class, 'job-authority' => JobExecutionAuthority::class] as $id => $abstract) {
            // bound() reads the binding table only. Do not make/resolve/probe a provider here.
            $registered = $this->container->bound($abstract);
            $ports[$id] = ['registered' => $registered, 'status' => $registered ? 'unaccepted' : 'missing-binding'];
        }

        return [
            'purpose' => 'studentity.operational-readiness.v1',
            'status' => 'blocked',
            'advisory' => true,
            'activationAllowed' => false,
            'runtime' => [
                'environment' => $this->choice('app.env', ['local', 'testing', 'production']),
                'sessionDriver' => $this->choice('session.driver', ['file', 'array', 'database', 'redis', 'cookie', 'memcached', 'dynamodb']),
                'sessionSerialization' => $this->choice('session.serialization', ['json', 'php']),
                'sessionEncrypted' => $this->boolean('session.encrypt'),
                'cookieSecure' => $this->boolean('session.secure'),
                'cookieHttpOnly' => $this->boolean('session.http_only'),
                'cookieSameSite' => $this->choice('session.same_site', ['lax', 'strict', 'none']),
                'queueDriver' => $this->choice('queue.default', ['sync', 'database', 'redis', 'null', 'sqs', 'beanstalkd']),
                'identityModel' => $this->read('auth.providers.users.model') === AuthenticatedUser::class ? 'authenticated-user' : 'unknown',
            ],
            'ports' => $ports,
            'blockers' => $this->blockers(),
        ];
    }

    private function read(string $key): mixed
    {
        try {
            return $this->config->get($key);
        } catch (\Throwable) {
            return null;
        }
    }

    private function choice(string $key, array $allowed): string
    {
        $value = $this->read($key);

        return is_string($value) && in_array($value, $allowed, true) ? $value : 'unknown';
    }

    private function boolean(string $key): ?bool
    {
        $value = $this->read($key);

        return is_bool($value) ? $value : null;
    }

    private function blockers(): array
    {
        // These operational proofs have not been supplied or accepted. Config flags, class
        // names and synthetic lab success cannot establish them. No ready branch exists.
        $requirements = [
            ['security-recovery', ['FND-03', 'FR-ACT-020', 'FR-ACT-021', 'AUD-13'], [
                'Named independent security authority, peer trust, current account/grant/credential/MFA/revocation evidence and failure behavior.',
                'Accepted independent recovery baseline/high-water/pruning, durable original commands and applied cursor; old restored state cannot revive privilege.',
                'Two-instance outage/restore tests; affected privileged sessions invalidated and applicable MFA reauthentication enforced.',
            ]],
            ['permanent-issuer', ['FND-03', 'P1', 'ADR-02', 'FR-ID-011'], [
                'Approved permanent System ID format and issuer ownership; durable same-operation allocation/lookup and nonrecycling registry.',
                'Lost ACK, concurrent allocation, conflict and restore tests against an independently trustworthy current issuer; local projection is not issuance authority.',
            ]],
            ['proof-key-custody', ['FND-03', 'AUD-13'], [
                'Approved authority namespaces/generations, server-owned trust roots, key custody and current key revocation/rotation process.',
                'Accepted proof policy, authenticated peer/transport, secret access and failure tests; no request-selected key or silent generation rollover.',
            ]],
            ['trusted-clock', ['FND-03', 'FND-05', 'FR-ACT-021'], [
                'Operational clock ownership/synchronization, accepted skew/deadline/lease policy and rollback detection across instances and providers.',
                'Measured expiration, nonce freshness, monotonic observation and clock-rollback tests; local wall time or test clock alone is not accepted trust.',
            ]],
            ['approved-notices', ['FND-05', 'G-04'], [
                'Named institutional approvers and exact approved notice/terms content, hashes, versions, purposes and effective history.',
                'Current independent approval confirmation for exact notices under SRS G-04 and the complete applicable institutional gate composition; pending/replayed/synthetic evidence cannot admit real data. Historical registration/invitation lab gate aliases are not institutional approval scopes.',
                'Separate approved processing legal basis and consent requirements; acknowledgement is not automatic consent or academic verification.',
            ]],
            ['session-transport', ['FND-03', 'ADR-01', 'FR-ACT-020', 'FR-ACT-021'], [
                'Accepted same-origin cookie/CSRF/guard and private encrypted durable session transport with approved key custody and no bearer/remember-me fallback.',
                'Two-instance native rotation/revocation/MFA/owner-fence/expiry and restore tests; local file/array mode stays a development observation.',
            ]],
            ['durable-delivery', ['FND-04', 'FR-NOT-003', 'FR-NOT-004', 'FR-NOT-005', 'FR-NOT-006', 'NFR-OPS-001'], [
                'Approved sender, encrypted private envelope custody, template versions, purpose/recipient binding and authenticated private-detail destinations.',
                'Recipient messages exclude confidential document contents, accusations, full identity records and private interview details.',
                'Shared durable admission/dedup/throttle/retry/expiry policy across start/resend/retry; track original attempt IDs, failures, template version, time and redacted audit.',
                'Accepted atomic idempotency or reviewed external commit semantics, lost-ACK lookup, transport confidentiality/deadline/cancellation and independent outage/restore/key-rotation tests.',
            ]],
            ['deployment-acceptance', ['FND-05', 'ADR-01', 'NFR-OPS-002', 'NFR-OPS-003'], [
                'Institutional privacy/security/release approvals, approved hosting/region/budget/provider ownership and least-privilege deployment configuration.',
                'Database/private-file backups encrypted, access-controlled, scheduled, monitored, off-site and periodically restored in documented tests.',
                'SRS NFR-OPS-003 initial RPO/RTO design targets <=24 hours alongside measured institutionally approved deployed objectives, reproducible exact artifacts and secure CI/release review; synthetic tests cannot sign operational acceptance.',
            ]],
        ];

        return array_map(fn ($r) => ['id' => $r[0], 'status' => 'unaccepted', 'basis' => $r[1], 'requiredEvidence' => $r[2]], $requirements);
    }
}
