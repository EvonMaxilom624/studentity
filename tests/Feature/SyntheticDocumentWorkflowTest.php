<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Documents\DocumentPreviewService;
use App\Domain\Documents\FileScanPolicy;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\QuarantineInspectionService;
use App\Domain\Documents\SyntheticDocumentWorkflow;
use App\Infrastructure\Documents\LocalQuarantineLabStore;
use App\Infrastructure\Documents\LocalSyntheticFileSecurityScanner;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SyntheticDocumentWorkflowTest extends TestCase
{
    private string $namespace;

    private LocalQuarantineLabStore $store;

    private LocalSyntheticFileSecurityScanner $scanner;

    private FileScanPolicy $policy;

    private QuarantineInspectionService $inspectionService;

    private DocumentPreviewService $previewService;

    private SyntheticDocumentWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->namespace = (string) Str::uuid();
        LocalQuarantineLabStore::install($this->namespace);

        $this->store = new LocalQuarantineLabStore($this->namespace, fn () => true);
        $this->scanner = new LocalSyntheticFileSecurityScanner;
        $this->policy = new FileScanPolicy(
            'test-policy',
            LocalSyntheticFileSecurityScanner::DEFAULT_SCANNER_ID,
            LocalSyntheticFileSecurityScanner::DEFAULT_SIGNATURE_VERSION,
            1000
        );

        $clock = fn () => new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->inspectionService = new QuarantineInspectionService(
            $this->store,
            $this->scanner,
            fn () => $this->policy,
            fn () => true,
            $clock
        );

        $this->previewService = new DocumentPreviewService(fn () => hash('sha256', 'synthetic-current-clearance'));

        $this->workflow = new SyntheticDocumentWorkflow(
            $this->store,
            $this->inspectionService,
            $this->previewService
        );
    }

    public function test_clean_jpeg_upload_stages_scans_and_generates_preview_successfully(): void
    {
        $bytes = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";
        $ownerId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $previewBytes = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00_preview_\xFF\xD9";
        $previewGenerator = fn ($orig, $origBytes) => $previewBytes;

        $result = $this->workflow->process(
            $ownerId,
            $versionId,
            'image/jpeg',
            $bytes,
            $now,
            $previewGenerator,
            'image/jpeg'
        );

        $this->assertSame('clean', $result->scanResult->status);
        $this->assertSame($ownerId, $result->object->ownerUserId);
        $this->assertSame($versionId, $result->object->documentVersionId);
        $this->assertNotNull($result->previewReference);
        $this->assertSame($result->object->objectId, $result->previewReference->originalObjectId);
        $this->assertNotSame($result->object->objectId, $result->previewReference->previewObjectId);
        $this->assertSame(hash('sha256', $previewBytes), $result->previewReference->sha256);

        // Original bytes in quarantine store are untouched
        $storedOriginal = $this->store->readForScan($result->object);
        $this->assertSame($bytes, $storedOriginal);
    }

    public function test_unsafe_malware_file_fails_closed_and_prevents_preview(): void
    {
        $bytes = "\xFF\xD8\xFF\xE0".LocalSyntheticFileSecurityScanner::SYNTHETIC_MALWARE_MARKER."\xFF\xD9";
        $ownerId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->expectException(FileSecurityUnavailable::class);

        $this->workflow->process(
            $ownerId,
            $versionId,
            'image/jpeg',
            $bytes,
            $now,
            fn () => 'preview-bytes',
            'image/jpeg'
        );
    }

    public function test_unsupported_media_type_is_rejected_fail_closed(): void
    {
        $bytes = 'hello text file content';
        $ownerId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->expectException(FileSecurityUnavailable::class);

        $this->workflow->process(
            $ownerId,
            $versionId,
            'text/plain',
            $bytes,
            $now
        );
    }

    public function test_oversized_file_is_rejected_fail_closed(): void
    {
        $bytes = str_repeat('A', 6000000); // 6MB, exceeds 5MB limit
        $ownerId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->expectException(FileSecurityUnavailable::class);

        $this->workflow->process(
            $ownerId,
            $versionId,
            'image/jpeg',
            $bytes,
            $now
        );
    }

    public function test_scanner_outage_fails_closed_without_generating_preview(): void
    {
        $unavailableScanner = new LocalSyntheticFileSecurityScanner(forceUnavailable: true);
        $clock = fn () => new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $inspection = new QuarantineInspectionService(
            $this->store,
            $unavailableScanner,
            fn () => $this->policy,
            fn () => true,
            $clock
        );

        $workflow = new SyntheticDocumentWorkflow(
            $this->store,
            $inspection,
            $this->previewService
        );

        $bytes = "\xFF\xD8\xFF\xE0\xFF\xD9";
        $ownerId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->expectException(FileSecurityUnavailable::class);

        $workflow->process(
            $ownerId,
            $versionId,
            'image/jpeg',
            $bytes,
            $now
        );
    }
}
