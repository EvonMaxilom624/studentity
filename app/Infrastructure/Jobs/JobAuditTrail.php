<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Domain\Jobs\JobItemOutcome;
use App\Domain\Jobs\JobPolicy;
use App\Domain\Jobs\ReferenceJobCommand;
use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\ReceiptSchema;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\Session;

/** Append-only per-job consistency chain, not an independently anchored production audit service. */
final class JobAuditTrail
{
    public function __construct(private Database $db, private JobPolicy $policy)
    {
        $this->db = $db->withOptions(['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
        OutboxLabDatabase::assertReady($db);
    }

    public static function time(UTCDateTime $d): string
    {
        return $d->toDateTime()->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function resultHash(array $r): string
    {
        unset($r['outcome_fingerprint']);

        return hash('sha256', OutboxLabDatabase::typed($r));
    }

    private static function hash(array $event): string
    {
        unset($event['event_hash']);
        $event['job_sequence'] = new Int64((string) $event['job_sequence']);

        return hash('sha256', OutboxLabDatabase::typed($event));
    }

    public static function aggregate(array $results): string
    {
        $failed = count(array_filter($results, static fn ($r) => $r['result'] === 'failed'));

        return $failed === 0 ? 'succeeded' : ($failed === count($results) ? 'failed' : 'partial_failure');
    }

    public function append(array $job, ReferenceJobCommand $command, string $id, string $action, string $outcome, UTCDateTime $now, array $o, array $owner = [], ?string $itemId = null, ?string $resultHash = null): array
    {
        if (array_keys($o) !== ['session'] || ! $o['session'] instanceof Session || ! $o['session']->isInTransaction()) {
            throw new \RuntimeException('Active outbox transaction required before audit writes.');
        }
        if ((int) (string) $job['audit_sequence'] > 0) {
            $previous = $this->db->audit_events->findOne(['target_id' => $job['_id'], 'job_sequence' => $job['audit_sequence']], $o);
            if (! $previous || $now->toDateTimeImmutable() < $previous['occurred_at']->toDateTimeImmutable()) {
                throw new \RuntimeException('Outbox audit observation moved backwards.');
            }
        }
        $sequence = (int) (string) $job['audit_sequence'] + 1;
        $claimed = $action === 'job.claimed';
        $event = ['_id' => $id, 'schema_version' => 1, 'action' => $action, 'target_type' => 'background_job', 'target_id' => $job['_id'], 'correlation_id' => $command->data['correlationId'], 'job_sequence' => new Int64($sequence), 'event_redacted' => ['commandFingerprint' => $command->fingerprint(), 'sequence' => (string) $sequence, 'attempt' => $job['attempt_count'], 'itemId' => $itemId, 'outcome' => $outcome, 'policyFingerprint' => $this->policy->fingerprint(), 'reservationFingerprint' => hash('sha256', CanonicalAuthorityJson::encode($job['reserved_audit_ids'])), 'leaseTokenHash' => $claimed ? hash('sha256', $job['lease_token']) : null, 'leaseUntil' => $claimed ? self::time($job['lease_until']) : null, 'outcomeFingerprint' => $resultHash], 'prior_hash' => new Binary(hex2bin($job['audit_head_hash'])), 'occurred_at' => $now];
        if ($command->data['requestedBy'] !== null) {
            $event['actor_user_id'] = $command->data['requestedBy'];
        }
        $hash = self::hash($event);
        $event['event_hash'] = new Binary(hex2bin($hash));
        $this->db->audit_events->insertOne($event, $o);
        $changed = $this->db->background_jobs->updateOne(['_id' => $job['_id'], 'audit_sequence' => $job['audit_sequence'], 'audit_head_hash' => $job['audit_head_hash']] + $owner, ['$set' => ['audit_sequence' => new Int64($sequence), 'audit_head_hash' => $hash]], $o);
        if ($changed->getModifiedCount() !== 1) {
            throw new \RuntimeException('Outbox audit head conflict.');
        }
        $job['audit_sequence'] = new Int64($sequence);
        $job['audit_head_hash'] = $hash;

        return $job;
    }

    /** Verify every original result and every event in both directions before dispatch or mutation. */
    public function verify(array $job, ReferenceJobCommand $c, array $o): array
    {
        $items = [];
        foreach ($c->data['items'] as $item) {
            $items[$item['targetId']] = $item;
        }
        $results = [];
        foreach ($this->db->job_results->find(['job_id' => $job['_id']], $o + ['limit' => 101]) as $r) {
            $item = $items[$r['target_ref']] ?? null;
            if (! $item || $r['_id'] !== $item['resultId'] || $r['command_fingerprint'] !== $c->fingerprint() || $r['outcome_fingerprint'] !== self::resultHash($r) || isset($results[$r['target_ref']])) {
                throw new \RuntimeException('Outbox immutable result mismatch.');
            }
            new JobItemOutcome($r['result'], $r['error_code']);
            $results[$r['target_ref']] = $r;
        }
        if (count($results) > count($items)) {
            throw new \RuntimeException('Extra outbox results.');
        }
        $events = iterator_to_array($this->db->audit_events->find(['target_id' => $job['_id']], $o + ['sort' => ['job_sequence' => 1], 'limit' => 113]), false);
        if (! $events || count($events) > 112) {
            throw new \RuntimeException('Outbox audit evidence missing or excessive.');
        }
        $head = str_repeat('0', 64);
        $sequence = 0;
        $attempt = 0;
        $last = null;
        $lastLease = null;
        $seen = [];
        $finished = false;
        foreach ($events as $e) {
            $b = $e['event_redacted'];
            $sequence++;
            $at = $e['occurred_at'];
            if ($finished || (int) (string) $e['job_sequence'] !== $sequence || $b['sequence'] !== (string) $sequence || $e['target_type'] !== 'background_job' || $e['correlation_id'] !== $c->data['correlationId'] || ($e['actor_user_id'] ?? null) !== $c->data['requestedBy'] || $b['commandFingerprint'] !== $c->fingerprint() || $b['policyFingerprint'] !== $this->policy->fingerprint() || $b['reservationFingerprint'] !== hash('sha256', CanonicalAuthorityJson::encode($job['reserved_audit_ids'])) || ! hash_equals($head, bin2hex($e['prior_hash']->getData())) || ! hash_equals(self::hash($e), bin2hex($e['event_hash']->getData())) || ($last !== null && (int) (string) $at < (int) (string) $last)) {
                throw new \RuntimeException('Outbox audit chain mismatch.');
            }
            if ($e['action'] === 'job.queued') {
                if ($sequence !== 1 || $e['_id'] !== $c->data['enqueueAuditId'] || $b['outcome'] !== 'queued' || $b['attempt'] !== 0 || self::time($at) !== self::time($job['created_at'])) {
                    throw new \RuntimeException('Outbox genesis mismatch.');
                }
            } elseif ($e['action'] === 'job.claimed') {
                $attempt++;
                F::digest($b['leaseTokenHash']);
                $until = ReceiptSchema::time($b['leaseUntil']);
                if ($sequence === 1 || $attempt > $this->policy->maxAttempts || $e['_id'] !== $job['reserved_audit_ids'][count($c->auditIds()) + $attempt - 1] || $b['attempt'] !== $attempt || $b['outcome'] !== 'claimed' || $until->format('U.v') !== (new UTCDateTime((int) (string) $at + $this->policy->leaseMilliseconds))->toDateTime()->format('U.v') || ($lastLease !== null && $at->toDateTimeImmutable() < $lastLease['until'])) {
                    throw new \RuntimeException('Outbox lease history mismatch.');
                }
                $lastLease = ['tokenHash' => $b['leaseTokenHash'], 'until' => $until, 'at' => $at->toDateTimeImmutable()];
            } elseif ($e['action'] === 'job.item-completed') {
                $target = $b['itemId'];
                $r = $results[$target] ?? null;
                if (! $lastLease || ! $r || isset($seen[$target]) || $e['_id'] !== $items[$target]['auditId'] || $b['outcome'] !== $r['result'] || $b['outcomeFingerprint'] !== $r['outcome_fingerprint'] || self::time($at) !== self::time($r['attempted_at']) || $at->toDateTimeImmutable() >= $lastLease['until']) {
                    throw new \RuntimeException('Outbox item audit mismatch.');
                }$seen[$target] = true;
            } elseif ($e['action'] === 'job.finished') {
                if (count($seen) !== count($items) || $e['_id'] !== $c->data['finishAuditId'] || $b['outcome'] !== self::aggregate($results) || $job['status'] !== $b['outcome'] || ! isset($job['finished_at']) || self::time($at) !== self::time($job['finished_at']) || $last === null || self::time($at) !== self::time($last)) {
                    throw new \RuntimeException('Outbox final aggregate mismatch.');
                }$finished = true;
            } else {
                throw new \RuntimeException('Unknown outbox audit action.');
            }
            if ($b['attempt'] !== $attempt || ($e['action'] !== 'job.claimed' && ($b['leaseTokenHash'] !== null || $b['leaseUntil'] !== null)) || ($e['action'] !== 'job.item-completed' && ($b['itemId'] !== null || $b['outcomeFingerprint'] !== null))) {
                throw new \RuntimeException('Outbox event bindings mismatch.');
            }
            $head = bin2hex($e['event_hash']->getData());
            $last = $at;
        }
        if (count($seen) !== count($results) || $job['audit_head_hash'] !== $head || (int) (string) $job['audit_sequence'] !== $sequence || $job['attempt_count'] !== $attempt || (int) (string) $job['revision'] !== $attempt) {
            throw new \RuntimeException('Outbox head or generation mismatch.');
        }
        if ($finished) {
            if (isset($job['lease_token']) || isset($job['lease_until'])) {
                throw new \RuntimeException('Final job retains live lease.');
            }
        } elseif ($job['status'] === 'queued') {
            if ($attempt !== 0 || $results) {
                throw new \RuntimeException('Queued outbox state mismatch.');
            }
        } elseif ($job['status'] === 'running') {
            if (! $lastLease || count($results) === count($items) || hash('sha256', $job['lease_token']) !== $lastLease['tokenHash'] || $job['lease_until']->toDateTimeImmutable() != $lastLease['until']) {
                throw new \RuntimeException('Live outbox lease mismatch.');
            }
        } else {
            throw new \RuntimeException('Unproved terminal outbox state.');
        }

        return ['results' => $results, 'observedAt' => $last];
    }
}
