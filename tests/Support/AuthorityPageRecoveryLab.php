<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Security\Authority\AuthorityChangePage;
use App\Domain\Security\Authority\AuthorityCheckpoint;
use App\Domain\Security\Authority\AuthorityStreamCursor;
use App\Domain\Security\Authority\AuthorityStreamPolicy;
use App\Domain\Security\Authority\ReceiptContractViolation;
use Closure;

/**
 * Test-only in-memory applied position. No durable cursor, feed provider or access grant.
 * Per-receipt transactions may commit a prefix; uncertainty holds the whole page.
 */
final class AuthorityPageRecoveryLab
{
    private AuthorityStreamCursor $applied;

    private ?string $pendingPageHash = null;

    private bool $busy = false;

    public function __construct(AuthorityCheckpoint $checkpoint, AuthorityStreamPolicy $policy, string $expectedNonce, private ReceiptJournalReplayLab $replay)
    {
        if (! app()->environment('testing')) {
            throw new \InvalidArgumentException('Synthetic recovery only.');
        }
        $this->applied = AuthorityStreamCursor::fromCheckpoint($checkpoint, $policy, $expectedNonce);
    }

    public function apply(AuthorityChangePage $page, string $expectedNonce, ?Closure $beforeCommit = null, ?Closure $afterCommit = null): string
    {
        if ($this->busy) {
            throw new \LogicException('Synthetic recovery already running.');
        }
        $this->busy = true;
        try {
            // Verify EVERY original before the first local write. Candidate remains private.
            $candidate = $this->applied->advance($page, $expectedNonce);
            $hash = hash('sha256', $page->wire());
            if ($this->pendingPageHash !== null && ! hash_equals($this->pendingPageHash, $hash)) {
                throw new ReceiptContractViolation('Original pending authority page required.');
            }
            $this->pendingPageHash = $hash;
            $historyOnly = false;
            foreach ($candidate->changes() as $change) {
                try {
                    $result = $this->replay->apply($change['receipt'], static fn () => $beforeCommit?->__invoke($change['position']), static fn () => $afterCommit?->__invoke($change['position']));
                } catch (\Throwable) {
                    return 'local-incomplete';
                }
                if (! in_array($result, ['confirmed', 'history-only'], true)) {
                    return 'local-incomplete';
                }
                $historyOnly = $historyOnly || $result === 'history-only';
            }
            if ($historyOnly) {
                return 'history-only';
            }
            $this->applied = $candidate;
            $this->pendingPageHash = null;

            return 'page-applied';
        } finally {
            $this->busy = false;
        }
    }

    public function position(): string
    {
        return $this->applied->position();
    }

    /** Receipt/control reconciliation only. This NEVER establishes current authorization. */
    public function isFullyReconciled(): bool
    {
        return $this->pendingPageHash === null && $this->applied->isFullyVerified();
    }
}
