<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kratos URL
    |--------------------------------------------------------------------------
    |
    | This value is your Ory Kratos Frontend URL
    |
    */
    'url' => env('KRATOS_URL', 'http://localhost:4434/'),

    /*
    |--------------------------------------------------------------------------
    | Kratos Session Cookie Name
    |--------------------------------------------------------------------------
    |
    | Ory kratos session cookie as described by your kratos config on path
    | `session.cookie.name`
    |
    */
    'session_cookie_name' => env('KRATOS_SESSION_COOKIE_NAME', 'ory_kratos_session'),

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    |
    | Set debug flag for Ory HTTP API
    |
    */
    'debug' => env('KRATOS_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Make use of cache for Session get from /sessions/whoami, by default
    | it will use ttl of 5 minutes
    |
    */
    'cache' => [
        'enabled' => env('KRATOS_CACHE_ENABLED', false),
        'ttl' => env('KRATOS_CACHE_TTL', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scaffold user on valid session
    |--------------------------------------------------------------------------
    |
    | Map Ory Sessions (/sessions/whoami) into Authenticatable
    |
    */
    'user_scaffold' => static function (\Ory\Client\Model\Session $session) {
        return new \Illuminate\Auth\GenericUser([
            'id' => $session->getIdentity()?->getId(),
            // 'name' => $session->getIdentity()?->getTraits()->name
        ]);
    },

    /*
    |--------------------------------------------------------------------------
    | Guzzle Client
    |--------------------------------------------------------------------------
    |
    | Guzzle Client used, if needed to inject other information such as tracing
    |
    */
    'guzzle_client' => new \GuzzleHttp\Client(),
];
