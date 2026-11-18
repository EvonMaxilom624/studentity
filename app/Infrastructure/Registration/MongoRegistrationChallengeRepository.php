<?php

declare(strict_types=1);

namespace App\Infrastructure\Registration;

use App\Domain\Authentication\ChallengePolicy;
use App\Domain\Authentication\ChallengeVerifier;
use App\Domain\Authentication\RegistrationChallengeRepository;
use App\Domain\Registration\RegistrationIntent;
use App\Infrastructure\Identity\PrerequisiteDatabase;
use MongoDB\BSON\Binary;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use MongoDB\Operation\FindOneAndUpdate;

/** Testing-only atomic backend. A consumed OTP is NOT account activation or authorization. */
final class MongoRegistrationChallengeRepository implements RegistrationChallengeRepository
{
    private Collection $rows;

    public function __construct(Database $db, private ChallengePolicy $policy, private ChallengeVerifier $verifier)
    {
        PrerequisiteDatabase::ensure($db, 'registration_challenges_v1');
        $this->rows = $db->selectCollection('registration_challenges_v1', ['readConcern' => new ReadConcern(ReadConcern::MAJORITY), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY), 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY)]);
    }

    private function id(string $id): void
    {
        if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $id)) {
            throw new \InvalidArgumentException('Invalid challenge identifier.');
        }
    }

    public function issue(string $id, RegistrationIntent $intent, #[\SensitiveParameter] string $code): void
    {
        $this->id($id);
        $digest = new Binary($this->verifier->digest($id, $intent, 'registration.email', $code));
        try {
            // Explicit insert-only issuance: an existing row fails unique _id rather
            // than resetting attempts/consumption. Every instant is the DB clock.
            $this->rows->updateOne(['_id' => $id, 'created_at' => ['$exists' => false]], [['$replaceWith' => ['_id' => $id, 'intent_id' => $intent->id(), 'intent_fingerprint' => $intent->fingerprint(), 'purpose' => 'registration.email', 'recipient_digest' => $this->verifier->recipientDigest($intent), 'code_digest' => $digest, 'created_at' => '$$NOW', 'expires_at' => ['$dateAdd' => ['startDate' => '$$NOW', 'unit' => 'millisecond', 'amount' => $this->policy->lifetimeMilliseconds]], 'attempts' => 0, 'max_attempts' => $this->policy->maxAttempts, 'schema_version' => 1]]], ['upsert' => true]);
        } catch (\Throwable) {
            throw new \RuntimeException('Challenge issuance unavailable or conflicting.');
        }
    }

    /** Trusted backend primitive; application admission must be checked by its calling service. */
    public function consume(string $id, RegistrationIntent $intent, string $purpose, #[\SensitiveParameter] string $code): bool
    {
        $this->id($id);
        if ($purpose !== 'registration.email') {
            return false;
        }
        try {
            $candidate = new Binary($this->verifier->digest($id, $intent, $purpose, $code));
        } catch (\InvalidArgumentException) {
            return false;
        }
        try {
            $row = $this->rows->findOneAndUpdate(['_id' => $id, 'intent_id' => $intent->id(), 'intent_fingerprint' => $intent->fingerprint(), 'purpose' => $purpose, 'recipient_digest' => $this->verifier->recipientDigest($intent), 'consumed_at' => ['$exists' => false], '$expr' => ['$and' => [['$gt' => ['$expires_at', '$$NOW']], ['$lt' => ['$attempts', '$max_attempts']]]]], [[
                '$set' => ['attempts' => ['$add' => ['$attempts', 1]], 'consumed_at' => ['$cond' => [['$eq' => ['$code_digest', $candidate]], '$$NOW', '$$REMOVE']]],
            ]], ['returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER, 'upsert' => false]);

            return $row !== null && isset($row['consumed_at']);
        } catch (\Throwable) {
            throw new \RuntimeException('Challenge verification outcome unavailable.');
        }
    }
}
