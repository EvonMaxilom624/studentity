<?php

namespace Tests\Unit;

use App\Infrastructure\Http\FoundationProblemResponses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class FoundationProblemResponsesTest extends TestCase
{
    public function test_repeated_boundaries_keep_the_same_server_correlation(): void
    {
        $responses = new FoundationProblemResponses;
        $first = $responses->normalize(new Response('synthetic-sensitive', 403));
        $second = $responses->normalize($first);
        self::assertSame($first->getContent(), $second->getContent());
    }

    public function test_downstream_mutation_cannot_replace_sealed_handler_error_with_success_or_secrets(): void
    {
        $responses = new FoundationProblemResponses;
        $response = $responses->forException(new \RuntimeException('synthetic-sensitive'));
        $original = $response->getContent();
        $response->setData(['detail' => 'synthetic-sensitive']);
        $response->setStatusCode(200);
        $response->headers->set('X-Secret', 'synthetic-sensitive');
        $safe = $responses->normalize($response);
        self::assertSame(500, $safe->getStatusCode());
        self::assertSame($original, $safe->getContent());
        self::assertFalse($safe->headers->has('X-Secret'));
    }

    public function test_mutated_response_cannot_poison_later_reporting_for_the_same_exception(): void
    {
        $responses = new FoundationProblemResponses;
        $error = new \RuntimeException('synthetic-sensitive');
        $first = $responses->forException($error);
        $original = $first->getContent();
        $first->setData(['code' => 'synthetic-sensitive', 'status' => 'synthetic-sensitive', 'correlationId' => 'synthetic-sensitive']);
        self::assertSame($original, $responses->forException($error)->getContent());
    }

    public function test_reused_exception_has_fresh_correlation_in_a_different_request_context(): void
    {
        $responses = new FoundationProblemResponses;
        $error = new \RuntimeException('synthetic-sensitive');
        $firstRequest = new \stdClass;
        $secondRequest = new \stdClass;
        $first = $responses->forException($error, $firstRequest)->getData(true);
        self::assertSame($first, $responses->forException($error, $firstRequest)->getData(true));
        self::assertNotSame($first['correlationId'], $responses->forException($error, $secondRequest)->getData(true)['correlationId']);
    }
}
