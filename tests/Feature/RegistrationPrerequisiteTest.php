<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Authentication\ChallengePolicy;
use App\Domain\Authentication\ChallengeVerifier;
use App\Domain\Governance\ApprovalRepository;
use App\Domain\Governance\CurrentApprovalAuthority;
use App\Domain\Governance\FeatureGatePolicy;
use App\Domain\Governance\GateApproval;
use App\Domain\Registration\RegistrationAdmissionPolicy;
use App\Domain\Registration\RegistrationIntent;
use App\Domain\Registration\RegistrationPrerequisiteService;
use App\Infrastructure\Registration\MongoRegistrationChallengeRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Database;
use Tests\Support\PrerequisiteProcessHarness;
use Tests\TestCase;

final class RegistrationPrerequisiteTest extends TestCase
{
    use PrerequisiteProcessHarness;

    private array $ids = [];

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->key = random_bytes(32);
    }

    protected function tearDown(): void
    {
        try {
            $this->stopPrerequisiteWorkers();
            if ($this->ids) {
                $this->db()->selectCollection('registration_challenges_v1')->deleteMany(['_id' => ['$in' => $this->ids]]);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function db(): Database
    {
        if (config('database.connections.mongodb.database') !== 'studentity_test_m0' || ! in_array(parse_url(config('database.connections.mongodb.dsn'), PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Exact local synthetic database required.');
        }

        return (new Client(config('database.connections.mongodb.dsn')))->selectDatabase('studentity_test_identity_prerequisites');
    }

    private function data(): array
    {
        return ['id' => (string) Str::uuid(), 'email' => 'synthetic@example.invalid', 'programId' => (string) Str::uuid(), 'departmentId' => (string) Str::uuid(), 'yearLevel' => '1', 'classification' => 'Incoming Freshman', 'termsVersion' => 'synthetic-terms-v1', 'termsHash' => hash('sha256', 'synthetic terms, not official text'), 'noticeVersion' => 'synthetic-notice-v1', 'noticeHash' => hash('sha256', 'synthetic notice, not official text'), 'acknowledged' => true, 'passwordHash' => null];
    }

    private function repo(int $attempts = 3): MongoRegistrationChallengeRepository
    {
        return new MongoRegistrationChallengeRepository($this->db(), new ChallengePolicy($attempts, 60000), new ChallengeVerifier($this->key));
    }

    private function challenge(): string
    {
        return $this->ids[] = (string) Str::uuid();
    }

    private function admission(RegistrationIntent $intent): RegistrationAdmissionPolicy
    {
        $version = $intent->noticeBundleFingerprint();
        $record = new GateApproval(['id' => (string) Str::uuid(), 'gate' => 'G-05', 'version' => $version, 'status' => 'approved', 'decisionRef' => 'synthetic:decision', 'authority' => 'Synthetic only', 'evidence' => 'urn:synthetic:evidence', 'effectiveAt' => 0, 'approvedAt' => 0, 'createdAt' => 0, 'schemaVersion' => 1, 'blocks' => 'registration']);
        $repo = new class($record) implements ApprovalRepository
        {
            public function __construct(private GateApproval $record) {}

            public function forGate(string $gate, string $version): array
            {
                return $version === $this->record->data['version'] ? [$this->record] : [];
            }
        };
        $authority = new class implements CurrentApprovalAuthority
        {
            public function isSynthetic(): bool
            {
                return true;
            }

            public function confirms(GateApproval $approval, string $feature, int $now): bool
            {
                return true;
            }
        };

        return new RegistrationAdmissionPolicy(new FeatureGatePolicy($repo, $authority, false));
    }

    public function test_intent_binds_exact_notices_and_acknowledgment_and_has_no_account_side_effect(): void
    {
        $data = $this->data();
        $data['passwordHash'] = Hash::make('synthetic-secret-password');
        $intent = new RegistrationIntent($data);
        $original = $intent->fingerprint();
        $data['email'] = 'changed@example.invalid';
        self::assertSame($original, $intent->fingerprint());
        $policy = $this->admission($intent);
        self::assertTrue($policy->allows($intent, $intent->noticeBundleFingerprint(), (int) (microtime(true) * 1000)));
        self::assertFalse($policy->allows($intent, hash('sha256', 'stale-notices'), (int) (microtime(true) * 1000)));
        $noAck = new RegistrationIntent(array_replace($this->data(), ['acknowledged' => false]));
        self::assertFalse($this->admission($noAck)->allows($noAck, $noAck->noticeBundleFingerprint(), (int) (microtime(true) * 1000)));
        ob_start();
        var_dump($intent);
        $debug = ob_get_clean();
        foreach (['synthetic-secret-password', $data['passwordHash'], 'synthetic@example.invalid'] as $secret) {
            self::assertFalse(str_contains($debug, $secret), 'Intent debug disclosed private input.');
        }
        self::assertSame(0, app('db')->connection('mongodb')->getDatabase()->selectCollection('users')->countDocuments(['_id' => $intent->id()]));
    }

    public function test_raw_password_is_rejected_and_purpose_recipient_and_intent_are_bound(): void
    {
        try {
            new RegistrationIntent(array_replace($this->data(), ['passwordHash' => 'raw-synthetic-password']));
            self::fail('Raw password accepted as a hash.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
        $data = $this->data();
        $intent = new RegistrationIntent($data);
        $repo = $this->repo();
        $id = $this->challenge();
        $repo->issue($id, $intent, '123456');
        self::assertFalse($repo->consume($id, $intent, 'password.reset', '123456'));
        self::assertFalse($repo->consume($id, new RegistrationIntent(array_replace($data, ['email' => 'other@example.invalid'])), 'registration.email', '123456'));
        self::assertFalse($repo->consume($id, new RegistrationIntent(array_replace($data, ['noticeHash' => hash('sha256', 'other')])), 'registration.email', '123456'));
        self::assertTrue($repo->consume($id, $intent, 'registration.email', '123456'));
        self::assertFalse($repo->consume($id, $intent, 'registration.email', '123456'));
        $row = $this->db()->selectCollection('registration_challenges_v1')->findOne(['_id' => $id]);
        self::assertSame(1, $row['attempts']);
        self::assertFalse(str_contains(json_encode($row, JSON_THROW_ON_ERROR), '123456'), 'Stored challenge disclosed OTP.');
    }

    public function test_atomic_attempt_budget_expiry_and_duplicate_issuance_never_reset_a_challenge(): void
    {
        $intent = new RegistrationIntent($this->data());
        $repo = $this->repo(2);
        $id = $this->challenge();
        $repo->issue($id, $intent, '123456');
        self::assertFalse($repo->consume($id, $intent, 'registration.email', '999999'));
        self::assertFalse($repo->consume($id, $intent, 'registration.email', '999999'));
        self::assertFalse($repo->consume($id, $intent, 'registration.email', '123456'));
        $issuanceError = null;
        try {
            $repo->issue($id, $intent, '123456');
        } catch (\RuntimeException $error) {
            $issuanceError = $error;
        }
        self::assertInstanceOf(\RuntimeException::class, $issuanceError);
        self::assertSame('Challenge issuance unavailable or conflicting.', $issuanceError->getMessage());
        self::assertSame(2, $this->db()->selectCollection('registration_challenges_v1')->findOne(['_id' => $id])['attempts']);
        $expired = $this->challenge();
        $repo->issue($expired, $intent, '123456');
        $this->db()->selectCollection('registration_challenges_v1')->updateOne(['_id' => $expired], ['$set' => ['expires_at' => new UTCDateTime(0)]]);
        self::assertFalse($repo->consume($expired, $intent, 'registration.email', '123456'));
    }

    public function test_single_correct_final_attempt_succeeds_without_exceeding_budget(): void
    {
        $intent = new RegistrationIntent($this->data());
        $repo = $this->repo(1);
        $id = $this->challenge();
        $repo->issue($id, $intent, '123456');
        self::assertTrue($repo->consume($id, $intent, 'registration.email', '123456'));
        self::assertFalse($repo->consume($id, $intent, 'registration.email', '123456'));
    }

    public function test_service_checks_current_admission_before_issuance_and_consumption(): void
    {
        $intent = new RegistrationIntent($this->data());
        $repo = $this->repo();
        $id = $this->challenge();
        $now = (int) (microtime(true) * 1000);
        $service = new RegistrationPrerequisiteService($this->admission($intent), $repo);
        $service->issue($id, $intent, $intent->noticeBundleFingerprint(), '123456', $now);
        $denied = new RegistrationPrerequisiteService(new RegistrationAdmissionPolicy(app(FeatureGatePolicy::class)), $repo);
        self::assertFalse($denied->verify($id, $intent, $intent->noticeBundleFingerprint(), '123456', $now));
        self::assertSame(0, $this->db()->selectCollection('registration_challenges_v1')->findOne(['_id' => $id])['attempts']);
        self::assertTrue($service->verify($id, $intent, $intent->noticeBundleFingerprint(), '123456', $now));
        self::assertFalse($service->verify($id, $intent, $intent->noticeBundleFingerprint(), '123456', $now));
        $other = $this->challenge();
        $admissionError = null;
        try {
            $denied->issue($other, $intent, $intent->noticeBundleFingerprint(), '123456', $now);
        } catch (\RuntimeException $error) {
            $admissionError = $error;
        }
        self::assertInstanceOf(\RuntimeException::class, $admissionError);
        self::assertSame(0, $this->db()->selectCollection('registration_challenges_v1')->countDocuments(['_id' => $other]));
    }

    public function test_separate_processes_can_consume_a_challenge_only_once(): void
    {
        $data = $this->data();
        $intent = new RegistrationIntent($data);
        $id = $this->challenge();
        $this->repo()->issue($id, $intent, '123456');
        $input = ['action' => 'consume', 'intent' => $data, 'challengeId' => $id, 'key' => base64_encode($this->key), 'code' => '123456'];
        $a = $this->startPrerequisiteWorker($input);
        $b = $this->startPrerequisiteWorker($input);
        self::assertSame(1, (int) $this->prerequisiteResult($a) + (int) $this->prerequisiteResult($b));
        $row = $this->db()->selectCollection('registration_challenges_v1')->findOne(['_id' => $id]);
        self::assertTrue(isset($row['consumed_at']));
        self::assertSame(1, $row['attempts']);
    }

    public function test_correct_and_incorrect_workers_share_the_final_attempt_budget(): void
    {
        $data = $this->data();
        $intent = new RegistrationIntent($data);
        $id = $this->challenge();
        $this->repo(1)->issue($id, $intent, '123456');
        $input = ['action' => 'consume', 'intent' => $data, 'challengeId' => $id, 'key' => base64_encode($this->key), 'code' => '123456'];
        $correct = $this->startPrerequisiteWorker($input);
        $incorrect = $this->startPrerequisiteWorker(array_replace($input, ['code' => '999999']));
        $consumed = $this->prerequisiteResult($correct);
        self::assertFalse($this->prerequisiteResult($incorrect));
        $row = $this->db()->selectCollection('registration_challenges_v1')->findOne(['_id' => $id]);
        self::assertSame(1, $row['attempts']);
        self::assertSame($consumed, isset($row['consumed_at']));
    }

    public function test_worker_fault_cannot_be_reported_as_a_valid_consumption_rejection(): void
    {
        $worker = $this->startPrerequisiteWorker(['action' => 'consume', 'intent' => $this->data(), 'challengeId' => $this->challenge(), 'key' => base64_encode(random_bytes(31)), 'code' => '123456']);
        $worker->wait();
        self::assertSame(2, $worker->getExitCode());
        self::assertSame('', $worker->getErrorOutput());
        self::assertSame('{"ok":false,"code":"worker_unavailable"}', trim($worker->getOutput()));
    }

    public function test_wrong_verification_key_cannot_consume_and_backend_refuses_accepted_database(): void
    {
        $intent = new RegistrationIntent($this->data());
        $repo = $this->repo();
        $id = $this->challenge();
        $repo->issue($id, $intent, '123456');
        $wrong = new MongoRegistrationChallengeRepository($this->db(), new ChallengePolicy(3, 60000), new ChallengeVerifier(random_bytes(32)));
        self::assertFalse($wrong->consume($id, $intent, 'registration.email', '123456'));
        self::assertTrue($repo->consume($id, $intent, 'registration.email', '123456'));
        $this->expectException(\InvalidArgumentException::class);
        new MongoRegistrationChallengeRepository(app('db')->connection('mongodb')->getDatabase(), new ChallengePolicy(3, 60000), new ChallengeVerifier($this->key));
    }
}
