<?php

use App\Domain\Security\SecurityStateUnavailable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (SecurityStateUnavailable $exception, Request $request) {
            $correlation = $request->attributes->get('authorization.correlation_id');
            $correlation = is_string($correlation) && Str::isUuid($correlation) ? $correlation : (string) Str::uuid();

            return response()->json(['type' => 'about:blank', 'title' => 'Security state unavailable', 'status' => 503, 'code' => 'security_state_unavailable', 'correlationId' => $correlation], 503, ['Content-Type' => 'application/problem+json']);
        });
    })->create();
