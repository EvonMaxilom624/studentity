<?php

use App\Http\Middleware\AuthenticateSessionIdentity;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

return [
    // Exact first-party hosts only. Configure the approved deployment host explicitly before release.
    'stateful' => array_filter(explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:8000,127.0.0.1,127.0.0.1:8000'))),
    'guard' => ['web'],
    'expiration' => null,
    'middleware' => [
        'authenticate_session' => AuthenticateSessionIdentity::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => PreventRequestForgery::class,
    ],
];
