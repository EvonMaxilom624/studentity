<?php

declare(strict_types=1);

namespace Tests\Support;

trait AuthorityReceiptFixtures
{
    protected function commandData(): array
    {
        return [
            'purpose' => 'studentity.session-revocation.command.v1', 'schemaVersion' => 1,
            'operationId' => '11111111-1111-4111-8111-111111111111', 'authorityNamespace' => 'synthetic-authority',
            'subjectUserId' => '22222222-2222-4222-8222-222222222222', 'sessionDigest' => str_repeat('a', 64),
            'securityEpoch' => '4', 'authorizationDigest' => str_repeat('b', 64), 'action' => 'session.revoke',
            'expectedIssuerGeneration' => 'generation-1', 'expectedCheckpointRef' => 'checkpoint-0',
            'correlationId' => '33333333-3333-4333-8333-333333333333',
        ];
    }
}
