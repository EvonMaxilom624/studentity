<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Security\Authority\RevocationCommand;
use App\Http\Middleware\EnforceCurrentAuthorization;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Database;
use Tests\Support\AuthorityReceiptFixtures;
use Tests\Support\EncryptedSessionLabSchema;
use Tests\Support\EncryptedSessionLabStore;
use Tests\Support\EncryptedStatefulLabStart;
use Tests\Support\GuardSessionLabHarness;
use Tests\Support\ReceiptAuthorityLab;
use Tests\Support\ReceiptJournalReplayLab;
use Tests\Support\ResolveGuardLabResource;
use Tests\Support\StagedSessionRepairLab;
use Tests\Support\StatefulSessionLabBoundary;
use Tests\TestCase;

final class EncryptedSessionLifecycleLabTest extends TestCase
{
    use AuthorityReceiptFixtures;
    use GuardSessionLabHarness { setUp as private setUpGuard;
        tearDown as private tearDownGuard; }

    private Database $encrypted;

    private Encrypter $cipher;

    private array $rawIds = [];

    private array $repairOperations = [];

    protected function setUp(): void
    {
        $this->setUpGuard();
        $this->encrypted = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase('studentity_test_session_activation');
        EncryptedSessionLabSchema::ensure($this->encrypted);
        $this->cipher = new Encrypter(random_bytes(32), 'AES-256-CBC');
        config(['app.debug' => false, 'sanctum.stateful' => ['localhost']]);
        $this->app->instance('stateful-lab.trace', (object) ['events' => []]);
        $this->app->make(Kernel::class)->prependMiddleware(StatefulSessionLabBoundary::class);
        StatefulSessionLabBoundary::registerReporting($this->app->make(ExceptionHandler::class));
        $this->app->bind(StartSession::class, fn ($app) => new EncryptedStatefulLabStart($app->make(SessionManager::class), $this->encrypted, $this->cipher, function (): string {
            return $this->rawIds[] = bin2hex(random_bytes(20));
        }));
        $actions = [];
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), '_guard_lab/')) {
                $actions[basename($route->uri())] = $route->getAction('uses');
            }
        }
        Route::middleware('api')->group(function () use ($actions): void {
            foreach (['state', 'login', 'helper', 'native-logout'] as $name) {
                Route::match($name === 'state' ? ['GET'] : ['POST'], '/_encrypted_lab/'.$name, function (Request $request) use ($actions, $name) {
                    return app()->call($actions[$name], ['request' => $request]);
                });
            }
            Route::middleware([ResolveGuardLabResource::class, EnforceCurrentAuthorization::class.':profile.read'])->get('/_encrypted_lab/protected', fn () => response()->json(['authorized' => true]));
            Route::middleware(ResolveGuardLabResource::class)->post('/_encrypted_lab/revoke', $actions['revoke']);
            Route::post('/_encrypted_lab/rotate', function (Request $request) {
                $request->session()->regenerate();

                return response()->noContent();
            });
            foreach (['failure', 'expired'] as $mode) {
                Route::post('/_encrypted_lab/'.$mode, function (Request $request) use ($actions, $mode) {
                    $response = app()->call($actions['login'], ['request' => $request]);
                    if ($mode === 'failure') {
                        abort(500);
                    }
                    $this->encrypted->selectCollection('envelopes_v1')->updateOne(['_id' => hash('sha256', $request->session()->getId())], ['$set' => ['lease_until' => new UTCDateTime(0)]]);

                    return $response;
                });
            }
        });
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->encrypted) && $this->rawIds) {
                $this->encrypted->selectCollection('envelopes_v1')->deleteMany(['_id' => ['$in' => array_map(static fn ($raw) => hash('sha256', $raw), $this->rawIds)]]);
            }
            if (isset($this->securityDb) && $this->repairOperations) {
                foreach (['security_decision_journal', 'audit_events'] as $name) {
                    $this->securityDb->selectCollection($name)->deleteMany(['_id' => ['$in' => $this->repairOperations]]);
                }
            }
        } finally {
            $this->tearDownGuard();
        }
    }

    public function test_real_web_csrf_cookie_issues_and_publishes_an_encrypted_anonymous_session(): void
    {
        $response = $this->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
        $raw = $response->getCookie('synlab_guard')->getValue();
        self::assertSame('live', $this->row($raw)['lifecycle']);
        self::assertSame($response->getCookie('XSRF-TOKEN')->getValue(), $this->payload($raw)['_token']);
        self::assertStringNotContainsString($raw, $this->row($raw)['payload']->getData());
        self::assertSame(1, request()->attributes->get('lab.starts'));
    }

    public function test_guard_login_rotates_and_rehydrates_both_guards_from_encrypted_payload(): void
    {
        [$old, $token] = $this->anonymous();
        $response = $this->postJson('/_encrypted_lab/login', ['password' => 'synthetic-guard-password', '_token' => $token], $this->origin())->assertNoContent();
        $new = $response->getCookie('synlab_guard')->getValue();
        self::assertNotSame($old, $new);
        self::assertSame('revoked', $this->row($old)['lifecycle']);
        self::assertSame($this->userId, $this->payload($new)[Auth::guard('web')->getName()]);
        self::assertStringNotContainsString($this->userId, $this->row($new)['payload']->getData());
        self::assertNotSame($token, $this->payload($new)['_token']);
        $this->withCookie('synlab_guard', $new)->getJson('/_encrypted_lab/state', $this->origin())->assertOk()->assertJsonPath('web', $this->userId)->assertJsonPath('sanctum', $this->userId)->assertJsonPath('sameStore', true);
        $this->getJson('/_encrypted_lab/protected', $this->origin())->assertOk();
        $this->withCookie('synlab_guard', $old)->getJson('/_encrypted_lab/state', $this->origin())->assertStatus(409);
    }

    public function test_missing_csrf_and_wrong_credentials_cannot_rotate_or_identify(): void
    {
        [$raw, $token] = $this->anonymous();
        $this->postJson('/_encrypted_lab/login', ['password' => 'synthetic-guard-password'], $this->origin())->assertStatus(419);
        $this->postJson('/_encrypted_lab/login', ['password' => 'wrong', '_token' => $token], $this->origin())->assertUnauthorized();
        self::assertCount(1, $this->rawIds);
        self::assertArrayNotHasKey(Auth::guard('web')->getName(), $this->payload($raw));
    }

    public function test_helper_and_native_logout_persist_anonymous_replacement_and_reject_old_cookie(): void
    {
        [$raw, $token] = $this->loginEncrypted();
        $this->postJson('/_encrypted_lab/helper', ['_token' => $token], $this->origin())->assertNoContent();
        self::assertSame(7, $this->payload($raw)['guard_counter']);
        $response = $this->postJson('/_encrypted_lab/native-logout', ['_token' => $token], $this->origin())->assertNoContent();
        $new = $response->getCookie('synlab_guard')->getValue();
        self::assertNotSame($raw, $new);
        self::assertArrayNotHasKey(Auth::guard('web')->getName(), $this->payload($new));
        $this->withCookie('synlab_guard', $new)->getJson('/_encrypted_lab/state', $this->origin())->assertOk()->assertJsonPath('web', null);
        $this->withCookie('synlab_guard', $raw)->getJson('/_encrypted_lab/state', $this->origin())->assertStatus(409);
    }

    public function test_security_logout_keeps_independent_and_local_revocation_with_encrypted_rotation(): void
    {
        [$raw, $token] = $this->loginEncrypted();
        $this->postJson('/_encrypted_lab/revoke', ['_token' => $token], $this->origin())->assertNoContent();
        self::assertTrue($this->authority->evidence($this->userId, hash('sha256', $raw))->revoked);
        self::assertNotNull($this->securityDb->selectCollection('user_sessions')->findOne(['user_id' => $this->userId])['revoked_at']);
        self::assertSame('revoked', $this->row($raw)['lifecycle']);
    }

    public function test_non_first_party_request_and_unknown_cookie_cannot_hydrate_or_create_payload(): void
    {
        $this->loginEncrypted();
        $this->getJson('/_encrypted_lab/protected')->assertUnauthorized();
        $unknown = bin2hex(random_bytes(20));
        $this->withCookie('synlab_guard', $unknown)->getJson('/_encrypted_lab/state', $this->origin())->assertStatus(409)->assertCookieMissing('synlab_guard');
        self::assertNull($this->row($unknown));
    }

    public function test_downstream_failure_after_login_leaves_only_unpublished_stage_and_no_success_cookies(): void
    {
        [$old, $token] = $this->anonymous();
        $this->postJson('/_encrypted_lab/failure', ['password' => 'synthetic-guard-password', '_token' => $token], $this->origin())->assertStatus(500)->assertCookieMissing('synlab_guard')->assertCookieMissing('XSRF-TOKEN');
        $raw = $this->rawIds[1];
        self::assertSame('staged', $this->row($raw)['lifecycle']);
        self::assertArrayNotHasKey('payload', $this->row($raw)->getArrayCopy());
        self::assertSame('revoked', $this->row($old)['lifecycle']);
        self::assertFalse(Auth::guard('web')->check());
        $this->withCookie('synlab_guard', $raw)->getJson('/_encrypted_lab/state', $this->origin())->assertStatus(409);
    }

    public function test_expired_final_login_save_withholds_rotated_cookie_and_identity(): void
    {
        [, $token] = $this->anonymous();
        $this->postJson('/_encrypted_lab/expired', ['password' => 'synthetic-guard-password', '_token' => $token], $this->origin())->assertStatus(409)->assertCookieMissing('synlab_guard')->assertCookieMissing('XSRF-TOKEN');
        self::assertSame('staged', $this->row($this->rawIds[1])['lifecycle']);
        self::assertFalse(Auth::guard('web')->check());
    }

    public function test_guard_cache_does_not_cross_into_a_new_anonymous_request(): void
    {
        $this->loginEncrypted();
        $this->defaultCookies = [];
        $this->getJson('/_encrypted_lab/state', $this->origin())->assertOk()->assertJsonPath('web', null)->assertJsonPath('sanctum', null)->assertJsonPath('sameStore', true);
        self::assertSame('array', config('session.driver'));
    }

    public function test_default_regenerate_mode_also_retires_the_old_anonymous_cookie(): void
    {
        [$old, $token] = $this->anonymous();
        $response = $this->postJson('/_encrypted_lab/rotate', ['_token' => $token], $this->origin())->assertNoContent();
        $new = $response->getCookie('synlab_guard')->getValue();
        self::assertNotSame($old, $new);
        self::assertNotSame($token, $this->payload($new)['_token']);
        self::assertSame('revoked', $this->row($old)['lifecycle']);
        $this->withCookie('synlab_guard', $old)->getJson('/_encrypted_lab/state', $this->origin())->assertStatus(409);
    }

    public function test_failed_http_login_stage_is_repaired_with_receipt_and_local_control_cleanup(): void
    {
        [, $token] = $this->anonymous();
        $this->postJson('/_encrypted_lab/failure', ['password' => 'synthetic-guard-password', '_token' => $token], $this->origin())->assertStatus(500);
        $raw = $this->rawIds[1];
        $row = $this->row($raw);
        $operation = $this->repairOperations[] = $row['issuance_operation_id'];
        $command = new RevocationCommand(array_replace($this->commandData(), ['operationId' => $operation, 'subjectUserId' => $this->userId, 'sessionDigest' => $row['_id'], 'correlationId' => $this->correlation]));
        $handler = new EncryptedSessionLabStore($this->encrypted, $this->cipher, static fn () => throw new \LogicException('Repair must not issue a session.'));
        $authority = new ReceiptAuthorityLab;
        $coordinator = new StagedSessionRepairLab($this->encrypted, $this->securityDb, $handler, new ReceiptJournalReplayLab($this->securityDb), $authority);
        self::assertSame('repaired', $coordinator->repair(['_id' => $row['_id'], 'issuance_operation_id' => $operation, 'generation' => (int) (string) $row['generation'], 'revision' => (int) (string) $row['revision']], $command));
        self::assertSame('revoked', $this->row($raw)['lifecycle']);
        self::assertNotNull($this->securityDb->selectCollection('user_sessions')->findOne(['session_digest' => new Binary(hex2bin($row['_id']))])['revoked_at']);
        self::assertSame(1, $this->securityDb->selectCollection('security_decision_journal')->countDocuments(['_id' => $operation]));
        $handler->close();
    }

    private function anonymous(): array
    {
        $response = $this->getJson('/_encrypted_lab/state', $this->origin())->assertOk();
        $raw = $response->getCookie('synlab_guard')->getValue();
        $this->withCookie('synlab_guard', $raw);

        return [$raw, $response->json('csrf')];
    }

    private function loginEncrypted(): array
    {
        [, $token] = $this->anonymous();
        $response = $this->postJson('/_encrypted_lab/login', ['password' => 'synthetic-guard-password', '_token' => $token], $this->origin())->assertNoContent();
        $raw = $response->getCookie('synlab_guard')->getValue();
        $this->withCookie('synlab_guard', $raw);

        return [$raw, $this->payload($raw)['_token']];
    }

    private function origin(): array
    {
        return ['Origin' => 'http://localhost'];
    }

    private function row(string $raw): mixed
    {
        return $this->encrypted->selectCollection('envelopes_v1')->findOne(['_id' => hash('sha256', $raw)]);
    }

    private function payload(string $raw): array
    {
        $body = json_decode($this->cipher->decryptString($this->row($raw)['payload']->getData()), true, 32, JSON_THROW_ON_ERROR);

        return json_decode($body['payload_json'], true, 32, JSON_THROW_ON_ERROR);
    }
}
