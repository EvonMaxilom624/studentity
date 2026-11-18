<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\RevocationCommand;
use App\Domain\Security\Authority\RevocationReceipt;
use Closure;
use MongoDB\BSON\Binary;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;

/** Synthetic repair coordinator, not a durable job queue or a production authority adapter. */
final class StagedSessionRepairLab
{
    public function __construct(private Database $transportDb, private Database $securityDb, private EncryptedSessionLabStore $transport, private ReceiptJournalReplayLab $replay, private ReceiptAuthorityLabPort $authority)
    {
        if (! app()->environment('testing') || $transportDb->getDatabaseName() !== 'studentity_test_session_activation' || $securityDb->getDatabaseName() !== 'studentity_test_m0') {
            throw new \InvalidArgumentException('Exact synthetic repair databases required.');
        }
        foreach ([$transportDb, $securityDb] as $database) {
            if (! in_array($database->getManager()->selectServer(new ReadPreference(ReadPreference::PRIMARY))->getHost(), ['127.0.0.1', 'localhost'], true)) {
                throw new \InvalidArgumentException('Local repair databases required.');
            }
        }
    }

    public function repair(array $snapshot, ?RevocationCommand $command = null, ?Closure $beforeLocalCommit = null, ?Closure $afterLocalCommit = null): string
    {
        // Copy validated scalars so caller references cannot change a task mid-operation.
        $digest = $snapshot['_id'] ?? null;
        $operation = $snapshot['issuance_operation_id'] ?? null;
        $generation = $snapshot['generation'] ?? null;
        $revision = $snapshot['revision'] ?? null;
        if (! is_string($digest) || ! preg_match('/\A[0-9a-f]{64}\z/D', $digest) || ! is_string($operation) || ! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $operation)
            || ! is_int($generation) || ! is_int($revision) || $generation < 0 || $revision < 0 || $generation >= PHP_INT_MAX || $revision >= PHP_INT_MAX) {
            throw new \InvalidArgumentException('Invalid owned stage snapshot.');
        }
        if ($command !== null && ($command->data['operationId'] !== $operation || $command->data['sessionDigest'] !== $digest)) {
            throw new ReceiptContractViolation('Repair command binding conflict.');
        }
        $readOptions = ['readPreference' => new ReadPreference(ReadPreference::PRIMARY), 'readConcern' => new ReadConcern(ReadConcern::MAJORITY)];
        $rows = $this->transportDb->selectCollection('envelopes_v1', $readOptions);
        $filter = ['_id' => $digest, 'issuance_operation_id' => $operation];
        $row = $rows->findOne($filter);
        if ($row === null) {
            return 'not-eligible';
        }
        if ($row['lifecycle'] === 'staged') {
            if ((string) $row['generation'] !== (string) $generation || (string) $row['revision'] !== (string) $revision || ! $this->transport->repairStaged($digest, $generation, $revision)) {
                return 'not-eligible';
            }
            $row = $rows->findOne($filter);
        }
        // Only this stage's exact CAS tombstone is resumable. A formerly live session
        // has an extra payload revision and cannot be revoked from an obsolete stage snapshot.
        if ($row === null || $row['lifecycle'] !== 'revoked' || (string) $row['generation'] !== (string) ($generation + 1) || (string) $row['revision'] !== (string) ($revision + 1)) {
            return 'not-eligible';
        }

        $record = $this->securityDb->selectCollection('user_sessions', $readOptions)->findOne(['session_digest' => new Binary(hex2bin($digest))]);
        if ($command === null) {
            return $record === null ? 'transport-only' : 'authority-unknown';
        }
        if ($record !== null && ($record['user_id'] !== $command->data['subjectUserId'] || (string) $record['security_epoch'] !== $command->data['securityEpoch'])) {
            throw new ReceiptContractViolation('Repair control binding conflict.');
        }

        try {
            $status = $this->authority->lookup($operation);
            $receipt = match ($status['status']) {
                'committed' => $status['receipt'],
                'definitivelyNotCommitted' => $this->authority->revoke($command),
                default => null,
            };
            if (! $receipt instanceof RevocationReceipt) {
                return 'authority-unknown';
            }
            $verified = $this->authority->verify($command, $receipt);
        } catch (\Throwable) {
            return 'authority-unknown';
        }
        try {
            $result = $this->replay->apply($verified, $beforeLocalCommit, $afterLocalCommit);
        } catch (\Throwable) {
            return 'local-incomplete';
        }

        return match ($result) {
            'confirmed' => 'repaired', 'history-only' => 'repaired-history-only', default => 'local-incomplete'
        };
    }
}
