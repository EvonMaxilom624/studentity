<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Documents\FileSecurityScanner;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\PrivateQuarantineStore;
use App\Infrastructure\Documents\UnavailableFileSecurityScanner;
use App\Infrastructure\Documents\UnavailableQuarantineStore;
use Tests\Support\FileSecurityFixtures;
use Tests\TestCase;

final class FileSecurityUnavailableTest extends TestCase
{
    use FileSecurityFixtures;

    public function test_runtime_defaults_never_stage_read_or_invent_a_clean_scan(): void
    {
        $store = app(PrivateQuarantineStore::class);
        $scanner = app(FileSecurityScanner::class);
        self::assertInstanceOf(UnavailableQuarantineStore::class, $store);
        self::assertInstanceOf(UnavailableFileSecurityScanner::class, $scanner);
        self::assertFalse($scanner->available($this->filePolicy()));
        foreach ([fn () => $store->stage($this->fileReference(), $this->fileBytes()), fn () => $store->readForScan($this->fileReference()), fn () => $scanner->inspect($this->fileReference(), $this->fileBytes(), $this->filePolicy(), '44444444-4444-4444-8444-444444444444')] as $work) {
            self::assertInstanceOf(FileSecurityUnavailable::class, $this->fileRejects($work));
        }
    }
}
