<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FileSecurityFixtures;

final class FileSecurityContractTest extends TestCase
{
    use FileSecurityFixtures;

    public function test_immutable_object_identity_and_every_content_owner_version_field_bind_fingerprint(): void
    {
        $r = $this->fileReference();
        $fp = $r->fingerprint();
        foreach (['objectId' => '55555555-5555-4555-8555-555555555555', 'documentVersionId' => '55555555-5555-4555-8555-555555555555', 'ownerUserId' => '55555555-5555-4555-8555-555555555555', 'sha256' => str_repeat('a', 64), 'byteCount' => 999, 'mediaType' => 'image/png'] as $k => $v) {
            self::assertNotSame($fp, $this->fileReference([$k => $v])->fingerprint());
        }
        self::assertSame($fp, $this->fileReference()->fingerprint());
        self::assertFalse(property_exists($r, 'objectKey'));
        self::assertFalse(property_exists($r, 'path'));
    }

    public static function invalidReferences(): array
    {
        return [[['objectId' => '../public/file']], [['ownerUserId' => 'not-an-id']], [['sha256' => 'bad']], [['byteCount' => 0]], [['byteCount' => -1]], [['mediaType' => 'application/x-php']]];
    }

    #[DataProvider('invalidReferences')]
    public function test_invalid_reference_cannot_reach_any_storage_or_scanner(array $replace): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->fileReference($replace);
    }

    public function test_policy_binds_current_scanner_signature_and_budget_without_production_defaults(): void
    {
        $p = $this->filePolicy();
        foreach (['reference' => 'synthetic-file-policy-v2', 'scannerId' => 'other-scanner', 'signatureVersion' => 'next-signatures', 'roundTripMilliseconds' => 100] as $k => $v) {
            self::assertNotSame($p->fingerprint(), $this->filePolicy([$k => $v])->fingerprint());
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->filePolicy(['roundTripMilliseconds' => 0]);
    }

    public function test_a_clean_status_requires_every_distinct_security_check(): void
    {
        $r = $this->fileReference();
        $p = $this->filePolicy();
        foreach (['typeValid', 'structureSafe', 'malwareClean', 'activeContentSafe'] as $f) {
            self::assertInstanceOf(\InvalidArgumentException::class, $this->fileRejects(fn () => $this->fileResult($r, $p, [$f => false])));
        }
        self::assertSame('clean', $this->fileResult($r, $p)->status);
    }

    public function test_result_rejects_unknown_status_and_unknown_clean_media_type(): void
    {
        $r = $this->fileReference();
        $p = $this->filePolicy();
        self::assertInstanceOf(\InvalidArgumentException::class, $this->fileRejects(fn () => $this->fileResult($r, $p, ['status' => 'approved'])));
        self::assertInstanceOf(\InvalidArgumentException::class, $this->fileRejects(fn () => $this->fileResult($r, $p, ['detectedMediaType' => null])));
    }
}
