<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Explicitly shared handler/boundary registry; only fixed safe payloads are retained. */
final class FoundationProblemResponses
{
    private \WeakMap $errors;

    private \WeakMap $sealed;

    private \WeakMap $contexts;

    public function __construct()
    {
        $this->errors = new \WeakMap;
        $this->sealed = new \WeakMap;
        $this->contexts = new \WeakMap;
    }

    public function forException(\Throwable $error, ?object $context = null): JsonResponse
    {
        $errors = $context === null ? $this->errors : ($this->contexts[$context] ??= new \WeakMap);
        if (! isset($errors[$error])) {
            $errors[$error] = FoundationProblemResponse::render($error);
        }

        return $this->seal(clone $errors[$error]);
    }

    private function seal(JsonResponse $response): JsonResponse
    {
        $this->sealed[$response] = clone $response;

        return $response;
    }

    public function normalize(Response $response): Response
    {
        if (isset($this->sealed[$response])) {
            return $this->seal(clone $this->sealed[$response]);
        }
        if ($response->getStatusCode() < 400) {
            return $response;
        }

        // Do not inspect a body, run a stream, or copy arbitrary error headers/cookies.
        return $this->seal(FoundationProblemResponse::render(new HttpException($response->getStatusCode(), '', null, ['Retry-After' => $response->headers->get('Retry-After')])));
    }
}
