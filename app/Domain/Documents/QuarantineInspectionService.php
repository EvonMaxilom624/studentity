<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use DateTimeImmutable;
use Illuminate\Support\Str;

/** One fresh scan of immutable bytes; returns history metadata, never ordinary access or clearance state. */
final readonly class QuarantineInspectionService
{
    public function __construct(private PrivateQuarantineStore $store, private FileSecurityScanner $scanner, private ?\Closure $policyProvider = null, private ?\Closure $gate = null, private ?\Closure $clock = null) {}

    private function allow(QuarantineObjectReference $object): void
    {
        if ($this->gate === null || ($this->gate)($object) !== true) {
            throw new \RuntimeException;
        }
    }

    private function policy(): FileScanPolicy
    {
        $p = $this->policyProvider === null ? null : ($this->policyProvider)();
        if (! $p instanceof FileScanPolicy) {
            throw new \RuntimeException;
        }

        return $p;
    }

    private function now(): DateTimeImmutable
    {
        $t = $this->clock === null ? null : ($this->clock)();
        if (! $t instanceof DateTimeImmutable) {
            throw new \RuntimeException;
        }

        return $t;
    }

    private function observe(FileScanPolicy $p, DateTimeImmutable $start, DateTimeImmutable &$last, int $tick): DateTimeImmutable
    {
        $t = $this->now();
        if ($t < $last || ((float) $t->format('U.u') - (float) $start->format('U.u')) * 1000 > $p->roundTripMilliseconds || (hrtime(true) - $tick) / 1000000 > $p->roundTripMilliseconds) {
            throw new \RuntimeException;
        }
        $last = $t;

        return $t;
    }

    private function boundary(QuarantineObjectReference $o, FileScanPolicy $p, DateTimeImmutable $start, DateTimeImmutable &$last, int $tick): DateTimeImmutable
    {
        $this->allow($o);
        $current = $this->policy();
        if ($current->fingerprint() !== $p->fingerprint()) {
            throw new \RuntimeException;
        }
        $this->observe($p, $start, $last, $tick);
        if (! $this->scanner->available($current)) {
            throw new \RuntimeException;
        }
        $this->allow($o);
        if ($this->policy()->fingerprint() !== $p->fingerprint()) {
            throw new \RuntimeException;
        }

        return $this->observe($p, $start, $last, $tick);
    }

    public function inspect(QuarantineObjectReference $object): FileScanResult
    {
        try {
            $this->allow($object);
            $policy = $this->policy();
            $start = $this->now();
            $last = $start;
            $tick = hrtime(true);
            $this->boundary($object, $policy, $start, $last, $tick);
            $bytes = $this->store->readForScan($object);
            if (strlen($bytes) !== $object->byteCount || ! hash_equals($object->sha256, hash('sha256', $bytes))) {
                throw new \RuntimeException;
            }
            $issued = $this->boundary($object, $policy, $start, $last, $tick);
            $scanId = (string) Str::uuid();
            $result = $this->scanner->inspect($object, $bytes, $policy, $scanId);
            $observed = $this->boundary($object, $policy, $start, $last, $tick);
            if ($result->objectFingerprint !== $object->fingerprint() || $result->policyFingerprint !== $policy->fingerprint() || $result->scanId !== $scanId || $result->scannerId !== $policy->scannerId || $result->signatureVersion !== $policy->signatureVersion || $result->checkedAt < $issued || $result->checkedAt > $observed || in_array($result->status, ['error', 'unavailable'], true) || ($result->status === 'clean' && $result->detectedMediaType !== $object->mediaType)) {
                throw new \RuntimeException;
            }

            return $result;
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('File security check unavailable; object remains quarantined.');
        }
    }
}
