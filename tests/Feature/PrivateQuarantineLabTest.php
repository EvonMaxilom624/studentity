<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Documents\FileSecurityUnavailable;
use App\Infrastructure\Documents\LocalQuarantineLabStore;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Support\FileSecurityFixtures;
use Tests\TestCase;

final class PrivateQuarantineLabTest extends TestCase
{
    use FileSecurityFixtures;

    private function namespaceId(): string
    {
        return (string) Str::uuid();
    }

    private function objectPath(string $ns): string
    {
        return base_path('storage/framework/testing/fnd06-quarantine-v1/'.$ns.'/'.$this->fileReference()->objectId.'.bin');
    }

    private function ready(string $ns, ?\Closure $gate = null): LocalQuarantineLabStore
    {
        LocalQuarantineLabStore::install($ns);

        return new LocalQuarantineLabStore($ns, $gate ?? fn () => true);
    }

    public function test_private_immutable_stage_roundtrip_and_restart_use_original_metadata_and_bytes(): void
    {
        $ns = $this->namespaceId();
        $s = $this->ready($ns);
        $r = $this->fileReference();
        $s->stage($r, $this->fileBytes());
        self::assertSame($this->fileBytes(), $s->readForScan($r));
        $before = file_get_contents($this->objectPath($ns));
        $s->stage($r, $this->fileBytes());
        self::assertSame($before, file_get_contents($this->objectPath($ns)));
        self::assertSame($this->fileBytes(), (new LocalQuarantineLabStore($ns, fn () => true))->readForScan($r));
        self::assertFalse(method_exists($s, 'url'));
        self::assertFalse(method_exists($s, 'delete'));
        self::assertFalse(method_exists($s, 'release'));
    }

    public function test_same_object_id_with_different_owner_version_or_type_never_rebinds_original(): void
    {
        $ns = $this->namespaceId();
        $s = $this->ready($ns);
        $s->stage($this->fileReference(), $this->fileBytes());
        $before = file_get_contents($this->objectPath($ns));
        foreach (['ownerUserId' => $this->namespaceId(), 'documentVersionId' => $this->namespaceId(), 'mediaType' => 'image/png'] as $k => $v) {
            $r = $this->fileReference([$k => $v]);
            self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $s->stage($r, $this->fileBytes())));
            $this->fileRejects(fn () => $s->readForScan($r));
        }
        self::assertSame($before, file_get_contents($this->objectPath($ns)));
    }

    public function test_hash_size_and_lab_limit_must_match_before_any_file_creation(): void
    {
        $ns = $this->namespaceId();
        $s = $this->ready($ns);
        foreach ([$this->fileReference(['sha256' => str_repeat('0', 64)]), $this->fileReference(['byteCount' => 1]), $this->fileReference(['byteCount' => 65537, 'sha256' => hash('sha256', str_repeat('a', 65537))])] as $r) {
            self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => $s->stage($r, $r->byteCount === 65537 ? str_repeat('a', 65537) : $this->fileBytes())));
        }
        self::assertFileDoesNotExist($this->objectPath($ns));
    }

    public function test_partial_conflicting_file_stays_fenced_and_is_never_overwritten(): void
    {
        $ns = $this->namespaceId();
        $s = $this->ready($ns);
        file_put_contents($this->objectPath($ns), 'synthetic partial file');
        $this->fileRejects(fn () => $s->stage($this->fileReference(), $this->fileBytes()));
        $this->fileRejects(fn () => $s->readForScan($this->fileReference()));
        self::assertSame('synthetic partial file', file_get_contents($this->objectPath($ns)));
    }

    public function test_staging_and_reads_require_current_explicit_gate_and_missing_objects_are_redacted(): void
    {
        $ns = $this->namespaceId();
        $s = $this->ready($ns);
        $s->stage($this->fileReference(), $this->fileBytes());
        $denied = new LocalQuarantineLabStore($ns);
        $this->fileRejects(fn () => $denied->stage($this->fileReference(), $this->fileBytes()));
        $this->fileRejects(fn () => $denied->readForScan($this->fileReference()));
        $throwing = new LocalQuarantineLabStore($ns, function () {
            throw new \RuntimeException('synthetic-secret-storage-path');
        });
        $e = $this->fileRejects(fn () => $throwing->readForScan($this->fileReference()));
        self::assertInstanceOf(FileSecurityUnavailable::class, $e);
        self::assertNull($e->getPrevious());
        self::assertStringNotContainsString('synthetic-secret-storage-path', (string) $e);
        $missing = $this->fileReference(['objectId' => $this->namespaceId()]);
        $e = $this->fileRejects(fn () => $s->readForScan($missing));
        self::assertStringNotContainsString($this->objectPath($ns), (string) $e);
        self::assertStringNotContainsString(base_path(), $e->getMessage());
    }

    public function test_constructor_never_installs_and_namespace_cannot_be_a_path(): void
    {
        $ns = $this->namespaceId();
        $dir = dirname($this->objectPath($ns));
        $this->fileRejects(fn () => new LocalQuarantineLabStore($ns, fn () => true));
        self::assertDirectoryDoesNotExist($dir);
        self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects(fn () => LocalQuarantineLabStore::install('../public')));
    }

    public function test_current_served_disk_overlap_is_rejected_before_install_and_after_staging(): void
    {
        $ns = $this->namespaceId();
        $s = $this->ready($ns);
        $s->stage($this->fileReference(), $this->fileBytes());
        config(['filesystems.disks.exposed' => ['driver' => 'local', 'root' => base_path('storage/framework/testing'), 'serve' => true]]);
        $this->fileRejects(fn () => $s->readForScan($this->fileReference()));
        $fresh = $this->namespaceId();
        $this->fileRejects(fn () => LocalQuarantineLabStore::install($fresh));
        self::assertDirectoryDoesNotExist(dirname($this->objectPath($fresh)));
    }

    public function test_public_root_and_future_configured_links_cannot_expose_the_lab(): void
    {
        $ns = $this->namespaceId();
        $s = $this->ready($ns);
        $s->stage($this->fileReference(), $this->fileBytes());
        config(['filesystems.links' => [public_path('synthetic-link') => base_path('storage/framework/testing/fnd06-quarantine-v1')]]);
        $this->fileRejects(fn () => $s->readForScan($this->fileReference()));
        config(['filesystems.links' => []]);
        $this->app->usePublicPath(base_path('storage/framework/testing'));
        $this->fileRejects(fn () => $s->readForScan($this->fileReference()));
        self::assertFileExists($this->objectPath($ns));
    }

    public function test_real_lab_bytes_are_inspected_without_changing_the_quarantine_record(): void
    {
        $ns = $this->namespaceId();
        $store = $this->ready($ns);
        $object = $this->fileReference();
        $store->stage($object, $this->fileBytes());
        $before = file_get_contents($this->objectPath($ns));
        self::assertSame('clean', $this->inspectionService($store)->inspect($object)->status);
        $scanner = $this->fileScanner(health: fn () => false);
        $this->fileRejects(fn () => $this->inspectionService($store, $scanner)->inspect($object));
        self::assertSame(0, $scanner->scans);
        self::assertSame($before, file_get_contents($this->objectPath($ns)));
        self::assertSame($this->fileBytes(), $store->readForScan($object));
    }

    public function test_even_a_signed_default_storage_url_cannot_serve_quarantined_objects(): void
    {
        $ns = $this->namespaceId();
        $store = $this->ready($ns);
        $store->stage($this->fileReference(), $this->fileBytes());
        foreach (['fnd06-quarantine-v1/'.$ns.'/'.$this->fileReference()->objectId.'.bin', '../../framework/testing/fnd06-quarantine-v1/'.$ns.'/'.$this->fileReference()->objectId.'.bin'] as $path) {
            $url = URL::temporarySignedRoute('storage.local', now()->addMinute(), ['path' => $path], absolute: false);
            $response = $this->get($url);
            $response->assertNotFound();
            $response->assertDontSee($this->fileBytes(), escape: false);
        }
        self::assertSame($this->fileBytes(), $store->readForScan($this->fileReference()));
    }

    public function test_testing_guard_is_checked_on_every_operation_and_isolation_is_by_namespace(): void
    {
        $ns = $this->namespaceId();
        $s = $this->ready($ns);
        $s->stage($this->fileReference(), $this->fileBytes());
        $other = $this->ready($this->namespaceId());
        $this->fileRejects(fn () => $other->readForScan($this->fileReference()));
        $this->app->instance('env', 'production');
        $this->fileRejects(fn () => $s->readForScan($this->fileReference()));
        $this->fileRejects(fn () => $s->stage($this->fileReference(),$this->fileBytes()));
        self::assertFileExists($this->objectPath($ns));
    }
}
