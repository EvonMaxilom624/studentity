<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Jobs\JobPolicy;
use App\Domain\Jobs\ReferenceJobCommand;
use App\Infrastructure\Jobs\MongoReferenceJobRepository;
use App\Infrastructure\Jobs\OutboxLabDatabase;
use Illuminate\Support\Str;
use MongoDB\Client;
use MongoDB\Database;

trait OutboxFixtures
{
    private function uuid(): string
    {
        return (string) Str::uuid();
    }

    private function db(): Database
    {
        return (new Client(config('database.connections.mongodb.dsn')))->selectDatabase(OutboxLabDatabase::NAME, ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
    }

    private function command(int $count = 2): ReferenceJobCommand
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = ['targetId' => $this->uuid(), 'resultId' => $this->uuid(), 'auditId' => $this->uuid()];
        }

        return new ReferenceJobCommand(['purpose' => 'studentity.job.command.v1', 'schemaVersion' => 1, 'operationId' => $this->uuid(), 'correlationId' => $this->uuid(), 'enqueueAuditId' => $this->uuid(), 'finishAuditId' => $this->uuid(), 'requestedBy' => $this->uuid(), 'jobType' => 'synthetic.reference-check.v1', 'items' => $items]);
    }

    private function repo(int $lease = 60000, int $attempts = 3, ?\Closure $gate = null, ?\Closure $fault = null): MongoReferenceJobRepository
    {
        OutboxLabDatabase::install($this->db());

        return new MongoReferenceJobRepository($this->db(), new JobPolicy('synthetic-job-policy-v1', $attempts, $lease), $gate ?? static fn () => true, $fault);
    }
}
