<?php

declare(strict_types=1);

namespace App\Infrastructure\Verification;

/** Local execution policy only; strict results never accept a CI host or release. */
final readonly class VerificationProfile
{
    private function __construct(public bool $strict, public bool $build, public bool $preflightOnly) {}

    public static function fromArguments(array $arguments): self
    {
        if (array_diff($arguments, ['--build', '--preflight-only', '--ci-candidate']) || count(array_unique($arguments)) !== count($arguments)) {
            throw new \InvalidArgumentException('Unsupported or duplicate verification option.');
        }
        $strict = in_array('--ci-candidate', $arguments, true);
        $preflight = in_array('--preflight-only', $arguments, true);
        if ($strict && $preflight) {
            throw new \InvalidArgumentException('Strict candidate requires the complete verification sequence.');
        }

        return new self($strict, $strict || in_array('--build', $arguments, true), $preflight);
    }

    public function phpunitArguments(): array
    {
        return $this->strict ? ['--colors=never', '--fail-on-all-issues', '--fail-on-empty-test-suite'] : ['--colors=never'];
    }
}
