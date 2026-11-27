<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Identity;

use Illuminate\Http\Request;

/** Engineering slice predicate only. It grants no authentication, route binding or acceptance. */
final class IdentityApiScope
{
    private const OPERATIONS = [
        'POST /api/v1/auth/registrations',
        'POST /api/v1/auth/email-verifications',
        'POST /api/v1/auth/sessions/password',
        'POST /api/v1/auth/sessions/otp-challenges',
        'POST /api/v1/auth/sessions/otp-verifications',
        'DELETE /api/v1/auth/sessions/current',
        'GET /api/v1/me',
    ];

    public static function matches(Request $request): bool
    {
        return in_array($request->getMethod().' '.$request->getPathInfo(), self::OPERATIONS, true);
    }
}
