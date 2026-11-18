<?php

declare(strict_types=1);

namespace App\Infrastructure\Registration;

use App\Domain\Identifiers\AllocationReservation;
use App\Domain\Identifiers\VerifiedAllocationReservation;
use App\Domain\Registration\ActivationCommand;
use App\Domain\Registration\RegistrationClaim;
use App\Domain\Registration\RegistrationPayload;
use App\Domain\Registration\VerifiedActivationDecision;
use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\AuthorityProofPolicy;
use App\Domain\Security\Authority\CanonicalAuthorityJson;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;

/** Synthetic transaction integration, no runtime binding or independently current production adapter. */
final class MongoRegistrationActivationRepository
{
    public function __construct(private Database $db, private RegistrationPayloadCipher $cipher, private MongoRegistrationIntentRepository $intents, private AuthorityProofPolicy $admissionPolicy, private AuthorityProofPolicy $allocationPolicy, private ?\Closure $gate = null, private ?\Closure $beforeCommit = null)
    {
        RegistrationLabDatabase::assertReady($db);
    }

    private function allow(string $stage, ActivationCommand $command, ?VerifiedActivationDecision $decision = null, ?VerifiedAllocationReservation $current = null): void
    {
        // Lab fixture only: production requires an accepted independent ordering/recovery protocol.
        // Callbacks here must be local/non-network and return strict true in this transaction.
        if ($this->gate === null || ($this->gate)($stage, $command, $decision, $current) !== true) {
            throw new \RuntimeException('Current registration recovery/admission unavailable.');
        }
    }

    public function prepare(ActivationCommand $command): void
    {
        $claim = new RegistrationClaim($command->data['claim']);
        VerifiedAllocationReservation::verify($claim, new AllocationReservation($command->data['allocation']), $this->allocationPolicy);
        if (! $this->admissionPolicy->binds($command->data['authorityNamespace'], $command->data['expectedIssuerGeneration'])) {
            throw new \RuntimeException('Untrusted activation destination.');
        }
        RegistrationLabDatabase::transaction($this->db, function ($o) use ($command, $claim) {
            $this->allow('prepare', $command);
            $id = $claim->data['operationId'];
            $row = $this->db->registration_activations_v1->findOne(['_id' => $id], $o);
            $stored = $this->intents->loadClaim($id, $o);
            if (! $stored || $stored->wire() !== $claim->wire()) {
                throw new \RuntimeException('Original claim unavailable or conflicting.');
            }
            $p = $this->intents->payload($id, $o);
            $this->bindPayload($p, $claim);
            if ($row['state'] !== 'claimed') {
                $original = $this->originalCommand($id, $o);
                if ($original->wire() !== $command->wire()) {
                    throw new \RuntimeException('Original command conflict.');
                }

                return;
            }
            $now = $this->now($o);
            if ((int) (string) $now >= (int) (string) $row['processing_deadline'] || new \DateTimeImmutable($command->data['preparedAt']) > $now->toDateTimeImmutable()) {
                throw new \RuntimeException('New activation preparation deadline elapsed.');
            }
            $r = $this->db->registration_activations_v1->updateOne(['_id' => $id, 'state' => 'claimed', 'revision' => $row['revision']], ['$set' => ['state' => 'prepared', 'system_id' => $command->data['allocation']['systemId'], 'allocation_ref' => $command->data['allocation']['authorityRef'], 'command_ciphertext' => $this->cipher->seal($command->wire(), MongoRegistrationIntentRepository::context('command', $id, $command->fingerprint(), $claim->data['sealedPayloadHash'])), 'command_key_id' => $this->cipher->keyId, 'command_fingerprint' => $command->fingerprint(), 'prepared_at' => new UTCDateTime(new \DateTimeImmutable($command->data['preparedAt']))], '$inc' => ['revision' => new Int64(1)]], $o);
            if ($r->getModifiedCount() !== 1) {
                throw new \RuntimeException('Preparation conflict.');
            }
        });
    }

    public function originalCommand(string $id, array $o = []): ActivationCommand
    {
        F::uuid($id);
        $read = $o ?: ['readConcern' => new ReadConcern(ReadConcern::MAJORITY), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY)];
        $r = $this->db->registration_activations_v1->findOne(['_id' => $id], $read);
        if (! $r || ! isset($r['command_ciphertext'])) {
            throw new \RuntimeException('Original activation command unavailable.');
        }
        $c = ActivationCommand::fromJson($this->cipher->open($r['command_ciphertext'], $r['command_key_id'], MongoRegistrationIntentRepository::context('command', $id, $r['command_fingerprint'], $r['sealed_payload_hash'])));
        $claim = new RegistrationClaim($c->data['claim']);
        $stored = $this->intents->loadClaim($id, $o);
        if (! $stored || $stored->wire() !== $claim->wire() || $c->fingerprint() !== $r['command_fingerprint'] || $c->data['allocation']['systemId'] !== $r['system_id'] || $c->data['allocation']['authorityRef'] !== $r['allocation_ref'] || $c->data['preparedAt'] !== $r['prepared_at']->toDateTime()->format('Y-m-d\TH:i:s.v\Z')) {
            throw new \RuntimeException('Original activation command binding mismatch.');
        }
        VerifiedAllocationReservation::verify($claim, new AllocationReservation($c->data['allocation']), $this->allocationPolicy);

        return $c;
    }

    /** End this read-only transaction before any external decide call. */
    public function assertCanSubmit(ActivationCommand $command): void
    {
        RegistrationLabDatabase::transaction($this->db, function ($o) use ($command) {
            $id = $command->data['claim']['operationId'];
            $this->allow('submit', $command);
            if ($this->originalCommand($id, $o)->wire() !== $command->wire()) {
                throw new \RuntimeException('Original submission command conflict.');
            }
            $row = $this->db->registration_activations_v1->findOne(['_id' => $id], $o);
            $now = $this->now($o);
            if ($row['state'] !== 'prepared' || $now->toDateTimeImmutable() >= new \DateTimeImmutable($command->data['claim']['processingDeadline']) || $now->toDateTimeImmutable() < new \DateTimeImmutable($command->data['preparedAt'])) {
                throw new \RuntimeException('New decision submission outside original processing window.');
            }
        });
    }

    public function apply(VerifiedActivationDecision $decision, VerifiedAllocationReservation $current): string
    {
        // A previously verified capability is not currentness. Revalidate configured trust and require the separate gate.
        $decision = VerifiedActivationDecision::verify($decision->command, $decision->decision, $this->admissionPolicy, $this->allocationPolicy);
        $claim = new RegistrationClaim($decision->command->data['claim']);
        $current = VerifiedAllocationReservation::verify($claim, $current->reservation, $this->allocationPolicy);
        $original = $decision->allocation->reservation;
        $latest = $current->reservation;
        foreach (['operationId', 'userId', 'intentFingerprint', 'systemId', 'authorityNamespace', 'issuerGeneration'] as $k) {
            if ($original->data[$k] !== $latest->data[$k]) {
                throw new \RuntimeException('Current allocation binding mismatch.');
            }
        }
        if (($original->data['state'] === 'issued' && $latest->data['state'] !== 'issued') || $latest->data['state'] === 'retired' || (int) $latest->data['revision'] < (int) $original->data['revision'] || ($latest->data['revision'] === $original->data['revision'] && $latest->wire() !== $original->wire()) || new \DateTimeImmutable($latest->data['committedAt']) < new \DateTimeImmutable($original->data['committedAt'])) {
            throw new \RuntimeException('Current allocation retired or nonmonotone.');
        }

        return RegistrationLabDatabase::transaction($this->db, function ($o) use ($decision, $current, $claim) {
            $command = $decision->command;
            $id = $claim->data['operationId'];
            $this->allow('apply', $command, $decision, $current);
            $observation = $this->now($o);
            if (new \DateTimeImmutable($current->reservation->data['committedAt']) > $observation->toDateTimeImmutable() || new \DateTimeImmutable($decision->decision->data['committedAt']) > $observation->toDateTimeImmutable()) {
                throw new \RuntimeException('Activation proof is after database observation.');
            }

            if ($this->originalCommand($id, $o)->wire() !== $command->wire()) {
                throw new \RuntimeException('Persisted original command conflict.');
            }
            $p = $this->intents->payload($id, $o);
            $this->bindPayload($p, $claim);
            $this->notices($p, $o);
            $row = $this->db->registration_activations_v1->findOne(['_id' => $id], $o);
            $intent = $this->db->registration_intents_v1->findOne(['_id' => $id], $o);
            $state = $decision->decision->data['result'] === 'allowed' ? 'applied' : 'denied';
            $allowed = $state === 'applied';
            if (in_array($row['state'], ['applied', 'denied'], true)) {
                foreach (['user_emails' => $allowed ? 1 : 0, 'credentials' => $allowed && $p->fields()['intent']['passwordHash'] !== null ? 1 : 0, 'notice_acknowledgments' => $allowed ? 2 : 0] as $name => $count) {
                    if ($this->db->selectCollection($name)->countDocuments(['user_id' => $id], $o) !== $count) {
                        throw new \RuntimeException('Later account records changed; repair fenced.');
                    }
                }
                if (! $allowed && $this->db->users->findOne(['_id' => $id], $o) !== null) {
                    throw new \RuntimeException('Denied operation conflicts with account.');
                }
            }

            if (in_array($row['state'], ['applied', 'denied'], true)) {
                if ($row['state'] !== $state || $row['decision_hash'] !== $decision->decision->fingerprint() || $row['decision_ref'] !== $decision->decision->data['authorityRef'] || $row['decision_at']->toDateTime()->format('Y-m-d\TH:i:s.v\Z') !== $decision->decision->data['committedAt'] || ($intent['operation_id'] ?? null) !== $id || $intent['state'] !== ($state === 'applied' ? 'activated' : 'denied') || ($state === 'applied' && (string) $intent['completed_at'] !== (string) $row['applied_at'])) {
                    throw new \RuntimeException('Final activation state conflict.');
                }
                $at = $state === 'applied' ? $row['applied_at'] : $intent['completed_at'];
                $this->records($this->documents($p, $claim, $decision, $at), $o, true);
                $this->projection($current, $o, true);

                return $state;
            }
            if ($row['state'] !== 'prepared' || $intent['state'] !== 'claimed' || $intent['operation_id'] !== $id) {
                throw new \RuntimeException('Activation state conflict.');
            }
            if ($this->db->users->findOne(['_id' => $id], $o) !== null) {
                throw new \RuntimeException('Existing account conflicts with first activation outcome.');
            }
            foreach (['user_emails', 'credentials', 'notice_acknowledgments'] as $name) {
                if ($this->db->selectCollection($name)->countDocuments(['user_id' => $id], $o) !== 0) {
                    throw new \RuntimeException('Existing account children conflict with first activation outcome.');
                }
            }
            $at = $this->now($o);
            if (new \DateTimeImmutable($decision->decision->data['committedAt']) > $at->toDateTimeImmutable()) {
                throw new \RuntimeException('Decision is after application observation.');
            }
            $this->records($this->documents($p, $claim, $decision, $at), $o, false);
            $this->projection($current, $o, false);
            $set = ['state' => $state, 'decision_ref' => $decision->decision->data['authorityRef'], 'decision_hash' => $decision->decision->fingerprint(), 'decision_at' => new UTCDateTime(new \DateTimeImmutable($decision->decision->data['committedAt']))];
            if ($state === 'applied') {
                $set['applied_at'] = $at;
            }
            $activationUpdate = $this->db->registration_activations_v1->updateOne(['_id' => $id, 'state' => 'prepared', 'revision' => $row['revision'], 'command_fingerprint' => $command->fingerprint()], ['$set' => $set, '$inc' => ['revision' => new Int64(1)]], $o);
            $intentUpdate = $this->db->registration_intents_v1->updateOne(['_id' => $id, 'state' => 'claimed', 'revision' => $intent['revision'], 'operation_id' => $id], ['$set' => ['state' => $state === 'applied' ? 'activated' : 'denied', 'completed_at' => $at, 'updated_at' => $at], '$inc' => ['revision' => new Int64(1)]], $o);
            if ($activationUpdate->getModifiedCount() !== 1 || $intentUpdate->getModifiedCount() !== 1) {
                throw new \RuntimeException('Final registration compare-and-swap conflict.');
            }
            if ($this->beforeCommit !== null) {
                ($this->beforeCommit)();
            }

            return $state;
        });
    }

    private function now(array $o): UTCDateTime
    {
        return iterator_to_array($this->db->aggregate([['$documents' => [(object) []]], ['$project' => ['now' => '$$NOW']]], $o), false)[0]['now'];
    }

    private function bindPayload(RegistrationPayload $p, RegistrationClaim $c): void
    {
        if (new \DateTimeImmutable($p->fields()['acknowledged_at']) > new \DateTimeImmutable($c->data['verifiedAt'])) {
            throw new \RuntimeException('Acknowledgment follows original verification.');
        }
        if ($p->intent->id() !== $c->data['intentId'] || $p->hash() !== $c->data['sealedPayloadHash'] || $p->intent->fingerprint() !== $c->data['intentFingerprint'] || $p->intent->noticeBundleFingerprint() !== $c->data['noticeBundleFingerprint']) {
            throw new \RuntimeException('Original payload binding mismatch.');
        }
    }

    private function notices(RegistrationPayload $p, array $o): void
    {
        $d = $p->fields();
        foreach (['terms' => ['terms_notice_id', 'termsVersion', 'termsHash'], 'privacy_notice' => ['privacy_notice_id', 'noticeVersion', 'noticeHash']] as $kind => $f) {
            [$id,$version,$hash] = $f;
            $r = $this->db->official_notice_versions->findOne(['_id' => $d[$id]], $o);
            if (! $r || $r['document_kind'] !== $kind || $r['version_label'] !== $d['intent'][$version] || ! hash_equals($r['content_hash']->getData(), hex2bin($d['intent'][$hash])) || $r['effective_at']->toDateTimeImmutable() > new \DateTimeImmutable($d['acknowledged_at']) || ! in_array($r['status'], ['active', 'approved'], true)) {
                throw new \RuntimeException('Original approved notice version unavailable.');
            }
        }
    }

    private function documents(RegistrationPayload $p, RegistrationClaim $claim, VerifiedActivationDecision $decision, UTCDateTime $at): array
    {
        $d = $p->fields();
        $c = $claim->data;
        $ids = $c['childIds'];
        $id = $c['userId'];
        $allowed = $decision->decision->data['result'] === 'allowed';
        $event = ['operationId' => $id, 'claimFingerprint' => $claim->fingerprint(), 'decisionHash' => $decision->decision->fingerprint(), 'result' => $allowed ? 'applied' : 'denied'];
        $hash = new Binary(hash('sha256', CanonicalAuthorityJson::encode($event), true));
        $rows = ['audit_events' => [['_id' => $ids['auditId'], 'schema_version' => 1, 'action' => 'registration.'.($allowed ? 'applied' : 'denied'), 'target_type' => 'registration', 'target_id' => $id, 'correlation_id' => $c['correlationId'], 'event_redacted' => $event, 'event_hash' => $hash, 'occurred_at' => $at]],
            'security_decision_journal' => [['_id' => $ids['journalId'], 'schema_version' => 1, 'subject_user_id' => $id, 'event_kind' => 'registration.'.($allowed ? 'applied' : 'denied'), 'authority_ref' => $decision->decision->data['authorityRef'], 'event_hash' => $hash, 'recovery_anchor_ref' => $decision->decision->data['checkpointRef'], 'external_anchor_status' => 'pending', 'occurred_at' => $at]]];
        if (! $allowed) {
            return $rows;
        }
        $rows['users'] = [['_id' => $id, 'schema_version' => 1, 'system_id' => $decision->decision->data['systemId'], 'kind' => 'student', 'account_state' => 'provisional', 'security_epoch' => new Int64(0), 'created_at' => $at, 'updated_at' => $at]];
        $rows['user_emails'] = [['_id' => $ids['emailId'], 'schema_version' => 1, 'user_id' => $id, 'email' => $p->intent->recipient(), 'email_normalized' => $p->intent->recipient(), 'is_primary' => true, 'is_current' => true, 'verified_at' => new UTCDateTime(new \DateTimeImmutable($c['verifiedAt'])), 'created_at' => $at]];
        $hasPassword = $d['intent']['passwordHash'] !== null;
        if ($hasPassword !== ($ids['credentialId'] !== null)) {
            throw new \RuntimeException('Original credential mapping mismatch.');
        }
        if ($hasPassword) {
            $rows['credentials'] = [['_id' => $ids['credentialId'], 'schema_version' => 1, 'user_id' => $id, 'credential_type' => 'password', 'secret_hash' => $d['intent']['passwordHash'], 'created_at' => $at]];
        }
        $rows['notice_acknowledgments'] = [];
        foreach (['termsAckId' => 'terms_notice_id', 'privacyAckId' => 'privacy_notice_id'] as $child => $notice) {
            $rows['notice_acknowledgments'][] = ['_id' => $ids[$child], 'schema_version' => 1, 'user_id' => $id, 'notice_version_id' => $d[$notice], 'acknowledged_at' => new UTCDateTime(new \DateTimeImmutable($d['acknowledged_at'])), 'evidence_digest' => new Binary(hex2bin($c['noticeBundleFingerprint']))];
        }

        return $rows;
    }

    private function records(array $documents, array $o, bool $retry): void
    {
        foreach ($documents as $name => $rows) {
            foreach ($rows as $row) {
                $collection = $this->db->selectCollection($name);
                if ($retry) {
                    $actual = $collection->findOne(['_id' => $row['_id']], $o);
                    if ($actual && $name === 'users') {
                        $actual['security_epoch'] = new Int64((string) $actual['security_epoch']);
                    }if (! $actual || RegistrationLabDatabase::typed($actual) !== RegistrationLabDatabase::typed($row)) {
                        throw new \RuntimeException('Applied immutable records missing or changed; repair fenced.');
                    }
                } else {
                    $collection->insertOne($row, $o);
                }
            }
        }
    }

    private function projection(VerifiedAllocationReservation $verified, array $o, bool $retry): void
    {
        $d = $verified->reservation->data;
        $row = ['_id' => $d['operationId'], 'schema_version' => 1, 'user_id' => $d['userId'], 'intent_fingerprint' => $d['intentFingerprint'], 'system_id' => $d['systemId'], 'state' => $d['state'], 'revision' => new Int64($d['revision']), 'issuer_namespace' => $d['authorityNamespace'], 'issuer_generation' => $d['issuerGeneration'], 'authority_ref' => $d['authorityRef'], 'receipt_hash' => $verified->reservation->fingerprint(), 'recorded_at' => new UTCDateTime(new \DateTimeImmutable($d['committedAt']))];
        if (! $retry) {
            $this->db->system_id_allocations_v1->insertOne($row, $o);

            return;
        }
        $old = $this->db->system_id_allocations_v1->findOne(['_id' => $d['operationId']], $o);
        if (! $old) {
            throw new \RuntimeException('Original allocation projection missing.');
        }$old['revision'] = new Int64((string) $old['revision']);
        foreach (['_id', 'user_id', 'intent_fingerprint', 'system_id', 'issuer_namespace', 'issuer_generation'] as $k) {
            if ($row[$k] !== $old[$k]) {
                throw new \RuntimeException('Allocation projection binding conflict.');
            }
        }
        if ($row['recorded_at']->toDateTimeImmutable() < $old['recorded_at']->toDateTimeImmutable() || (int) (string) $old['revision'] > (int) $d['revision'] || $old['state'] === 'retired' || ($old['state'] === 'issued' && $d['state'] !== 'issued') || ((string) $old['revision'] === (string) $row['revision'] && RegistrationLabDatabase::typed($old) !== RegistrationLabDatabase::typed($row))) {
            throw new \RuntimeException('Allocation projection rollback or conflict.');
        }
        if ((int) (string) $old['revision'] < (int) $d['revision']) {
            $this->db->system_id_allocations_v1->replaceOne(['_id' => $d['operationId'], 'revision' => $old['revision']], $row, $o);
        }
    }
}
