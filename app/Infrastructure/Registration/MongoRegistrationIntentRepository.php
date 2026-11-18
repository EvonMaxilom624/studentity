<?php

declare(strict_types=1);

namespace App\Infrastructure\Registration;

use App\Domain\Authentication\ChallengePolicy;
use App\Domain\Authentication\ChallengeVerifier;
use App\Domain\Registration\RegistrationClaim;
use App\Domain\Registration\RegistrationPayload;
use App\Domain\Registration\RegistrationRecipientKey;
use App\Domain\Security\Authority\AuthorityContractFields as F;
use Illuminate\Support\Str;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Operation\FindOneAndUpdate;

/** Isolated backend primitive, deliberately unbound from application runtime. */
final class MongoRegistrationIntentRepository
{
    public function __construct(private Database $db, private RegistrationPayloadCipher $cipher, private ChallengeVerifier $verifier, private ChallengePolicy $policy, private string $policyRef, private int $processingMilliseconds, private ?\Closure $gate = null, private ?\Closure $beforeCommit = null, private ?RegistrationRecipientKey $recipientKey = null, private string $verificationKeyId = '')
    {
        RegistrationLabDatabase::assertReady($db);
        F::reference($policyRef);
        F::reference($verificationKeyId);
        if ($recipientKey === null || $recipientKey->id === $verificationKeyId) {
            throw new \InvalidArgumentException('Independent identified registration MAC keys required.');
        }
        if ($processingMilliseconds < 1 || $processingMilliseconds > 86400000) {
            throw new \InvalidArgumentException('Bounded processing policy required.');
        }
    }

    private function allow(string $stage, RegistrationPayload $p): void
    {
        if ($this->gate === null || ($this->gate)($stage, $p) !== true) {
            throw new \RuntimeException('Current registration admission unavailable.');
        }
    }

    private function now(array $options): UTCDateTime
    {
        return iterator_to_array($this->db->aggregate([['$documents' => [(object) []]], ['$project' => ['now' => '$$NOW']]], $options), false)[0]['now'];
    }

    public static function context(string $kind, string $id, string $fingerprint, string $hash): string
    {
        return 'studentity.registration.'.$kind.'.v1:'.$id.':'.$fingerprint.':'.$hash;
    }

    private function challenge(string $id, RegistrationPayload $p, string $code, UTCDateTime $now): array
    {
        F::uuid($id);

        return ['_id' => $id, 'schema_version' => 1, 'intent_id' => $p->intent->id(), 'intent_fingerprint' => $p->intent->fingerprint(), 'purpose' => 'registration.email', 'recipient_digest' => $this->recipientKey->digest($p->intent), 'recipient_key_id' => $this->recipientKey->id, 'code_digest' => new Binary($this->verifier->digest($id, $p->intent, 'registration.email', $code)), 'verification_key_id' => $this->verificationKeyId, 'policy_ref' => $this->policyRef, 'attempts' => 0, 'max_attempts' => $this->policy->maxAttempts, 'is_current' => true, 'created_at' => $now, 'expires_at' => new UTCDateTime((int) (string) $now + $this->policy->lifetimeMilliseconds)];
    }

    private function challengeBinding(RegistrationPayload $p, UTCDateTime $now): array
    {
        return ['intent_id' => $p->intent->id(), 'intent_fingerprint' => $p->intent->fingerprint(), 'recipient_digest' => $this->recipientKey->digest($p->intent), 'recipient_key_id' => $this->recipientKey->id, 'verification_key_id' => $this->verificationKeyId, 'policy_ref' => $this->policyRef, 'purpose' => 'registration.email', 'max_attempts' => $this->policy->maxAttempts, 'is_current' => true, 'consumed_at' => ['$exists' => false], 'created_at' => ['$lte' => $now], 'expires_at' => ['$gt' => $now],
            '$expr' => ['$and' => [['$lt' => ['$attempts', '$max_attempts']], ['$eq' => [['$subtract' => ['$expires_at', '$created_at']], $this->policy->lifetimeMilliseconds]]]]];
    }

    public function start(RegistrationPayload $p, string $challengeId, #[\SensitiveParameter] string $code): void
    {
        RegistrationLabDatabase::transaction($this->db, function ($o) use ($p, $challengeId, $code) {
            $this->allow('start', $p);
            $now = $this->now($o);
            $id = $p->intent->id();
            if (new \DateTimeImmutable($p->fields()['acknowledged_at']) > $now->toDateTimeImmutable()) {
                throw new \RuntimeException('Acknowledgment is after registration observation.');
            }
            $row = ['_id' => $id, 'schema_version' => 1, 'intent_fingerprint' => $p->intent->fingerprint(), 'sealed_payload_hash' => $p->hash(), 'notice_bundle_fingerprint' => $p->intent->noticeBundleFingerprint(), 'recipient_digest' => $this->recipientKey->digest($p->intent), 'recipient_key_id' => $this->recipientKey->id, 'payload_ciphertext' => $this->cipher->seal($p->wire(), self::context('intent', $id, $p->intent->fingerprint(), $p->hash())), 'payload_key_id' => $this->cipher->keyId, 'state' => 'pending', 'revision' => new Int64(0), 'created_at' => $now, 'updated_at' => $now, 'expires_at' => new UTCDateTime((int) (string) $now + $this->policy->lifetimeMilliseconds)];
            $this->db->registration_intents_v1->insertOne($row, $o);
            $this->db->registration_challenges_v1->insertOne($this->challenge($challengeId, $p, $code, $now), $o);
        });
    }

    public function payload(string $id, array $options = []): RegistrationPayload
    {
        F::uuid($id);
        $r = $this->db->registration_intents_v1->findOne(['_id' => $id], $options);
        if (! $r) {
            throw new \RuntimeException('Original registration intent unavailable.');
        }
        $p = RegistrationPayload::fromJson($this->cipher->open($r['payload_ciphertext'], $r['payload_key_id'], self::context('intent', $id, $r['intent_fingerprint'], $r['sealed_payload_hash'])));
        if ($p->intent->id() !== $id || $p->intent->fingerprint() !== $r['intent_fingerprint'] || $p->hash() !== $r['sealed_payload_hash'] || $p->intent->noticeBundleFingerprint() !== $r['notice_bundle_fingerprint'] || $this->recipientKey->digest($p->intent) !== $r['recipient_digest'] || $r['recipient_key_id'] !== $this->recipientKey->id) {
            throw new \RuntimeException('Registration payload binding mismatch.');
        }

        if (new \DateTimeImmutable($p->fields()['acknowledged_at']) > $r['created_at']->toDateTimeImmutable()) {
            throw new \RuntimeException('Acknowledgment is after original intent creation.');
        }

        return $p;
    }

    public function resend(string $id, int $expectedRevision, string $challengeId, #[\SensitiveParameter] string $code): bool
    {
        return RegistrationLabDatabase::transaction($this->db, function ($o) use ($id, $expectedRevision, $challengeId, $code) {
            $p = $this->payload($id, $o);
            $this->allow('resend', $p);
            $now = $this->now($o);
            $r = $this->db->registration_intents_v1->updateOne(['_id' => $id, 'state' => 'pending', 'revision' => new Int64($expectedRevision), 'expires_at' => ['$gt' => $now]], ['$inc' => ['revision' => new Int64(1)], '$set' => ['updated_at' => $now]], $o);
            if ($r->getModifiedCount() !== 1) {
                return false;
            }
            $superseded = $this->db->registration_challenges_v1->updateMany($this->challengeBinding($p, $now), ['$set' => ['is_current' => false]], $o);
            if ($superseded->getModifiedCount() !== 1) {
                throw new \RuntimeException('Original current registration challenge unavailable.');
            }
            $this->db->registration_challenges_v1->insertOne($this->challenge($challengeId, $p, $code, $now), $o);

            return true;
        });
    }

    public function claim(string $id, string $challengeId, #[\SensitiveParameter] string $code): ?RegistrationClaim
    {
        F::uuid($id);
        F::uuid($challengeId);

        return RegistrationLabDatabase::transaction($this->db, function ($o) use ($id, $challengeId, $code) {
            $p = $this->payload($id, $o);
            $this->allow('claim', $p);
            $now = $this->now($o);
            $pending = $this->db->registration_intents_v1->findOne(['_id' => $id, 'state' => 'pending', 'expires_at' => ['$gt' => $now], 'created_at' => ['$lte' => $now]], $o);
            if (! $pending) {
                return null;
            }
            try {
                $candidate = new Binary($this->verifier->digest($challengeId, $p->intent, 'registration.email', $code));
            } catch (\InvalidArgumentException) {
                return null;
            }
            $row = $this->db->registration_challenges_v1->findOneAndUpdate(['_id' => $challengeId] + $this->challengeBinding($p, $now), ['$inc' => ['attempts' => 1]], $o + ['returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]);
            if (! $row || ! hash_equals($row['code_digest']->getData(), $candidate->getData())) {
                return null;
            }
            $this->db->registration_challenges_v1->updateOne(['_id' => $challengeId], ['$set' => ['is_current' => false, 'consumed_at' => $now, 'claim_operation_id' => $id]], $o);
            $changed = $this->db->registration_intents_v1->updateOne(['_id' => $id, 'state' => 'pending', 'revision' => $pending['revision']], ['$set' => ['state' => 'claimed', 'operation_id' => $id, 'updated_at' => $now], '$inc' => ['revision' => new Int64(1)]], $o);
            if ($changed->getModifiedCount() !== 1) {
                throw new \RuntimeException('Claim conflict.');
            }
            $children = [];
            foreach (['emailId', 'termsAckId', 'privacyAckId', 'auditId', 'journalId'] as $k) {
                $children[$k] = (string) Str::uuid();
            }$children['credentialId'] = $p->fields()['intent']['passwordHash'] === null ? null : (string) Str::uuid();
            $deadline = new UTCDateTime((int) (string) $now + $this->processingMilliseconds);
            $claim = new RegistrationClaim(['purpose' => 'studentity.registration.claim.v1', 'schemaVersion' => 1, 'operationId' => $id, 'intentId' => $id, 'userId' => $id, 'intentFingerprint' => $p->intent->fingerprint(), 'sealedPayloadHash' => $p->hash(), 'noticeBundleFingerprint' => $p->intent->noticeBundleFingerprint(), 'challengeId' => $challengeId, 'verifiedAt' => $now->toDateTime()->format('Y-m-d\TH:i:s.v\Z'), 'processingDeadline' => $deadline->toDateTime()->format('Y-m-d\TH:i:s.v\Z'), 'correlationId' => (string) Str::uuid(), 'childIds' => $children]);
            $this->db->registration_activations_v1->insertOne(['_id' => $id, 'schema_version' => 1, 'intent_id' => $id, 'user_id' => $id, 'intent_fingerprint' => $p->intent->fingerprint(), 'sealed_payload_hash' => $p->hash(), 'notice_bundle_fingerprint' => $p->intent->noticeBundleFingerprint(), 'challenge_id' => $challengeId, 'claim_ciphertext' => $this->cipher->seal($claim->wire(), self::context('claim', $id, $claim->fingerprint(), $p->hash())), 'claim_key_id' => $this->cipher->keyId, 'claim_fingerprint' => $claim->fingerprint(), 'state' => 'claimed', 'revision' => new Int64(0), 'created_at' => $now, 'verified_at' => $now, 'processing_deadline' => $deadline], $o);
            if ($this->beforeCommit !== null) {
                ($this->beforeCommit)();
            }

            return $claim;
        });
    }

    /** Historical data only: never implicitly resumes or authorizes a consumed OTP. */
    public function loadClaim(string $id, array $options = []): ?RegistrationClaim
    {
        F::uuid($id);
        $r = $this->db->registration_activations_v1->findOne(['_id' => $id], ($options ?: ['readConcern' => new ReadConcern(ReadConcern::MAJORITY), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY)]));
        if (! $r) {
            return null;
        }
        $c = RegistrationClaim::fromJson($this->cipher->open($r['claim_ciphertext'], $r['claim_key_id'], self::context('claim', $id, $r['claim_fingerprint'], $r['sealed_payload_hash'])));
        if ($c->fingerprint() !== $r['claim_fingerprint'] || $c->data['operationId'] !== $id || $c->data['sealedPayloadHash'] !== $r['sealed_payload_hash'] || $c->data['intentFingerprint'] !== $r['intent_fingerprint'] || $c->data['noticeBundleFingerprint'] !== $r['notice_bundle_fingerprint'] || $c->data['challengeId'] !== $r['challenge_id'] || $c->data['verifiedAt'] !== $r['verified_at']->toDateTime()->format('Y-m-d\TH:i:s.v\Z') || $c->data['processingDeadline'] !== $r['processing_deadline']->toDateTime()->format('Y-m-d\TH:i:s.v\Z')) {
            throw new \RuntimeException('Registration claim binding mismatch.');
        }

        return $c;
    }
}
