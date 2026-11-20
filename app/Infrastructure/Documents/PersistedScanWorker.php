<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Domain\Documents\FileScanCommand;
use App\Domain\Documents\FileScanResult;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\QuarantineInspectionService;

/** Unbound synthetic worker integration; no lease, history/head, cached result or promotion. */
final readonly class PersistedScanWorker
{
    public function __construct(private MongoOriginalScanCommandStore $commands, private QuarantineInspectionService $inspection) {}

    public function run(FileScanCommand $command): FileScanResult
    {
        try {
            $this->commands->prepare($command);

            return $this->resume($command->scanId);
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('Persisted scan unresolved; retain original command.');
        }
    }

    public function resume(string $scanId): FileScanResult
    {
        try {
            $command = $this->commands->lookup($scanId);
            if ($command === null) {
                throw new \RuntimeException;
            }

            return $this->inspection->inspectCommand($command);
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('Original scan unavailable; no replacement identity created.');
        }
    }
}
