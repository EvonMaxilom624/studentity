<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Domain\Documents\FileScanCommand;
use App\Domain\Documents\FileScanResult;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\ScanAttemptJournal;
use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityJson as J;
use App\Domain\Security\Authority\ReceiptSchema;
use App\Infrastructure\Jobs\HandlerLabDatabase;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;

/** Unbound lab-only consistency journal. Gates are server-owned local callbacks, not network I/O. */
final class MongoScanAttemptJournal implements ScanAttemptJournal
{
    public function __construct(private Database $db, private ?\Closure $gate = null, private ?\Closure $recovery = null, private ?\Closure $beforeCommit = null, private ?\Closure $afterCommit = null)
    {
        ScanJournalLabDatabase::assertReady($db);
        $this->db = $db->withOptions(['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
    }

    private function allow(string $stage, FileScanCommand $c): void
    {
        if ($this->gate === null || $this->recovery === null || ($this->recovery)($stage, $c) !== true || ($this->gate)($stage, $c) !== true) {
            throw new \RuntimeException('Current journal admission/recovery unavailable.');
        }
    }

    private function tx(string $stage, FileScanCommand $c, \Closure $work): mixed
    {
        try {
            $this->allow($stage, $c);
            $result = ScanJournalLabDatabase::transaction($this->db, function ($o) use ($stage, $c, $work) {
                $this->allow($stage, $c);
                $r = $work($o);
                $this->allow($stage, $c);
                if ($this->beforeCommit !== null) {
                    ($this->beforeCommit)($stage);
                }

                return $r;
            });
            if ($this->afterCommit !== null) {
                ($this->afterCommit)($stage);
            }$this->allow($stage, $c);

            return $result;
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('Scan journal unavailable or conflicting; evidence retained.');
        }
    }

    private function row(string $id, string $object, array $d): array
    {
        $wire = J::encode($d);

        return ['_id' => $id, 'object_id' => $object, 'wire' => $wire, 'hash' => hash('sha256', $wire)];
    }

    private function unpack(array $r): array
    {
        $d = json_decode($r['wire'], true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($d) || J::encode($d) !== $r['wire'] || hash('sha256', $r['wire']) !== $r['hash']) {
            throw new \RuntimeException('Journal wire mismatch.');
        }

        return $d;
    }

    private function now(array $o, ?array $head): string
    {
        $at = HandlerLabDatabase::now($this->db, $o)->toDateTimeImmutable()->format('Y-m-d\TH:i:s.v\Z');
        if ($head !== null && ReceiptSchema::time($at) < ReceiptSchema::time($head['at'])) {
            throw new \RuntimeException('Journal clock rollback.');
        }

        return $at;
    }

    private function rawResult(FileScanResult $r): array
    {
        $d = get_object_vars($r);
        $d['checkedAt'] = (new UTCDateTime($r->checkedAt))->toDateTimeImmutable()->format('Y-m-d\TH:i:s.v\Z');

        return $d;
    }

    private function validateResult(FileScanCommand $c, array $d, string $nonce, string $lower, string $upper): FileScanResult
    {
        F::keys($d, ['objectFingerprint', 'policyFingerprint', 'scanId', 'scannerId', 'signatureVersion', 'status', 'detectedMediaType', 'checkedAt', 'typeValid', 'structureSafe', 'malwareClean', 'activeContentSafe', 'attemptNonce']);
        $d['checkedAt'] = ReceiptSchema::time($d['checkedAt']);
        $r = new FileScanResult(...$d);
        if ($r->scanId !== $c->scanId || $r->objectFingerprint !== $c->object->fingerprint() || $r->policyFingerprint !== $c->policy->fingerprint() || $r->attemptNonce !== $nonce || $r->scannerId !== $c->policy->scannerId || $r->signatureVersion !== $c->policy->signatureVersion || $r->checkedAt < ReceiptSchema::time($lower) || $r->checkedAt > ReceiptSchema::time($upper) || ($r->status === 'clean' && $r->detectedMediaType !== $c->object->mediaType)) {
            throw new \RuntimeException('Journal result binding mismatch.');
        }

        return $r;
    }

    private function evidence(array $event, array $row): array
    {
        $audit = $this->row($row['_id'], $row['object_id'], ['purpose' => 'studentity.scan.audit.v1', 'eventId' => $row['_id'], 'eventHash' => $row['hash'], 'kind' => $event['kind'], 'at' => $event['at']]);
        $outbox = $event['kind'] === 'completed' ? $this->row($row['_id'], $row['object_id'], ['purpose' => 'studentity.scan.outbox.v1', 'eventId' => $row['_id'], 'eventHash' => $row['hash'], 'state' => $this->resultState($event['result']['status'])]) : null;

        return [$audit, $outbox];
    }

    private function resultState(string $status): string
    {
        return ['clean' => 'cleared', 'unsafe' => 'unsafe'][$status] ?? 'pending';
    }

    /** Full snapshot forward/reverse proof; hashes detect inconsistency, never a coherent whole-backup rollback. */
    private function read(FileScanCommand $expected, array $o): array
    {
        $object = $expected->object->objectId;
        $headRow = $this->db->scan_heads->findOne(['_id' => $object], $o);
        $physicalHeads = iterator_to_array($this->db->scan_heads->find(['object_id' => $object], $o + ['limit' => 2]), false);
        if (count($physicalHeads) !== ($headRow === null ? 0 : 1)) {
            throw new \RuntimeException('Orphan or duplicate physical head.');
        }$rows = iterator_to_array($this->db->scan_history->find(['object_id' => $object], $o + ['limit' => 1001]), false);
        if (count($rows) > 1000) {
            throw new \RuntimeException('Lab history cap reached.');
        }
        $events = [];
        foreach ($rows as $row) {
            $d = $this->unpack($row);
            F::keys($d, ['purpose', 'objectId', 'sequence', 'previousHash', 'kind', 'command', 'nonce', 'generation', 'at', 'result']);
            if (! is_int($d['sequence']) || $d['sequence'] < 1 || isset($events[$d['sequence']])) {
                throw new \RuntimeException;
            }$events[$d['sequence']] = [$d, $row];
        }ksort($events);
        $head = null;
        $tip = str_repeat('0', 64);
        $seq = 0;
        $generation = 0;
        $nonces = [];
        $audits = [];
        $outboxes = [];
        foreach ($events as [$d,$row]) {
            $c = FileScanCommand::fromWire($d['command']);
            F::digest($d['nonce']);
            ReceiptSchema::time($d['at']);
            if ($d['purpose'] !== 'studentity.scan.event.v1' || $d['objectId'] !== $object || $row['object_id'] !== $object || $d['sequence'] !== ++$seq || $row['_id'] !== $object.':'.$seq || $d['previousHash'] !== $tip || $c->wire() !== $d['command'] || ($c->scanId === $expected->scanId && $c->wire() !== $expected->wire()) || $c->object->fingerprint() !== $expected->object->fingerprint() || ReceiptSchema::time($c->issuedAt) > ReceiptSchema::time($d['at']) || ($head !== null && ReceiptSchema::time($d['at']) < ReceiptSchema::time($head['at']))) {
                throw new \RuntimeException('Journal chain conflict.');
            }
            if ($d['kind'] === 'requested') {
                if ($d['result'] !== null || $d['generation'] !== ++$generation || isset($nonces[$d['nonce']])) {
                    throw new \RuntimeException;
                }$nonces[$d['nonce']] = true;
                $state = 'pending';
                $done = false;
            } elseif ($d['kind'] === 'completed') {
                if ($head === null || $head['done'] || $d['generation'] !== $generation || $head['nonce'] !== $d['nonce'] || $head['command'] !== $d['command']) {
                    throw new \RuntimeException;
                }$this->validateResult($c, $d['result'], $d['nonce'], $head['at'], $d['at']);
                $state = $this->resultState($d['result']['status']);
                $done = true;
            } else {
                throw new \RuntimeException;
            }
            $tip = $row['hash'];
            $head = ['objectId' => $object, 'sequence' => $seq, 'tip' => $tip, 'generation' => $generation, 'nonce' => $d['nonce'], 'command' => $d['command'], 'state' => $state, 'done' => $done, 'at' => $d['at']];
            [$a,$b] = $this->evidence($d, $row);
            $audits[$row['_id']] = $a;
            if ($b !== null) {
                $outboxes[$row['_id']] = $b;
            }
        }
        if (($head === null) !== ($headRow === null) || ($headRow !== null && ($headRow['object_id'] !== $object || J::encode($this->unpack($headRow)) !== J::encode($head)))) {
            throw new \RuntimeException('Latest head/history mismatch.');
        }
        foreach (['scan_audit' => $audits, 'scan_outbox' => $outboxes] as $name => $wanted) {
            $actual = iterator_to_array($this->db->selectCollection($name)->find(['object_id' => $object], $o + ['limit' => 1001]), false);
            if (count($actual) !== count($wanted)) {
                throw new \RuntimeException('Orphan journal evidence.');
            }foreach ($actual as $row) {
                if (! isset($wanted[$row['_id']]) || J::encode($row) !== J::encode($wanted[$row['_id']])) {
                    throw new \RuntimeException('Journal evidence mismatch.');
                }
            }
        }

        return [$head, $headRow, $nonces];
    }

    private function append(FileScanCommand $c, string $nonce, string $kind, ?array $result, ?array $head, ?array $oldRow, string $at, array $o): void
    {
        $object = $c->object->objectId;
        $seq = ($head['sequence'] ?? 0) + 1;
        if ($seq > 1000) {
            throw new \RuntimeException('Lab history cap reached.');
        }$generation = ($head['generation'] ?? 0) + ($kind === 'requested' ? 1 : 0);
        $d = ['purpose' => 'studentity.scan.event.v1', 'objectId' => $object, 'sequence' => $seq, 'previousHash' => $head['tip'] ?? str_repeat('0', 64), 'kind' => $kind, 'command' => $c->wire(), 'nonce' => $nonce, 'generation' => $generation, 'at' => $at, 'result' => $result];
        $row = $this->row($object.':'.$seq, $object, $d);
        $h = ['objectId' => $object, 'sequence' => $seq, 'tip' => $row['hash'], 'generation' => $generation, 'nonce' => $nonce, 'command' => $c->wire(), 'state' => $kind === 'requested' ? 'pending' : $this->resultState($result['status']), 'done' => $kind === 'completed', 'at' => $at];
        $new = $this->row($object, $object, $h);
        if ($oldRow === null) {
            $this->db->scan_heads->insertOne($new, $o);
        } elseif ($this->db->scan_heads->replaceOne(['_id' => $object, 'hash' => $oldRow['hash']], $new, $o)->getModifiedCount() !== 1) {
            throw new \RuntimeException('Journal head CAS conflict.');
        }
        $this->db->scan_history->insertOne($row, $o);
        [$audit,$outbox] = $this->evidence($d, $row);
        $this->db->scan_audit->insertOne($audit, $o);
        if ($outbox !== null) {
            $this->db->scan_outbox->insertOne($outbox, $o);
        }
    }

    public function begin(FileScanCommand $c, string $attemptNonce): void
    {
        $this->tx('begin', $c, function ($o) use ($c, $attemptNonce) {
            F::digest($attemptNonce);
            [$head,$row,$nonces] = $this->read($c, $o);
            $at = $this->now($o, $head);
            if (ReceiptSchema::time($c->issuedAt) > ReceiptSchema::time($at)) {
                throw new \RuntimeException;
            }
            if (isset($nonces[$attemptNonce])) {
                if ($head['nonce'] !== $attemptNonce || $head['command'] !== $c->wire()) {
                    throw new \RuntimeException('Old admission cannot reactivate.');
                }

                return;
            }
            $this->append($c, $attemptNonce, 'requested', null, $head, $row, $at, $o);
        });
    }

    public function complete(FileScanCommand $c, FileScanResult $r): void
    {
        $this->tx('complete', $c, function ($o) use ($c, $r) {
            [$head,$row] = $this->read($c, $o);
            $at = $this->now($o, $head);
            if ($head === null || $r->attemptNonce === null || $head['nonce'] !== $r->attemptNonce || $head['command'] !== $c->wire()) {
                throw new \RuntimeException('Superseded scan completion.');
            }$d = $this->rawResult($r);
            if ($head['done']) {
                $last = $this->db->scan_history->findOne(['_id' => $c->object->objectId.':'.$head['sequence']], $o);
                if (J::encode($this->unpack($last)['result']) !== J::encode($d)) {
                    throw new \RuntimeException('Conflicting result replay.');
                }

                return;
            }
            $this->validateResult($c, $d, $r->attemptNonce, $head['at'], $at);
            $this->append($c, $r->attemptNonce, 'completed', $d, $head, $row, $at, $o);
        });
    }

    public function state(FileScanCommand $c): ?array
    {
        return $this->tx('read', $c, function ($o) use ($c) {
            [$head] = $this->read($c, $o);
            $this->now($o, $head);
            if ($head !== null && FileScanCommand::fromWire($head['command'])->policy->fingerprint() !== $c->policy->fingerprint()) {
                throw new \RuntimeException('Current journal policy mismatch.');
            }

            return $head;
        });
    }
}
