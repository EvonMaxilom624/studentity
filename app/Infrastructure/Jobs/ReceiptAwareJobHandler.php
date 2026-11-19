<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Domain\Jobs\JobExecutionAuthority;
use App\Domain\Jobs\JobItemLookupQuery;
use App\Domain\Jobs\JobItemOutcome;
use App\Domain\Jobs\JobItemReceipt;
use App\Domain\Jobs\JobOutcomeUnknown;
use App\Domain\Jobs\ReferenceJobCommand;
use App\Domain\Jobs\ReferenceJobHandler;
use App\Domain\Jobs\VerifiedJobItemLookup;
use App\Domain\Jobs\VerifiedJobItemReceipt;
use App\Domain\Security\Authority\AuthorityProofPolicy;
use App\Domain\Security\Authority\ReceiptSchema;
use DateTimeImmutable;
use DateTimeZone;

/** Unbound reconciliation adapter. Every handler I/O occurs outside a Mongo transaction. */
final readonly class ReceiptAwareJobHandler implements ReferenceJobHandler
{
    public function __construct(private MongoReferenceJobRepository $repository, private MongoJobItemStore $store, private JobExecutionAuthority $authority, private \Closure $policyProvider, private string $namespace, private string $generation, private int $roundTripMilliseconds, private ?\Closure $gate = null, private ?\Closure $clock = null)
    {
        if ($roundTripMilliseconds < 1 || $roundTripMilliseconds > 60000) {
            throw new \InvalidArgumentException('Bounded handler observation required.');
        }
    }

    private function allow(): void
    {
        if ($this->gate === null || ($this->gate)() !== true || $this->clock === null) {
            throw new \RuntimeException('Current handler admission unavailable.');
        }
    }

    private function policy(): AuthorityProofPolicy
    {
        $p = ($this->policyProvider)();
        if (! $p instanceof AuthorityProofPolicy || ! $p->binds($this->namespace, $this->generation)) {
            throw new \RuntimeException('Current handler proof policy unavailable.');
        }

        return $p;
    }

    private function now(): DateTimeImmutable
    {
        $n = ($this->clock)();
        if (! $n instanceof DateTimeImmutable) {
            throw new \RuntimeException('Trusted handler clock unavailable.');
        }

        return ReceiptSchema::time($n->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'));
    }

    private function observation(DateTimeImmutable $start, DateTimeImmutable &$last): DateTimeImmutable
    {
        $now = $this->now();
        if ($now < $last || ((float) $now->format('U.u') - (float) $start->format('U.u')) * 1000 > $this->roundTripMilliseconds) {
            throw new \RuntimeException('Handler observation expired.');
        }
        $last = $now;

        return $now;
    }

    public function handle(ReferenceJobCommand $command, array $item): JobItemOutcome
    {
        try {
            $this->allow();
            $this->policy();
            $lower = $this->repository->originalForHandler($command, $item);
            $c = $this->store->prepare($command, $item, $this->namespace, $this->generation, $lower);
            $cached = $this->store->receipt($c);
            $start = $this->now();
            $last = $start;
            $q = JobItemLookupQuery::forCommand($c, bin2hex(random_bytes(32)), $start->format('Y-m-d\TH:i:s.v\Z'));
            $raw = $this->authority->lookup($q);
            $observed = $this->observation($start, $last);
            $this->allow();
            $policy = $this->policy();
            $lookup = VerifiedJobItemLookup::verify($c, $q, $raw, $policy, $lower, $start, $observed);
            $resolved = $lookup->resolveFor($c, $q, $policy, $lower, $start, $observed);
            if ($resolved['status'] === 'unknown') {
                throw new \RuntimeException('Provider history unavailable.');
            }
            if ($resolved['status'] === 'committed') {
                $receipt = new JobItemReceipt($raw->data['receipt']);
            } else {
                if ($cached !== null) {
                    throw new \RuntimeException('Provider history contradicts prior commit.');
                }
                $this->allow();
                $lookup->resolveFor($c, $q, $this->policy(), $lower, $start, $this->observation($start, $last));
                $receipt = $this->authority->execute($c);
            }
            $observed = $this->observation($start, $last);
            $this->allow();
            $policy = $this->policy();
            $verified = VerifiedJobItemReceipt::verify($c, $receipt, $policy, $lower, $observed);
            if ($cached !== null && $cached->wire() !== $receipt->wire()) {
                throw new \RuntimeException('Provider receipt fork.');
            }
            $this->store->recordReceipt($c, $receipt, $policy, $observed);
            $this->allow();

            return $verified->outcomeFor($c, $this->policy(), $lower, $this->observation($start,$last));
        } catch (\Throwable) {
            throw new JobOutcomeUnknown('Synthetic handler outcome unknown; reconcile original command.');
        }
    }
}
