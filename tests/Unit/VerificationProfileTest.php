<?php

namespace Tests\Unit;

use App\Infrastructure\Verification\VerificationProfile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class VerificationProfileTest extends TestCase
{
    public function test_strict_candidate_requires_build_and_cannot_be_reduced_to_preflight(): void
    {
        $profile = VerificationProfile::fromArguments(['--ci-candidate']);
        self::assertTrue($profile->strict);
        self::assertTrue($profile->build);
        self::assertFalse($profile->preflightOnly);
        $this->expectException(\InvalidArgumentException::class);
        VerificationProfile::fromArguments(['--ci-candidate', '--preflight-only']);
    }

    public function test_default_and_explicit_build_preserve_local_behavior(): void
    {
        self::assertFalse(VerificationProfile::fromArguments([])->strict);
        self::assertFalse(VerificationProfile::fromArguments([])->build);
        self::assertTrue(VerificationProfile::fromArguments(['--build'])->build);
        self::assertTrue(VerificationProfile::fromArguments(['--preflight-only'])->preflightOnly);
    }

    public function test_unknown_and_duplicate_options_are_rejected(): void
    {
        foreach ([['--deploy'], ['--ci-candidate', '--ci-candidate'], ['--build', '--build']] as $args) {
            try {
                VerificationProfile::fromArguments($args);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);

                continue;
            }
            self::fail('Ambiguous or unsupported profile accepted.');
        }
    }

    public static function issues(): array
    {
        return [
            'skipped' => ["self::markTestSkipped('synthetic skip');"],
            'incomplete' => ["self::markTestIncomplete('synthetic incomplete');"],
            'risky' => ['// intentionally no assertions'],
            'warning' => ["trigger_error('synthetic warning', E_USER_WARNING); self::assertTrue(true);"],
            'notice' => ["trigger_error('synthetic notice', E_USER_NOTICE); self::assertTrue(true);"],
            'deprecation' => ["trigger_error('synthetic deprecation', E_USER_DEPRECATED); self::assertTrue(true);"],
        ];
    }

    public function test_actual_strict_phpunit_accepts_clean_suite_and_rejects_empty_selection(): void
    {
        $root = dirname(__DIR__, 2);
        $dir = $root.'/storage/app/private/verification-profile-fixtures/'.bin2hex(random_bytes(8));
        mkdir($dir, 0700, true);
        $file = $dir.'/SyntheticCleanTest.php';
        file_put_contents($file, '<?php final class SyntheticCleanTest extends \PHPUnit\Framework\TestCase { public function test_clean(): void { self::assertTrue(true); } }');
        foreach ([false, true] as $empty) {
            $args = [PHP_BINARY, $root.'/vendor/bin/phpunit', '--no-configuration', '--bootstrap', $root.'/vendor/autoload.php', ...VerificationProfile::fromArguments(['--ci-candidate'])->phpunitArguments()];
            if ($empty) {
                array_push($args, '--filter', 'NoMatchingSyntheticTest');
            }
            $args[] = $file;
            $p = new Process($args, $root);
            $p->setTimeout(20);
            $p->run();
            self::assertSame($empty ? 1 : 0, $p->getExitCode(), $p->getOutput().$p->getErrorOutput());
        }
    }

    #[DataProvider('issues')]
    public function test_actual_phpunit_issues_fail_strict_candidate_but_not_default(string $body): void
    {
        $root = dirname(__DIR__, 2);
        $dir = $root.'/storage/app/private/verification-profile-fixtures/'.bin2hex(random_bytes(8));
        mkdir($dir, 0700, true);
        $file = $dir.'/SyntheticOutcomeTest.php';
        file_put_contents($file, "<?php\nfinal class SyntheticOutcomeTest extends \\PHPUnit\\Framework\\TestCase { public function test_outcome(): void {\n".$body."\n} }\n");
        foreach ([false, true] as $strict) {
            $profile = VerificationProfile::fromArguments($strict ? ['--ci-candidate'] : []);
            $p = new Process([PHP_BINARY, $root.'/vendor/bin/phpunit', '--no-configuration', '--bootstrap', $root.'/vendor/autoload.php', ...$profile->phpunitArguments(), $file], $root);
            $p->setTimeout(20);
            $p->run();
            self::assertSame($strict ? 1 : 0, $p->getExitCode(), $p->getOutput().$p->getErrorOutput());
        }
    }
}
