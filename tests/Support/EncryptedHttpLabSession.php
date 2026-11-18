<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Session\Store;

/** Request-local JSON lifecycle adapter for the isolated encrypted candidate. */
final class EncryptedHttpLabSession extends Store
{
    public function __construct(string $name, private EncryptedSessionLabStore $encrypted, string $id, private bool $fresh)
    {
        parent::__construct($name, $encrypted, $id, 'json');
    }

    protected function readFromHandler()
    {
        if ($this->fresh) {
            $this->fresh = false;

            return []; // issue() already holds the private stage lease; cookies cannot read stages.
        }

        return parent::readFromHandler();
    }

    public function migrate($destroy = false)
    {
        $this->encrypted->retireOwned($this->getId());
        $this->setId($this->encrypted->issue());
        $this->setExists(false);
        $this->fresh = true;

        return true;
    }

    protected function generateSessionId()
    {
        throw new SessionFenceRejected('Implicit encrypted session issuance forbidden.');
    }
}
