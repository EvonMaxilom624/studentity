<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use Tests\Support\FencedHttpLabMiddleware;
use Tests\Support\FencedSessionLabStore;
use Tests\Support\SessionLabHarness;
use Tests\TestCase;

/** In-process HTTP kernel requests with real encrypted cookies and isolated MongoDB storage. */
final class SharedMongoSessionHttpLabTest extends TestCase
{
    use SessionLabHarness { setUp as private setUpLab; }

    private Database $backend;

    private bool $failIssuance = false;

    private string $passwordHash;

    protected function setUp(): void
    {
        $this->setUpLab();
        $this->withCredentials();
        $this->backend = $this->lab;
        $this->passwordHash = Hash::make('synthetic-lab-password');
        config(['session.cookie' => 'synlab_http', 'session.lottery' => [0, 100], 'session.expire_on_close' => true]);
        $this->app->bind(FencedHttpLabMiddleware::class, fn ($app) => new FencedHttpLabMiddleware(
            $app->make(SessionManager::class), $this->backend, function (): string {
                $id = $this->id();
                if ($this->failIssuance) {
                    $this->unavailableDatabase()->command(['ping' => 1]);
                }

                return $id;
            },
        ));
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $middleware = [EncryptCookies::class, AddQueuedCookiesToResponse::class, FencedHttpLabMiddleware::class, PreventRequestForgery::class];
        Route::middleware($middleware)->group(function (): void {
            Route::get('/_session_lab/state', fn (Request $request) => response()->json([
                'identified' => $request->session()->get('synthetic_principal') === 'fixture',
                'counter' => $request->session()->get('counter', 0),
                'csrf' => $request->session()->token(),
            ]));
            Route::post('/_session_lab/login', function (Request $request) {
                if (! Hash::check((string) $request->input('password'), $this->passwordHash)) {
                    return response()->json(['code' => 'invalid_credentials'], 401);
                }
                $request->session()->regenerate();
                $request->session()->put('synthetic_principal', 'fixture');

                return response()->noContent();
            });
            Route::post('/_session_lab/logout', function (Request $request) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return response()->noContent();
            });
            Route::post('/_session_lab/probe/{mode}', function (Request $request, string $mode) {
                $request->session()->put('counter', 1);
                if (in_array($mode, ['takeover-save', 'takeover-rotate', 'expired'], true)) {
                    $this->rows()->updateOne(['_id' => $request->session()->getId()], ['$set' => ['lease_until' => new UTCDateTime(0)]]);
                    if ($mode !== 'expired') {
                        $successor = new FencedSessionLabStore($this->lab);
                        $successor->read($request->session()->getId());
                        $successor->write($request->session()->getId(), '{"counter":99}');
                        $successor->close();
                    }
                }
                if ($mode === 'takeover-rotate' || $mode === 'failed-issue') {
                    $this->failIssuance = $mode === 'failed-issue';
                    $request->session()->regenerate(true);
                }
                if ($mode === 'server-error') {
                    return response()->json(['code' => 'synthetic_failure'], 500);
                }
                if ($mode === 'not-found') {
                    abort(404);
                }

                return response()->noContent();
            });
        });
    }

    public function test_anonymous_cookie_is_encrypted_and_rehydrates_persisted_json_on_another_request(): void
    {
        $response = $this->getJson('/_session_lab/state')->assertOk()->assertJsonPath('identified', false);
        $id = $this->cookieId($response);
        self::assertNotSame($id, $response->getCookie('synlab_http', false)->getValue());
        self::assertTrue($response->getCookie('synlab_http')->isHttpOnly());
        self::assertSame('lax', $response->getCookie('synlab_http')->getSameSite());
        self::assertSame($response->json('csrf'), $this->payload($id)['_token']);
        $this->withCookie('synlab_http', $id)->getJson('/_session_lab/state')->assertOk()->assertJsonPath('csrf', $response->json('csrf'));
        self::assertCount(1, $this->ids);
        self::assertSame('', $this->rows()->findOne(['_id' => $id])['owner']);
    }

    public function test_wrong_password_does_not_rotate_or_identify_the_anonymous_session(): void
    {
        [$id, $token] = $this->anonymous();
        $response = $this->postJson('/_session_lab/login', ['password' => 'wrong', '_token' => $token])->assertUnauthorized();
        self::assertSame($id, $this->cookieId($response));
        self::assertArrayNotHasKey('synthetic_principal', $this->payload($id));
        self::assertCount(1, $this->ids);
    }

    public function test_verified_password_rotates_id_and_csrf_and_tombstones_the_fixation_cookie(): void
    {
        [$old, $token] = $this->anonymous();
        $response = $this->postJson('/_session_lab/login', ['password' => 'synthetic-lab-password', '_token' => $token])->assertNoContent();
        $new = $this->cookieId($response);
        self::assertNotSame($old, $new);
        self::assertNotSame($token, $this->payload($new)['_token']);
        self::assertTrue($this->rows()->findOne(['_id' => $old])['revoked']);
        self::assertArrayNotHasKey('payload', (array) $this->rows()->findOne(['_id' => $old]));
        $this->withCookie('synlab_http', $new)->getJson('/_session_lab/state')->assertOk()->assertJsonPath('identified', true);
        $this->withCookie('synlab_http', $old)->getJson('/_session_lab/state')->assertStatus(409)->assertCookieMissing('synlab_http');
    }

    public function test_logout_persists_a_new_anonymous_session_and_rejects_the_previous_cookie(): void
    {
        [, $token] = $this->anonymous();
        $login = $this->postJson('/_session_lab/login', ['password' => 'synthetic-lab-password', '_token' => $token])->assertNoContent();
        $old = $this->cookieId($login);
        $token = $this->payload($old)['_token'];
        $logout = $this->withCookie('synlab_http', $old)->postJson('/_session_lab/logout', ['_token' => $token])->assertNoContent();
        $new = $this->cookieId($logout);
        self::assertNotSame($old, $new);
        self::assertNotSame($token, $this->payload($new)['_token']);
        self::assertArrayNotHasKey('synthetic_principal', $this->payload($new));
        $this->withCookie('synlab_http', $new)->getJson('/_session_lab/state')->assertOk()->assertJsonPath('identified', false);
        $this->withCookie('synlab_http', $old)->getJson('/_session_lab/state')->assertStatus(409)->assertCookieMissing('synlab_http');
    }

    public function test_unknown_and_malformed_client_ids_are_never_issued_or_adopted(): void
    {
        $unknown = $this->id();
        foreach ([$unknown, 'invalid-client-id'] as $id) {
            $this->withCookie('synlab_http', $id)->getJson('/_session_lab/state')->assertStatus(409)->assertCookieMissing('synlab_http');
        }
        self::assertNull($this->rows()->findOne(['_id' => $unknown]));
        self::assertCount(1, $this->ids);
    }

    public function test_active_csrf_blocks_missing_and_wrong_tokens_before_credential_rotation(): void
    {
        [$id, $token] = $this->anonymous();
        foreach ([[], ['_token' => 'wrong']] as $input) {
            $this->postJson('/_session_lab/login', $input + ['password' => 'synthetic-lab-password'])->assertStatus(419);
        }
        self::assertFalse($this->rows()->findOne(['_id' => $id])['revoked']);
        self::assertArrayNotHasKey('synthetic_principal', $this->payload($id));
        $this->postJson('/_session_lab/login', ['password' => 'synthetic-lab-password', '_token' => $token])->assertNoContent();
    }

    public function test_stale_final_save_cannot_publish_success_or_overwrite_the_successor(): void
    {
        [$id, $token] = $this->anonymous();
        $this->postJson('/_session_lab/probe/takeover-save', ['_token' => $token])->assertStatus(409)->assertCookieMissing('synlab_http')->assertJsonPath('code', 'session_fence_rejected');
        self::assertSame(99, $this->payload($id)['counter']);
    }

    public function test_stale_rotation_cannot_tombstone_a_successor_or_issue_a_replacement(): void
    {
        [$id, $token] = $this->anonymous();
        $this->postJson('/_session_lab/probe/takeover-rotate', ['_token' => $token])->assertStatus(409)->assertCookieMissing('synlab_http');
        self::assertFalse($this->rows()->findOne(['_id' => $id])['revoked']);
        self::assertSame(99, $this->payload($id)['counter']);
        self::assertCount(1, $this->ids);
    }

    public function test_expired_lease_during_request_rejects_the_final_save(): void
    {
        [$id, $token] = $this->anonymous();
        $this->postJson('/_session_lab/probe/expired', ['_token' => $token])->assertStatus(409)->assertCookieMissing('synlab_http');
        self::assertArrayNotHasKey('counter', $this->payload($id));
    }

    public function test_backend_outage_is_a_sanitized_503_without_a_session_cookie(): void
    {
        $this->backend = $this->unavailableDatabase();
        $response = $this->getJson('/_session_lab/state')->assertStatus(503)->assertCookieMissing('synlab_http')->assertJsonPath('code', 'session_transport_unavailable');
        self::assertSame(['type', 'title', 'status', 'code', 'correlationId'], array_keys($response->json()));
        self::assertStringNotContainsString('127.0.0.1', $response->getContent());
    }

    public function test_downstream_500_does_not_persist_mutation_or_publish_cookie_and_releases_lease(): void
    {
        [$id, $token] = $this->anonymous();
        $this->postJson('/_session_lab/probe/server-error', ['_token' => $token])->assertStatus(500)->assertJsonPath('code', 'synthetic_failure')->assertCookieMissing('synlab_http');
        self::assertArrayNotHasKey('counter', $this->payload($id));
        self::assertSame('', $this->rows()->findOne(['_id' => $id])['owner']);
        $this->getJson('/_session_lab/state')->assertOk()->assertJsonPath('counter', 0);
    }

    public function test_unrelated_404_keeps_its_http_status_and_releases_lease(): void
    {
        [$id, $token] = $this->anonymous();
        $this->postJson('/_session_lab/probe/not-found', ['_token' => $token])->assertNotFound();
        self::assertSame('', $this->rows()->findOne(['_id' => $id])['owner']);
    }

    public function test_failed_replacement_issuance_keeps_old_tombstone_and_never_reports_rotation_success(): void
    {
        [$id, $token] = $this->anonymous();
        $this->postJson('/_session_lab/probe/failed-issue', ['_token' => $token])->assertStatus(503)->assertCookieMissing('synlab_http');
        self::assertTrue($this->rows()->findOne(['_id' => $id])['revoked']);
        $this->failIssuance = false;
        $this->getJson('/_session_lab/state')->assertStatus(409)->assertCookieMissing('synlab_http');
    }

    private function anonymous(): array
    {
        $response = $this->getJson('/_session_lab/state')->assertOk();
        $id = $this->cookieId($response);
        $this->withCookie('synlab_http', $id);

        return [$id, $response->json('csrf')];
    }

    private function cookieId(TestResponse $response): string
    {
        $cookie = $response->getCookie('synlab_http');
        self::assertNotNull($cookie);

        return $cookie->getValue();
    }

    private function rows(): Collection
    {
        return $this->lab->selectCollection('fenced_sessions');
    }

    private function payload(string $id): array
    {
        return json_decode($this->rows()->findOne(['_id' => $id])['payload']->getData(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function unavailableDatabase(): Database
    {
        return (new Client('mongodb://127.0.0.1:1/?serverSelectionTimeoutMS=100&connectTimeoutMS=100'))->selectDatabase('studentity_test_session_lab');
    }
}
