<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateSessionIdentity;
use App\Http\Middleware\EnforceCurrentAuthorization;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Support\GuardSessionLabHarness;
use Tests\Support\ResolveGuardLabResource;
use Tests\Support\SessionLabTransportUnavailable;
use Tests\Support\StatefulSessionLabBoundary;
use Tests\Support\StatefulSessionLabStart;
use Tests\TestCase;

final class SanctumFencedSessionActivationLabTest extends TestCase
{
    use GuardSessionLabHarness { setUp as private setUpGuardLab; }

    private TestHandler $logs;

    protected function setUp(): void
    {
        $this->setUpGuardLab();
        config(['app.debug' => false, 'sanctum.stateful' => ['localhost']]);
        $this->app->instance('stateful-lab.trace', (object) ['events' => []]);
        $this->logs = new TestHandler;
        Log::swap(new \Illuminate\Log\Logger(new Logger('stateful-lab', [$this->logs])));
        $this->app->make(Kernel::class)->prependMiddleware(StatefulSessionLabBoundary::class);
        StatefulSessionLabBoundary::registerReporting($this->app->make(ExceptionHandler::class));
        $this->app->bind(StartSession::class, fn ($app) => new StatefulSessionLabStart($app->make(SessionManager::class), $this->lab, fn () => $this->id()));
        config(['sanctum.middleware.authenticate_session' => 'stateful-lab.identity']);
        $this->app->bind('stateful-lab.identity', fn () => new class
        {
            public function handle(Request $request, \Closure $next): mixed
            {
                app('stateful-lab.trace')->events[] = 'identity';

                return app(AuthenticateSessionIdentity::class)->handle($request, $next);
            }
        });
        // Reuse fixture handlers only; the actual api group supplies Sanctum's full chain.
        $actions = [];
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), '_guard_lab/')) {
                $actions[basename($route->uri())] = $route->getAction('uses');
            }
        }
        Route::middleware('api')->group(function () use ($actions): void {
            foreach (['state', 'login', 'helper', 'native-logout'] as $name) {
                Route::match($name === 'state' ? ['GET'] : ['POST'], '/_stateful_lab/'.$name, function (Request $request) use ($actions, $name) {
                    app('stateful-lab.trace')->events[] = 'route';
                    $response = app()->call($actions[$name], ['request' => $request]);
                    if ($name === 'state') {
                        $response->setData($response->getData(true) + ['starts' => $request->attributes->get('lab.starts')]);
                    }

                    return $response;
                });
            }
            Route::middleware([ResolveGuardLabResource::class, EnforceCurrentAuthorization::class.':profile.read'])->get('/_stateful_lab/protected', fn () => response()->json(['authorized' => true]));
            Route::post('/_stateful_lab/fail-login', function (Request $request) {
                Auth::guard('web')->attempt(['_id' => $this->userId, 'password' => 'synthetic-guard-password']);
                throw new SessionLabTransportUnavailable('synthetic-private-driver-message '.$request->session()->getId());
            });
            Route::post('/_stateful_lab/controller-failure', function (Request $request) {
                Auth::guard('web')->attempt(['_id' => $this->userId, 'password' => 'synthetic-guard-password']);
                abort(500);
            });
        });
    }

    public function test_actual_first_party_chain_starts_once_before_guard_hydration_and_rehydrates_cookie(): void
    {
        [$id] = $this->statefulLogin();
        $this->getJson('/_stateful_lab/state', ['Origin' => 'http://localhost'])->assertOk()->assertJsonPath('web', $this->userId)->assertJsonPath('sanctum', $this->userId)->assertJsonPath('sameStore', true)->assertJsonPath('starts', 1);
        self::assertSame(['start', 'bound', 'identity', 'route'], app('stateful-lab.trace')->events);
        self::assertSame('', $this->lab->selectCollection('fenced_sessions')->findOne(['_id' => $id])['owner']);
    }

    public function test_real_csrf_cookie_web_path_also_uses_the_single_native_binding(): void
    {
        $response = $this->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN')->assertCookie('synlab_guard');
        $id = $response->getCookie('synlab_guard')->getValue();
        self::assertSame(['start', 'bound'], app('stateful-lab.trace')->events);
        self::assertNotEmpty($this->guardPayload($id)['_token']);
    }

    public function test_missing_and_forged_frontend_headers_do_not_hydrate_even_a_valid_cookie(): void
    {
        $this->statefulLogin();
        foreach ([[], ['Origin' => 'https://attacker.invalid'], ['Origin' => 'http://localhost.attacker.invalid']] as $headers) {
            $this->getJson('/_stateful_lab/protected', $headers)->assertUnauthorized();
            self::assertSame([], app('stateful-lab.trace')->events);
        }
    }

    public function test_referer_classifier_and_disabled_bearer_fallback_have_real_chain_evidence(): void
    {
        $this->getJson('/_stateful_lab/state', ['Referer' => 'http://localhost/portal'])->assertOk()->assertJsonPath('starts', 1)->assertJsonPath('web', null);
        $this->withToken('synthetic-bearer-disabled')->getJson('/_stateful_lab/protected', ['Origin' => 'http://localhost'])->assertUnauthorized();
    }

    public function test_missing_csrf_stops_before_identity_and_route_with_no_login_rotation(): void
    {
        [$id] = $this->statefulAnonymous();
        $this->postJson('/_stateful_lab/login', ['password' => 'synthetic-guard-password'], ['Origin' => 'http://localhost'])->assertStatus(419);
        self::assertSame(['start', 'bound'], app('stateful-lab.trace')->events);
        self::assertCount(1, $this->ids);
        self::assertArrayNotHasKey(Auth::guard('web')->getName(), $this->guardPayload($id));
    }

    public function test_helper_and_current_authorization_use_the_same_chain_and_native_logout_retires_cookie(): void
    {
        [$id, $token] = $this->statefulLogin();
        $this->postJson('/_stateful_lab/helper', ['_token' => $token], ['Origin' => 'http://localhost'])->assertNoContent();
        self::assertSame(7, $this->guardPayload($id)['guard_counter']);
        $this->getJson('/_stateful_lab/protected', ['Origin' => 'http://localhost'])->assertOk();
        $logout = $this->postJson('/_stateful_lab/native-logout', ['_token' => $token], ['Origin' => 'http://localhost'])->assertNoContent();
        self::assertNotSame($id, $logout->getCookie('synlab_guard')->getValue());
        $this->getJson('/_stateful_lab/state', ['Origin' => 'http://localhost'])->assertStatus(409);
    }

    public function test_typed_failure_after_guard_mutation_is_sanitized_in_body_logs_and_cookie_headers(): void
    {
        [, $token] = $this->statefulAnonymous();
        $response = $this->postJson('/_stateful_lab/fail-login', ['_token' => $token], ['Origin' => 'http://localhost'])->assertStatus(503)->assertJsonPath('code', 'session_transport_unavailable')->assertCookieMissing('synlab_guard')->assertCookieMissing('XSRF-TOKEN');
        $new = $this->ids[1];
        self::assertArrayNotHasKey(Auth::guard('web')->getName(), $this->guardPayload($new));
        $logged = json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        self::assertCount(1, $this->logs->getRecords());
        foreach (['synthetic-private-driver-message', $new, 'synthetic-guard-password'] as $secret) {
            self::assertStringNotContainsString($secret, $logged);
            self::assertStringNotContainsString($secret, $response->getContent());
        }
    }

    public function test_downstream_500_after_login_persists_no_guard_identity_or_success_cookies(): void
    {
        [, $token] = $this->statefulAnonymous();
        $this->postJson('/_stateful_lab/controller-failure', ['_token' => $token], ['Origin' => 'http://localhost'])->assertStatus(500)->assertCookieMissing('synlab_guard')->assertCookieMissing('XSRF-TOKEN');
        self::assertArrayNotHasKey(Auth::guard('web')->getName(), $this->guardPayload($this->ids[1]));
        self::assertFalse(Auth::guard('web')->check());
    }

    private function statefulAnonymous(): array
    {
        $response = $this->getJson('/_stateful_lab/state', ['Origin' => 'http://localhost'])->assertOk();
        $id = $response->getCookie('synlab_guard')->getValue();
        $this->withCookie('synlab_guard', $id);

        return [$id, $response->json('csrf')];
    }

    private function statefulLogin(): array
    {
        [, $token] = $this->statefulAnonymous();
        $response = $this->postJson('/_stateful_lab/login', ['_token' => $token, 'password' => 'synthetic-guard-password'], ['Origin' => 'http://localhost'])->assertNoContent();
        $id = $response->getCookie('synlab_guard')->getValue();
        $this->withCookie('synlab_guard', $id);

        return [$id, $this->guardPayload($id)['_token']];
    }
}
