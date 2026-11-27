<?php

namespace Tests\Feature;

use App\Infrastructure\Http\FoundationProblemBoundary;
use App\Infrastructure\Http\FoundationProblemIntegration;
use App\Infrastructure\Http\FoundationProblemResponses;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FoundationProblemBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $responses = new FoundationProblemResponses;
        $scope = fn (Request $r) => $r->is('_fnd07_boundary/*');
        FoundationProblemIntegration::register(new Exceptions($this->app->make(ExceptionHandler::class)), $scope, $responses);
        $this->app->instance(FoundationProblemBoundary::class, new FoundationProblemBoundary($responses, $scope));
    }

    public function test_router_caught_response_exception_and_direct_errors_are_normalized(): void
    {
        foreach ([400, 401, 403, 404, 409, 419, 422, 429, 500, 503] as $status) {
            foreach (['direct', 'throw'] as $kind) {
                Route::middleware(FoundationProblemBoundary::class)->get('/_fnd07_boundary/'.$kind.$status, function () use ($status, $kind) {
                    $response = response('synthetic-sensitive-body', $status, ['X-Secret' => 'synthetic-sensitive-header', 'Retry-After' => '17']);
                    if ($kind === 'throw') {
                        throw new HttpResponseException($response);
                    }

                    return $response;
                });
                $r = $this->getJson('/_fnd07_boundary/'.$kind.$status)->assertStatus($status)->assertHeader('Content-Type', 'application/problem+json')->assertHeader('Cache-Control', 'no-store, private');
                self::assertSame(['type', 'title', 'status', 'code', 'correlationId'], array_keys($r->json()));
                self::assertTrue(Str::isUuid($r->json('correlationId')));
                self::assertStringNotContainsString('synthetic-sensitive', $r->getContent());
                self::assertFalse($r->headers->has('X-Secret'));
                self::assertSame($status === 429 ? '17' : null, $r->headers->get('Retry-After'));
            }
        }
    }

    public function test_streamed_error_is_replaced_without_running_its_callback(): void
    {
        $calls = (object) ['count' => 0];
        Route::middleware(FoundationProblemBoundary::class)->get('/_fnd07_boundary/stream', fn () => response()->stream(function () use ($calls) {
            $calls->count++;
            echo 'synthetic-sensitive';
        }, 500));
        $this->getJson('/_fnd07_boundary/stream')->assertStatus(500)->assertJsonPath('code', 'internal_error');
        self::assertSame(0, $calls->count);
    }

    public function test_unknown_status_and_untrusted_retry_after_are_safe(): void
    {
        Route::middleware(FoundationProblemBoundary::class)->get('/_fnd07_boundary/unknown', fn () => response('synthetic-sensitive', 418));
        $this->getJson('/_fnd07_boundary/unknown')->assertStatus(500)->assertJsonPath('code', 'internal_error');
        Route::middleware(FoundationProblemBoundary::class)->get('/_fnd07_boundary/retry', fn () => response('synthetic-sensitive', 429, ['Retry-After' => 'synthetic-sensitive']));
        self::assertFalse($this->getJson('/_fnd07_boundary/retry')->assertStatus(429)->headers->has('Retry-After'));
    }

    public function test_handler_correlation_is_preserved_with_safe_reporting(): void
    {
        $records = [];
        Log::listen(function ($event) use (&$records) {
            $records[] = $event->context;
        });
        Route::middleware(FoundationProblemBoundary::class)->get('/_fnd07_boundary/error', function () {
            throw new \RuntimeException('synthetic-sensitive');
        });
        $r = $this->getJson('/_fnd07_boundary/error')->assertStatus(500)->assertJsonPath('code', 'internal_error');
        self::assertCount(1, $records);
        self::assertSame($records[0]['correlationId'], $r->json('correlationId'));
        self::assertStringNotContainsString('synthetic-sensitive', json_encode($records));
    }

    public function test_success_and_outside_scope_responses_are_unchanged(): void
    {
        Route::middleware(FoundationProblemBoundary::class)->get('/_fnd07_boundary/ok', fn () => response()->json(['accepted' => true], 200, ['X-Application' => 'retained']));
        $this->getJson('/_fnd07_boundary/ok')->assertOk()->assertExactJson(['accepted' => true])->assertHeader('X-Application', 'retained');
        Route::middleware(FoundationProblemBoundary::class)->get('/_outside_boundary/error', fn () => response('synthetic-outside', 422, ['X-Application' => 'retained']));
        $this->get('/_outside_boundary/error')->assertStatus(422)->assertContent('synthetic-outside')->assertHeader('X-Application', 'retained');
    }

    public function test_inner_middleware_error_is_sanitized_without_entering_action(): void
    {
        $calls = (object) ['count' => 0];
        $this->app->instance('fnd07.direct', new class
        {
            public function handle($request, $next)
            {
                return response('synthetic-sensitive-middleware', 403, ['X-Secret' => 'synthetic-sensitive']);
            }
        });
        Route::middleware([FoundationProblemBoundary::class, 'fnd07.direct'])->get('/_fnd07_boundary/middleware', function () use ($calls) {
            $calls->count++;

            return response('must-not-enter');
        });
        $r = $this->getJson('/_fnd07_boundary/middleware')->assertStatus(403)->assertJsonPath('code', 'permission_denied');
        self::assertSame(0, $calls->count);
        self::assertStringNotContainsString('synthetic-sensitive', $r->getContent());
        self::assertFalse($r->headers->has('X-Secret'));
    }

    public function test_untrusted_problem_and_correlation_are_regenerated(): void
    {
        $forged = (string) Str::uuid();
        Route::middleware(FoundationProblemBoundary::class)->get('/_fnd07_boundary/forged', fn () => response()->json(['title' => 'synthetic-sensitive', 'code' => 'synthetic-sensitive', 'correlationId' => $forged], 500));
        $r = $this->withHeader('X-Correlation-ID', $forged)->getJson('/_fnd07_boundary/forged')->assertStatus(500)->assertJsonPath('code', 'internal_error');
        self::assertNotSame($forged, $r->json('correlationId'));
        self::assertStringNotContainsString('synthetic-sensitive', $r->getContent());
    }

    public function test_repeated_reporting_after_mutation_is_safe_and_requests_get_distinct_correlations(): void
    {
        $records = [];
        Log::listen(function ($event) use (&$records) {
            $records[] = $event->context;
        });
        $error = new \RuntimeException('synthetic-sensitive');
        $handler = $this->app->make(ExceptionHandler::class);
        $this->app->instance('fnd07.mutate', new class($error, $handler)
        {
            public function __construct(private $error, private $handler) {}

            public function handle($request, $next)
            {
                $response = $next($request);
                $response->setData(['code' => 'synthetic-sensitive', 'status' => 'synthetic-sensitive', 'correlationId' => 'synthetic-sensitive']);
                $this->handler->report($this->error);

                return $response;
            }
        });
        Route::middleware([FoundationProblemBoundary::class, 'fnd07.mutate'])->get('/_fnd07_boundary/mutate', function () use ($error) {
            throw $error;
        });
        $first = $this->getJson('/_fnd07_boundary/mutate')->assertStatus(500)->assertJsonPath('code', 'internal_error');
        $second = $this->getJson('/_fnd07_boundary/mutate')->assertStatus(500)->assertJsonPath('code', 'internal_error');
        self::assertCount(4, $records);
        self::assertSame($first->json('correlationId'), $records[0]['correlationId']);
        self::assertSame($first->json('correlationId'), $records[1]['correlationId']);
        self::assertSame($second->json('correlationId'), $records[2]['correlationId']);
        self::assertSame($second->json('correlationId'), $records[3]['correlationId']);
        self::assertNotSame($first->json('correlationId'), $second->json('correlationId'));
        self::assertStringNotContainsString('synthetic-sensitive', json_encode($records));
    }
}
