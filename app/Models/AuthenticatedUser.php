<?php

declare(strict_types=1);

namespace App\Models;

use MongoDB\Laravel\Auth\User as MongoUser;

/** Session identity adapter. Passwords/OTP live in separate collections and are not implemented here. */
final class AuthenticatedUser extends MongoUser
{
    protected $connection = 'mongodb';

    protected $table = 'users';

    protected $guarded = ['*'];

    protected $hidden = ['security_epoch'];

    public function getRememberTokenName(): string
    {
        return ''; // No unrevocable remember-me fallback or schema-incompatible token field.
    }
}
