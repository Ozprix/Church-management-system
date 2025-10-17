<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from these domains / hosts will receive stateful API cookies.
    | Typically this is used for SPA authentication flows. For this API-
    | first project we default to values derived from the environment.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,127.0.0.1')),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array lists the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these
    | guards are able to authenticate the request, Sanctum will
    | resort to its token based authentication guard.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will
    | be considered expired. This only applies to personal access tokens
    | that are not "remember me" tokens. If this value is null, token
    | expiration is disabled and tokens must be revoked manually.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prepend a given prefix to all tokens for easy readability
    | and the ability to then strip the prefix before validating where
    | necessary. The default value of this setting is "sac_".
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'sac_'),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as needed.
    |
    */

    'middleware' => [
        'verify_csrf_token' => Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
    ],

    'personal_access_token_model' => Sanctum::$personalAccessTokenModel,
];
