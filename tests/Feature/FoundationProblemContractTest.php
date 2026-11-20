<?php

namespace Tests\Feature;

use App\Domain\Security\SecurityStateUnavailable;
use App\Infrastructure\Http\FoundationProblemResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class FoundationProblemContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $handler = new Handler($this->app);
        $this->app->instance(ExceptionHandler::class, $handler);
        $handler->reportable(fn (\Throwable $e) => false);
        $handler->renderable(fn (\Throwable $e, Request $r) => str_starts_with($r->path(), '_fnd07_lab/') ? FoundationProblemResponse::render($e) : null);
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter'])extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        Route::middleware('web')->post('/_fnd07_lab/action', fn () => response()->json(['accepted' => true], 200));
        RateLimiter::for('fnd07-lab', fn () => Limit::perMinute(1)->by('synthetic-contract'));
        Route::middleware('throttle:fnd07-lab')->get('/_fnd07_lab/limited', fn () => response()->json(['accepted' => true]));
    }

    public function test_real_csrf_rejection_is_safe_problem_and_valid_action_uses200(): void
    {
        $r = $this->postJson('/_fnd07_lab/action')->assertStatus(419)->assertHeader('Content-Type', 'application/problem+json')->assertJsonPath('code', 'csrf_mismatch');
        self::assertSame(['type', 'title', 'status', 'code', 'correlationId'], array_keys($r->json()));
        $this->withSession(['_token' => 'synthetic-token'])->postJson('/_fnd07_lab/action', ['_token' => 'synthetic-token'])->assertOk()->assertExactJson(['accepted' => true]);
    }

    public function test_real_throttle_produces_safe429_and_retry_after(): void
    {
        $this->getJson('/_fnd07_lab/limited')->assertOk();
        $r = $this->getJson('/_fnd07_lab/limited')->assertStatus(429)->assertHeader('Content-Type', 'application/problem+json')->assertJsonPath('code', 'throttled');
        self::assertGreaterThan(0, (int) $r->headers->get('Retry-After'));
    }

    public function test_raw_dependency_validation_and_unknown_errors_are_never_reflected(): void
    {
        foreach ([new SecurityStateUnavailable('synthetic-secret-dsn'), ValidationException::withMessages(['password' => 'synthetic-secret-value']), new \RuntimeException('synthetic-secret-token')] as $i => $e) {
            Route::get('/_fnd07_lab/error-'.$i, function () use ($e) {
                throw $e;
            });
            $r = $this->withHeader('X-Correlation-ID', 'synthetic-secret-header')->getJson('/_fnd07_lab/error-'.$i)->assertStatus([503, 422, 500][$i])->assertHeader('Content-Type', 'application/problem+json');
            self::assertStringNotContainsString('synthetic-secret', $r->getContent());
            self::assertTrue(Str::isUuid($r->json('correlationId')));
        }
    }

    public function test_untrusted_retry_header_is_not_reflected(): void
    {
        $r = FoundationProblemResponse::render(new HttpException(429, 'secret', null, ['Retry-After' => "secret\r\nInjected: true"]));
        self::assertFalse($r->headers->has('Retry-After'));
    }
}
