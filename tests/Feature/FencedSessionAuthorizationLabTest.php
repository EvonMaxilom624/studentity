<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Security\SecurityStateAuthority;
use App\Infrastructure\Security\AccessDecisionAuditRecorder;
use App\Infrastructure\Security\MongoAuthorizationRepository;
use App\Infrastructure\Security\UnavailableSecurityStateAuthority;
use Illuminate\Support\Str;
use MongoDB\BSON\Int64;
use MongoDB\Client;
use Tests\Support\GuardSessionLabHarness;
use Tests\TestCase;

final class FencedSessionAuthorizationLabTest extends TestCase
{
    use GuardSessionLabHarness;

    public function test_anonymous_and_bearer_only_requests_cannot_pass_the_real_sanctum_gate(): void
    {
        $this->getJson('/_guard_lab/protected')->assertUnauthorized()->assertJsonPath('code', 'authentication_required');
        $this->withToken('synthetic-disabled-bearer')->getJson('/_guard_lab/protected')->assertUnauthorized();
    }

    public function test_current_authority_allows_and_records_a_redacted_decision_through_the_fenced_cookie(): void
    {
        [$id, $token] = $this->signIn();
        $this->getJson('/_guard_lab/protected')->assertOk()->assertExactJson(['authorized' => true]);
        $event = $this->securityDb->selectCollection('audit_events')->findOne(['correlation_id' => $this->correlation, 'event_redacted.allowed' => true]);
        self::assertNotNull($event);
        self::assertSame($this->userId, $event['actor_user_id']);
        self::assertSame('profile.read', $event['event_redacted']['ability']);
        $json = json_encode($event, JSON_THROW_ON_ERROR);
        foreach ([$id, $token, 'synthetic-guard-password'] as $secret) {
            self::assertStringNotContainsString($secret, $json);
        }
    }

    public function test_existing_cookie_obeys_current_program_scope_despite_forged_query_parameters(): void
    {
        [$id] = $this->signIn();
        $this->securityDb->selectCollection('role_grants')->updateOne(['_id' => $this->grantId], ['$set' => ['program_id' => (string) Str::uuid()]]);
        $this->authority->trust(app(MongoAuthorizationRepository::class)->context($this->userId, hash('sha256', $id)));
        $this->getJson('/_guard_lab/protected?programId='.$this->programId.'&studentId='.$this->userId)->assertForbidden();
    }

    public function test_deactivated_account_is_denied_on_its_next_existing_cookie_request(): void
    {
        $this->signIn();
        $this->securityDb->selectCollection('users')->updateOne(['_id' => $this->userId], ['$set' => ['account_state' => 'disabled']]);
        $this->getJson('/_guard_lab/protected')->assertForbidden();
    }

    public function test_privileged_mfa_pending_session_cannot_read_the_resource(): void
    {
        $this->withMfa = false;
        $this->signIn();
        $this->getJson('/_guard_lab/protected')->assertForbidden();
    }

    public function test_epoch_change_invalidates_an_existing_guard_session(): void
    {
        $this->signIn();
        $this->securityDb->selectCollection('users')->updateOne(['_id' => $this->userId], ['$set' => ['security_epoch' => new Int64(5)]]);
        $this->getJson('/_guard_lab/protected')->assertUnauthorized();
    }

    public function test_default_unavailable_authority_denies_even_a_valid_guard_cookie(): void
    {
        $this->signIn();
        $this->app->bind(SecurityStateAuthority::class, UnavailableSecurityStateAuthority::class);
        $this->getJson('/_guard_lab/protected')->assertStatus(503)->assertJsonPath('code', 'security_state_unavailable')->assertCookieMissing('synlab_guard');
    }

    public function test_independent_revocation_is_enforced_while_transport_and_guard_still_rehydrate(): void
    {
        [$id] = $this->signIn();
        $this->authority->revoke($this->userId, hash('sha256', $id));
        $this->getJson('/_guard_lab/state')->assertOk()->assertJsonPath('sanctum', $this->userId);
        $this->getJson('/_guard_lab/protected')->assertUnauthorized();
    }

    public function test_security_logout_revokes_authority_and_local_record_and_persists_anonymous_replacement(): void
    {
        [$old, $token] = $this->signIn();
        $response = $this->postJson('/_guard_lab/revoke', ['_token' => $token])->assertNoContent();
        $new = $response->getCookie('synlab_guard')->getValue();
        self::assertNotSame($old, $new);
        self::assertNotSame($token, $this->guardPayload($new)['_token']);
        self::assertTrue($this->authority->evidence($this->userId, hash('sha256', $old))->revoked);
        self::assertNotNull($this->securityDb->selectCollection('user_sessions')->findOne(['user_id' => $this->userId])['revoked_at']);
        self::assertSame(1, $this->securityDb->selectCollection('audit_events')->countDocuments(['correlation_id' => $this->correlation, 'event_redacted.ability' => 'session.revoke']));
        $this->withCookie('synlab_guard', $new)->getJson('/_guard_lab/protected')->assertUnauthorized();
        $this->withCookie('synlab_guard', $old)->getJson('/_guard_lab/state')->assertStatus(409);
    }

    public function test_restoring_owned_transport_and_local_security_record_cannot_undo_independent_logout(): void
    {
        [$id, $token] = $this->signIn();
        $transport = $this->lab->selectCollection('fenced_sessions')->findOne(['_id' => $id])->getArrayCopy();
        $control = $this->securityDb->selectCollection('user_sessions')->findOne(['user_id' => $this->userId])->getArrayCopy();
        // Native PHP integers would otherwise re-encode small BSON longs as int32.
        $control['security_epoch'] = new Int64((string) $control['security_epoch']);
        $transport['generation'] = new Int64((string) $transport['generation']);
        $transport['revision'] = new Int64((string) $transport['revision']);
        $this->postJson('/_guard_lab/revoke', ['_token' => $token])->assertNoContent();
        $this->lab->selectCollection('fenced_sessions')->replaceOne(['_id' => $id], $transport);
        $this->securityDb->selectCollection('user_sessions')->replaceOne(['_id' => $control['_id']], $control);
        $this->getJson('/_guard_lab/state')->assertOk()->assertJsonPath('sanctum', $this->userId);
        $this->getJson('/_guard_lab/protected')->assertUnauthorized();
        self::assertTrue($this->authority->evidence($this->userId, hash('sha256', $id))->revoked);
    }

    public function test_failed_authority_acknowledgment_still_tombstones_old_transport_without_claiming_success(): void
    {
        [$id, $token] = $this->signIn();
        $this->authority->unavailable = true;
        $this->postJson('/_guard_lab/revoke', ['_token' => $token])->assertStatus(503)->assertCookieMissing('synlab_guard');
        self::assertFalse(isset($this->securityDb->selectCollection('user_sessions')->findOne(['user_id' => $this->userId])['revoked_at']));
        self::assertTrue($this->lab->selectCollection('fenced_sessions')->findOne(['_id' => $id])['revoked']);
        $this->getJson('/_guard_lab/state')->assertStatus(409);
    }

    public function test_real_audit_backend_outage_cannot_allow_an_otherwise_trusted_session(): void
    {
        $this->signIn();
        $database = (new Client('mongodb://127.0.0.1:1/?serverSelectionTimeoutMS=100&connectTimeoutMS=100'))->selectDatabase('studentity_test_m0');
        $this->app->instance(AccessDecisionAuditRecorder::class, new AccessDecisionAuditRecorder($database));
        $this->getJson('/_guard_lab/protected')->assertStatus(503)->assertJsonPath('code', 'security_state_unavailable')->assertCookieMissing('synlab_guard');
    }
}
