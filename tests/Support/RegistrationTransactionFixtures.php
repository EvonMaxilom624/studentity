<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Authentication\ChallengePolicy;
use App\Domain\Authentication\ChallengeVerifier;
use App\Domain\Registration\RegistrationPayload;
use App\Domain\Registration\RegistrationRecipientKey;
use App\Infrastructure\Registration\MongoRegistrationIntentRepository;
use App\Infrastructure\Registration\RegistrationLabDatabase;
use App\Infrastructure\Registration\RegistrationPayloadCipher;
use Illuminate\Support\Str;
use MongoDB\Client;
use MongoDB\Database;

trait RegistrationTransactionFixtures
{
    private function uuid(): string
    {
        return (string) Str::uuid();
    }

    private function registrationDb(): Database
    {
        return (new Client(config('database.connections.mongodb.dsn')))->selectDatabase('studentity_test_registration_activation_v1', ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
    }

    private function payload(?string $email = null, bool $password = false, ?\DateTimeImmutable $observedAt = null): RegistrationPayload
    {
        $acknowledgedAt = ($observedAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'))->modify('-1 minute');

        return new RegistrationPayload(['purpose' => 'studentity.registration.intent-payload.v1', 'schemaVersion' => 1,
            'intent' => ['id' => $this->uuid(), 'email' => $email ?? $this->uuid().'@example.invalid', 'programId' => $this->uuid(), 'departmentId' => $this->uuid(), 'yearLevel' => '1', 'classification' => 'Incoming Freshman', 'termsVersion' => 'synthetic-terms-'.$this->uuid(), 'termsHash' => hash('sha256', 'synthetic terms'), 'noticeVersion' => 'synthetic-privacy-'.$this->uuid(), 'noticeHash' => hash('sha256', 'synthetic privacy'), 'acknowledged' => true, 'passwordHash' => $password ? password_hash('synthetic-only-password', PASSWORD_BCRYPT, ['cost' => 4]) : null],
            'terms_notice_id' => $this->uuid(), 'privacy_notice_id' => $this->uuid(), 'acknowledged_at' => $acknowledgedAt->format('Y-m-d\TH:i:s.v\Z')]);
    }

    private function cipher(): RegistrationPayloadCipher
    {
        return new RegistrationPayloadCipher('synthetic-payload-v1', ['synthetic-payload-v1' => str_repeat('K', 32)]);
    }

    private function intents(?\Closure $gate = null, ?\Closure $beforeCommit = null, int $processingMilliseconds = 120000): MongoRegistrationIntentRepository
    {
        RegistrationLabDatabase::install($this->registrationDb());

        return new MongoRegistrationIntentRepository($this->registrationDb(), $this->cipher(), new ChallengeVerifier(str_repeat('V', 32)), new ChallengePolicy(3, 60000), 'synthetic-policy-v1', $processingMilliseconds, $gate ?? static fn () => true, $beforeCommit, new RegistrationRecipientKey('synthetic-recipient-v1', str_repeat('R', 32)), 'synthetic-verification-v1');
    }
}
