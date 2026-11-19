<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Governance\CurrentApprovalAuthority;
use App\Domain\Identifiers\SystemIdIssuer;
use App\Domain\Jobs\JobExecutionAuthority;
use App\Domain\Registration\RegistrationActivationAuthority;
use App\Domain\Security\SecurityStateAuthority;
use App\Infrastructure\Readiness\OperationalReadinessInspector;
use App\Models\AuthenticatedUser;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OperationalReadinessInspectorTest extends TestCase
{
    private function config(array $replace = []): Repository
    {
        $config = new Repository;
        foreach (array_replace(['app.env' => 'testing', 'session.driver' => 'file', 'session.serialization' => 'json', 'session.encrypt' => false, 'session.secure' => false, 'session.http_only' => true, 'session.same_site' => 'lax', 'queue.default' => 'sync', 'auth.providers.users.model' => AuthenticatedUser::class], $replace) as $k => $v) {
            $config->set($k, $v);
        }

        return $config;
    }

    private function ports(): array
    {
        return ['security-authority' => SecurityStateAuthority::class, 'approval-authority' => CurrentApprovalAuthority::class, 'system-id-issuer' => SystemIdIssuer::class, 'registration-authority' => RegistrationActivationAuthority::class, 'job-authority' => JobExecutionAuthority::class];
    }

    public function test_missing_ports_and_every_operational_requirement_remain_blocked(): void
    {
        $report = (new OperationalReadinessInspector(new Container, $this->config()))->report();
        self::assertSame('studentity.operational-readiness.v1', $report['purpose']);
        self::assertSame('blocked', $report['status']);
        self::assertFalse($report['activationAllowed']);
        self::assertTrue($report['advisory']);
        self::assertSame(['security-authority', 'approval-authority', 'system-id-issuer', 'registration-authority', 'job-authority'], array_keys($report['ports']));
        foreach ($report['ports'] as $p) {
            self::assertFalse($p['registered']);
            self::assertSame('missing-binding', $p['status']);
        }
        self::assertSame(['security-recovery', 'permanent-issuer', 'proof-key-custody', 'trusted-clock', 'approved-notices', 'session-transport', 'durable-delivery', 'deployment-acceptance'], array_column($report['blockers'], 'id'));
        foreach ($report['blockers'] as $blocker) {
            self::assertSame('unaccepted', $blocker['status']);
            self::assertNotEmpty($blocker['requiredEvidence']);
            self::assertNotEmpty($blocker['basis']);
        }
    }

    public function test_registered_factories_and_fake_approval_flags_never_execute_or_grant_readiness(): void
    {
        $container = new Container;
        $calls = 0;
        foreach ($this->ports() as $port) {
            $container->bind($port, function () use (&$calls) {
                $calls++;
                throw new \RuntimeException('synthetic-secret-provider');
            });
        }
        $config = $this->config(['readiness.approved' => true, 'readiness.activationAllowed' => true, 'security.authority_verified' => true, 'registration.g05_approved' => true]);
        $report = (new OperationalReadinessInspector($container, $config))->report();
        self::assertSame(0, $calls);
        self::assertFalse($report['activationAllowed']);
        self::assertSame('blocked', $report['status']);
        foreach ($report['ports'] as $port) {
            self::assertTrue($port['registered']);
            self::assertSame('unaccepted', $port['status']);
        }
        self::assertStringNotContainsString('synthetic-secret-provider', json_encode($report, JSON_THROW_ON_ERROR));
    }

    public static function environments(): array
    {
        return [['local'], ['testing'], ['production'], ['unknown-environment']];
    }

    #[DataProvider('environments')]
    public function test_environment_and_secure_settings_cannot_promote_operational_acceptance(string $environment): void
    {
        $r = (new OperationalReadinessInspector(new Container, $this->config(['app.env' => $environment, 'session.encrypt' => true, 'session.secure' => true, 'session.http_only' => true, 'session.same_site' => 'strict'])))->report();
        self::assertFalse($r['activationAllowed']);
        self::assertSame('blocked', $r['status']);
        self::assertSame($environment === 'unknown-environment' ? 'unknown' : $environment, $r['runtime']['environment']);
        self::assertSame('unaccepted', array_column($r['blockers'], 'status', 'id')['trusted-clock']);
    }

    public function test_report_excludes_unrecognized_strings_secrets_control_characters_and_objects(): void
    {
        $casts = 0;
        $untrusted = new class($casts) implements \Stringable
        {
            public function __construct(private int &$calls) {}

            public function __toString(): string
            {
                $this->calls++;

                return 'secret-object';
            }
        };
        $secret = "synthetic-SECRET\n\x1b[31m";
        $c = $this->config(['app.env' => $secret, 'app.key' => $secret, 'database.connections.mongodb.dsn' => $secret, 'mail.password' => $secret, 'session.driver' => $secret, 'session.serialization' => ['php' => $secret], 'session.encrypt' => 'true', 'session.secure' => $untrusted, 'session.http_only' => 1, 'session.same_site' => $secret, 'queue.default' => $untrusted, 'auth.providers.users.model' => $secret]);
        $report = (new OperationalReadinessInspector(new Container, $c))->report();
        self::assertSame(['environment' => 'unknown', 'sessionDriver' => 'unknown', 'sessionSerialization' => 'unknown', 'sessionEncrypted' => null, 'cookieSecure' => null, 'cookieHttpOnly' => null, 'cookieSameSite' => 'unknown', 'queueDriver' => 'unknown', 'identityModel' => 'unknown'], $report['runtime']);
        $wire = json_encode($report, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('synthetic-SECRET', $wire);
        self::assertStringNotContainsString('secret-object', $wire);
        self::assertSame(0, $casts);
    }

    public function test_a_read_failure_is_redacted_and_never_becomes_positive(): void
    {
        $config = new class extends Repository
        {
            public function get($key, $default = null)
            {
                throw new \RuntimeException('secret-configuration-value');
            }
        };
        $r = (new OperationalReadinessInspector(new Container, $config))->report();
        self::assertFalse($r['activationAllowed']);
        self::assertSame('unknown', $r['runtime']['environment']);
        self::assertStringNotContainsString('secret-configuration-value',json_encode($r,JSON_THROW_ON_ERROR));
    }
}
