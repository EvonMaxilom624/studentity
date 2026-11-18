<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Identity\UserKind;
use App\Domain\Security\AuthorizationContext;
use App\Domain\Security\RoleGrant;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use RuntimeException;
use Throwable;

final class MongoAuthorizationRepository
{
    public function __construct(private Database $database) {}

    private function collection(string $name): Collection
    {
        return $this->database->selectCollection($name, ['readPreference' => new ReadPreference(ReadPreference::PRIMARY), 'readConcern' => new ReadConcern(ReadConcern::MAJORITY), 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY)]);
    }

    public function context(string $userId, string $sessionDigest): ?AuthorizationContext
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $sessionDigest)) {
            return null;
        }
        $user = $this->collection('users')->findOne(['_id' => $userId]);
        $session = $this->collection('user_sessions')->findOne(['user_id' => $userId, 'session_digest' => new Binary(hex2bin($sessionDigest))]);
        if ($user === null || $session === null) {
            return null;
        }
        $grants = [];
        foreach ($this->collection('role_grants')->find(['user_id' => $userId, 'is_active' => true, 'revoked_at' => ['$exists' => false]]) as $grant) {
            $permissions = json_decode(json_encode($grant['permission_set'], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $grants[] = new RoleGrant($grant['_id'], UserKind::from($grant['role_name']), $grant['program_id'] ?? null, $permissions);
        }

        return new AuthorizationContext(
            $userId, UserKind::from($user['kind']), $user['account_state'], (int) (string) $user['security_epoch'], (int) (string) $session['security_epoch'], $sessionDigest,
            $session['created_at']->toDateTime()->getTimestamp(), $session['expires_at']->toDateTime()->getTimestamp(),
            isset($session['revoked_at']) ? $session['revoked_at']->toDateTime()->getTimestamp() : null,
            isset($session['mfa_verified_at']) ? $session['mfa_verified_at']->toDateTime()->getTimestamp() : null, $grants, $session['_id'],
        );
    }

    /** Independent revocation must already be acknowledged; this local update and audit commit together. */
    public function revokeWithAudit(string $userId, string $sessionDigest, callable $audit): void
    {
        $session = $this->database->getManager()->startSession();
        try {
            $session->startTransaction(['readConcern' => new ReadConcern(ReadConcern::SNAPSHOT), 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY), 'readPreference' => new ReadPreference(ReadPreference::PRIMARY)]);
            $result = $this->collection('user_sessions')->updateOne(['user_id' => $userId, 'session_digest' => new Binary(hex2bin($sessionDigest)), 'revoked_at' => ['$exists' => false]], ['$set' => ['revoked_at' => new UTCDateTime]], ['session' => $session]);
            if ($result->getMatchedCount() !== 1) {
                throw new RuntimeException('Current local session could not be revoked.');
            }
            $audit($session);
            $session->commitTransaction();
        } catch (Throwable $exception) {
            if ($session->isInTransaction()) {
                $session->abortTransaction();
            }
            throw $exception;
        } finally {
            $session->endSession();
        }
    }
}
