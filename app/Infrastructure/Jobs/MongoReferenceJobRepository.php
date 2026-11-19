<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Domain\Jobs\JobItemOutcome;
use App\Domain\Jobs\JobLease;
use App\Domain\Jobs\JobPolicy;
use App\Domain\Jobs\ReferenceJobCommand;
use App\Domain\Security\Authority\AuthorityContractFields as F;
use Illuminate\Support\Str;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;

/** Unbound synthetic outbox. No business mutation or external effect is implemented here. */
final class MongoReferenceJobRepository
{
    private JobAuditTrail $audit;

    public function __construct(private Database $db, private JobPolicy $policy, private ?\Closure $gate = null, private ?\Closure $beforeCommit = null)
    {
        $this->db = $db->withOptions(['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
        OutboxLabDatabase::assertReady($db);
        $this->audit = new JobAuditTrail($db, $policy);
    }

    private function allow(string $stage, ReferenceJobCommand $command): void
    {
        // Local non-network fixture gate only. No default positive provider or request-supplied permission.
        if ($this->gate === null || ($this->gate)($stage, $command) !== true) {
            throw new \RuntimeException('Current synthetic job admission unavailable.');
        }
    }

    private function transaction(string $stage, \Closure $work): mixed
    {
        return OutboxLabDatabase::transaction($this->db, function ($o) use ($stage, $work) {
            $r = $work($o);
            if ($this->beforeCommit !== null) {
                ($this->beforeCommit)($stage);
            }

            return $r;
        });
    }

    private function now(array $o): UTCDateTime
    {
        return iterator_to_array($this->db->aggregate([['$documents' => [(object) []]], ['$project' => ['now' => '$$NOW']]], $o), false)[0]['now'];
    }

    private function read(string $id, array $o): array
    {
        F::uuid($id);
        $row = $this->db->background_jobs->findOne(['_id' => $id], $o);
        if (! $row) {
            throw new \RuntimeException('Original outbox command unavailable.');
        }
        $c = new ReferenceJobCommand($row['payload_redacted']);
        if ($c->data['operationId'] !== $id || $row['idempotency_key'] !== $id || $row['job_type'] !== $c->data['jobType'] || $row['command_fingerprint'] !== $c->fingerprint() || $row['policy_ref'] !== $this->policy->reference || $row['max_attempts'] !== $this->policy->maxAttempts || $row['lease_milliseconds'] !== $this->policy->leaseMilliseconds) {
            throw new \RuntimeException('Original outbox command or policy mismatch.');
        }
        $base = $c->auditIds();
        $reserved = $row['reserved_audit_ids'];
        if (! array_is_list($reserved) || count($reserved) !== count($base) + $this->policy->maxAttempts || array_slice($reserved, 0, count($base)) !== $base || count(array_unique($reserved)) !== count($reserved)) {
            throw new \RuntimeException('Outbox audit reservation mismatch.');
        }
        foreach ($reserved as $id) {
            F::uuid($id);
        }
        $this->physicalIds($c, $reserved, $o, false);
        $proof = $this->audit->verify($row, $c, $o);

        return [$row, $c, $proof['results'], $proof['observedAt']];
    }

    private function physicalIds(ReferenceJobCommand $command, array $reserved, array $o, bool $fresh): void
    {
        $id = $command->data['operationId'];
        $items = [];
        foreach ($command->data['items'] as $item) {
            $items[$item['resultId']] = $item;
        }
        foreach ($this->db->job_results->find(['_id' => ['$in' => array_keys($items)]], $o) as $r) {
            if ($fresh || $r['job_id'] !== $id || $r['target_ref'] !== $items[$r['_id']]['targetId']) {
                throw new \RuntimeException('Original result ID has conflicting physical evidence.');
            }
        }
        $actions = [$command->data['enqueueAuditId'] => 'job.queued', $command->data['finishAuditId'] => 'job.finished'];
        foreach ($command->data['items'] as $item) {
            $actions[$item['auditId']] = 'job.item-completed';
        }
        foreach (array_slice($reserved, count($command->auditIds())) as $claimId) {
            $actions[$claimId] = 'job.claimed';
        }
        foreach ($this->db->audit_events->find(['_id' => ['$in' => $reserved]], $o) as $event) {
            if ($fresh || $event['target_id'] !== $id || $event['action'] !== $actions[$event['_id']]) {
                throw new \RuntimeException('Original audit ID has conflicting physical evidence.');
            }
        }
    }

    /** Verified original enqueue time only; neither a lease nor current external permission. */
    public function originalForHandler(ReferenceJobCommand $command, array $item): \DateTimeImmutable
    {
        return $this->transaction('handler-original', function ($o) use ($command, $item) {
            [$row, $stored] = $this->read($command->data['operationId'], $o);
            $this->allow('handler-original', $stored);
            if ($stored->wire() !== $command->wire() || ! in_array($item, $stored->data['items'], true)) {
                throw new \RuntimeException('Original handler membership mismatch.');
            }

            return $row['created_at']->toDateTimeImmutable();
        });
    }

    public function enqueue(ReferenceJobCommand $command): void
    {
        $this->transaction('enqueue', fn ($o) => $this->enqueueUsing($command, $o));
    }

    /** The context owns commit/abort; caught errors still poison that owning transaction. */
    public function enqueueIn(OutboxProducerTransaction $context, ReferenceJobCommand $command): void
    {
        try {
            $this->enqueueUsing($command, $context->optionsFor($this->db));
            if ($this->beforeCommit !== null) {
                ($this->beforeCommit)('enqueue');
            }
        } catch (\Throwable $error) {
            $context->rollbackOnly($error);
            throw $error;
        }
    }

    /** apply is insert-only/expected-prior CAS; verify is read-only exact evidence. Both return strict true. */
    public function produce(ReferenceJobCommand $command, \Closure $apply, \Closure $verify): bool
    {
        return OutboxProducerTransaction::run($this->db, function ($context) use ($command, $apply, $verify) {
            try {
                $o = $context->optionsFor($this->db);
                $this->allow('enqueue', $command);
                $this->allow('produce', $command);
                $existing = $this->db->background_jobs->findOne(['_id' => $command->data['operationId']], $o);
                if ($existing !== null) {
                    [, $original] = $this->read($command->data['operationId'], $o);
                    if ($original->wire() !== $command->wire()) {
                        throw new \RuntimeException('Producer outbox original command conflict.');
                    }
                    if ($verify($context) !== true) {
                        throw new \RuntimeException('Original producer evidence unavailable or changed.');
                    }
                    $created = false;
                } else {
                    $this->assertFreshEvidenceEmpty($command, $o);
                    if ($apply($context) !== true || $verify($context) !== true) {
                        throw new \RuntimeException('Fresh producer mutation or verification failed.');
                    }
                    $this->enqueueIn($context, $command);
                    $created = true;
                }
                if ($this->beforeCommit !== null) {
                    ($this->beforeCommit)('produce');
                }

                return $created;
            } catch (\Throwable $error) {
                $context->rollbackOnly($error);
                throw $error;
            }
        });
    }

    private function assertFreshEvidenceEmpty(ReferenceJobCommand $command, array $o): void
    {
        $id = $command->data['operationId'];
        if ($this->db->audit_events->findOne(['target_id' => $id], $o) !== null || $this->db->job_results->findOne(['job_id' => $id], $o) !== null) {
            throw new \RuntimeException('Orphan operation evidence blocks a fresh producer.');
        }
        $this->physicalIds($command, $command->auditIds(), $o, true);
    }

    private function enqueueUsing(ReferenceJobCommand $command, array $o): void
    {
        $this->allow('enqueue', $command);
        $id = $command->data['operationId'];
        if ($this->db->background_jobs->findOne(['_id' => $id], $o) !== null) {
            [, $original] = $this->read($id, $o);
            if ($original->wire() !== $command->wire()) {
                throw new \RuntimeException('Outbox idempotency conflict.');
            }

            return;
        }
        $this->assertFreshEvidenceEmpty($command, $o);
        $reserved = $command->auditIds();
        for ($i = 0; $i < $this->policy->maxAttempts; $i++) {
            do {
                $candidate = (string) Str::uuid();
            } while (in_array($candidate, $reserved, true));
            $reserved[] = $candidate;
        }
        $this->physicalIds($command, $reserved, $o, true);
        $now = $this->now($o);
        $row = ['_id' => $id, 'schema_version' => 1, 'job_type' => $command->data['jobType'], 'idempotency_key' => $id, 'status' => 'queued', 'payload_redacted' => $command->data, 'command_fingerprint' => $command->fingerprint(), 'reserved_audit_ids' => $reserved, 'attempt_count' => 0, 'max_attempts' => $this->policy->maxAttempts, 'policy_ref' => $this->policy->reference, 'lease_milliseconds' => $this->policy->leaseMilliseconds, 'revision' => new Int64(0), 'audit_sequence' => new Int64(0), 'audit_head_hash' => str_repeat('0', 64), 'created_at' => $now];
        $this->db->background_jobs->insertOne($row, $o);
        $this->audit->append($row, $command, $command->data['enqueueAuditId'], 'job.queued', 'queued', $now, $o);
    }

    public function claim(string $id): ?JobLease
    {
        return $this->transaction('claim', function ($o) use ($id) {
            [$row,$command,,$lastObservation] = $this->read($id, $o);
            $this->allow('claim', $command);
            $now = $this->now($o);
            if ($now->toDateTimeImmutable() < $row['created_at']->toDateTimeImmutable() || $now->toDateTimeImmutable() < $lastObservation->toDateTimeImmutable()) {
                throw new \RuntimeException('Outbox database clock precedes creation.');
            }
            if (! in_array($row['status'], ['queued', 'running'], true) || $row['attempt_count'] >= $this->policy->maxAttempts || ($row['status'] === 'running' && $row['lease_until']->toDateTimeImmutable() > $now->toDateTimeImmutable())) {
                return null;
            }
            $token = (string) Str::uuid();
            $attempt = $row['attempt_count'] + 1;
            $until = new UTCDateTime((int) (string) $now + $this->policy->leaseMilliseconds);
            $filter = ['_id' => $id, 'revision' => $row['revision'], 'attempt_count' => $row['attempt_count'], 'status' => $row['status']];
            if ($row['status'] === 'running') {
                $filter['$expr'] = ['$lte' => ['$lease_until', '$$NOW']];
            }
            $set = ['status' => 'running', 'attempt_count' => $attempt, 'revision' => new Int64($attempt), 'lease_token' => $token, 'lease_until' => $until];
            if ($this->db->background_jobs->updateOne($filter, ['$set' => $set], $o)->getModifiedCount() !== 1) {
                throw new \RuntimeException('Outbox lease claim conflict.');
            }
            $row = array_replace($row, $set);
            $owner = $this->ownerFields($token, $attempt, $now);
            $this->audit->append($row, $command, $row['reserved_audit_ids'][count($command->auditIds()) + $attempt - 1], 'job.claimed', 'claimed', $now, $o, $owner);

            return new JobLease($command, $token, $attempt, $until->toDateTimeImmutable());
        });
    }

    private function ownerFields(string $token, int $generation, UTCDateTime $observedAt): array
    {
        return ['status' => 'running', 'lease_token' => $token, 'revision' => new Int64($generation), '$expr' => ['$and' => [['$gt' => ['$lease_until', '$$NOW']], ['$lte' => [$observedAt, '$$NOW']]]]];
    }

    private function owner(JobLease $lease, array $row, ReferenceJobCommand $command, array $o, UTCDateTime $observedAt): array
    {
        if ($lease->command->wire() !== $command->wire() || $row['status'] !== 'running' || $row['lease_token'] !== $lease->token || (int) (string) $row['revision'] !== $lease->generation || $row['lease_until']->toDateTimeImmutable() != $lease->expiresAt) {
            throw new \RuntimeException('Outbox lease owner mismatch.');
        }
        $owner = $this->ownerFields($lease->token, $lease->generation, $observedAt);
        if ($this->db->background_jobs->findOne(['_id' => $row['_id']] + $owner, $o) === null) {
            throw new \RuntimeException('Outbox lease expired.');
        }

        return $owner;
    }

    private function item(ReferenceJobCommand $command, string $target): array
    {
        foreach ($command->data['items'] as $item) {
            if ($item['targetId'] === $target) {
                return $item;
            }
        }throw new \RuntimeException('Unknown original outbox target.');
    }

    public function result(JobLease $lease, string $target): ?JobItemOutcome
    {
        return $this->transaction('read-result', function ($o) use ($lease, $target) {
            [$row,$command,$results,$observedAt] = $this->read($lease->command->data['operationId'], $o);
            $this->allow('read-result', $command);
            $this->owner($lease, $row, $command, $o, $observedAt);
            $this->item($command, $target);
            $r = $results[$target] ?? null;

            return $r ? new JobItemOutcome($r['result'], $r['error_code']) : null;
        });
    }

    public function record(JobLease $lease, string $target, JobItemOutcome $outcome): ?string
    {
        return $this->transaction('record', function ($o) use ($lease, $target, $outcome) {
            [$row,$command,$results,$observedAt] = $this->read($lease->command->data['operationId'], $o);
            $this->allow('record', $command);
            $owner = $this->owner($lease, $row, $command, $o, $observedAt);
            $item = $this->item($command, $target);
            if (isset($results[$target])) {
                if ($results[$target]['result'] !== $outcome->result || $results[$target]['error_code'] !== $outcome->errorCode) {
                    throw new \RuntimeException('Outbox immutable outcome conflict.');
                }

                return null;
            }
            $now = $this->now($o);
            $owner = $this->ownerFields($lease->token, $lease->generation, $now);
            $r = ['_id' => $item['resultId'], 'schema_version' => 1, 'job_id' => $row['_id'], 'target_ref' => $target, 'command_fingerprint' => $command->fingerprint(), 'result' => $outcome->result, 'error_code' => $outcome->errorCode, 'attempted_at' => $now];
            $r['outcome_fingerprint'] = JobAuditTrail::resultHash($r);
            $this->db->job_results->insertOne($r, $o);
            $row = $this->audit->append($row, $command, $item['auditId'], 'job.item-completed', $outcome->result, $now, $o, $owner, $target, $r['outcome_fingerprint']);
            $results[$target] = $r;
            if (count($results) === count($command->data['items'])) {
                $status = JobAuditTrail::aggregate($results);
                $row = $this->audit->append($row, $command, $command->data['finishAuditId'], 'job.finished', $status, $now, $o, $owner);
                $changed = $this->db->background_jobs->updateOne(['_id' => $row['_id'], 'audit_sequence' => $row['audit_sequence'], 'audit_head_hash' => $row['audit_head_hash']] + $owner, ['$set' => ['status' => $status, 'finished_at' => $now], '$unset' => ['lease_token' => '', 'lease_until' => '']], $o);
                if ($changed->getModifiedCount() !== 1) {
                    throw new \RuntimeException('Outbox final compare-and-swap conflict.');
                }

                return $status;
            }

            return null;
        });
    }

    public function status(string $id): string
    {
        return $this->transaction('status', function ($o) use ($id) {
            [$row,$command] = $this->read($id, $o);
            $this->allow('status', $command);

            return $row['status'];
        });
    }
}
