<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * User provider for the legacy tblStaff table.
 *
 * tblStaff.Password stores bcrypt hashes (varchar(255)), so credentials are
 * compared with Hash::check(). An account is also rejected when it is
 * disabled (status = 1).
 */
class PusenStaffUserProvider extends EloquentUserProvider
{
    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;

        if (! is_string($plain) || ! $this->hasher->check($plain, (string) $user->getAuthPassword())) {
            return false;
        }

        // 0 = Enable, 1 = Disable
        return (int) ($user->getAttribute('status') ?? 0) === 0;
    }

    /**
     * Rehash the stored bcrypt hash when the configured algorithm/cost
     * changes. Legacy plain-text values (should not exist after
     * `php artisan staff:hash-passwords`) are left untouched, and the write
     * targets the real 'Password' column (the parent implementation uses the
     * lowercase 'password' attribute, which would not persist here).
     */
    public function rehashPasswordIfRequired(UserContract $user, array $credentials, bool $force = false)
    {
        $plain = $credentials['password'] ?? null;

        if (! is_string($plain) || ! $this->hasher->isHashed($user->getAuthPassword())) {
            return;
        }

        if ($force || $this->hasher->needsRehash($user->getAuthPassword())) {
            $user->forceFill(['Password' => $this->hasher->make($plain)])->save();
        }
    }
}
