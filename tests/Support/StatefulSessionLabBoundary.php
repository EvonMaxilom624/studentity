<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Outer test-only boundary; no config/provider registration in the application. */
final class StatefulSessionLabBoundary
{
    public static function registerReporting(Handler $handler): void
    {
        $handler->reportable(static function (SessionLabTransportUnavailable $exception): bool {
            Log::warning('Session transport unavailable.', ['exception_type' => $exception::class, 'code' => 'session_transport_unavailable']);

            return false; // Routing\Pipeline must not log the original message or trace.
        });
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('testing')) {
            throw new \LogicException('Stateful session lab requires testing.');
        }
        app('stateful-lab.trace')->events = [];
        app('auth')->forgetGuards();
        app()->forgetInstance('auth.driver');
        try {
            $response = $next($request);
            if (($response->exception ?? null) instanceof SessionLabTransportUnavailable) {
                $response = response()->json(['type' => 'about:blank', 'title' => 'Session unavailable', 'status' => 503, 'code' => 'session_transport_unavailable', 'correlationId' => (string) Str::uuid()], 503, ['Content-Type' => 'application/problem+json']);
            }
            if ($response->getStatusCode() >= 500) {
                foreach ($response->headers->getCookies() as $cookie) {
                    if (in_array($cookie->getName(), [config('session.cookie'), 'XSRF-TOKEN'], true)) {
                        $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
                    }
                }
            }

            return $response;
        } finally {
            app('auth')->forgetGuards();
            app()->forgetInstance('auth.driver');
        }
    }
}
