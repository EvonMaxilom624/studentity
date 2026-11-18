<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Session\Store;

/** Test-only lifecycle adapter; explicit IDs replace the native implicit new-ID/upsert assumption. */
final class FencedHttpLabSession extends Store
{
    public function __construct(string $name, FencedSessionLabStore $handler, private Closure $issueId, string $id)
    {
        parent::__construct($name, $handler, $id, 'json');
    }

    public function migrate($destroy = false)
    {
        // Retirement is irreversible even if issuing or acquiring the replacement fails.
        // This lab retires the old ID for BOTH Laravel regenerate modes.
        $this->handler->retireOwned($this->getId());
        $id = ($this->issueId)();
        $this->handler->create($id, '{}');
        $this->handler->read($id);
        $this->setId($id);
        $this->setExists(false);

        return true;
    }

    protected function generateSessionId()
    {
        throw new SessionFenceRejected('Implicit session issuance is forbidden.');
    }
}
