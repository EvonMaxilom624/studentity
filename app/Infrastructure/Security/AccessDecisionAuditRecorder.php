<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Security\AccessDecision;
use App\Domain\Security\AuthorizationContext;
use App\Domain\Security\ResourceScope;
use Illuminate\Support\Str;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\Session;
use MongoDB\Driver\WriteConcern;

class AccessDecisionAuditRecorder
{
    public function __construct(private Database $database) {}

    public function record(?AuthorizationContext $context, ResourceScope $scope, string $ability, AccessDecision $decision, string $correlationId, ?Session $session = null): void
    {
        $payload = ['ability' => $ability, 'allowed' => $decision->allowed, 'reason' => $decision->reason, 'program_id' => $scope->programId, 'security_epoch' => $context?->securityEpoch];
        $document = ['_id' => (string) Str::uuid(), 'action' => 'authorization.decision', 'target_type' => $scope->type, 'correlation_id' => $correlationId, 'event_redacted' => (object) $payload, 'occurred_at' => new UTCDateTime, 'schema_version' => 1];
        if ($context !== null) {
            $document['actor_user_id'] = $context->userId;
        }
        if ($scope->id !== null) {
            $document['target_id'] = $scope->id;
        }
        // Per-event integrity hash; this is not a substitute for an independent signed audit anchor.
        $document['event_hash'] = new Binary(hash('sha256', json_encode($document, JSON_THROW_ON_ERROR), true));
        $this->database->selectCollection('audit_events', ['writeConcern' => new WriteConcern(WriteConcern::MAJORITY)])->insertOne($document, $session === null ? [] : ['session' => $session]);
    }
}
