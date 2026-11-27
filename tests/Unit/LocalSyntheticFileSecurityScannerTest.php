<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Documents\FileScanPolicy;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\QuarantineObjectReference;
use App\Infrastructure\Documents\LocalSyntheticFileSecurityScanner;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class LocalSyntheticFileSecurityScannerTest extends TestCase
{
    private function policy(
        string $scannerId = LocalSyntheticFileSecurityScanner::DEFAULT_SCANNER_ID,
        string $sigVer = LocalSyntheticFileSecurityScanner::DEFAULT_SIGNATURE_VERSION
    ): FileScanPolicy {
        return new FileScanPolicy('ref-test-policy', $scannerId, $sigVer, 1000);
    }

    private function ref(string $mediaType, string $bytes): QuarantineObjectReference
    {
        return new QuarantineObjectReference(
            (string) Str::uuid(),
            (string) Str::uuid(),
            (string) Str::uuid(),
            hash('sha256', $bytes),
            strlen($bytes),
            $mediaType
        );
    }

    public function test_clean_jpeg_scan_passes(): void
    {
        $scanner = new LocalSyntheticFileSecurityScanner;
        $bytes = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";
        $ref = $this->ref('image/jpeg', $bytes);
        $policy = $this->policy();
        $scanId = (string) Str::uuid();

        $result = $scanner->inspect($ref, $bytes, $policy, $scanId);

        $this->assertSame('clean', $result->status);
        $this->assertSame('image/jpeg', $result->detectedMediaType);
        $this->assertTrue($result->typeValid);
        $this->assertTrue($result->malwareClean);
        $this->assertTrue($result->activeContentSafe);
        $this->assertTrue($result->structureSafe);
        $this->assertSame($scanId, $result->scanId);
    }

    public function test_clean_png_scan_passes(): void
    {
        $scanner = new LocalSyntheticFileSecurityScanner;
        $bytes = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\x00IEND\xaeB`\x82";
        $ref = $this->ref('image/png', $bytes);
        $policy = $this->policy();

        $result = $scanner->inspect($ref, $bytes, $policy, (string) Str::uuid());

        $this->assertSame('clean', $result->status);
        $this->assertSame('image/png', $result->detectedMediaType);
        $this->assertTrue($result->typeValid);
    }

    public function test_clean_pdf_scan_passes(): void
    {
        $scanner = new LocalSyntheticFileSecurityScanner;
        $bytes = "%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\nxref\n0 2\ntrailer\n<< /Root 1 0 R >>\nstartxref\n50\n%%EOF";
        $ref = $this->ref('application/pdf', $bytes);
        $policy = $this->policy();

        $result = $scanner->inspect($ref, $bytes, $policy, (string) Str::uuid());

        $this->assertSame('clean', $result->status);
        $this->assertSame('application/pdf', $result->detectedMediaType);
        $this->assertTrue($result->typeValid);
        $this->assertTrue($result->structureSafe);
    }

    public function test_mismatched_media_type_is_marked_unsafe(): void
    {
        $scanner = new LocalSyntheticFileSecurityScanner;
        // PNG bytes but declared as image/jpeg
        $bytes = "\x89PNG\r\n\x1a\n\x00\x00\x00\x00IEND\xaeB`\x82";
        $ref = $this->ref('image/jpeg', $bytes);
        $policy = $this->policy();

        $result = $scanner->inspect($ref, $bytes, $policy, (string) Str::uuid());

        $this->assertSame('unsafe', $result->status);
        $this->assertFalse($result->typeValid);
        $this->assertSame('image/png', $result->detectedMediaType);
    }

    public function test_eicar_signature_is_marked_unsafe(): void
    {
        $scanner = new LocalSyntheticFileSecurityScanner;
        $bytes = "%PDF-1.7\n".LocalSyntheticFileSecurityScanner::EICAR_SIGNATURE."\n%%EOF";
        $ref = $this->ref('application/pdf', $bytes);
        $policy = $this->policy();

        $result = $scanner->inspect($ref, $bytes, $policy, (string) Str::uuid());

        $this->assertSame('unsafe', $result->status);
        $this->assertFalse($result->malwareClean);
    }

    public function test_synthetic_malware_marker_is_marked_unsafe(): void
    {
        $scanner = new LocalSyntheticFileSecurityScanner;
        $bytes = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00".LocalSyntheticFileSecurityScanner::SYNTHETIC_MALWARE_MARKER."\xFF\xD9";
        $ref = $this->ref('image/jpeg', $bytes);
        $policy = $this->policy();

        $result = $scanner->inspect($ref, $bytes, $policy, (string) Str::uuid());

        $this->assertSame('unsafe', $result->status);
        $this->assertFalse($result->malwareClean);
    }

    public function test_pdf_with_active_javascript_is_marked_unsafe(): void
    {
        $scanner = new LocalSyntheticFileSecurityScanner;
        $bytes = "%PDF-1.7\n<< /Names << /JavaScript 2 0 R >> >>\n%%EOF";
        $ref = $this->ref('application/pdf', $bytes);
        $policy = $this->policy();

        $result = $scanner->inspect($ref, $bytes, $policy, (string) Str::uuid());

        $this->assertSame('unsafe', $result->status);
        $this->assertFalse($result->activeContentSafe);
    }

    public function test_image_with_embedded_script_tag_is_marked_unsafe(): void
    {
        $scanner = new LocalSyntheticFileSecurityScanner;
        $bytes = "\xFF\xD8\xFF\xE0<script>alert(1)</script>\xFF\xD9";
        $ref = $this->ref('image/jpeg', $bytes);
        $policy = $this->policy();

        $result = $scanner->inspect($ref, $bytes, $policy, (string) Str::uuid());

        $this->assertSame('unsafe', $result->status);
        $this->assertFalse($result->activeContentSafe);
    }

    public function test_corrupted_bytes_fail_closed(): void
    {
        $scanner = new LocalSyntheticFileSecurityScanner;
        $bytes = "\xFF\xD8\xFF\xE0\xFF\xD9";
        $ref = $this->ref('image/jpeg', $bytes);
        $policy = $this->policy();

        $this->expectException(FileSecurityUnavailable::class);
        $scanner->inspect($ref, "\xFF\xD8\xFF\x00\xFF\xD9", $policy, (string) Str::uuid());
    }

    public function test_mismatched_policy_rejects_scan(): void
    {
        $scanner = new LocalSyntheticFileSecurityScanner;
        $bytes = "\xFF\xD8\xFF\xE0\xFF\xD9";
        $ref = $this->ref('image/jpeg', $bytes);
        $policy = $this->policy(sigVer: 'unknown-signature-version');

        $this->assertFalse($scanner->available($policy));

        $this->expectException(FileSecurityUnavailable::class);
        $scanner->inspect($ref, $bytes, $policy, (string) Str::uuid());
    }

    public function test_inspect_attempt_binds_nonce(): void
    {
        $scanner = new LocalSyntheticFileSecurityScanner;
        $bytes = "\xFF\xD8\xFF\xE0\xFF\xD9";
        $ref = $this->ref('image/jpeg', $bytes);
        $policy = $this->policy();
        $scanId = (string) Str::uuid();
        $nonce = hash('sha256', 'test-attempt-nonce');

        $result = $scanner->inspectAttempt($ref, $bytes, $policy, $scanId, $nonce);

        $this->assertSame('clean', $result->status);
        $this->assertSame($nonce, $result->attemptNonce);
    }

    public function test_forced_unavailable_scanner_fails_closed(): void
    {
        $scanner = new LocalSyntheticFileSecurityScanner(forceUnavailable: true);
        $bytes = "\xFF\xD8\xFF\xE0\xFF\xD9";
        $ref = $this->ref('image/jpeg', $bytes);
        $policy = $this->policy();

        $this->assertFalse($scanner->available($policy));

        $this->expectException(FileSecurityUnavailable::class);
        $scanner->inspect($ref, $bytes, $policy, (string) Str::uuid());
    }
}
