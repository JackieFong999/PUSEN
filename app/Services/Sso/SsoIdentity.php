<?php

namespace App\Services\Sso;

/**
 * The verified identity returned by an SSO provider after a successful
 * assertion (email is the primary key used to map onto tblStaff.SSO_Email).
 */
class SsoIdentity
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $staffId = null,
    ) {}
}
