<?php

namespace Tests\Feature;

use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Security\SecurityStateUnavailable;
use App\Infrastructure\Http\FoundationProblemIntegration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class FoundationProblemIntegrationTest extends TestCase
{
    public function test_real_csrf_and_throttle_middleware_use_safe_finalized_problems(): void
    {
        FoundationProblemIntegration::register(new Exceptions($this->app->make(ExceptionHandler::class)), fn (Request $r) => $r->is('_fnd07_integration/*'));
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        Route::middleware('web')->post('/_fnd07_integration/action', fn () => response()->json(['accepted' => true]));
        $this->postJson('/_fnd07_integration/action')->assertStatus(419)->assertJsonPath('code', 'csrf_mismatch')->assertHeader('Cache-Control', 'no-store, private');
        $this->withSession(['_token' => 'synthetic-token'])->postJson('/_fnd07_integration/action', ['_token' => 'synthetic-token'])->assertOk();
        RateLimiter::for('fnd07-integration', fn () => Limit::perMinute(1)->by('synthetic-integration'));
        Route::middleware('throttle:fnd07-integration')->get('/_fnd07_integration/limited', fn () => response()->json(['accepted' => true]));
        $this->getJson('/_fnd07_integration/limited')->assertOk();
        $r = $this->getJson('/_fnd07_integration/limited')->assertStatus(429)->assertJsonPath('code', 'throttled');
        self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', $r->headers->get('Retry-After'));
    }

    public function test_exception_owned_response_and_validation_cannot_reflect_sensitive_details(): void
    {
        FoundationProblemIntegration::register(new Exceptions($this->app->make(ExceptionHandler::class)), fn (Request $r) => $r->is('_fnd07_integration/*'));
        Route::get('/_fnd07_integration/validation', function () {
            throw ValidationException::withMessages(['password' => 'synthetic-sensitive']);
        });
        $this->getJson('/_fnd07_integration/validation')->assertStatus(422)->assertJsonMissingPath('errors')->assertJsonPath('code', 'validation_failed');
        Route::get('/_fnd07_integration/owned', function () {
            throw new class('synthetic-sensitive') extends \RuntimeException
            {
                public function render($request)
                {
                    return response('synthetic-sensitive', 500, ['X-Secret' => 'synthetic-sensitive']);
                }
            };
        });
        $r = $this->getJson('/_fnd07_integration/owned')->assertStatus(500)->assertJsonPath('code', 'internal_error');
        self::assertStringNotContainsString('synthetic-sensitive', $r->getContent());
        self::assertFalse($r->headers->has('X-Secret'));
    }

    public function test_configured_handler_is_finalized_safely_without_replacing_existing_handler(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        FoundationProblemIntegration::register(new Exceptions($handler), fn (Request $r) => $r->is('_fnd07_integration/*'));
        self::assertSame($handler, $this->app->make(ExceptionHandler::class));
        Route::get('/_fnd07_integration/security', function () {
            throw new SecurityStateUnavailable('synthetic-sensitive-dsn');
        });
        $r = $this->getJson('/_fnd07_integration/security')->assertStatus(503)->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Content-Type', 'application/problem+json');
        self::assertSame(['type', 'title', 'status', 'code', 'correlationId'], array_keys($r->json()));
        self::assertStringNotContainsString('synthetic-sensitive', $r->getContent());
        self::assertSame('security_state_unavailable', $r->json('code'));
    }

    public function test_private_file_dependency_failure_is_unavailable_without_raw_detail(): void
    {
        FoundationProblemIntegration::register(new Exceptions($this->app->make(ExceptionHandler::class)), fn (Request $r) => $r->is('_fnd07_integration/*'));
        Route::get('/_fnd07_integration/file', function () {
            throw new FileSecurityUnavailable('synthetic-sensitive-storage');
        });
        $r = $this->getJson('/_fnd07_integration/file')->assertStatus(503)->assertJsonPath('code', 'security_state_unavailable');
        self::assertStringNotContainsString('synthetic-sensitive', $r->getContent());
    }

    public function test_safe_reporting_excludes_exception_and_request_content_and_shares_correlation(): void
    {
        $records = [];
        Log::listen(function ($event) use (&$records) {
            $records[] = [$event->message, $event->context];
        });
        FoundationProblemIntegration::register(new Exceptions($this->app->make(ExceptionHandler::class)), fn (Request $r) => $r->is('_fnd07_integration/*'));
        Route::post('/_fnd07_integration/error', function () {
            throw new \RuntimeException('synthetic-sensitive-exception');
        });
        $r = $this->postJson('/_fnd07_integration/error?secret=synthetic-sensitive-query', ['password' => 'synthetic-sensitive-body'])->assertStatus(500);
        $wire = json_encode($records, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('synthetic-sensitive', $wire);
        self::assertCount(1, $records);
        self::assertSame(['code' => 'internal_error', 'status' => 500, 'correlationId' => $r->json('correlationId')], $records[0][1]);
    }

    public function test_outside_scope_retains_existing_renderer_and_success_is_unchanged(): void
    {
        FoundationProblemIntegration::register(new Exceptions($this->app->make(ExceptionHandler::class)), fn (Request $r) => $r->is('_fnd07_integration/*'));
        Route::get('/_fnd07_unbound/security', function () {
            throw new SecurityStateUnavailable('synthetic');
        });
        $r = $this->getJson('/_fnd07_unbound/security')->assertStatus(503);
        self::assertNotSame('no-store, private', $r->headers->get('Cache-Control'));
        Route::get('/_fnd07_integration/ok', fn () => response()->json(['accepted' => true]));
        $this->getJson('/_fnd07_integration/ok')->assertOk()->assertExactJson(['accepted' => true]);
    }
}
