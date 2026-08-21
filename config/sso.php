<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SSO master switch & protocol selection
    |--------------------------------------------------------------------------
    |
    | enabled:  show the SSO button on the login screen / allow SSO routes.
    | provider: which SsoProviderInterface implementation to bind.
    |           'saml' now; 'oidc' can be added later behind the same interface.
    */
    'enabled' => env('SSO_ENABLED', false),
    'provider' => env('SSO_PROVIDER', 'saml'),

    /*
    |--------------------------------------------------------------------------
    | SAML 2.0 settings (onelogin/php-saml format)
    |--------------------------------------------------------------------------
    | Dev defaults point at the local test IdP (kristophjunge/test-saml-idp on
    | :8082). Swap SSO_IDP_* values for the school's real IdP in production —
    | no code changes required.
    */
    'saml' => [
        'strict' => env('SSO_SAML_STRICT', false),
        'debug' => env('SSO_SAML_DEBUG', false),

        'sp' => [
            'entityId' => env('SSO_SP_ENTITY_ID', 'http://localhost:8080/login/sso/metadata'),
            'assertionConsumerService' => [
                'url' => env('SSO_SP_ACS', 'http://localhost:8080/login/sso/callback'),
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
            ],
            'singleLogoutService' => [
                'url' => env('SSO_SP_SLO', 'http://localhost:8080/login/sso/slo'),
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ],
            'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            // SP cert/key (leave empty unless the IdP requires signed requests)
            'x509cert' => env('SSO_SP_CERT', ''),
            'privateKey' => env('SSO_SP_KEY', ''),
        ],

        'idp' => [
            'entityId' => env('SSO_IDP_ENTITY_ID', 'http://localhost:8082/saml2/idp/metadata.php'),
            'singleSignOnService' => [
                'url' => env('SSO_IDP_SSO', 'http://localhost:8082/saml2/idp/SSOService.php'),
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ],
            'singleLogoutService' => [
                'url' => env('SSO_IDP_SLO', 'http://localhost:8082/saml2/idp/SingleLogoutService.php'),
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ],
            // IdP signing cert (from the IdP metadata XML)
            'x509cert' => env('SSO_IDP_CERT', ''),
        ],

        'security' => [
            'authnRequestsSigned' => false,
            'wantAssertionsSigned' => false,
            'wantNameId' => true,
            'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
            'digestAlgorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
        ],
    ],
];
