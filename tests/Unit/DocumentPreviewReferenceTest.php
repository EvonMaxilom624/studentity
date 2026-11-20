<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Documents\DocumentPreviewReference;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class DocumentPreviewReferenceTest extends TestCase
{
    private function validData(array $replace = []): array
    {
        return array_replace([
            'previewObjectId' => (string) Str::uuid(),
            'originalObjectId' => (string) Str::uuid(),
            'documentVersionId' => (string) Str::uuid(),
            'sha256' => hash('sha256', 'synthetic-preview-bytes'),
            'byteCount' => 1024,
            'mediaType' => 'image/png',
            'generatedAt' => '2026-11-20T00:00:00.000Z',
        ], $replace);
    }

    public function test_valid_preview_reference_constructs_and_generates_fingerprint(): void
    {
        $data = $this->validData();
        $ref = new DocumentPreviewReference(...$data);
        self::assertSame($data['previewObjectId'], $ref->previewObjectId);
        self::assertSame($data['originalObjectId'], $ref->originalObjectId);
        self::assertSame($data['documentVersionId'], $ref->documentVersionId);
        self::assertSame($data['sha256'], $ref->sha256);
        self::assertSame(1024, $ref->byteCount);
        self::assertSame('image/png', $ref->mediaType);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $ref->fingerprint());
    }

    public function test_preview_object_id_must_differ_from_original_object_id(): void
    {
        $id = (string) Str::uuid();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Preview must be a distinct immutable object preserving original.');
        new DocumentPreviewReference(...$this->validData(['previewObjectId' => $id, 'originalObjectId' => $id]));
    }

    public function test_unsupported_media_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DocumentPreviewReference(...$this->validData(['mediaType' => 'application/pdf']));
    }

    public function test_invalid_sha256_is_rejected(): void
    {
        $this->expectException(\Throwable::class);
        new DocumentPreviewReference(...$this->validData(['sha256' => 'invalid-hash']));
    }

    public function test_zero_or_negative_byte_count_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DocumentPreviewReference(...$this->validData(['byteCount' => 0]));
    }

    public function test_debug_info_redacts_preview(): void
    {
        $ref = new DocumentPreviewReference(...$this->validData());
        self::assertSame(['preview' => 'redacted'], $ref->__debugInfo());
    }
}
