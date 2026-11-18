<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identifiers\VerifiedAllocationReservation;
use App\Domain\Registration\ActivationAuthorityUnavailable;
use App\Domain\Registration\ActivationCommand;
use App\Domain\Registration\ActivationDecision;
use App\Domain\Registration\ActivationLookup;
use App\Domain\Registration\RegistrationActivationAuthority;
use App\Domain\Registration\RegistrationService;
use App\Domain\Registration\VerifiedActivationAbsence;
use App\Domain\Registration\VerifiedActivationDecision;
use App\Domain\Security\Authority\AuthorityProofPolicy;
use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Infrastructure\Registration\MongoRegistrationActivationRepository;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RegistrationActivationFixtures;
use Tests\Support\RegistrationTransactionFixtures;
use Tests\TestCase;

final class RegistrationActivationTransactionTest extends TestCase
{
    use RegistrationActivationFixtures, RegistrationTransactionFixtures;

    private function transactionPolicy(bool $issuer = false): AuthorityProofPolicy
    {
        $key = $issuer ? 'issuer-key' : 'admission-key';

        return new AuthorityProofPolicy($issuer ? 'synthetic-issuer' : 'synthetic-registration', $issuer ? 'issuer-1' : 'admission-1', [$key], fn ($body, $proof, $id) => $id === $key && hash_equals(hash_hmac('sha256', $body, $this->proofKey($issuer)), $proof), new \DateTimeImmutable('+1 hour'));
    }

    private function repository(?\Closure $gate = null, ?\Closure $beforeCommit = null): MongoRegistrationActivationRepository
    {
        return new MongoRegistrationActivationRepository($this->registrationDb(), $this->cipher(), $this->intents(), $this->transactionPolicy(), $this->transactionPolicy(true), $gate ?? static fn () => true, $beforeCommit);
    }

    private function prepared(?string $email = null, bool $password = false, string $allocationState = 'reserved', int $processingMilliseconds = 120000): array
    {
        $p = $this->payload($email, $password);
        $r = $this->intents(processingMilliseconds: $processingMilliseconds);
        $ch = $this->uuid();
        $r->start($p, $ch, '123456');
        $claim = $r->claim($p->intent->id(), $ch, '123456');
        // Times derive from the actual DB-clock claim; these are synthetic proofs, not live issuers.
        $time = new \DateTimeImmutable($claim->data['verifiedAt']);
        $wireTime = static fn ($t) => $t->format('Y-m-d\TH:i:s.v\Z');
        $reservation = $this->reservation($claim, ['systemId' => 'synthetic-'.$this->uuid(), 'state' => $allocationState, 'authorityRef' => 'urn:synthetic:'.$this->uuid(), 'committedAt' => $wireTime($time)]);
        $verified = VerifiedAllocationReservation::verify($claim, $reservation, $this->transactionPolicy(true));
        $command = ActivationCommand::prepare($claim, $verified, 'synthetic-registration', 'admission-1', $wireTime($time));
        $this->seedNotices($p);
        $this->repository()->prepare($command);
        $decision = $this->decision($command, ['decisionId' => $this->uuid(), 'authorityRef' => 'urn:synthetic:'.$this->uuid(), 'committedAt' => $wireTime($time)]);

        return [$p, $claim, $command, VerifiedActivationDecision::verify($command, $decision, $this->transactionPolicy(), $this->transactionPolicy(true)), $verified];
    }

    private function seedNotices($p): void
    {
        $d = $p->fields();
        foreach (['terms' => ['terms_notice_id', 'termsVersion', 'termsHash'], 'privacy_notice' => ['privacy_notice_id', 'noticeVersion', 'noticeHash']] as $kind => $fields) {
            [$id,$version,$hash] = $fields;
            $this->registrationDb()->official_notice_versions->insertOne(['_id' => $d[$id], 'document_kind' => $kind, 'version_label' => $d['intent'][$version], 'content_hash' => new Binary(hex2bin($d['intent'][$hash])), 'effective_at' => new UTCDateTime(0), 'approval_id' => $this->uuid(), 'stored_object_id' => $this->uuid(), 'status' => 'active', 'schema_version' => 1]);
        }
    }

    public function test_final_apply_retry_keeps_original_children_and_reserved_issuer_state(): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared(password: true);
        $r = $this->repository();
        self::assertSame($command->wire(), $r->originalCommand($p->intent->id())->wire());
        self::assertSame('applied', $r->apply($decision, $allocation));
        self::assertSame('applied', $this->repository()->apply($decision, $allocation));
        $db = $this->registrationDb();
        $id = $p->intent->id();
        $user = $db->users->findOne(['_id' => $id]);
        self::assertSame('provisional', $user['account_state']);
        self::assertSame('student', $user['kind']);
        self::assertSame(1, $db->user_emails->countDocuments(['user_id' => $id]));
        self::assertSame($claim->data['childIds']['emailId'], $db->user_emails->findOne(['user_id' => $id])['_id']);
        self::assertSame(2, $db->notice_acknowledgments->countDocuments(['user_id' => $id]));
        self::assertSame($claim->data['childIds']['credentialId'], $db->credentials->findOne(['user_id' => $id])['_id']);
        self::assertSame('reserved', $db->system_id_allocations_v1->findOne(['_id' => $id])['state']);
        self::assertSame(0, $db->selectCollection('user_sessions')->countDocuments(['user_id' => $id]));
    }

    public function test_unknown_authority_and_denied_current_gate_cannot_apply_historical_proof(): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared();
        $service = new RegistrationService(app(RegistrationActivationAuthority::class), $this->repository(), $this->transactionPolicy(), $this->transactionPolicy(true));
        try {
            $service->complete($p->intent->id(), $allocation);
            self::fail('Unavailable authority accepted');
        } catch (\RuntimeException) {
            self::assertNull($this->registrationDb()->users->findOne(['_id' => $p->intent->id()]));
        }
        $this->expectException(\RuntimeException::class);
        $this->repository(static fn () => false)->apply($decision, $allocation);
    }

    public function test_duplicate_email_aborts_all_new_account_records(): void
    {
        $email = $this->uuid().'@example.invalid';
        [$first,$c,$cmd,$d,$a] = $this->prepared($email);
        $this->repository()->apply($d, $a);
        [$p,$claim,$command,$decision,$allocation] = $this->prepared($email);
        try {
            $this->repository()->apply($decision, $allocation);
            self::fail('Duplicate accepted');
        } catch (\RuntimeException) {
            $db = $this->registrationDb();
            $id = $p->intent->id();
            self::assertNull($db->users->findOne(['_id' => $id]));
            self::assertSame(0, $db->notice_acknowledgments->countDocuments(['user_id' => $id]));
            self::assertNull($db->system_id_allocations_v1->findOne(['_id' => $id]));
            self::assertSame('prepared', $db->registration_activations_v1->findOne(['_id' => $id])['state']);
        }
    }

    public function test_later_recovery_and_false_local_restore_never_recreate_or_reset_account(): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared();
        $r = $this->repository();
        $r->apply($decision, $allocation);
        $id = $p->intent->id();
        $this->registrationDb()->users->updateOne(['_id' => $id], ['$set' => ['account_state' => 'disabled', 'security_epoch' => new Int64(9)]]);
        try {
            $r->apply($decision, $allocation);
            self::fail('Recovered account accepted');
        } catch (\RuntimeException) {
            self::assertSame('disabled', $this->registrationDb()->users->findOne(['_id' => $id])['account_state']);
        }
        // A local prepared flag beside an existing account is conflicting evidence, never an upsert grant.
        $this->registrationDb()->registration_activations_v1->updateOne(['_id' => $id], ['$set' => ['state' => 'prepared'], '$unset' => ['decision_ref' => '', 'decision_hash' => '', 'decision_at' => '', 'applied_at' => '']]);
        $this->expectException(\RuntimeException::class);
        $r->apply($decision, $allocation);
    }

    public function test_retired_current_allocation_and_changed_original_command_are_rejected(): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared();
        $retired = $this->reservation($claim, array_replace($allocation->reservation->data, ['state' => 'retired', 'revision' => '2']));
        try {
            $this->repository()->apply($decision, VerifiedAllocationReservation::verify($claim, $retired, $this->transactionPolicy(true)));
            self::fail('Retired accepted');
        } catch (\RuntimeException) {
            self::assertNull($this->registrationDb()->users->findOne(['_id' => $p->intent->id()]));
        }
        $data = $command->data;
        $data['preparedAt'] = (new \DateTimeImmutable($data['preparedAt']))->modify('+1 millisecond')->format('Y-m-d\TH:i:s.v\Z');
        $this->expectException(\RuntimeException::class);
        $this->repository()->prepare(new ActivationCommand($data));
    }

    public function test_rejection_records_denial_without_account_and_apply_abort_is_atomic(): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared();
        $rejected = $this->decision($command, ['result' => 'rejected', 'committedAt' => $command->data['preparedAt']]);
        self::assertSame('denied', $this->repository()->apply(VerifiedActivationDecision::verify($command, $rejected, $this->transactionPolicy(), $this->transactionPolicy(true)), $allocation));
        self::assertNull($this->registrationDb()->users->findOne(['_id' => $p->intent->id()]));
        [$p,$claim,$command,$decision,$allocation] = $this->prepared();
        try {
            $this->repository(beforeCommit: static function () {
                throw new \RuntimeException('synthetic abort');
            })->apply($decision, $allocation);
            self::fail('Abort ignored');
        } catch (\RuntimeException) {
            self::assertNull($this->registrationDb()->users->findOne(['_id' => $p->intent->id()]));
            self::assertSame('prepared', $this->registrationDb()->registration_activations_v1->findOne(['_id' => $p->intent->id()])['state']);
        }
    }

    public function test_later_issuer_revision_updates_only_projection_with_original_command_preserved(): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared();
        $r = $this->repository();
        $r->apply($decision, $allocation);
        $issued = $this->reservation($claim, array_replace($allocation->reservation->data, ['state' => 'issued', 'revision' => '2', 'authorityRef' => 'urn:synthetic:issued:'.$this->uuid()]));
        self::assertSame('applied', $r->apply($decision, VerifiedAllocationReservation::verify($claim, $issued, $this->transactionPolicy(true))));
        self::assertSame('issued', $this->registrationDb()->system_id_allocations_v1->findOne(['_id' => $p->intent->id()])['state']);
        self::assertSame($command->wire(), $r->originalCommand($p->intent->id())->wire());
        $this->expectException(\RuntimeException::class);
        $r->apply($decision, $allocation);
    }

    public function test_added_credential_after_apply_fences_repair_even_when_original_had_none(): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared();
        $r = $this->repository();
        $r->apply($decision, $allocation);
        $this->registrationDb()->credentials->insertOne(['_id' => $this->uuid(), 'schema_version' => 1, 'user_id' => $p->intent->id(), 'credential_type' => 'password', 'secret_hash' => password_hash('synthetic-recovery-password', PASSWORD_BCRYPT, ['cost' => 4]), 'created_at' => new UTCDateTime]);
        $this->expectException(\RuntimeException::class);
        $r->apply($decision, $allocation);
    }

    public function test_future_original_preparation_is_rejected_using_database_clock(): void
    {
        $p = $this->payload();
        $r = $this->intents();
        $ch = $this->uuid();
        $r->start($p, $ch, '123456');
        $claim = $r->claim($p->intent->id(), $ch, '123456');
        $a = $this->reservation($claim, ['systemId' => 'synthetic-'.$this->uuid(), 'committedAt' => $claim->data['verifiedAt']]);
        $command = ActivationCommand::prepare($claim, VerifiedAllocationReservation::verify($claim, $a, $this->transactionPolicy(true)), 'synthetic-registration', 'admission-1', (new \DateTimeImmutable($claim->data['verifiedAt']))->modify('+30 seconds')->format('Y-m-d\TH:i:s.v\Z'));
        $this->expectException(\RuntimeException::class);
        $this->repository()->prepare($command);
    }

    public function test_future_current_issuer_observation_cannot_apply(): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared();
        $future = $this->reservation($claim, array_replace($allocation->reservation->data, ['revision' => '2', 'committedAt' => (new \DateTimeImmutable($claim->data['verifiedAt']))->modify('+30 seconds')->format('Y-m-d\TH:i:s.v\Z')]));
        $this->expectException(\RuntimeException::class);
        $this->repository()->apply($decision, VerifiedAllocationReservation::verify($claim, $future, $this->transactionPolicy(true)));
    }

    #[DataProvider('lookupOutcomes')]
    public function test_coordinator_reconciles_original_lookup_without_inventing_noncommit(string $outcome): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared();
        $nonce = hash('sha256', $this->uuid());
        $early = new \DateTimeImmutable('-1 minute');
        $late = new \DateTimeImmutable('+1 minute');
        if ($outcome === 'committed') {
            $lookup = ActivationLookup::committed($p->intent->id(), $decision);
        } elseif ($outcome === 'not-committed') {
            $d = $this->absenceData();
            $d['operationId'] = $p->intent->id();
            $d['nonce'] = $nonce;
            $d['checkedAt'] = (new \DateTimeImmutable)->format('Y-m-d\TH:i:s.v\Z');
            unset($d['proof']);
            $d['proof'] = hash_hmac('sha256', CanonicalAuthorityJson::encode($d), $this->proofKey(false));
            $absence = VerifiedActivationAbsence::verify($d, $p->intent->id(), $nonce, $this->transactionPolicy(), $early);
            $lookup = ActivationLookup::notCommitted($p->intent->id(), $nonce, $absence);
        } else {
            $lookup = ActivationLookup::unknown();
        }
        $authority = new class($lookup, $decision->decision) implements RegistrationActivationAuthority
        {
            public int $submissions = 0;

            public ?string $submittedFingerprint = null;

            public function __construct(private ActivationLookup $result, private ActivationDecision $decision) {}

            public function decide(ActivationCommand $command): ActivationDecision
            {
                $this->submissions++;
                $this->submittedFingerprint = $command->fingerprint();

                return $this->decision;
            }

            public function lookup(string $operationId, string $nonce): ActivationLookup
            {
                return $this->result;
            }
        };
        $service = new RegistrationService($authority, $this->repository(), $this->transactionPolicy(), $this->transactionPolicy(true));
        if ($outcome === 'unknown') {
            try {
                $service->reconcile($p->intent->id(), $nonce, $early, $late, $allocation);
                self::fail('Unknown accepted');
            } catch (ActivationAuthorityUnavailable) {
                self::assertSame(0, $authority->submissions);
                self::assertNull($this->registrationDb()->users->findOne(['_id' => $p->intent->id()]));
            }
        } else {
            self::assertSame('applied', $service->reconcile($p->intent->id(), $nonce, $early, $late, $allocation));
            self::assertSame($outcome === 'committed' ? 0 : 1, $authority->submissions);
            if ($outcome === 'not-committed') {
                self::assertSame($command->fingerprint(), $authority->submittedFingerprint);
            }
        }
    }

    public static function lookupOutcomes(): array
    {
        return [['unknown'], ['committed'], ['not-committed']];
    }

    #[DataProvider('orphanAccountRecords')]
    public function test_first_application_fences_preexisting_account_and_child_records(string $record, bool $reject): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared();
        $id = $p->intent->id();
        $db = $this->registrationDb();
        if ($record === 'credentials') {
            $db->credentials->insertOne(['_id' => $this->uuid(), 'schema_version' => 1, 'user_id' => $id, 'credential_type' => 'password', 'secret_hash' => password_hash('synthetic-orphan', PASSWORD_BCRYPT, ['cost' => 4]), 'created_at' => new UTCDateTime]);
        } else {
            $db->users->insertOne(['_id' => $id, 'schema_version' => 1, 'kind' => 'student', 'account_state' => 'disabled', 'security_epoch' => new Int64(9), 'created_at' => new UTCDateTime, 'updated_at' => new UTCDateTime]);
        }
        if ($reject) {
            $raw = $this->decision($command, ['result' => 'rejected', 'committedAt' => $command->data['preparedAt']]);
            $decision = VerifiedActivationDecision::verify($command, $raw, $this->transactionPolicy(), $this->transactionPolicy(true));
        }
        $this->expectException(\RuntimeException::class);
        $this->repository()->apply($decision, $allocation);
    }

    public static function orphanAccountRecords(): array
    {
        return [['credentials', false], ['users', true], ['credentials', true]];
    }

    public function test_original_issued_allocation_cannot_revert_to_reserved_on_first_apply(): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared(allocationState: 'issued');
        $backward = $this->reservation($claim, array_replace($allocation->reservation->data, ['state' => 'reserved', 'revision' => '2']));
        $this->expectException(\RuntimeException::class);
        $this->repository()->apply($decision, VerifiedAllocationReservation::verify($claim, $backward, $this->transactionPolicy(true)));
    }

    public function test_higher_issuer_revision_cannot_move_stored_projection_time_backwards(): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared();
        $r = $this->repository();
        $later = $this->reservation($claim, array_replace($allocation->reservation->data, ['state' => 'issued', 'revision' => '2', 'committedAt' => (new \DateTimeImmutable($claim->data['verifiedAt']))->modify('+1 millisecond')->format('Y-m-d\TH:i:s.v\Z')]));
        $r->apply($decision, VerifiedAllocationReservation::verify($claim, $later, $this->transactionPolicy(true)));
        $backward = $this->reservation($claim, array_replace($allocation->reservation->data, ['state' => 'issued', 'revision' => '3']));
        $this->expectException(\RuntimeException::class);
        $r->apply($decision, VerifiedAllocationReservation::verify($claim, $backward, $this->transactionPolicy(true)));
    }

    public function test_foreign_verified_noncommit_is_reauthenticated_before_resubmission(): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared();
        $nonce = hash('sha256', $this->uuid());
        $early = new \DateTimeImmutable('-1 minute');
        $late = new \DateTimeImmutable('+1 minute');
        $d = $this->absenceData();
        $d['operationId'] = $p->intent->id();
        $d['nonce'] = $nonce;
        $d['checkedAt'] = (new \DateTimeImmutable)->format('Y-m-d\TH:i:s.v\Z');
        $d['authorityNamespace'] = 'foreign-synthetic-admission';
        unset($d['proof']);
        $d['proof'] = hash_hmac('sha256', CanonicalAuthorityJson::encode($d), $this->proofKey(false));
        $foreignPolicy = new AuthorityProofPolicy('foreign-synthetic-admission', 'admission-1', ['admission-key'], fn ($body, $proof, $key) => hash_equals(hash_hmac('sha256', $body, $this->proofKey(false)), $proof), $late);
        $absence = VerifiedActivationAbsence::verify($d, $p->intent->id(), $nonce, $foreignPolicy, $early);
        $lookup = ActivationLookup::notCommitted($p->intent->id(), $nonce, $absence);
        $authority = new class($lookup, $decision->decision) implements RegistrationActivationAuthority
        {
            public int $submissions = 0;

            public function __construct(private ActivationLookup $result, private ActivationDecision $decision) {}

            public function decide(ActivationCommand $command): ActivationDecision
            {
                $this->submissions++;

                return $this->decision;
            }

            public function lookup(string $operationId, string $nonce): ActivationLookup
            {
                return $this->result;
            }
        };
        $error = null;
        try {
            (new RegistrationService($authority, $this->repository(), $this->transactionPolicy(), $this->transactionPolicy(true)))->reconcile($p->intent->id(), $nonce, $early, $late, $allocation);
        } catch (ReceiptContractViolation $e) {
            $error = $e;
        }
        self::assertSame(0, $authority->submissions);
        self::assertNotNull($error);
    }

    #[DataProvider('finalMetadataAttacks')]
    public function test_final_retry_rejects_changed_decision_and_intent_metadata(string $field): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared();
        $r = $this->repository();
        $r->apply($decision, $allocation);
        $id = $p->intent->id();
        if ($field === 'decision_ref') {
            $this->registrationDb()->registration_activations_v1->updateOne(['_id' => $id], ['$set' => [$field => 'urn:synthetic:conflicting-receipt']]);
        } elseif ($field === 'decision_at') {
            $this->registrationDb()->registration_activations_v1->updateOne(['_id' => $id], ['$set' => [$field => new UTCDateTime(0)]]);
        } elseif ($field === 'operation_id') {
            $this->registrationDb()->registration_intents_v1->updateOne(['_id' => $id], ['$set' => [$field => $this->uuid()]]);
        } else {
            $this->registrationDb()->registration_intents_v1->updateOne(['_id' => $id], ['$set' => ['completed_at' => new UTCDateTime(0)]]);
        }
        $this->expectException(\RuntimeException::class);
        $r->apply($decision, $allocation);
    }

    public static function finalMetadataAttacks(): array
    {
        return [['decision_ref'], ['decision_at'], ['operation_id'], ['completed_at']];
    }

    #[DataProvider('submissionPreflightAttacks')]
    public function test_new_decision_submission_requires_current_destination_and_original_deadline(string $attack): void
    {
        [$p,$claim,$command,$decision,$allocation] = $this->prepared(processingMilliseconds: $attack === 'expired' ? 2000 : 120000);
        $authority = new class($decision->decision) implements RegistrationActivationAuthority
        {
            public int $submissions = 0;

            public function __construct(private ActivationDecision $decision) {}

            public function decide(ActivationCommand $command): ActivationDecision
            {
                $this->submissions++;

                return $this->decision;
            }

            public function lookup(string $operationId, string $nonce): ActivationLookup
            {
                return ActivationLookup::unknown();
            }
        };
        $policy = $this->transactionPolicy();
        if ($attack === 'expired') {
            usleep(2100000);
        } else {
            $policy = new AuthorityProofPolicy('different-current-destination', 'admission-2', ['new-key'], static fn () => true, new \DateTimeImmutable('+1 hour'));
        }
        $error = null;
        try {
            (new RegistrationService($authority, $this->repository(), $policy, $this->transactionPolicy(true)))->complete($p->intent->id(), $allocation);
        } catch (\RuntimeException $e) {
            $error = $e;
        }
        self::assertSame(0, $authority->submissions);
        self::assertNotNull($error);
    }

    public static function submissionPreflightAttacks(): array
    {
        return [['expired'], ['destination']];
    }
}
