<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Security\Authority\CanonicalAuthorityJson;
use App\Domain\Security\Authority\ReceiptContractViolation;
use App\Domain\Security\Authority\VerifiedRevocationReceipt;
use Closure;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Document;
use MongoDB\BSON\Int64;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;

/** Test-only replay of already verified denial receipts. Never calls authority or controller code. */
final class ReceiptJournalReplayLab
{
    public function __construct(private Database $database)
    {
        if (! app()->environment('testing') || $database->getDatabaseName() !== 'studentity_test_m0') {
            throw new \InvalidArgumentException('Exact synthetic receipt database required.');
        }
        if (! in_array($database->getManager()->selectServer(new ReadPreference(ReadPreference::PRIMARY))->getHost(), ['127.0.0.1', 'localhost'], true)) {
            throw new \InvalidArgumentException('Local receipt replica set required.');
        }
    }

    public function apply(VerifiedRevocationReceipt $verified, ?Closure $beforeCommit = null, ?Closure $afterCommit = null): string
    {
        $receipt = $verified->receipt->data;
        $session = $this->database->getManager()->startSession();
        $commitAttempted = false;
        try {
            $session->startTransaction(['readConcern' => new ReadConcern(ReadConcern::SNAPSHOT), 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY)]);
            $control = $this->database->selectCollection('user_sessions');
            $record = $control->findOne(['session_digest' => new Binary(hex2bin($receipt['sessionDigest']))], ['session' => $session]);
            if ($record !== null) {
                if ($record['user_id'] !== $receipt['subjectUserId'] || (string) $record['security_epoch'] !== $receipt['securityEpoch']) {
                    throw new ReceiptContractViolation('Receipt control binding conflict.');
                }
                if (! isset($record['revoked_at'])) {
                    $updated = $control->updateOne(['_id' => $record['_id'], 'user_id' => $receipt['subjectUserId'], 'security_epoch' => new Int64($receipt['securityEpoch']), 'revoked_at' => ['$exists' => false]], ['$set' => ['revoked_at' => $verified->journal()['occurred_at']]], ['session' => $session, 'upsert' => false]);
                    if ($updated->getMatchedCount() !== 1) {
                        throw new ReceiptContractViolation('Receipt control changed.');
                    }
                }
            }
            foreach (['security_decision_journal' => $verified->journal(), 'audit_events' => $verified->audit()] as $name => $document) {
                $collection = $this->database->selectCollection($name);
                $existing = $collection->findOne(['_id' => $document['_id']], ['session' => $session]);
                if ($existing === null) {
                    $collection->insertOne($document, ['session' => $session]);
                } elseif (! hash_equals(self::typedBody($document), self::typedBody($existing->getArrayCopy()))) {
                    throw new ReceiptContractViolation('Conflicting immutable receipt history.');
                }
            }
            $beforeCommit?->__invoke();
            $commitAttempted = true;
            $session->commitTransaction();
            $afterCommit?->__invoke();

            return $record === null ? 'history-only' : 'confirmed';
        } catch (\Throwable $exception) {
            if ($commitAttempted) {
                // A fresh call can reconcile actual state. Never assume noncommit or replay external work.
                throw new \RuntimeException('Local receipt outcome uncertain.');
            }
            if ($session->isInTransaction()) {
                $session->abortTransaction();
            }
            throw $exception;
        } finally {
            $session->endSession();
        }
    }

    private static function typedBody(array $document): string
    {
        $extended = Document::fromPHP($document)->toCanonicalExtendedJSON();

        return CanonicalAuthorityJson::encode(json_decode($extended, false, 32, JSON_THROW_ON_ERROR));
    }
}
