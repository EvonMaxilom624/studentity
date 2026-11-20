<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Jobs\JobPolicy;
use App\Domain\Jobs\ReferenceJobCommand;
use App\Infrastructure\Jobs\JobAuditTrail;
use App\Infrastructure\Jobs\MongoReferenceJobRepository;
use App\Infrastructure\Jobs\OutboxLabDatabase;
use App\Infrastructure\Jobs\OutboxProducerTransaction;
use App\Infrastructure\Registration\RegistrationLabDatabase;
use MongoDB\BSON\Int64;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\OutboxFixtures;
use Tests\TestCase;

final class OutboxProducerTransactionTest extends TestCase
{
    use OutboxFixtures;

    private Client $client;

    private Database $outbox;

    private Database $producer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client(config('database.connections.mongodb.dsn'));
        $this->outbox = $this->client->selectDatabase(OutboxLabDatabase::NAME);
        $this->producer = $this->client->selectDatabase(RegistrationLabDatabase::NAME);
        OutboxLabDatabase::install($this->outbox);
        RegistrationLabDatabase::install($this->producer);
    }

    private function ownedRepo(?\Closure $gate = null, ?\Closure $fault = null): MongoReferenceJobRepository
    {
        return new MongoReferenceJobRepository($this->outbox, new JobPolicy('synthetic-job-policy-v1', 3, 60000), $gate ?? static fn () => true, $fault);
    }

    private function producerRow(ReferenceJobCommand $c): array
    {
        $at = new UTCDateTime;

        return ['_id' => $c->data['items'][0]['targetId'], 'schema_version' => 1, 'kind' => 'student', 'account_state' => 'provisional', 'security_epoch' => new Int64(0), 'created_at' => $at, 'updated_at' => $at];
    }

    private function apply(array $row): \Closure
    {
        return fn ($tx) => $this->producer->users->insertOne($row, $tx->optionsFor($this->producer))->getInsertedCount() === 1;
    }

    private function verify(array $row): \Closure
    {
        return function ($tx) use ($row) {
            $a = $this->producer->users->findOne(['_id' => $row['_id']], $tx->optionsFor($this->producer) + ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
            if (! $a) {
                return false;
            }$a['security_epoch'] = new Int64((string) $a['security_epoch']);

            return OutboxLabDatabase::typed($a) === OutboxLabDatabase::typed($row);
        };
    }

    private function fresh(Database $db): Database
    {
        return (new Client(config('database.connections.mongodb.dsn')))->selectDatabase($db->getDatabaseName(), ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
    }

    private function capture(\Closure $work): ?\RuntimeException
    {
        try {
            $work();

            return null;
        } catch (\RuntimeException $e) {
            return $e;
        }
    }

    public function test_producer_job_and_audit_commit_together_and_exact_retry_skips_producer(): void
    {
        $r = $this->ownedRepo();
        $c = $this->command(1);
        $row = $this->producerRow($c);
        $writes = 0;
        $insert = $this->apply($row);
        $apply = function ($tx) use (&$writes, $insert) {
            $writes++;

            return $insert($tx);
        };
        self::assertTrue($r->produce($c, $apply, $this->verify($row)));
        self::assertFalse($r->produce(ReferenceJobCommand::fromJson($c->wire()), $apply, $this->verify($row)));
        self::assertSame(1, $writes);
        self::assertNotNull($this->fresh($this->producer)->users->findOne(['_id' => $row['_id']]));
        self::assertSame('queued', $r->status($c->data['operationId']));
        self::assertSame(1, $this->fresh($this->outbox)->audit_events->countDocuments(['target_id' => $c->data['operationId']]));
    }

    #[DataProvider('abortStages')]
    public function test_producer_and_enqueue_failures_abort_both_sides(string $stage): void
    {
        $c = $this->command(1);
        $row = $this->producerRow($c);
        $apply = $this->apply($row);
        $faultCalls = 0;
        $faultStage = null;
        $r = $this->ownedRepo(fault: in_array($stage, ['enqueue', 'produce'], true) ? static function ($s) use ($stage, &$faultCalls, &$faultStage) {
            $faultCalls++;
            $faultStage = $s;
            if ($s === $stage) {
                throw new \RuntimeException('synthetic transaction abort');
            }
        } : null);
        $error = $this->capture(fn () => $r->produce($c, function ($tx) use ($apply, $stage) {
            $apply($tx);
            if ($stage === 'producer') {
                throw new \RuntimeException('synthetic producer abort');
            }

            return true;
        }, $this->verify($row)));
        self::assertInstanceOf(\RuntimeException::class, $error);
        if (in_array($stage, ['enqueue', 'produce'], true)) {
            self::assertSame($stage === 'produce' ? 2 : 1, $faultCalls);
            self::assertSame($stage, $faultStage);
        } else {
            self::assertSame(0, $faultCalls);
        }
        self::assertNull($this->fresh($this->producer)->users->findOne(['_id' => $row['_id']]));
        self::assertNull($this->fresh($this->outbox)->background_jobs->findOne(['_id' => $c->data['operationId']]));
        self::assertSame(0, $this->fresh($this->outbox)->audit_events->countDocuments(['target_id' => $c->data['operationId']]));
    }

    public static function abortStages(): array
    {
        return [['producer'], ['enqueue'], ['produce']];
    }

    public function test_caught_enqueue_error_still_poisons_the_outer_transaction(): void
    {
        $c = $this->command(1);
        $row = $this->producerRow($c);
        $insert = $this->apply($row);
        $r = $this->ownedRepo(fault: static function ($s) {
            if ($s === 'enqueue') {
                throw new \RuntimeException('synthetic secret should not escape');
            }
        });
        $error = $this->capture(fn () => OutboxProducerTransaction::run($this->outbox, function ($tx) use ($c, $insert, $r) {
            $insert($tx);
            try {
                $r->enqueueIn($tx, $c);
            } catch (\Throwable) {
            }

            return true;
        }));
        self::assertInstanceOf(\RuntimeException::class, $error);
        self::assertStringNotContainsString('synthetic secret', $error->getMessage());
        self::assertNull($error->getPrevious());
        self::assertNull($this->fresh($this->producer)->users->findOne(['_id' => $row['_id']]));
        self::assertNull($this->fresh($this->outbox)->background_jobs->findOne(['_id' => $c->data['operationId']]));
    }

    #[DataProvider('badCallbackResults')]
    public function test_both_callbacks_require_strict_success_markers(string $which, mixed $value): void
    {
        $c = $this->command(1);
        $row = $this->producerRow($c);
        $insert = $this->apply($row);
        $apply = function ($tx) use ($insert, $which, $value) {
            $insert($tx);

            return $which === 'apply' ? $value : true;
        };
        $verify = $which === 'verify' ? static fn () => $value : $this->verify($row);
        self::assertNotNull($this->capture(fn () => $this->ownedRepo()->produce($c, $apply, $verify)));
        self::assertNull($this->fresh($this->producer)->users->findOne(['_id' => $row['_id']]));
        self::assertNull($this->fresh($this->outbox)->background_jobs->findOne(['_id' => $c->data['operationId']]));
    }

    public static function badCallbackResults(): array
    {
        return [['apply', false], ['apply', 1], ['verify', false], ['verify', 1]];
    }

    public function test_missing_or_recovered_producer_never_gets_recreated_or_reset_on_replay(): void
    {
        $r = $this->ownedRepo();
        $c = $this->command(1);
        $row = $this->producerRow($c);
        $r->enqueue($c);
        $calls = 0;
        self::assertNotNull($this->capture(fn () => $r->produce($c, function () use (&$calls) {
            $calls++;

            return true;
        }, $this->verify($row))));
        self::assertSame(0, $calls);
        self::assertNull($this->fresh($this->producer)->users->findOne(['_id' => $row['_id']]));
        $c = $this->command(1);
        $row = $this->producerRow($c);
        $r->produce($c, $this->apply($row), $this->verify($row));
        $this->producer->users->updateOne(['_id' => $row['_id']], ['$set' => ['account_state' => 'disabled', 'security_epoch' => new Int64(9)]]);
        self::assertNotNull($this->capture(fn () => $r->produce($c, $this->apply($row), $this->verify($row))));
        self::assertSame('disabled', $this->fresh($this->producer)->users->findOne(['_id' => $row['_id']])['account_state']);
    }

    public function test_surviving_producer_without_job_is_not_attached_as_a_fresh_operation(): void
    {
        $r = $this->ownedRepo();
        $c = $this->command(1);
        $row = $this->producerRow($c);
        $this->producer->users->insertOne($row);
        self::assertNotNull($this->capture(fn () => $r->produce($c, $this->apply($row), $this->verify($row))));
        self::assertNull($this->fresh($this->outbox)->background_jobs->findOne(['_id' => $c->data['operationId']]));
        self::assertSame(1, $this->fresh($this->producer)->users->countDocuments(['_id' => $row['_id']]));
    }

    public function test_conflicting_original_body_and_denied_gate_never_invoke_producer_callbacks(): void
    {
        $r = $this->ownedRepo();
        $c = $this->command(1);
        $row = $this->producerRow($c);
        $r->produce($c, $this->apply($row), $this->verify($row));
        $data = $c->data;
        $data['correlationId'] = $this->uuid();
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return true;
        };
        self::assertNotNull($this->capture(fn () => $r->produce(new ReferenceJobCommand($data), $callback, $callback)));
        self::assertSame(0, $calls);
        $c = $this->command(1);
        self::assertNotNull($this->capture(fn () => $this->ownedRepo(gate: static fn () => false)->produce($c, $callback, $callback)));
        self::assertSame(0, $calls);
    }

    #[DataProvider('contextAttacks')]
    public function test_context_rejects_forbidden_database_foreign_manager_and_copies(string $attack): void
    {
        $error = $this->capture(fn () => OutboxProducerTransaction::run($this->outbox, function ($tx) use ($attack) {
            if ($attack === 'namespace') {
                $tx->optionsFor($this->client->selectDatabase('studentity_test_m0'));
            } elseif ($attack === 'manager') {
                $tx->optionsFor((new Client(config('database.connections.mongodb.dsn')))->selectDatabase(OutboxLabDatabase::NAME));
            } elseif ($attack === 'clone') {
                $copy = clone $tx;
            } else {
                serialize($tx);
            }

            return true;
        }));
        self::assertNotNull($error);
    }

    public static function contextAttacks(): array
    {
        return [['namespace'], ['manager'], ['clone'], ['serialize']];
    }

    public function test_escaped_context_is_invalid_after_success_or_abort_even_during_a_later_transaction(): void
    {
        $escaped = null;
        OutboxProducerTransaction::run($this->outbox, function ($tx) use (&$escaped) {
            $escaped = $tx;

            return true;
        });
        self::assertNotNull($this->capture(fn () => $escaped->optionsFor($this->outbox)));
        $this->capture(fn () => OutboxProducerTransaction::run($this->outbox, function ($tx) use (&$escaped) {
            $escaped = $tx;
            throw new \RuntimeException('synthetic abort');
        }));
        self::assertNotNull($this->capture(fn () => OutboxProducerTransaction::run($this->outbox, fn () => $escaped->optionsFor($this->outbox))));
    }

    #[DataProvider('orphanKinds')]
    public function test_parent_orphan_evidence_blocks_fresh_producer_before_callbacks(string $kind): void
    {
        $r = $this->ownedRepo();
        $c = $this->command(1);
        $id = $c->data['operationId'];
        if ($kind === 'result') {
            $row = ['_id' => $this->uuid(), 'schema_version' => 1, 'job_id' => $id, 'target_ref' => $this->uuid(), 'command_fingerprint' => $c->fingerprint(), 'result' => 'succeeded', 'error_code' => 'none', 'attempted_at' => new UTCDateTime];
            $row['outcome_fingerprint'] = JobAuditTrail::resultHash($row);
            $this->outbox->job_results->insertOne($row);
        } else {
            $seed = $this->command(1);
            $r->enqueue($seed);
            $event = (array) $this->outbox->audit_events->findOne(['target_id' => $seed->data['operationId']]);
            $event['_id'] = $this->uuid();
            $event['target_id'] = $id;
            $event['job_sequence'] = new Int64((string) $event['job_sequence']);
            $this->outbox->audit_events->insertOne($event);
        }
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return true;
        };
        self::assertNotNull($this->capture(fn () => $r->produce($c, $callback, $callback)));
        self::assertSame(0, $calls);
        self::assertNull($this->fresh($this->outbox)->background_jobs->findOne(['_id' => $id]));
    }

    public static function orphanKinds(): array
    {
        return [['result'], ['audit']];
    }

    #[DataProvider('transientExitPaths')]
    public function test_caught_transient_driver_error_retries_with_a_new_closed_context_and_original_ids(string $exit): void
    {
        $c = $this->command(1);
        $row = $this->producerRow($c);
        $insert = $this->apply($row);
        $attempts = 0;
        $contexts = [];
        $transient = new class extends \MongoDB\Driver\Exception\RuntimeException
        {
            public function __construct()
            {
                parent::__construct('synthetic transient retry');
                $this->errorLabels = ['TransientTransactionError'];
            }
        };
        self::assertTrue($transient->hasErrorLabel('TransientTransactionError'));
        $hookCalls = 0;
        $r = $this->ownedRepo(fault: static function ($stage) use (&$hookCalls, $transient) {
            if ($stage === 'enqueue' && ++$hookCalls === 1) {
                throw $transient;
            }
        });
        OutboxProducerTransaction::run($this->outbox, function ($tx) use (&$attempts, &$contexts, $insert, $c, $r, $exit) {
            $attempts++;
            $contexts[] = $tx;
            $insert($tx);
            try {
                $r->enqueueIn($tx, $c);
            } catch (\Throwable) {
                $tx->rollbackOnly(new \RuntimeException('Later error must not replace the driver label.'));
                if ($exit === 'throw-replacement') {
                    throw new \RuntimeException('Replacement exception hides the transient cause.');
                }
            }

            return true;
        });
        self::assertSame(2, $attempts);
        self::assertSame(2, $hookCalls);
        self::assertNotSame($contexts[0], $contexts[1]);
        foreach ($contexts as $context) {
            self::assertNotNull($this->capture(fn () => $context->optionsFor($this->outbox)));
        }
        self::assertSame(1, $this->fresh($this->producer)->users->countDocuments(['_id' => $row['_id']]));
        self::assertSame(1, $this->fresh($this->outbox)->audit_events->countDocuments(['target_id' => $c->data['operationId']]));
        self::assertSame('queued', $r->status($c->data['operationId']));
    }

    public function test_context_owns_strong_transaction_options_and_rejects_aborted_session(): void
    {
        OutboxProducerTransaction::run($this->outbox, function ($tx) {
            $o = $tx->optionsFor($this->producer);
            self::assertSame(['session'], array_keys($o));
            $options = $o['session']->getTransactionOptions();
            self::assertSame('snapshot', $options['readConcern']->getLevel());
            self::assertSame('majority', $options['writeConcern']->getW());
            self::assertSame('primary', $options['readPreference']->getModeString());

            return true;
        });
        $c = $this->command(1);
        $row = $this->producerRow($c);
        $insert = $this->apply($row);
        $error = $this->capture(fn () => OutboxProducerTransaction::run($this->outbox, function ($tx) use ($insert) {
            $insert($tx);
            $tx->optionsFor($this->producer)['session']->abortTransaction();

            return true;
        }));
        self::assertNotNull($error);
        self::assertNull($this->fresh($this->producer)->users->findOne(['_id' => $row['_id']]));
    }

    public function test_enqueue_gate_is_checked_before_any_producer_callback(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return true;
        };
        $r = $this->ownedRepo(gate: static fn ($stage) => $stage !== 'enqueue');
        self::assertNotNull($this->capture(fn () => $r->produce($this->command(1), $callback, $callback)));
        self::assertSame(0, $calls);
    }

    public static function transientExitPaths(): array
    {
        return [['return'], ['throw-replacement']];
    }

    public function test_strict_callback_rejection_does_not_hide_an_earlier_transient_driver_failure(): void
    {
        $c = $this->command(1);
        $row = $this->producerRow($c);
        $insert = $this->apply($row);
        $calls = 0;
        $transient = new class extends \MongoDB\Driver\Exception\RuntimeException
        {
            public function __construct()
            {
                parent::__construct('synthetic caught producer error');
                $this->errorLabels = ['TransientTransactionError'];
            }
        };
        self::assertTrue($this->ownedRepo()->produce($c, function ($tx) use ($insert, &$calls, $transient) {
            $calls++;
            $insert($tx);
            if ($calls === 1) {
                $tx->rollbackOnly($transient);

                return false;
            }

            return true;
        }, $this->verify($row)));
        self::assertSame(2, $calls);
        self::assertSame(1, $this->fresh($this->producer)->users->countDocuments(['_id' => $row['_id']]));
        self::assertSame(1, $this->fresh($this->outbox)->audit_events->countDocuments(['target_id' => $c->data['operationId']]));
    }
}
