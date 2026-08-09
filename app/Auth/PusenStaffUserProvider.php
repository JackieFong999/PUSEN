<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * User provider for the legacy tblStaff table.
 *
 * tblStaff.Password is stored as plain text (varchar(10)), so credentials are
 * compared directly instead of using Hash::check(). An account is also rejected
 * when it is disabled (status = 1).
 */
class PusenStaffUserProvider extends EloquentUserProvider
{
    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;

        if (! is_string($plain)) {
            return false;
        }

        if (! hash_equals((string) $user->getAuthPassword(), $plain)) {
            return false;
        }

        // 0 = Enable, 1 = Disable
        return (int) ($user->getAttribute('status') ?? 0) === 0;
    }

    /**
     * Legacy schema stores plain-text passwords (varchar(10)); never rehash or
     * write the password back on login, otherwise the value would be truncated.
     */
    public function rehashPasswordIfRequired(UserContract $user, array $credentials, bool $force = false)
    {
        // no-op
    }
}
