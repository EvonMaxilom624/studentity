<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Registration\RegistrationPayload;
use App\Infrastructure\Registration\RegistrationLabDatabase;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\RegistrationTransactionFixtures;
use Tests\TestCase;

final class RegistrationClaimTransactionTest extends TestCase
{
    use RegistrationTransactionFixtures;

    public function test_atomic_claim_persists_original_children_and_restart_history_without_otp_resume(): void
    {
        $repo = $this->intents();
        $payload = $this->payload();
        $id = $payload->intent->id();
        $challenge = $this->uuid();
        $repo->start($payload, $challenge, '123456');
        $row = $this->registrationDb()->registration_intents_v1->findOne(['_id' => $id]);
        self::assertStringNotContainsString($payload->intent->recipient(), $row['payload_ciphertext']->getData());
        self::assertNull($repo->claim($id, $challenge, '000000'));
        self::assertSame(1, $this->registrationDb()->registration_challenges_v1->findOne(['_id' => $challenge])['attempts']);
        $claim = $repo->claim($id, $challenge, '123456');
        self::assertNotNull($claim);
        self::assertSame($id, $claim->data['operationId']);
        self::assertSame($claim->wire(), $this->intents()->loadClaim($id)->wire());
        self::assertNull($repo->claim($id, $challenge, '123456'));
        self::assertNull($this->registrationDb()->users->findOne(['_id' => $id]));
        self::assertSame(2, $this->registrationDb()->registration_challenges_v1->findOne(['_id' => $challenge])['attempts']);
    }

    public function test_last_wrong_attempt_and_expiry_never_claim(): void
    {
        $repo = $this->intents();
        $p = $this->payload();
        $ch = $this->uuid();
        $repo->start($p, $ch, '123456');
        for ($i = 0; $i < 3; $i++) {
            self::assertNull($repo->claim($p->intent->id(), $ch, '000000'));
        }
        self::assertNull($repo->claim($p->intent->id(), $ch, '123456'));
        self::assertSame(3, $this->registrationDb()->registration_challenges_v1->findOne(['_id' => $ch])['attempts']);
        $p = $this->payload();
        $ch = $this->uuid();
        $repo->start($p, $ch, '123456');
        $this->registrationDb()->registration_challenges_v1->updateOne(['_id' => $ch], ['$set' => ['expires_at' => new UTCDateTime(0)]]);
        self::assertNull($repo->claim($p->intent->id(), $ch, '123456'));
        self::assertNull($repo->loadClaim($p->intent->id()));
    }

    public function test_resend_cas_supersedes_old_code_and_cannot_reset_claim(): void
    {
        $repo = $this->intents();
        $p = $this->payload();
        $ch = $this->uuid();
        $next = $this->uuid();
        $id = $p->intent->id();
        $repo->start($p, $ch, '123456');
        self::assertTrue($repo->resend($id, 0, $next, '654321'));
        self::assertFalse($repo->resend($id, 0, $this->uuid(), '555555'));
        self::assertNull($repo->claim($id, $ch, '123456'));
        self::assertNotNull($repo->claim($id, $next, '654321'));
        self::assertFalse($repo->resend($id, 2, $this->uuid(), '555555'));
    }

    public function test_abort_rolls_back_consumption_attempt_and_original_claim(): void
    {
        $p = $this->payload();
        $ch = $this->uuid();
        $this->intents()->start($p, $ch, '123456');
        $repo = $this->intents(beforeCommit: static function () {
            throw new \RuntimeException('synthetic abort');
        });
        try {
            $repo->claim($p->intent->id(), $ch, '123456');
            self::fail('Expected aborted claim.');
        } catch (\RuntimeException $e) {
            self::assertSame('Registration transaction unavailable or conflicting.', $e->getMessage());
        }
        $row = $this->registrationDb()->registration_challenges_v1->findOne(['_id' => $ch]);
        self::assertSame(0, $row['attempts']);
        self::assertArrayNotHasKey('consumed_at', $row);
        self::assertNull($repo->loadClaim($p->intent->id()));
        self::assertNotNull($this->intents()->claim($p->intent->id(), $ch, '123456'));
    }

    public function test_ciphertext_tamper_and_unavailable_current_gate_deny_without_attempt(): void
    {
        $repo = $this->intents();
        $p = $this->payload();
        $ch = $this->uuid();
        $id = $p->intent->id();
        $repo->start($p, $ch, '123456');
        $this->registrationDb()->registration_intents_v1->updateOne(['_id' => $id], ['$set' => ['payload_ciphertext' => new Binary('tampered')]]);
        try {
            $repo->claim($id, $ch, '123456');
            self::fail('Tamper accepted');
        } catch (\RuntimeException) {
            self::assertSame(0, $this->registrationDb()->registration_challenges_v1->findOne(['_id' => $ch])['attempts']);
        }
        $p = $this->payload();
        $ch = $this->uuid();
        $repo->start($p, $ch, '123456');
        $this->expectException(\RuntimeException::class);
        $this->intents(static fn () => false)->claim($p->intent->id(), $ch, '123456');
    }

    public function test_accepted_database_is_rejected_before_any_schema_write(): void
    {
        $db = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase('studentity_test_m0');
        $this->expectException(\InvalidArgumentException::class);
        RegistrationLabDatabase::install($db);
    }

    #[DataProvider('races')]
    public function test_two_process_claim_resend_and_last_attempt_races(string $race): void
    {
        $repo = $this->intents();
        $p = $this->payload();
        $ch = $this->uuid();
        $id = $p->intent->id();
        $repo->start($p, $ch, '123456');
        if ($race === 'last-attempt') {
            self::assertNull($repo->claim($id, $ch, '000000'));
            self::assertNull($repo->claim($id, $ch, '000000'));
        }
        $input = ['action' => 'claim', 'id' => $id, 'challenge' => $ch, 'code' => $race === 'last-attempt' ? '000000' : '123456'];
        $inputs = [$input, $race === 'resend' ? ['action' => 'resend', 'id' => $id, 'challenge' => $this->uuid(), 'code' => '654321'] : $input];
        $barrierId = $this->uuid();
        $barrier = storage_path('app/private/registration-transaction-barriers/'.$barrierId);
        if (! mkdir($barrier, 0700, true) && ! is_dir($barrier)) {
            throw new \RuntimeException('Synthetic barrier unavailable.');
        }
        $workers = [];
        try {
            foreach ($inputs as $slot => $v) {
                $v['barrierId'] = $barrierId;
                $v['slot'] = $slot;
                $w = new Process([PHP_BINARY, base_path('tests/Support/registration-transaction-worker.php')], base_path(), ['APP_ENV' => 'testing']);
                $w->setTimeout(20);
                $w->setInput(json_encode($v, JSON_THROW_ON_ERROR));
                $w->start();
                $workers[] = $w;
            }
            $deadline = hrtime(true) + 5_000_000_000;
            while (hrtime(true) < $deadline) {
                clearstatcache();
                if (is_file($barrier.'/0.ready') && is_file($barrier.'/1.ready')) {
                    break;
                }
                if (! $workers[0]->isRunning() || ! $workers[1]->isRunning()) {
                    break;
                }
                usleep(10000);
            }
            self::assertFileExists($barrier.'/0.ready');
            self::assertFileExists($barrier.'/1.ready');
            self::assertTrue($workers[0]->isRunning());
            self::assertTrue($workers[1]->isRunning());
            self::assertSame($race === 'last-attempt' ? 2 : 0, $this->registrationDb()->registration_challenges_v1->findOne(['_id' => $ch])['attempts']);
            file_put_contents($barrier.'/release', 'release', LOCK_EX);
            $wins = 0;
            foreach ($workers as $w) {
                $w->wait();
                self::assertSame(0, $w->getExitCode(), $w->getOutput());
                self::assertSame('', $w->getErrorOutput());
                $wins += (int) json_decode($w->getOutput(), true, 8, JSON_THROW_ON_ERROR)['ok'];
            }
            self::assertFileExists($barrier.'/0.entered');
            self::assertFileExists($barrier.'/1.entered');
            self::assertSame($race === 'last-attempt' ? 0 : 1, $wins);
            if ($race === 'last-attempt') {
                self::assertSame(3, $this->registrationDb()->registration_challenges_v1->findOne(['_id' => $ch])['attempts']);
                self::assertNull($repo->loadClaim($id));
            }
            self::assertLessThanOrEqual(1, $this->registrationDb()->registration_activations_v1->countDocuments(['_id' => $id]));
        } finally {
            foreach ($workers as $w) {
                if ($w->isRunning()) {
                    $w->stop(0);
                }
            }
        }
    }

    public static function races(): array
    {
        return [['claim'], ['resend'], ['last-attempt']];
    }

    public function test_duplicate_challenge_aborts_new_intent_and_unknown_cipher_key_fences_read(): void
    {
        $repo = $this->intents();
        $p = $this->payload();
        $ch = $this->uuid();
        $repo->start($p, $ch, '123456');
        $next = $this->payload();
        try {
            $repo->start($next, $ch, '123456');
            self::fail('Duplicate accepted');
        } catch (\RuntimeException) {
            self::assertNull($this->registrationDb()->registration_intents_v1->findOne(['_id' => $next->intent->id()]));
        }
        $this->registrationDb()->registration_intents_v1->updateOne(['_id' => $p->intent->id()], ['$set' => ['payload_key_id' => 'missing-synthetic-key']]);
        $this->expectException(\RuntimeException::class);
        $repo->claim($p->intent->id(), $ch, '123456');
    }

    public function test_resend_fences_missing_original_current_challenge(): void
    {
        $r = $this->intents();
        $p = $this->payload();
        $ch = $this->uuid();
        $r->start($p, $ch, '123456');
        // Synthetic partial-state simulation without deleting evidence.
        $this->registrationDb()->registration_challenges_v1->updateOne(['_id' => $ch], ['$set' => ['is_current' => false]]);
        $this->expectException(\RuntimeException::class);
        $r->resend($p->intent->id(), 0, $this->uuid(), '654321');
    }

    #[DataProvider('challengePolicyAttacks')]
    public function test_mutable_challenge_row_cannot_override_pinned_policy_or_database_clock(string $attack): void
    {
        $r = $this->intents();
        $p = $this->payload();
        $ch = $this->uuid();
        $r->start($p, $ch, '123456');
        $db = $this->registrationDb();
        $row = $db->registration_challenges_v1->findOne(['_id' => $ch]);
        if ($attack === 'limits') {
            $set = ['max_attempts' => 10, 'attempts' => 3];
        } elseif ($attack === 'future') {
            $set = ['created_at' => new UTCDateTime((int) (string) $row['created_at'] + 30000), 'expires_at' => new UTCDateTime((int) (string) $row['expires_at'] + 30000)];
        } else {
            $set = ['expires_at' => new UTCDateTime((int) (string) $row['expires_at'] + 30000)];
        }
        $db->registration_challenges_v1->updateOne(['_id' => $ch], ['$set' => $set]);
        self::assertNull($r->claim($p->intent->id(), $ch, '123456'));
        self::assertNull($r->loadClaim($p->intent->id()));
    }

    public static function challengePolicyAttacks(): array
    {
        return [['limits'], ['future'], ['interval']];
    }

    public function test_future_acknowledgment_cannot_be_persisted_as_historical_evidence(): void
    {
        $data = $this->payload()->fields();
        $data['acknowledged_at'] = (new \DateTimeImmutable('+1 day'))->format('Y-m-d\TH:i:s.v\Z');
        $p = new RegistrationPayload($data);
        $this->expectException(\RuntimeException::class);
        $this->intents()->start($p, $this->uuid(), '123456');
    }

    public function test_recipient_digest_and_verification_keys_have_independent_identifiers_and_domains(): void
    {
        $r = $this->intents();
        $p = $this->payload();
        $ch = $this->uuid();
        $r->start($p, $ch, '123456');
        $row = $this->registrationDb()->registration_challenges_v1->findOne(['_id' => $ch]);
        self::assertSame('synthetic-recipient-v1', $row['recipient_key_id']);
        self::assertSame('synthetic-verification-v1', $row['verification_key_id']);
        self::assertSame(hash_hmac('sha256', 'studentity.registration.recipient.v1:'.$p->intent->recipient(), str_repeat('R', 32)), $row['recipient_digest']);
    }
}
