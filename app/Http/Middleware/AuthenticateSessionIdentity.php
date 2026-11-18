<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Security\SecurityStateUnavailable;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Bound only around user resolution: do not remap unrelated downstream HTTP/application failures. */
final class AuthenticateSessionIdentity extends AuthenticateSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $resolve = $request->getUserResolver();
        $request->setUserResolver(static function (?string $guard = null) use ($request, $resolve) {
            try {
                return $resolve($guard);
            } catch (AuthenticationException|SecurityStateUnavailable $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $correlation = $request->attributes->get('authorization.correlation_id');
                $correlation = is_string($correlation) && Str::isUuid($correlation) ? $correlation : (string) Str::uuid();
                $request->attributes->set('authorization.correlation_id', $correlation);
                Log::warning('Session identity could not be established.', ['exception_type' => $exception::class, 'correlation_id' => $correlation]);
                throw new SecurityStateUnavailable('Session identity could not be established.');
            }
        });

        return parent::handle($request, $next);
    }
}
