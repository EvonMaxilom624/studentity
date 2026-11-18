<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Security\ResourceScope;
use App\Domain\Security\SecurityStateAuthority;
use App\Http\Middleware\AuthenticateSessionIdentity;
use App\Http\Middleware\EnforceCurrentAuthorization;
use App\Infrastructure\Security\MongoAuthorizationRepository;
use App\Infrastructure\Security\SessionRevocationService;
use App\Models\AuthenticatedUser;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;

/** Shared synthetic fixture and in-memory route scaffolding for the two guard integration tasks. */
trait GuardSessionLabHarness
{
    use SessionLabHarness {
        setUp as private setUpTransport;
        tearDown as private tearDownTransport;
    }

    protected Database $securityDb;

    protected InMemorySecurityStateAuthority $authority;

    protected string $userId;

    protected string $grantId;

    protected string $programId;

    protected string $correlation;

    protected bool $withMfa = true;

    protected bool $expireLoginLease = false;

    private array $securityIds = [];

    protected function setUp(): void
    {
        $this->setUpTransport();
        $this->withCredentials();
        $this->securityDb = $this->app['db']->connection('mongodb')->getDatabase();
        if ($this->securityDb->getDatabaseName() !== 'studentity_test_m0') {
            throw new \RuntimeException('Security fixtures require the exact synthetic test database.');
        }
        $this->userId = (string) Str::uuid();
        $this->grantId = (string) Str::uuid();
        $this->programId = (string) Str::uuid();
        $this->correlation = (string) Str::uuid();
        $this->authority = new InMemorySecurityStateAuthority;
        $this->app->instance(SecurityStateAuthority::class, $this->authority);
        $this->app->instance('guard-lab.scope', new ResourceScope('profile', $this->userId, $this->programId, $this->userId));
        $this->app->instance('guard-lab.correlation', $this->correlation);
        $this->insertSecurity('users', ['_id' => $this->userId, 'kind' => 'coordinator', 'account_state' => 'active', 'security_epoch' => new Int64(4), 'created_at' => new UTCDateTime, 'updated_at' => new UTCDateTime, 'schema_version' => 1]);
        $this->insertSecurity('role_grants', ['_id' => $this->grantId, 'user_id' => $this->userId, 'role_name' => 'coordinator', 'program_id' => $this->programId, 'permission_set' => (object) ['profile.read' => true], 'is_active' => true, 'granted_at' => new UTCDateTime, 'schema_version' => 1]);
        $hashes = [$this->userId => Hash::make('synthetic-guard-password')];
        $this->app['auth']->provider('guard-lab-credentials', static fn ($app) => new FixtureCredentialLabProvider($app['hash'], AuthenticatedUser::class, $hashes));
        config(['auth.providers.users.driver' => 'guard-lab-credentials', 'session.cookie' => 'synlab_guard', 'session.lottery' => [0, 100], 'session.expire_on_close' => true]);
        $this->app->bind(FencedHttpLabMiddleware::class, fn ($app) => new FencedHttpLabMiddleware($app->make(SessionManager::class), $this->lab, fn () => $this->id()));
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        Route::middleware([EncryptCookies::class, FencedHttpLabMiddleware::class, BindFencedLabGuard::class, PreventRequestForgery::class, AuthenticateSessionIdentity::class])->group(function (): void {
            Route::get('/_guard_lab/state', function (Request $request) {
                $web = Auth::guard('web');

                return response()->json([
                    'web' => $web->user()?->getAuthIdentifier(), 'sanctum' => $request->user('sanctum')?->getAuthIdentifier(),
                    'csrf' => $request->session()->token(), 'counter' => session('guard_counter', 0),
                    'sameStore' => $request->session() === app('session.store') && $request->session() === app('session')->driver() && $request->session() === $web->getSession()
                        && $request->session() === app('auth.driver')->getSession() && $request->session()->getId() === Session::getId(),
                ]);
            });
            Route::post('/_guard_lab/login', function (Request $request) {
                if (! Auth::guard('web')->attempt(['_id' => $request->input('user', $this->userId), 'password' => $request->input('password')], false)) {
                    return response()->json(['code' => 'invalid_credentials'], 401);
                }
                $digest = hash('sha256', $request->session()->getId());
                $record = ['_id' => (string) Str::uuid(), 'user_id' => $this->userId, 'session_digest' => new Binary(hex2bin($digest)), 'security_epoch' => new Int64(4), 'created_at' => new UTCDateTime((time() - 2) * 1000), 'expires_at' => new UTCDateTime((time() + 600) * 1000), 'schema_version' => 1];
                if ($this->withMfa) {
                    $record['mfa_verified_at'] = new UTCDateTime;
                }
                $this->insertSecurity('user_sessions', $record);
                $this->authority->trust($this->app->make(MongoAuthorizationRepository::class)->context($this->userId, $digest));
                if ($this->expireLoginLease) {
                    $this->lab->selectCollection('fenced_sessions')->updateOne(['_id' => $request->session()->getId()], ['$set' => ['lease_until' => new UTCDateTime(0)]]);
                }

                return response()->noContent();
            });
            Route::post('/_guard_lab/helper', function () {
                session(['guard_counter' => 7]);

                return response()->noContent();
            });
            Route::post('/_guard_lab/native-logout', function (Request $request) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return response()->noContent();
            });
            Route::middleware([ResolveGuardLabResource::class, EnforceCurrentAuthorization::class.':profile.read'])->get('/_guard_lab/protected', fn () => response()->json(['authorized' => true]));
            Route::middleware(ResolveGuardLabResource::class)->post('/_guard_lab/revoke', function (Request $request) {
                app(SessionRevocationService::class)->revoke($request);

                return response()->noContent();
            });
        });
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->securityDb)) {
                foreach ($this->securityIds as $collection => $ids) {
                    $this->securityDb->selectCollection($collection)->deleteMany(['_id' => ['$in' => $ids]]);
                }
                $this->securityDb->selectCollection('audit_events')->deleteMany(['correlation_id' => $this->correlation]);
            }
        } finally {
            $this->tearDownTransport();
        }
    }

    protected function insertSecurity(string $collection, array $row): void
    {
        $this->securityIds[$collection][] = $row['_id'];
        $this->securityDb->selectCollection($collection)->insertOne($row);
    }

    protected function anonymousGuardSession(): array
    {
        $response = $this->getJson('/_guard_lab/state')->assertOk();
        $id = $response->getCookie('synlab_guard')->getValue();
        $this->withCookie('synlab_guard', $id);

        return [$id, $response->json('csrf')];
    }

    protected function signIn(): array
    {
        [, $token] = $this->anonymousGuardSession();
        $response = $this->postJson('/_guard_lab/login', ['_token' => $token, 'password' => 'synthetic-guard-password'])->assertNoContent();
        $id = $response->getCookie('synlab_guard')->getValue();
        $this->withCookie('synlab_guard', $id);

        return [$id, $this->guardPayload($id)['_token']];
    }

    protected function guardPayload(string $id): array
    {
        return json_decode($this->lab->selectCollection('fenced_sessions')->findOne(['_id' => $id])['payload']->getData(), true, 512, JSON_THROW_ON_ERROR);
    }
}

final class ResolveGuardLabResource
{
    public function handle(Request $request, \Closure $next): mixed
    {
        $request->attributes->set('authorization.resource', app('guard-lab.scope'));
        $request->attributes->set('authorization.correlation_id', app('guard-lab.correlation'));

        return $next($request);
    }
}
