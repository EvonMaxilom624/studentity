<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identity\UserKind;
use App\Domain\Security\ResourceScope;
use App\Domain\Security\SecurityStateAuthority;
use App\Http\Middleware\EnforceCurrentAuthorization;
use App\Infrastructure\Security\AccessDecisionAuditRecorder;
use App\Infrastructure\Security\MongoAuthorizationRepository;
use App\Infrastructure\Security\SessionRevocationService;
use App\Infrastructure\Security\UnavailableSecurityStateAuthority;
use App\Models\AuthenticatedUser;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Database;
use Tests\Support\InMemorySecurityStateAuthority;
use Tests\TestCase;

final class Fnd03FoundationTest extends TestCase
{
    private Database $db;

    private InMemorySecurityStateAuthority $authority;

    private array $owned = [];

    private string $correlation;

    private string $userId;

    private string $grantId;

    private string $programId;

    private string $sessionDigest;

    private string $sessionId;

    private string $cookie;

    private bool $mfa = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials(); // Laravel's JSON test client otherwise deliberately omits cookies.
        $name = config('database.connections.mongodb.database');
        $dsn = config('database.connections.mongodb.dsn');
        if ($name !== 'studentity_test_m0' || ! in_array(parse_url($dsn, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('FND-03 fixtures require exactly local studentity_test_m0');
        }
        $this->db = (new Client($dsn))->selectDatabase($name);
        $this->correlation = (string) Str::uuid();
        $this->userId = (string) Str::uuid();
        $this->grantId = (string) Str::uuid();
        $this->programId = (string) Str::uuid();
        $this->authority = new InMemorySecurityStateAuthority;
        $this->app->instance(SecurityStateAuthority::class, $this->authority);

        // All routes in this class exist only in this PHPUnit application's memory.
        Route::middleware('web')->post('/_fnd/login', function (Request $request) {
            Auth::guard('web')->login(AuthenticatedUser::findOrFail($this->userId));
            $request->session()->regenerate();
            $this->sessionId = $request->session()->getId();
            $this->sessionDigest = hash('sha256', $this->sessionId);
            $record = ['_id' => (string) Str::uuid(), 'user_id' => $this->userId, 'session_digest' => new Binary(hex2bin($this->sessionDigest)), 'security_epoch' => new Int64(4), 'created_at' => new UTCDateTime((time() - 2) * 1000), 'expires_at' => new UTCDateTime((time() + 600) * 1000), 'schema_version' => 1];
            if ($this->mfa) {
                $record['mfa_verified_at'] = new UTCDateTime((time() - 1) * 1000);
            }
            $this->insert('user_sessions', $record);
            $this->authority->trust($this->app->make(MongoAuthorizationRepository::class)->context($this->userId, $this->sessionDigest));

            return response()->noContent();
        });
        $this->app->instance('fnd03.scope', new ResourceScope('profile', $this->userId, $this->programId, $this->userId));
        $this->app->instance('fnd03.correlation', $this->correlation);
        Route::middleware(['web', ResolveFnd03Resource::class, EnforceCurrentAuthorization::class.':profile.read'])->get('/_fnd/protected', fn () => response()->json(['authorized' => true]));
        Route::middleware(['web', ResolveFnd03Resource::class])->post('/_fnd/logout', function (Request $request) {
            $this->app->make(SessionRevocationService::class)->revoke($request);

            return response()->noContent();
        });
        Route::middleware(['web', EnforceCurrentAuthorization::class.':profile.read'])->get('/_fnd/no-resource', fn () => response()->noContent());
        Route::middleware(['api', ResolveFnd03Resource::class, EnforceCurrentAuthorization::class.':profile.read'])->get('/_fnd/api-protected', fn () => response()->json(['authorized' => true]));
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->db)) {
                foreach ($this->owned as $collection => $ids) {
                    $this->db->selectCollection($collection)->deleteMany(['_id' => ['$in' => $ids]]);
                }
                $this->db->selectCollection('audit_events')->deleteMany(['correlation_id' => $this->correlation]);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function insert(string $collection, array $record): void
    {
        $this->owned[$collection][] = $record['_id'];
        $this->db->selectCollection($collection)->insertOne($record);
    }

    private function login(UserKind $kind = UserKind::Coordinator): void
    {
        $this->insert('users', ['_id' => $this->userId, 'kind' => $kind->value, 'account_state' => 'active', 'security_epoch' => new Int64(4), 'created_at' => new UTCDateTime, 'updated_at' => new UTCDateTime, 'schema_version' => 1]);
        $grant = ['_id' => $this->grantId, 'user_id' => $this->userId, 'role_name' => $kind->value, 'permission_set' => (object) ['profile.read' => true], 'is_active' => true, 'granted_at' => new UTCDateTime, 'schema_version' => 1];
        if ($kind === UserKind::Coordinator) {
            $grant['program_id'] = $this->programId;
        }
        $this->insert('role_grants', $grant);
        $response = $this->postJson('/_fnd/login')->assertNoContent();
        $this->cookie = $response->getCookie(config('session.cookie'))->getValue();
        $this->withCookie(config('session.cookie'), $this->cookie);
        $this->app['auth']->forgetGuards();
    }

    private function protectedRequest(string $uri = '/_fnd/protected'): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->getJson($uri);
    }

    public function test_anonymous_and_bearer_only_requests_are_denied_without_sql_token_lookup(): void
    {
        $this->protectedRequest()->assertUnauthorized()->assertJsonStructure(['type', 'title', 'status', 'code', 'correlationId']);
        $this->withToken('synthetic-not-a-personal-token')->protectedRequest()->assertUnauthorized();
    }

    public function test_real_cookie_authentication_rehydrates_the_mongodb_principal_and_audits(): void
    {
        $this->login();
        $this->protectedRequest()->assertOk()->assertExactJson(['authorized' => true]);
        self::assertSame(AuthenticatedUser::class, get_class(Auth::user()));
        $event = $this->db->selectCollection('audit_events')->findOne(['correlation_id' => $this->correlation, 'event_redacted.allowed' => true]);
        self::assertNotNull($event);
        self::assertSame('profile.read', $event['event_redacted']['ability']);
        $json = json_encode($event);
        self::assertStringNotContainsString($this->cookie, $json);
        self::assertStringNotContainsString($this->sessionId, $json);
        self::assertInstanceOf(Binary::class, $event['event_hash']);
        $hashedFields = $event->getArrayCopy();
        unset($hashedFields['event_hash']);
        self::assertSame(hash('sha256', json_encode($hashedFields, JSON_THROW_ON_ERROR), true), $event['event_hash']->getData());
    }

    public function test_scope_change_is_enforced_on_an_existing_session_without_relogin(): void
    {
        $this->login();
        $this->protectedRequest()->assertOk();
        $this->db->selectCollection('role_grants')->updateOne(['_id' => $this->grantId], ['$set' => ['program_id' => (string) Str::uuid()]]);
        $this->protectedRequest()->assertUnauthorized(); // independently anchored grant digest is now different
        $this->authority->trust($this->app->make(MongoAuthorizationRepository::class)->context($this->userId, $this->sessionDigest));
        $this->protectedRequest()->assertForbidden(); // current trusted grant still belongs to the wrong program
    }

    public function test_forged_student_id_and_missing_resolved_resource_do_not_grant_access(): void
    {
        $this->login(UserKind::Student);
        $this->programId = (string) Str::uuid();
        $this->protectedRequest()->assertOk();
        $this->protectedRequest('/_fnd/no-resource?studentId='.$this->userId)->assertForbidden();
    }

    public function test_privileged_mfa_pending_session_is_denied(): void
    {
        $this->mfa = false;
        $this->login();
        $this->protectedRequest()->assertForbidden();
    }

    public function test_default_and_unavailable_authority_fail_closed(): void
    {
        $this->login();
        $this->authority->unavailable = true;
        $this->protectedRequest()->assertStatus(503);
        $this->app->instance(SecurityStateAuthority::class, new UnavailableSecurityStateAuthority);
        $this->protectedRequest()->assertStatus(503);
    }

    public function test_revoked_session_stays_denied_after_local_revocation_is_restored_away(): void
    {
        $this->login();
        $this->protectedRequest()->assertOk();
        $this->authority->revoke($this->userId, $this->sessionDigest);
        $this->db->selectCollection('user_sessions')->updateOne(['user_id' => $this->userId], ['$set' => ['revoked_at' => new UTCDateTime]]);
        $this->protectedRequest()->assertUnauthorized();
        $this->db->selectCollection('user_sessions')->updateOne(['user_id' => $this->userId], ['$unset' => ['revoked_at' => '']]);
        $this->protectedRequest()->assertUnauthorized();
    }

    public function test_restored_old_grants_do_not_override_independent_current_authorization(): void
    {
        $this->login();
        $this->protectedRequest()->assertOk();
        $oldGrant = $this->db->selectCollection('role_grants')->findOne(['_id' => $this->grantId]);
        $this->db->selectCollection('role_grants')->updateOne(['_id' => $this->grantId], ['$set' => ['is_active' => false, 'revoked_at' => new UTCDateTime]]);
        $current = $this->app->make(MongoAuthorizationRepository::class)->context($this->userId, $this->sessionDigest);
        $this->authority->trust($current);
        $this->db->selectCollection('role_grants')->replaceOne(['_id' => $this->grantId], $oldGrant);
        $this->protectedRequest()->assertUnauthorized();
    }

    public function test_logout_revokes_independent_state_rotates_csrf_and_rejects_replay(): void
    {
        $this->login();
        $token = $this->app['session']->token();
        $this->postJson('/_fnd/logout')->assertNoContent();
        self::assertNotSame($token, $this->app['session']->token());
        $record = $this->db->selectCollection('user_sessions')->findOne(['user_id' => $this->userId]);
        self::assertNotNull($record['revoked_at']);
        self::assertTrue($this->authority->evidence($this->userId, $this->sessionDigest)->revoked);
        $event = $this->db->selectCollection('audit_events')->findOne(['correlation_id' => $this->correlation, 'event_redacted.ability' => 'session.revoke']);
        self::assertSame($record['_id'], $event['target_id']);
        $this->withCookie(config('session.cookie'), $this->cookie)->protectedRequest()->assertUnauthorized();
    }

    public function test_real_csrf_middleware_rejects_missing_invalid_and_cross_site_tokens(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        Route::middleware('web')->post('/_fnd/csrf', fn () => response()->noContent());
        $this->postJson('/_fnd/csrf')->assertStatus(419);
        $this->withSession(['_token' => 'synthetic-csrf-token'])->postJson('/_fnd/csrf', ['_token' => 'wrong'])->assertStatus(419);
        $this->withHeaders(['Sec-Fetch-Site' => 'cross-site'])->postJson('/_fnd/csrf')->assertStatus(419);
        $this->withHeaders(['Sec-Fetch-Site' => 'same-origin'])->postJson('/_fnd/csrf')->assertNoContent();
        $this->flushHeaders();
        $this->withSession(['_token' => 'synthetic-csrf-token'])->postJson('/_fnd/csrf', ['_token' => 'synthetic-csrf-token'])->assertNoContent();
    }

    public function test_session_configuration_and_cookie_flags_are_hardened(): void
    {
        self::assertSame('json', config('session.serialization'));
        self::assertFalse(config('cache.serializable_classes'));
        self::assertTrue(config('session.http_only'));
        self::assertSame('lax', config('session.same_site'));
        config(['session.secure' => true]);
        $response = $this->get('/sanctum/csrf-cookie')->assertNoContent();
        $cookie = $response->getCookie(config('session.cookie'), false);
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame('lax', $cookie->getSameSite());
        self::assertFalse($response->getCookie('XSRF-TOKEN', false)->isHttpOnly());
    }

    public function test_stateful_api_accepts_only_configured_first_party_origins(): void
    {
        $this->login();
        $this->withHeaders(['Origin' => 'http://localhost'])->protectedRequest('/_fnd/api-protected')->assertOk();
        $this->withHeaders(['Origin' => 'https://synthetic-attacker.invalid'])->protectedRequest('/_fnd/api-protected')->assertUnauthorized();
    }

    public function test_login_rotates_existing_session_id_and_old_cookie_cannot_authenticate(): void
    {
        $this->withSession(['_token' => 'synthetic-prelogin']);
        $oldId = $this->app['session']->getId();
        $this->withCookie(config('session.cookie'), $oldId);
        $this->login();
        self::assertNotSame($oldId, $this->sessionId);
        $this->withCookie(config('session.cookie'), $oldId)->protectedRequest()->assertUnauthorized();
        $this->withCookie(config('session.cookie'), $this->cookie)->protectedRequest()->assertOk();
    }

    public function test_account_deactivation_and_expired_local_session_take_effect_immediately(): void
    {
        $this->login();
        $this->db->selectCollection('users')->updateOne(['_id' => $this->userId], ['$set' => ['account_state' => 'disabled']]);
        $this->protectedRequest()->assertForbidden();
        $this->db->selectCollection('users')->updateOne(['_id' => $this->userId], ['$set' => ['account_state' => 'active']]);
        $this->db->selectCollection('user_sessions')->updateOne(['user_id' => $this->userId], ['$set' => ['expires_at' => new UTCDateTime((time() - 1) * 1000)]]);
        $this->protectedRequest()->assertUnauthorized();
    }

    public function test_audit_outage_never_allows_an_otherwise_authorized_request(): void
    {
        $this->login();
        $recorder = \Mockery::mock(AccessDecisionAuditRecorder::class);
        $recorder->shouldReceive('record')->once()->andThrow(new \RuntimeException('Synthetic audit outage'));
        $this->app->instance(AccessDecisionAuditRecorder::class, $recorder);
        $this->protectedRequest()->assertStatus(503)->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_revocation_audit_failure_aborts_local_write_but_independent_revocation_stays_effective(): void
    {
        $this->login();
        $recorder = \Mockery::mock(AccessDecisionAuditRecorder::class, [$this->db])->makePartial();
        $recorder->shouldReceive('record')->withArgs(static fn ($context, $scope, $ability, ...$rest) => $ability === 'session.revoke')->andThrow(new \RuntimeException('Synthetic audit outage'));
        $this->app->instance(AccessDecisionAuditRecorder::class, $recorder);
        $this->postJson('/_fnd/logout')->assertStatus(503)->assertJsonStructure(['type', 'title', 'status', 'code', 'correlationId']);
        $record = $this->db->selectCollection('user_sessions')->findOne(['user_id' => $this->userId]);
        self::assertFalse(isset($record['revoked_at']));
        self::assertTrue($this->authority->evidence($this->userId, $this->sessionDigest)->revoked);
        self::assertSame(0, $this->db->selectCollection('audit_events')->countDocuments(['correlation_id' => $this->correlation, 'event_redacted.ability' => 'session.revoke']));
        $this->withCookie(config('session.cookie'), $this->cookie)->protectedRequest()->assertUnauthorized();
    }

    public function test_unconfirmed_authority_revocation_still_invalidates_the_browser_session(): void
    {
        $this->login();
        $this->authority->unavailable = true;
        $this->postJson('/_fnd/logout')->assertStatus(503);
        self::assertFalse(isset($this->db->selectCollection('user_sessions')->findOne(['user_id' => $this->userId])['revoked_at']));
        $this->withCookie(config('session.cookie'), $this->cookie)->protectedRequest()->assertUnauthorized();
    }

    private function failIdentityRehydration(): void
    {
        $provider = \Mockery::mock(UserProvider::class);
        $provider->shouldReceive('retrieveById')->andThrow(new \RuntimeException('Synthetic identity provider outage'));
        $this->app['auth']->provider('fnd03-unavailable', static fn () => $provider);
        config(['auth.providers.users.driver' => 'fnd03-unavailable']);
        $this->app['auth']->forgetGuards();
    }

    public function test_identity_lookup_failure_during_logout_cannot_skip_browser_invalidation(): void
    {
        $this->login();
        $token = $this->app['session']->token();
        $this->failIdentityRehydration();
        $this->postJson('/_fnd/logout')->assertStatus(503)->assertJsonPath('code', 'security_state_unavailable');
        self::assertNotSame($token, $this->app['session']->token());
        config(['auth.providers.users.driver' => 'eloquent']);
        $this->withCookie(config('session.cookie'), $this->cookie)->protectedRequest()->assertUnauthorized();
        self::assertFalse($this->authority->evidence($this->userId, $this->sessionDigest)->revoked, 'Do not falsely claim independent revocation when identity lookup failed.');
    }

    public function test_stateful_api_identity_outage_returns_generic_problem_before_authorization_middleware(): void
    {
        $this->login();
        $this->failIdentityRehydration();
        $this->withHeaders(['Origin' => 'http://localhost'])->getJson('/_fnd/api-protected')
            ->assertStatus(503)->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonStructure(['type', 'title', 'status', 'code', 'correlationId'])
            ->assertJsonPath('code', 'security_state_unavailable')
            ->assertDontSee('Synthetic identity provider outage');
    }

    public function test_stateful_identity_boundary_preserves_unrelated_downstream_http_errors(): void
    {
        $this->login();
        Route::middleware('api')->get('/_fnd/not-found', fn () => abort(404));
        $this->withHeaders(['Origin' => 'http://localhost'])->getJson('/_fnd/not-found')->assertNotFound();
    }
}

/** Server-resolved synthetic fixture metadata, never registered in production routes. */
final class ResolveFnd03Resource
{
    public function handle(Request $request, \Closure $next): mixed
    {
        $request->attributes->set('authorization.resource', app('fnd03.scope'));
        $request->attributes->set('authorization.correlation_id', app('fnd03.correlation'));

        return $next($request);
    }
}
