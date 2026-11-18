<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;

/** Real Mongo identity retrieval plus fixture-only credentials; no credential storage/rehash workflow. */
final class FixtureCredentialLabProvider extends EloquentUserProvider
{
    public function __construct(Hasher $hasher, string $model, private array $hashes)
    {
        parent::__construct($hasher, $model);
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials)
    {
        $hash = $this->hashes[$user->getAuthIdentifier()] ?? null;
        $plain = $credentials['password'] ?? null;

        return is_string($hash) && is_string($plain) && $this->hasher->check($plain, $hash);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false)
    {
        // Deliberately no users.password write: fixture hashes live only in this test instance.
    }
}
