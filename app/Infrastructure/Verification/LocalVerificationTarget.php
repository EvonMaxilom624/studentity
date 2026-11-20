<?php

declare(strict_types=1);

namespace App\Infrastructure\Verification;

/** Conservative initial URI preflight, not network isolation or deployment acceptance. */
final class LocalVerificationTarget
{
    public static function allows(mixed $dsn): bool
    {
        if (! is_string($dsn) || ! preg_match('~\Amongodb://(?:127\.0\.0\.1|localhost)(?::[1-9][0-9]{0,4})?/\?replicaSet=rs0(?:&directConnection=true)?\z~D', $dsn)) {
            return false;
        }
        try {
            $p = parse_url($dsn);

            return is_array($p) && (! isset($p['port']) || ($p['port'] > 0 && $p['port'] <= 65535));
        } catch (\Throwable) {
            return false;
        }
    }

    public static function allowsTopology(array $hello, string $selectedHost): bool
    {
        if (! in_array($selectedHost, ['127.0.0.1', 'localhost'], true) || ($hello['setName'] ?? null) !== 'rs0' || ($hello['isWritablePrimary'] ?? null) !== true || ! isset($hello['hosts']) || ! is_array($hello['hosts']) || $hello['hosts'] === []) {
            return false;
        }
        $members = [];
        foreach (['hosts', 'passives', 'arbiters'] as $k) {
            if (isset($hello[$k])) {
                if (! is_array($hello[$k])) {
                    return false;
                }$members = [...$members, ...$hello[$k]];
            }
        }foreach (['primary', 'me'] as $k) {
            if (isset($hello[$k])) {
                $members[] = $hello[$k];
            }
        }
        foreach ($members as $member) {
            if (! is_string($member) || ! preg_match('/\A(?:127\.0\.0\.1|localhost):[1-9][0-9]{0,4}\z/D', $member)) {
                return false;
            }
        }

        return true;
    }
}
