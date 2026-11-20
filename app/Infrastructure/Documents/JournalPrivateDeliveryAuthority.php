<?php

declare(strict_types=1);

namespace App\Infrastructure\Documents;

use App\Domain\Documents\FileScanCommand;
use App\Domain\Documents\FileScanPolicy;
use App\Domain\Documents\FileSecurityUnavailable;
use App\Domain\Documents\PrivateDeliveryAuthority;
use App\Domain\Documents\QuarantineObjectReference;
use App\Domain\Security\Authority\AuthorityContractFields as F;
use App\Domain\Security\Authority\CanonicalAuthorityJson as J;

/** Lab journal proof plus server-owned current callbacks; never request-provided booleans. */
final readonly class JournalPrivateDeliveryAuthority implements PrivateDeliveryAuthority
{
    public function __construct(private MongoScanAttemptJournal $journal, private ?\Closure $command = null, private ?\Closure $access = null, private ?\Closure $recovery = null, private ?\Closure $policy = null, private ?\Closure $scannerHealth = null) {}

    private function revisions(string $actor, QuarantineObjectReference $object, string $operation): array
    {
        $access = $this->access === null ? null : ($this->access)($actor, $object, $operation);
        $recovery = $this->recovery === null ? null : ($this->recovery)($actor, $object, $operation);
        F::digest($access);
        F::digest($recovery);

        return [$access, $recovery];
    }

    public function witness(string $actorUserId, QuarantineObjectReference $object, string $operation): string
    {
        try {
            F::uuid($actorUserId);
            if (! in_array($operation, ['view', 'download'], true)) {
                throw new \RuntimeException;
            }
            $before = $this->revisions($actorUserId, $object, $operation);
            $c = $this->command === null ? null : ($this->command)($object);
            $p = $this->policy === null ? null : ($this->policy)();
            if (! $c instanceof FileScanCommand || ! $p instanceof FileScanPolicy || $c->object->fingerprint() !== $object->fingerprint() || $c->policy->fingerprint() !== $p->fingerprint() || $this->scannerHealth === null || ($this->scannerHealth)($p) !== true) {
                throw new \RuntimeException;
            }
            $head = $this->journal->state($c);
            if ($head === null || $head['state'] !== 'cleared' || $head['done'] !== true || $head['command'] !== $c->wire()) {
                throw new \RuntimeException;
            }
            $current = ($this->policy)();
            if (! $current instanceof FileScanPolicy || $current->fingerprint() !== $p->fingerprint() || $before !== $this->revisions($actorUserId, $object, $operation)) {
                throw new \RuntimeException;
            }

            return hash('sha256', J::encode(['purpose' => 'studentity.private-delivery.witness.v1', 'operation' => $operation, 'actor' => $actorUserId, 'object' => $object->fingerprint(), 'policy' => $p->fingerprint(), 'revisions' => $before, 'head' => $head]));
        } catch (\Throwable) {
            throw new FileSecurityUnavailable('Current private delivery authority unavailable.');
        }
    }
}
