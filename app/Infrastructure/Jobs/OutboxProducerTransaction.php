<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Infrastructure\Registration\RegistrationLabDatabase;
use MongoDB\Database;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Session;

/** Trusted local producer work only. Participating writes must use optionsFor; never control/cache its session. */
final class OutboxProducerTransaction
{
    private bool $closed = false;

    private ?\Throwable $failure = null;

    private function __construct(private readonly Manager $manager, private readonly Session $session) {}

    private function __clone() {}

    /** Driver retries create a new, separately closed context; prepare immutable operation IDs before calling. */
    public static function run(Database $outbox, \Closure $work): mixed
    {
        return OutboxLabDatabase::transaction($outbox, function ($options) use ($outbox, $work) {
            $context = new self($outbox->getManager(), $options['session']);
            try {
                $value = $work($context);
                $context->assertOpen();

                return $value;
            } catch (\Throwable $error) {
                $context->rollbackOnly($error);
                throw $context->failure;
            } finally {
                $context->closed = true;
            }
        });
    }

    public function optionsFor(Database $database): array
    {
        try {
            $this->assertOpen();
            if ($database->getManager() !== $this->manager) {
                throw new \RuntimeException('Producer transaction manager mismatch.');
            }
            match ($database->getDatabaseName()) {
                OutboxLabDatabase::NAME => OutboxLabDatabase::assertReady($database),
                RegistrationLabDatabase::NAME => RegistrationLabDatabase::assertReady($database),
                default => throw new \RuntimeException('Unapproved producer transaction namespace.')
            };

            return ['session' => $this->session];
        } catch (\Throwable $error) {
            $this->rollbackOnly($error);
            throw $error;
        }
    }

    /** Keep the first driver error, including transient labels, until WithTransaction has handled it. */
    public function rollbackOnly(\Throwable $error): void
    {
        $this->failure ??= $error;
    }

    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new \RuntimeException('Producer transaction context is closed.');
        }
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if (! $this->session->isInTransaction()) {
            throw new \RuntimeException('Active owned producer transaction required.');
        }
    }

    public function __serialize(): array
    {
        $error = new \RuntimeException('Producer transaction contexts cannot be serialized.');
        $this->rollbackOnly($error);
        throw $error;
    }

    public function __unserialize(array $data): void
    {
        throw new \RuntimeException('Producer transaction contexts cannot be restored.');
    }

    public function __debugInfo(): array
    {
        return ['state' => $this->closed ? 'closed' : 'active', 'session' => 'redacted'];
    }
}
