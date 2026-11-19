<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Governance\CurrentApprovalAuthority;
use App\Domain\Identifiers\SystemIdIssuer;
use App\Domain\Jobs\JobExecutionAuthority;
use App\Domain\Registration\RegistrationActivationAuthority;
use App\Domain\Security\SecurityStateAuthority;
use App\Infrastructure\Governance\UnavailableApprovalAuthority;
use App\Infrastructure\Identity\UnavailableSystemIdIssuer;
use App\Infrastructure\Jobs\UnavailableJobExecutionAuthority;
use App\Infrastructure\Registration\UnavailableRegistrationActivationAuthority;
use App\Infrastructure\Security\UnavailableSecurityStateAuthority;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Tests\TestCase;

final class OperationalReadinessCommandTest extends TestCase
{
    public function test_json_command_is_redacted_blocked_and_does_not_resolve_any_provider_or_database(): void
    {
        $calls = 0;
        foreach ([SecurityStateAuthority::class, CurrentApprovalAuthority::class, SystemIdIssuer::class, RegistrationActivationAuthority::class, JobExecutionAuthority::class, 'db', 'db.connection', 'mailer', 'queue', 'filesystem'] as $port) {
            $this->app->bind($port, function () use (&$calls) {
                $calls++;
                throw new \RuntimeException('secret-side-effect-provider');
            });
        }
        config(['app.key' => 'synthetic-secret-key', 'database.connections.mongodb.dsn' => 'synthetic-secret-dsn', 'mail.mailers.smtp.password' => 'synthetic-secret-password']);
        $exit = Artisan::call('studentity:readiness', ['--json' => true]);
        $output = Artisan::output();
        $r = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
        self::assertSame(1, $exit);
        self::assertSame(0, $calls);
        self::assertSame('blocked', $r['status']);
        self::assertFalse($r['activationAllowed']);
        foreach (['synthetic-secret-key', 'synthetic-secret-dsn', 'synthetic-secret-password', 'secret-side-effect-provider'] as $s) {
            self::assertStringNotContainsString($s, $output);
        }
        foreach ($r['ports'] as $p) {
            self::assertTrue($p['registered']);
            self::assertSame('unaccepted', $p['status']);
        }
    }

    public function test_text_command_names_blockers_and_explains_registration_is_not_acceptance(): void
    {
        self::assertSame(1, Artisan::call('studentity:readiness'));
        $output = Artisan::output();
        foreach (['BLOCKED', 'diagnostic only', 'trusted-clock', 'approved-notices', 'Registration is a local observation'] as $part) {
            self::assertStringContainsString($part, $output);
        }
    }

    public function test_command_rejects_activation_or_approval_options(): void
    {
        $kernel = $this->app->make(Kernel::class);
        $kernel->bootstrap();
        $definition = $kernel->all()['studentity:readiness']->getDefinition();
        self::assertTrue($definition->hasOption('json'));
        self::assertFalse($definition->hasOption('activate'));
        self::assertFalse($definition->hasOption('approve'));
        self::assertFalse($definition->hasOption('force'));
        $this->expectException(InvalidOptionException::class);
        Artisan::call('studentity:readiness', ['--activate' => true]);
    }

    public function test_default_runtime_ports_remain_unavailable_after_readiness_report(): void
    {
        self::assertSame(1, Artisan::call('studentity:readiness', ['--json' => true]));
        foreach ([SecurityStateAuthority::class => UnavailableSecurityStateAuthority::class, CurrentApprovalAuthority::class => UnavailableApprovalAuthority::class, SystemIdIssuer::class => UnavailableSystemIdIssuer::class, RegistrationActivationAuthority::class => UnavailableRegistrationActivationAuthority::class, JobExecutionAuthority::class => UnavailableJobExecutionAuthority::class] as $port => $expected) {
            self::assertInstanceOf($expected, $this->app->make($port));
        }
        self::assertSame('array', config('session.driver'));
        self::assertSame('sync',config('queue.default'));
    }
}
