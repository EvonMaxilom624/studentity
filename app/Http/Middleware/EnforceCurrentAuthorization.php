<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Security\AccessDecision;
use App\Domain\Security\AccessDecisionService;
use App\Domain\Security\ResourceScope;
use App\Infrastructure\Security\AccessDecisionAuditRecorder;
use App\Infrastructure\Security\MongoAuthorizationRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnforceCurrentAuthorization
{
    public function __construct(private MongoAuthorizationRepository $repository, private AccessDecisionService $decisions, private AccessDecisionAuditRecorder $audit) {}

    public function handle(Request $request, Closure $next, string $ability = ''): Response
    {
        $resolved = $request->attributes->get('authorization.resource');
        $scope = $resolved instanceof ResourceScope ? $resolved : new ResourceScope('authorization');
        $context = null;
        try {
            $user = $request->user('sanctum');
            if ($user === null || ! $request->hasSession()) {
                $decision = new AccessDecision(false, 'unauthenticated', 401);
            } else {
                $context = $this->repository->context((string) $user->getAuthIdentifier(), hash('sha256', $request->session()->getId()));
                $decision = $context === null ? new AccessDecision(false, 'session_invalid', 401)
                    : ($resolved instanceof ResourceScope ? $this->decisions->decide($context, $scope, $ability, time()) : new AccessDecision(false, 'resource_scope_missing'));
            }
        } catch (Throwable $exception) {
            Log::warning('Authorization state could not be established.', ['exception_type' => $exception::class]);
            $decision = new AccessDecision(false, 'security_state_unavailable', 503);
        }
        $correlation = $request->attributes->get('authorization.correlation_id');
        $correlation = is_string($correlation) && Str::isUuid($correlation) ? $correlation : (string) Str::uuid();
        try {
            $this->audit->record($context, $scope, $ability, $decision, $correlation);
        } catch (Throwable $exception) {
            Log::warning('Authorization audit could not be recorded.', ['exception_type' => $exception::class]);
            $decision = new AccessDecision(false, 'audit_unavailable', 503);
        }
        if (! $decision->allowed) {
            $code = match ($decision->status) {
                401 => 'authentication_required', 503 => 'security_state_unavailable', default => 'access_denied'
            };

            return response()->json(['type' => 'about:blank', 'title' => $decision->status === 503 ? 'Security state unavailable' : 'Access denied', 'status' => $decision->status, 'code' => $code, 'correlationId' => $correlation], $decision->status, ['Content-Type' => 'application/problem+json']);
        }

        return $next($request);
    }
}
