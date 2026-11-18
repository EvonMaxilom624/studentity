<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Security\AccessDecision;
use App\Domain\Security\ResourceScope;
use App\Domain\Security\SecurityStateAuthority;
use App\Domain\Security\SecurityStateUnavailable;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MongoDB\Driver\Session;
use RuntimeException;
use Throwable;

final class SessionRevocationService
{
    public function __construct(private SecurityStateAuthority $authority, private MongoAuthorizationRepository $repository, private AccessDecisionAuditRecorder $audit, private Factory $auth) {}

    /** Call only from an authenticated, CSRF-protected handler. No public handler is enabled by this foundation. */
    public function revoke(Request $request): void
    {
        $failure = null;
        $guard = $this->auth->guard('web');
        try {
            $user = $guard->user();
            $digest = hash('sha256', $request->session()->getId());
            $context = $user === null ? null : $this->repository->context((string) $user->getAuthIdentifier(), $digest);
            if ($context === null) {
                throw new RuntimeException('Current security session is unavailable.');
            }
            // Anchor first: a later local commit failure cannot leave the independent authority granting access.
            $this->authority->revoke($context->userId, $digest);
            $correlation = $request->attributes->get('authorization.correlation_id');
            $correlation = is_string($correlation) && Str::isUuid($correlation) ? $correlation : (string) Str::uuid();
            $this->repository->revokeWithAudit($context->userId, $digest, function (Session $session) use ($context, $correlation): void {
                $this->audit->record($context, new ResourceScope('user_session', $context->sessionRecordId), 'session.revoke', new AccessDecision(true, 'revoked', 200), $correlation, $session);
            });
        } catch (Throwable $exception) {
            $failure = $exception;
        } finally {
            // A failed identity provider must not make logout() skip the independent cleanup attempts.
            foreach ([fn () => $guard->logout(), fn () => $guard->forgetUser(), fn () => $request->session()->invalidate(), fn () => $request->session()->regenerateToken()] as $cleanup) {
                try {
                    $cleanup();
                } catch (Throwable $exception) {
                    $failure ??= $exception;
                }
            }
        }
        if ($failure !== null) {
            Log::warning('Session revocation could not be confirmed.', ['exception_type' => $failure::class]);
            throw new SecurityStateUnavailable('Session revocation could not be confirmed.');
        }
    }
}
