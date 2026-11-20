<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Opt-in outer response middleware; unregistered until API scope/composition review. */
final readonly class FoundationProblemBoundary
{
    public function __construct(private FoundationProblemResponses $responses, private \Closure $scope) {}

    public function handle(Request $request, \Closure $next): Response
    {
        $inside = ($this->scope)($request) === true;
        $response = $next($request);

        return $inside ? $this->responses->normalize($response) : $response;
    }
}
