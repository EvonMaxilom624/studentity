<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Explicit opt-in adapter. Not registered in bootstrap; scope/DTO acceptance remains mandatory. */
final class FoundationProblemIntegration
{
    public static function register(Exceptions $exceptions, \Closure $scope, ?FoundationProblemResponses $responses = null): void
    {
        $responses ??= new FoundationProblemResponses;
        $safe = function (\Throwable $error) use ($responses) {
            return $responses->forException($error, request());
        };
        $exceptions->report(function (\Throwable $error) use ($scope, $safe) {
            if ($scope(request()) !== true) {
                return null;
            }
            $body = $safe($error)->getData(true);
            Log::error('Studentity request failed', ['code' => $body['code'], 'status' => $body['status'], 'correlationId' => $body['correlationId']]);

            return false;
        });
        // Finalization also covers earlier render callbacks and exception-owned responses.
        $exceptions->respond(function (Response $response, \Throwable $error, Request $request) use ($scope, $safe): Response {
            return $scope($request) === true ? $safe($error) : $response;
        });
    }
}
