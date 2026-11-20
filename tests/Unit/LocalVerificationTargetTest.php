<?php

namespace Tests\Unit;

use App\Infrastructure\Verification\LocalVerificationTarget;
use PHPUnit\Framework\TestCase;

final class LocalVerificationTargetTest extends TestCase
{
    public function test_only_explicit_loopback_rs0_uri_is_admissible_before_connecting(): void
    {
        self::assertTrue(LocalVerificationTarget::allows('mongodb://127.0.0.1:27017/?replicaSet=rs0'));
        self::assertTrue(LocalVerificationTarget::allows('mongodb://localhost:27017/?replicaSet=rs0'));
        self::assertTrue(LocalVerificationTarget::allows('mongodb://127.0.0.1:27017/?replicaSet=rs0&directConnection=true'));
        foreach (['mongodb+srv://example.invalid/', 'mongodb://remote.invalid/?replicaSet=rs0', 'mongodb://127.0.0.1,remote.invalid/?replicaSet=rs0', 'mongodb://127.0.0.1/?replicaSet=other', 'mongodb://127.0.0.1/', 'mongodb://secret@remote.invalid/?replicaSet=rs0', 'mongodb://127.0.0.1/?replicaSet=rs0&replicaSet=other'] as $dsn) {
            self::assertFalse(LocalVerificationTarget::allows($dsn));
        }
    }

    public function test_actual_replica_topology_must_remain_local_before_any_tests(): void
    {
        $hello = ['setName' => 'rs0', 'isWritablePrimary' => true, 'hosts' => ['127.0.0.1:27017'], 'primary' => '127.0.0.1:27017', 'me' => '127.0.0.1:27017'];
        self::assertTrue(LocalVerificationTarget::allowsTopology($hello, '127.0.0.1'));
        self::assertFalse(LocalVerificationTarget::allowsTopology($hello, 'remote.invalid'));
        $hello['hosts'][] = 'remote.invalid:27017';
        self::assertFalse(LocalVerificationTarget::allowsTopology($hello, '127.0.0.1'));
        $hello['hosts'] = ['127.0.0.1:27017'];
        $hello['isWritablePrimary'] = false;
        self::assertFalse(LocalVerificationTarget::allowsTopology($hello, '127.0.0.1'));
    }
}
