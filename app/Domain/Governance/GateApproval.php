<?php

declare(strict_types=1);

namespace App\Domain\Governance;

use App\Domain\Security\Authority\CanonicalAuthorityJson;

/** Local evidence only. A separate current authority must confirm its exact fingerprint. */
final readonly class GateApproval
{
    public array $data;

    public function __construct(array $data)
    {
        $copy = [];
        foreach ($data as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException('Invalid approval evidence.');
            }
            $copy[$key] = $value;
        }
        $keys = array_keys($copy);
        sort($keys);
        $expected = ['id', 'gate', 'version', 'status', 'decisionRef', 'authority', 'evidence', 'effectiveAt', 'approvedAt', 'createdAt', 'schemaVersion', 'blocks'];
        sort($expected);
        if ($keys !== $expected) {
            throw new \InvalidArgumentException('Invalid approval shape.');
        }
        foreach (['id', 'gate', 'version', 'status', 'decisionRef', 'authority', 'evidence'] as $field) {
            if (! is_string($copy[$field]) || $copy[$field] === '' || strlen($copy[$field]) > 2048) {
                throw new \InvalidArgumentException('Invalid approval field.');
            }
        }
        if (! preg_match('/\AG-(?:0[1-9]|1[0-2])\z/D', $copy['gate']) || ! is_string($copy['blocks']) || ! is_int($copy['effectiveAt']) || ! is_int($copy['approvedAt']) || ! is_int($copy['createdAt']) || $copy['schemaVersion'] !== 1) {
            throw new \InvalidArgumentException('Invalid approval metadata.');
        }
        $this->data = $copy;
    }

    public function fingerprint(): string
    {
        return hash('sha256', CanonicalAuthorityJson::encode($this->data));
    }
}
