<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Security\SecurityStateUnavailable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Unbound response factory; runtime activation/reporting rules require separate API review. */
final class FoundationProblemResponse
{
    public static function render(\Throwable $error): JsonResponse
    {
        $status = match (true) {
            $error instanceof SecurityStateUnavailable, $error instanceof FileSecurityUnavailable => 503,$error instanceof TokenMismatchException => 419,$error instanceof AuthenticationException => 401,$error instanceof ValidationException => 422,$error instanceof HttpExceptionInterface => $error->getStatusCode(),default => 500
        };
        $map = [400 => ['Malformed request', 'bad_request'], 409 => ['Conflict', 'conflict'], 401 => ['Authentication required', 'authentication_required'], 403 => ['Permission denied', 'permission_denied'], 404 => ['Not found', 'not_found'], 419 => ['CSRF validation failed', 'csrf_mismatch'], 422 => ['Validation failed', 'validation_failed'], 429 => ['Too many requests', 'throttled'], 503 => ['Security state unavailable', 'security_state_unavailable'], 500 => ['Internal server error', 'internal_error']];
        if (! isset($map[$status])) {
            $status = 500;
        }[$title,$code] = $map[$status];
        $headers = ['Content-Type' => 'application/problem+json', 'Cache-Control' => 'no-store'];
        if ($status === 429 && $error instanceof HttpExceptionInterface) {
            $value = $error->getHeaders()['Retry-After'] ?? null;
            if ((is_int($value) || is_string($value)) && preg_match('/\A(?:0|[1-9][0-9]*)\z/D', (string) $value) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
                $headers['Retry-After'] = (string) $value;
            }
        }

        return new JsonResponse(['type' => 'about:blank', 'title' => $title, 'status' => $status, 'code' => $code, 'correlationId' => (string) Str::uuid()], $status, $headers);
    }
}
