<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Documents\DocumentPreviewReference;
use App\Domain\Documents\DocumentPreviewService;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\QuarantineObjectReference;
use Illuminate\Support\Str;
use Tests\Support\FileSecurityFixtures;
use Tests\TestCase;

final class DocumentPreviewServiceTest extends TestCase
{
    use FileSecurityFixtures;

    public function test_clearance_revoked_during_generation_rejects_preview(): void
    {
        $state = (object) ['clear' => true];
        $service = new DocumentPreviewService(fn () => $state->clear ? hash('sha256', 'synthetic-current-clearance') : null);
        $this->expectException(FileSecurityUnavailable::class);
        $service->generate($this->fileReference(), $this->fileBytes(), (string) Str::uuid(), function () use ($state) {
            $state->clear = false;

            return 'synthetic-preview';
        }, 'image/png', new \DateTimeImmutable);
    }

    public function test_generated_timestamp_represents_the_actual_utc_instant(): void
    {
        $service = new DocumentPreviewService(fn () => hash('sha256', 'synthetic-current-clearance'));
        $preview = $service->generate($this->fileReference(), $this->fileBytes(), (string) Str::uuid(), fn () => 'synthetic-preview', 'image/png', new \DateTimeImmutable('2026-11-20T12:00:00+08:00'));
        self::assertSame('2026-11-20T04:00:00.000Z', $preview->generatedAt);
    }

    public function test_changed_clearance_revision_or_expired_budget_rejects_preview(): void
    {
        $state = (object) ['revision' => 'before'];
        $service = new DocumentPreviewService(fn () => hash('sha256', $state->revision));
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $service->generate($this->fileReference(), $this->fileBytes(), (string) Str::uuid(), function () use ($state) {
            $state->revision = 'after';

            return 'synthetic-preview';
        }, 'image/png', new \DateTimeImmutable)));
        $service = new DocumentPreviewService(fn () => hash('sha256', 'synthetic-current'), budgetMilliseconds: 1);
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $service->generate($this->fileReference(), $this->fileBytes(), (string) Str::uuid(), function () {
            usleep(10000);

            return 'synthetic-preview';
        }, 'image/png', new \DateTimeImmutable)));
    }

    public function test_cleared_object_generates_preview_and_preserves_original_bytes(): void
    {
        $original = $this->fileReference(['mediaType' => 'application/pdf']);
        $originalBytes = $this->fileBytes();
        $previewId = (string) Str::uuid();
        $previewBytes = "PNG\x0D\x0A\x1A\x0A-synthetic-preview-image-data";

        $service = new DocumentPreviewService(fn (QuarantineObjectReference $o) => hash('sha256', 'synthetic-current-clearance'));
        $preview = $service->generate(
            $original,
            $originalBytes,
            $previewId,
            fn ($o, $bytes) => $previewBytes,
            'image/png',
            new \DateTimeImmutable('2026-11-20T00:00:00.000Z')
        );

        self::assertInstanceOf(DocumentPreviewReference::class, $preview);
        self::assertSame($previewId, $preview->previewObjectId);
        self::assertSame($original->objectId, $preview->originalObjectId);
        self::assertSame($original->documentVersionId, $preview->documentVersionId);
        self::assertSame(hash('sha256', $previewBytes), $preview->sha256);
        self::assertSame(strlen($previewBytes), $preview->byteCount);
        self::assertSame('image/png', $preview->mediaType);

        // Original reference and original bytes remain unchanged and preserved
        self::assertNotSame($original->objectId, $preview->previewObjectId);
        self::assertSame(hash('sha256', $originalBytes), $original->sha256);
    }

    public function test_uncleared_or_unsafe_object_refuses_preview_generation(): void
    {
        $original = $this->fileReference();
        $service = new DocumentPreviewService(fn (QuarantineObjectReference $o) => false);

        $this->expectException(FileSecurityUnavailable::class);
        $service->generate(
            $original,
            $this->fileBytes(),
            (string) Str::uuid(),
            fn () => 'preview',
            'image/png',
            new \DateTimeImmutable
        );
    }

    public function test_identical_preview_id_to_original_id_fails_closed(): void
    {
        $original = $this->fileReference();
        $service = new DocumentPreviewService(fn () => hash('sha256', 'synthetic-current-clearance'));

        $this->expectException(FileSecurityUnavailable::class);
        $service->generate(
            $original,
            $this->fileBytes(),
            $original->objectId,
            fn () => 'preview',
            'image/png',
            new \DateTimeImmutable
        );
    }

    public function test_corrupt_original_bytes_fail_closed(): void
    {
        $original = $this->fileReference();
        $corruptBytes = 'corrupted-bytes-different-length';
        $service = new DocumentPreviewService(fn () => hash('sha256', 'synthetic-current-clearance'));

        $this->expectException(FileSecurityUnavailable::class);
        $service->generate(
            $original,
            $corruptBytes,
            (string) Str::uuid(),
            fn () => 'preview',
            'image/png',
            new \DateTimeImmutable
        );
    }

    public function test_oversize_preview_output_fails_closed(): void
    {
        $original = $this->fileReference();
        $service = new DocumentPreviewService(fn () => hash('sha256', 'synthetic-current-clearance'), maximumBytes: 10);

        $this->expectException(FileSecurityUnavailable::class);
        $service->generate(
            $original,
            $this->fileBytes(),
            (string) Str::uuid(),
            fn () => str_repeat('p', 11),
            'image/png',
            new \DateTimeImmutable
        );
    }
}
