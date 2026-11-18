<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Security\Authority\RevocationCommand;
use App\Domain\Security\Authority\VerifiedRevocationReceipt;
use App\Domain\Security\AuthorityEvidence;
use App\Domain\Security\SecurityStateAuthority;
use App\Http\Middleware\EnforceCurrentAuthorization;
use App\Infrastructure\Security\MongoAuthorizationRepository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\Client;
use MongoDB\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AuthorityReceiptFixtures;
use Tests\Support\EncryptedSessionLabSchema;
use Tests\Support\EncryptedSessionLabStore;
use Tests\Support\EncryptedStatefulLabStart;
use Tests\Support\GuardSessionLabHarness;
use Tests\Support\ReceiptAuthorityLab;
use Tests\Support\ReceiptDenyListLabAuthority;
use Tests\Support\ReceiptJournalReplayLab;
use Tests\Support\ResolveGuardLabResource;
use Tests\Support\StatefulSessionLabBoundary;
use Tests\TestCase;

/** Owned snapshot replacement only; no database restore or external durability claim. */
final class EncryptedSessionRollbackLabTest extends TestCase
{
    use AuthorityReceiptFixtures;
    use GuardSessionLabHarness { setUp as private setUpGuard;
        tearDown as private tearDownGuard; }

    private Database $encrypted;

    private Encrypter $cipher;

    private array $rawIds = [];

    private array $operations = [];

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
            foreach (['state', 'login'] as $name) {
                Route::match($name === 'state' ? ['GET'] : ['POST'], '/_rollback_lab/'.$name, function (Request $request) use ($actions, $name) {
                    return app()->call($actions[$name], ['request' => $request]);
                });
            }
            Route::middleware([ResolveGuardLabResource::class, EnforceCurrentAuthorization::class.':profile.read'])->get('/_rollback_lab/protected', fn () => response()->json(['authorized' => true]));
        });
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->encrypted) && $this->rawIds) {
                $this->encrypted->selectCollection('envelopes_v1')->deleteMany(['_id' => ['$in' => array_map(static fn ($id) => hash('sha256', $id), $this->rawIds)]]);
            }
            if (isset($this->securityDb) && $this->operations) {
                foreach (['security_decision_journal', 'audit_events'] as $name) {
                    $this->securityDb->selectCollection($name)->deleteMany(['_id' => ['$in' => $this->operations]]);
                }
            }
        } finally {
            $this->tearDownGuard();
        }
    }

    #[DataProvider('restoreAuthorityStates')]
    public function test_restored_envelope_and_control_rehydrate_guards_but_cannot_resume_revoked_or_unknown_access(string $state, int $httpStatus): void
    {
        $raw = $this->login();
        $snapshot = $this->snapshot($raw);
        $verified = $this->receipt($raw);
        $replay = new ReceiptJournalReplayLab($this->securityDb);
        self::assertSame('confirmed', $replay->apply($verified));
        $this->retire($raw);
        $this->getJson('/_rollback_lab/state', $this->origin())->assertStatus(409);
        $this->restore($raw, $snapshot);
        self::assertFalse(isset($this->control($raw)['revoked_at']));
        $overlay = new ReceiptDenyListLabAuthority($this->authority, [$verified]);
        if ($state === 'missing') {
            unset($this->authority->records[hash('sha256', $raw)]);
        } elseif ($state === 'offline') {
            $this->authority->unavailable = true;
        }
        $this->app->instance(SecurityStateAuthority::class, $overlay);
        $this->getJson('/_rollback_lab/state', $this->origin())->assertOk()->assertJsonPath('web', $this->userId)->assertJsonPath('sanctum', $this->userId)->assertJsonPath('sameStore', true);
        $response = $this->getJson('/_rollback_lab/protected', $this->origin())->assertStatus($httpStatus);
        if ($httpStatus === 503) {
            $response->assertCookieMissing('synlab_guard');
        }
        // Original signed negative evidence can reconcile restored local control;
        // successful local history never creates missing/offline current authority.
        self::assertSame('confirmed', $replay->apply($verified));
        self::assertTrue(isset($this->control($raw)['revoked_at']));
        $this->assertSingleReceipt($verified);
        $this->getJson('/_rollback_lab/protected', $this->origin())->assertUnauthorized();
    }

    public static function restoreAuthorityStates(): array
    {
        return [['revoked', 401], ['missing', 503], ['offline', 503]];
    }

    public function test_restored_old_program_grant_cannot_override_independently_current_reduced_scope(): void
    {
        $raw = $this->login();
        $snapshot = $this->snapshot($raw);
        $grant = $this->securityDb->selectCollection('role_grants')->findOne(['_id' => $this->grantId])->getArrayCopy();
        $this->securityDb->selectCollection('role_grants')->updateOne(['_id' => $this->grantId], ['$set' => ['program_id' => (string) Str::uuid()]]);
        $this->authority->trust(app(MongoAuthorizationRepository::class)->context($this->userId, hash('sha256', $raw)));
        $this->securityDb->selectCollection('role_grants')->replaceOne(['_id' => $this->grantId], $grant);
        $this->restore($raw, $snapshot);
        $this->app->instance(SecurityStateAuthority::class, new ReceiptDenyListLabAuthority($this->authority, []));
        $this->getJson('/_rollback_lab/state', $this->origin())->assertOk()->assertJsonPath('sanctum', $this->userId);
        $this->getJson('/_rollback_lab/protected', $this->origin())->assertUnauthorized();
        self::assertSame($this->programId, $this->securityDb->selectCollection('role_grants')->findOne(['_id' => $this->grantId])['program_id']);
    }

    public function test_restored_privileged_mfa_timestamp_cannot_clear_external_reauthentication_requirement(): void
    {
        $raw = $this->login();
        $snapshot = $this->snapshot($raw);
        self::assertTrue(isset($snapshot['control']['mfa_verified_at']));
        $digest = hash('sha256', $raw);
        $current = $this->authority->records[$digest];
        $this->authority->records[$digest] = new AuthorityEvidence($current->userId, $digest, $current->securityEpoch, $current->authorizationDigest, $current->validUntil, requiresReauthentication: true);
        $this->restore($raw, $snapshot);
        $this->app->instance(SecurityStateAuthority::class, new ReceiptDenyListLabAuthority($this->authority, []));
        $this->getJson('/_rollback_lab/state', $this->origin())->assertOk()->assertJsonPath('sanctum', $this->userId);
        $this->getJson('/_rollback_lab/protected', $this->origin())->assertUnauthorized();
        self::assertTrue($this->authority->records[$digest]->requiresReauthentication);
    }

    public function test_negative_receipt_for_another_digest_does_not_revoke_this_current_session(): void
    {
        $raw = $this->login();
        $other = $this->receipt(bin2hex(random_bytes(20)), false);
        $this->app->instance(SecurityStateAuthority::class, new ReceiptDenyListLabAuthority($this->authority, [$other]));
        $this->getJson('/_rollback_lab/protected', $this->origin())->assertOk();
        // The overlay cannot manufacture evidence if its current source loses state.
        unset($this->authority->records[hash('sha256', $raw)]);
        $this->getJson('/_rollback_lab/protected', $this->origin())->assertStatus(503);
    }

    public function test_unverified_receipt_cannot_be_used_as_a_denial_fixture(): void
    {
        $verified = $this->receipt(bin2hex(random_bytes(20)), false);
        $this->expectException(\InvalidArgumentException::class);
        new ReceiptDenyListLabAuthority($this->authority, [$verified->receipt]);
    }

    private function login(): string
    {
        $anonymous = $this->getJson('/_rollback_lab/state', $this->origin())->assertOk();
        $this->withCookie('synlab_guard', $anonymous->getCookie('synlab_guard')->getValue());
        $response = $this->postJson('/_rollback_lab/login', ['password' => 'synthetic-guard-password', '_token' => $anonymous->json('csrf')], $this->origin())->assertNoContent();
        $raw = $response->getCookie('synlab_guard')->getValue();
        $this->withCookie('synlab_guard', $raw);
        $this->getJson('/_rollback_lab/protected', $this->origin())->assertOk();

        return $raw;
    }

    private function receipt(string $raw, bool $existingControl = true): VerifiedRevocationReceipt
    {
        $operation = $this->operations[] = (string) Str::uuid();
        $authorization = $existingControl ? app(MongoAuthorizationRepository::class)->context($this->userId, hash('sha256', $raw))->authorizationDigest() : str_repeat('b', 64);
        $command = new RevocationCommand(array_replace($this->commandData(), ['operationId' => $operation, 'subjectUserId' => $this->userId, 'sessionDigest' => hash('sha256', $raw), 'authorizationDigest' => $authorization, 'correlationId' => $this->correlation]));
        $independentFixture = new ReceiptAuthorityLab;

        return $independentFixture->verify($command, $independentFixture->revoke($command));
    }

    private function control(string $raw): mixed
    {
        return $this->securityDb->selectCollection('user_sessions')->findOne(['session_digest' => new Binary(hex2bin(hash('sha256', $raw)))]);
    }

    private function snapshot(string $raw): array
    {
        $envelope = $this->encrypted->selectCollection('envelopes_v1')->findOne(['_id' => hash('sha256', $raw)])->getArrayCopy();
        foreach (['generation', 'revision'] as $key) {
            $envelope[$key] = new Int64((string) $envelope[$key]);
        }
        $control = $this->control($raw)->getArrayCopy();
        $control['security_epoch'] = new Int64((string) $control['security_epoch']);

        return ['envelope' => $envelope, 'control' => $control];
    }

    private function restore(string $raw, array $snapshot): void
    {
        // Only this invocation's owned fixtures may be replaced, without upsert.
        if (! in_array($raw, $this->rawIds, true) || $snapshot['envelope']['_id'] !== hash('sha256', $raw) || ! in_array($snapshot['control']['_id'], $this->securityIds['user_sessions'] ?? [], true)) {
            throw new \LogicException('Owned rollback fixtures required.');
        }
        self::assertSame(1, $this->encrypted->selectCollection('envelopes_v1')->replaceOne(['_id' => hash('sha256', $raw)], $snapshot['envelope'], ['upsert' => false])->getMatchedCount());
        self::assertSame(1, $this->securityDb->selectCollection('user_sessions')->replaceOne(['_id' => $snapshot['control']['_id']], $snapshot['control'], ['upsert' => false])->getMatchedCount());
    }

    private function retire(string $raw): void
    {
        $handler = new EncryptedSessionLabStore($this->encrypted, $this->cipher, static fn () => throw new \LogicException('Retirement cannot issue.'));
        try {
            $handler->read($raw);
            $handler->retireOwned($raw);
        } finally {
            $handler->close();
        }
    }

    private function assertSingleReceipt(VerifiedRevocationReceipt $receipt): void
    {
        foreach (['security_decision_journal', 'audit_events'] as $name) {
            self::assertSame(1, $this->securityDb->selectCollection($name)->countDocuments(['_id' => $receipt->receipt->data['operationId']]));
        }
    }

    private function origin(): array
    {
        return ['Origin' => 'http://localhost'];
    }
}
