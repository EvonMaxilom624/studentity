<?php

declare(strict_types=1);

namespace App\Infrastructure\Governance;

use App\Domain\Governance\ApprovalRepository;
use App\Domain\Governance\GateApproval;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;

/** Read-only bookkeeping adapter, never an independent approval authority. */
final class MongoFeatureGateRepository implements ApprovalRepository
{
    public function __construct(private Database $database) {}

    public function forGate(string $gate, string $version): array
    {
        $result = [];
        foreach ($this->database->selectCollection('institutional_approvals', ['readConcern' => new ReadConcern(ReadConcern::MAJORITY), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY)])->find(['gate_code' => $gate, 'approved_version' => $version], ['limit' => 2]) as $row) {
            $known = ['_id', 'gate_code', 'approved_version', 'status', 'decision_ref', 'authority_label', 'evidence_uri', 'effective_at', 'approved_at', 'created_at', 'blocks_feature', 'schema_version'];
            if (($row['schema_version'] ?? null) !== 1 || array_diff(array_keys($row->getArrayCopy()), $known)) {
                throw new \RuntimeException('Unsupported approval representation.');
            }
            if (! ($row['effective_at'] ?? null) instanceof UTCDateTime || ! ($row['approved_at'] ?? null) instanceof UTCDateTime || ! ($row['created_at'] ?? null) instanceof UTCDateTime) {
                throw new \RuntimeException('Approval time evidence missing.');
            }
            $result[] = new GateApproval(['id' => $row['_id'], 'gate' => $row['gate_code'], 'version' => $row['approved_version'], 'status' => $row['status'], 'decisionRef' => $row['decision_ref'] ?? '', 'authority' => $row['authority_label'], 'evidence' => $row['evidence_uri'] ?? '', 'effectiveAt' => (int) (string) $row['effective_at'], 'approvedAt' => (int) (string) $row['approved_at'], 'createdAt' => (int) (string) $row['created_at'], 'schemaVersion' => 1, 'blocks' => $row['blocks_feature']]);
        }

        return $result;
    }
}
