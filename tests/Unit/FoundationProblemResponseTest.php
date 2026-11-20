<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Security\SecurityStateUnavailable;
use App\Infrastructure\Http\FoundationProblemResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FoundationProblemResponseTest extends TestCase
{
    public function test_security_state_unavailable_maps_to_503_problem(): void
    {
        $response = FoundationProblemResponse::render(new SecurityStateUnavailable('synthetic-dsn'));

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(503, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $data = $response->getData(true);
        self::assertSame(['type', 'title', 'status', 'code', 'correlationId'], array_keys($data));
        self::assertSame('about:blank', $data['type']);
        self::assertSame('Security state unavailable', $data['title']);
        self::assertSame('security_state_unavailable', $data['code']);
        self::assertSame(503, $data['status']);
        self::assertTrue(Str::isUuid($data['correlationId']));
    }

    public function test_file_security_unavailable_maps_to_503_problem(): void
    {
        $response = FoundationProblemResponse::render(new FileSecurityUnavailable('synthetic-storage'));

        self::assertSame(503, $response->getStatusCode());
        $data = $response->getData(true);
        self::assertSame('security_state_unavailable', $data['code']);
        self::assertSame(503, $data['status']);
    }

    public function test_token_mismatch_maps_to_419_problem(): void
    {
        $response = FoundationProblemResponse::render(new TokenMismatchException);

        self::assertSame(419, $response->getStatusCode());
        $data = $response->getData(true);
        self::assertSame('csrf_mismatch', $data['code']);
        self::assertSame(419, $data['status']);
    }

    public function test_authentication_exception_maps_to_401_problem(): void
    {
        $response = FoundationProblemResponse::render(new AuthenticationException);

        self::assertSame(401, $response->getStatusCode());
        $data = $response->getData(true);
        self::assertSame('authentication_required', $data['code']);
        self::assertSame(401, $data['status']);
    }

    public function test_http_exception_maps_to_its_status_code(): void
    {
        $response = FoundationProblemResponse::render(new NotFoundHttpException);

        self::assertSame(404, $response->getStatusCode());
        $data = $response->getData(true);
        self::assertSame('not_found', $data['code']);
        self::assertSame(404, $data['status']);
    }

    public function test_unknown_exception_maps_to_500_problem(): void
    {
        $response = FoundationProblemResponse::render(new \RuntimeException('synthetic-unexpected'));

        self::assertSame(500, $response->getStatusCode());
        $data = $response->getData(true);
        self::assertSame('internal_error', $data['code']);
        self::assertSame(500, $data['status']);
    }

    public function test_429_retains_valid_integer_retry_after(): void
    {
        $error = new HttpException(429, '', null, ['Retry-After' => '17']);
        $response = FoundationProblemResponse::render($error);

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('17', $response->headers->get('Retry-After'));
        $data = $response->getData(true);
        self::assertSame('throttled', $data['code']);
    }

    public function test_429_rejects_invalid_retry_after(): void
    {
        $error = new HttpException(429, '', null, ['Retry-After' => 'synthetic-secret']);
        $response = FoundationProblemResponse::render($error);

        self::assertSame(429, $response->getStatusCode());
        self::assertFalse($response->headers->has('Retry-After'));
    }

    public function test_429_rejects_injection_retry_after(): void
    {
        $error = new HttpException(429, '', null, ['Retry-After' => "secret\r\nInjected: true"]);
        $response = FoundationProblemResponse::render($error);

        self::assertSame(429, $response->getStatusCode());
        self::assertFalse($response->headers->has('Retry-After'));
    }

    public function test_each_call_generates_fresh_correlation_id(): void
    {
        $error = new \RuntimeException('synthetic');
        $first = FoundationProblemResponse::render($error)->getData(true);
        $second = FoundationProblemResponse::render($error)->getData(true);

        self::assertTrue(Str::isUuid($first['correlationId']));
        self::assertTrue(Str::isUuid($second['correlationId']));
        self::assertNotSame($first['correlationId'], $second['correlationId']);
    }

    public function test_response_contains_no_sensitive_raw_details(): void
    {
        $response = FoundationProblemResponse::render(new \RuntimeException('sensitive-secret-message'));

        $content = $response->getContent();
        self::assertStringNotContainsString('sensitive-secret-message', $content);
        $data = $response->getData(true);
        self::assertNotSame('sensitive-secret-message', $data['title']);
        self::assertNotSame('sensitive-secret-message', $data['code']);
    }

    public function test_http_exception_interface_is_safe_type(): void
    {
        $error = new class(403) extends \RuntimeException implements HttpExceptionInterface
        {
            public function __construct(int $code)
            {
                parent::__construct('synthetic', $code);
            }

            public function getStatusCode(): int
            {
                return 403;
            }

            public function getHeaders(): array
            {
                return [];
            }
        };
        $response = FoundationProblemResponse::render($error);

        self::assertSame(403, $response->getStatusCode());
        $data = $response->getData(true);
        self::assertSame('permission_denied', $data['code']);
    }
}
