<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Jobs\JobExecutionAuthority;
use App\Domain\Jobs\JobItemCommand;
use App\Domain\Jobs\JobItemLookupQuery;
use App\Domain\Jobs\JobItemLookupResponse;
use App\Domain\Jobs\JobItemReceipt;
use App\Domain\Security\Authority\AuthorityProofPolicy;
use App\Infrastructure\Jobs\HandlerLabDatabase;
use App\Infrastructure\Jobs\MongoJobItemStore;
use App\Infrastructure\Jobs\MongoReferenceJobRepository;
use App\Infrastructure\Jobs\MongoSyntheticJobExecutionAuthority;
use App\Infrastructure\Jobs\ReceiptAwareJobHandler;
use MongoDB\Client;
use MongoDB\Database;
use PHPUnit\Framework\AssertionFailedError;

trait HandlerReconciliationFixtures
{
    use OutboxFixtures;

    private function handlerDb(bool $provider = false): Database
    {
        return (new Client(config('database.connections.mongodb.dsn')))->selectDatabase($provider ? HandlerLabDatabase::PROVIDER : HandlerLabDatabase::CONSUMER);
    }

    private function store(?\Closure $gate = null): MongoJobItemStore
    {
        HandlerLabDatabase::install($this->handlerDb());

        return new MongoJobItemStore($this->handlerDb(), $gate ?? fn () => true);
    }

    private function handlerPolicy(array $keys = ['receipt-key', 'lookup-key']): AuthorityProofPolicy
    {
        return new AuthorityProofPolicy('synthetic-handler', 'handler-1', $keys,
            fn ($body, $proof, $key) => hash_equals(hash_hmac('sha256', $body, str_repeat($key === 'receipt-key' ? 'H' : 'L', 32)), $proof),
            new \DateTimeImmutable('+1 hour'));
    }

    private function signed(string $class, array $body): object
    {
        unset($body['proof']);
        $r = new $class($body + ['proof' => 'placeholder']);

        return new $class($body + ['proof' => hash_hmac('sha256', $r->canonical(), str_repeat($body['signingKeyId'] === 'receipt-key' ? 'H' : 'L', 32))]);
    }

    private function handlerReceipt(JobItemCommand $c): JobItemReceipt
    {
        $d = $c->data;
        unset($d['purpose'], $d['expectedIssuerGeneration']);

        return $this->signed(JobItemReceipt::class, $d + ['purpose' => 'studentity.job.item-receipt.v1', 'requestFingerprint' => $c->fingerprint(), 'result' => 'succeeded', 'errorCode' => 'none', 'receiptId' => $this->uuid(), 'issuerGeneration' => 'handler-1', 'signingKeyId' => 'receipt-key', 'authorityRef' => 'urn:synthetic:handler', 'checkpointRef' => 'checkpoint:1', 'committedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z')]);
    }

    private function handlerLookup(JobItemLookupQuery $q, ?JobItemReceipt $receipt = null): JobItemLookupResponse
    {
        $d = $q->data;
        unset($d['purpose'], $d['expectedIssuerGeneration'], $d['issuedAt']);

        return $this->signed(JobItemLookupResponse::class, $d + ['purpose' => 'studentity.job.item-lookup-response.v1', 'status' => $receipt ? 'committed' : 'not-committed', 'queryFingerprint' => $q->fingerprint(), 'checkedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'), 'historyComplete' => true, 'issuerGeneration' => 'handler-1', 'signingKeyId' => 'lookup-key', 'authorityRef' => 'urn:synthetic:handler', 'checkpointRef' => 'checkpoint:2', 'receipt' => $receipt?->data]);
    }

    private function scriptedAuthority(\Closure $lookup, \Closure $execute): JobExecutionAuthority
    {
        return new class($lookup, $execute) implements JobExecutionAuthority
        {
            public int $lookups = 0;

            public int $executions = 0;

            public function __construct(private \Closure $l, private \Closure $e) {}

            public function lookup(JobItemLookupQuery $q): JobItemLookupResponse
            {
                $this->lookups++;

                return ($this->l)($q);
            }

            public function execute(JobItemCommand $c): JobItemReceipt
            {
                $this->executions++;

                return ($this->e)($c);
            }
        };
    }

    private function adapter(MongoReferenceJobRepository $r, JobExecutionAuthority $a, ?MongoJobItemStore $s = null, ?\Closure $gate = null, ?\Closure $clock = null, int $budget = 5000, ?AuthorityProofPolicy $policy = null): ReceiptAwareJobHandler
    {
        return new ReceiptAwareJobHandler($r, $s ?? $this->store(), $a, fn () => $policy ?? $this->handlerPolicy(), 'synthetic-handler', 'handler-1', $budget, $gate ?? fn () => true, $clock ?? fn () => new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    private function provider(?\Closure $gate = null, ?\Closure $ready = null, ?\Closure $before = null, ?\Closure $after = null): MongoSyntheticJobExecutionAuthority
    {
        HandlerLabDatabase::install($this->handlerDb(true));

        return new MongoSyntheticJobExecutionAuthority($this->handlerDb(true), 'synthetic-handler', 'handler-1', 'receipt-key', 'lookup-key', ['receipt-key' => str_repeat('H', 32), 'lookup-key' => str_repeat('L', 32)], $gate ?? fn () => true, $ready ?? fn () => true, $before, $after);
    }

    private function assertOneEffect(JobItemCommand $c): void
    {
        self::assertSame(1, $this->handlerDb(true)->effects_v1->countDocuments(['_id' => $c->data['itemOperationId']]));
        self::assertSame(1, $this->handlerDb(true)->executions_v1->countDocuments(['_id' => $c->data['itemOperationId']]));
    }

    private function rejects(\Closure $work): \Throwable
    {
        try {
            $work();
        } catch (AssertionFailedError $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $e;
        }
        self::fail('Expected a fenced operation.');
    }
}
