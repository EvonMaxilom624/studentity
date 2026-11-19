<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Domain\Jobs\JobHandlerUnavailable;
use App\Domain\Jobs\JobOutcomeUnknown;
use App\Domain\Jobs\ReferenceJobHandler;

/** Unbound lab worker. Each handler call receives the original immutable item/result UUID outside transactions. */
final readonly class ReferenceJobWorker
{
    public function __construct(private MongoReferenceJobRepository $repository, private ?ReferenceJobHandler $handler = null) {}

    public function run(string $id): ?string
    {
        if ($this->handler === null) {
            throw new JobHandlerUnavailable('Synthetic job handler unavailable.');
        }
        $lease = $this->repository->claim($id);
        if ($lease === null) {
            return null;
        }
        foreach ($lease->command->data['items'] as $item) {
            if ($this->repository->result($lease, $item['targetId']) !== null) {
                continue;
            }
            try {
                $outcome = $this->handler->handle($lease->command, $item);
            } catch (\Throwable) {
                throw new JobOutcomeUnknown('Synthetic job outcome unknown; original item requires reconciliation.');
            }
            $terminal = $this->repository->record($lease, $item['targetId'], $outcome);
            if ($terminal !== null) {
                return $terminal;
            }
        }

        return $this->repository->status($id);
    }
}
