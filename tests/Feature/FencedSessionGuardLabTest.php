<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Tests\Support\GuardSessionLabHarness;
use Tests\TestCase;

final class FencedSessionGuardLabTest extends TestCase
{
    use GuardSessionLabHarness;

    public function test_native_attempt_uses_the_fenced_store_and_rehydrates_both_guards(): void
    {
        [$id] = $this->signIn();
        $key = Auth::guard('web')->getName();
        self::assertSame($this->userId, $this->guardPayload($id)[$key]);
        $this->getJson('/_guard_lab/state')->assertOk()->assertJsonPath('web', $this->userId)->assertJsonPath('sanctum', $this->userId)->assertJsonPath('sameStore', true);
    }

    public function test_wrong_password_and_unknown_identity_never_rotate_or_authenticate(): void
    {
        [$old, $token] = $this->anonymousGuardSession();
        foreach ([['password' => 'wrong'], ['user' => (string) Str::uuid(), 'password' => 'synthetic-guard-password']] as $input) {
            $response = $this->postJson('/_guard_lab/login', $input + ['_token' => $token])->assertUnauthorized();
            self::assertSame($old, $response->getCookie('synlab_guard')->getValue());
        }
        self::assertCount(1, $this->ids);
        $this->getJson('/_guard_lab/state')->assertOk()->assertJsonPath('web', null)->assertJsonPath('sanctum', null);
    }

    public function test_login_rotates_the_native_guard_cookie_and_old_cookie_cannot_replay(): void
    {
        [$old, $token] = $this->anonymousGuardSession();
        $response = $this->postJson('/_guard_lab/login', ['_token' => $token, 'password' => 'synthetic-guard-password'])->assertNoContent();
        $new = $response->getCookie('synlab_guard')->getValue();
        self::assertNotSame($old, $new);
        self::assertNotSame($token, $this->guardPayload($new)['_token']);
        $this->withCookie('synlab_guard', $old)->getJson('/_guard_lab/state')->assertStatus(409)->assertCookieMissing('synlab_guard');
    }

    public function test_session_helper_writes_reach_the_same_persisted_envelope(): void
    {
        [$id, $token] = $this->signIn();
        $this->postJson('/_guard_lab/helper', ['_token' => $token])->assertNoContent();
        self::assertSame(7, $this->guardPayload($id)['guard_counter']);
        $this->getJson('/_guard_lab/state')->assertOk()->assertJsonPath('counter', 7);
    }

    public function test_cached_guards_and_driver_cannot_leak_a_principal_into_a_new_anonymous_request(): void
    {
        $this->app['session']->driver();
        Auth::guard('web');
        Auth::guard('sanctum');
        app('auth.driver');
        Session::getId();
        $this->signIn();
        $this->getJson('/_guard_lab/state')->assertOk()->assertJsonPath('web', $this->userId);
        $this->defaultCookies = [];
        $this->getJson('/_guard_lab/state')->assertOk()->assertJsonPath('web', null)->assertJsonPath('sanctum', null)->assertJsonPath('sameStore', true);
        self::assertSame('array', config('session.driver'));
        self::assertFalse(Auth::guard('web')->check());
    }

    public function test_native_logout_persists_anonymous_replacement_without_a_recaller_cookie(): void
    {
        [$old, $token] = $this->signIn();
        $response = $this->postJson('/_guard_lab/native-logout', ['_token' => $token])->assertNoContent();
        $new = $response->getCookie('synlab_guard')->getValue();
        self::assertNotSame($old, $new);
        self::assertArrayNotHasKey(Auth::guard('web')->getName(), $this->guardPayload($new));
        $this->withCookie('synlab_guard', $new)->getJson('/_guard_lab/state')->assertOk()->assertJsonPath('web', null)->assertJsonPath('sanctum', null);
        $this->withCookie('synlab_guard', $old)->getJson('/_guard_lab/state')->assertStatus(409);
        foreach ($response->headers->getCookies() as $cookie) {
            self::assertFalse(str_starts_with($cookie->getName(), 'remember_'));
        }
    }

    public function test_active_csrf_rejects_guard_login_before_rotating_or_writing_identity(): void
    {
        [$id] = $this->anonymousGuardSession();
        $this->postJson('/_guard_lab/login', ['password' => 'synthetic-guard-password'])->assertStatus(419);
        self::assertCount(1, $this->ids);
        self::assertArrayNotHasKey(Auth::guard('web')->getName(), $this->guardPayload($id));
    }

    public function test_failed_final_login_save_does_not_publish_identity_or_leave_a_cached_guard(): void
    {
        [$old, $token] = $this->anonymousGuardSession();
        $this->expireLoginLease = true;
        $this->postJson('/_guard_lab/login', ['_token' => $token, 'password' => 'synthetic-guard-password'])->assertStatus(409)->assertCookieMissing('synlab_guard');
        $new = $this->ids[1];
        self::assertArrayNotHasKey(Auth::guard('web')->getName(), $this->guardPayload($new));
        self::assertFalse(Auth::guard('web')->check());
        $this->withCookie('synlab_guard', $old)->getJson('/_guard_lab/state')->assertStatus(409);
    }
}
