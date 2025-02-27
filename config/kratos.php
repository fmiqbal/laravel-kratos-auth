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
    | Kratos Admin URL
    |--------------------------------------------------------------------------
    |
    | This value is your Ory Kratos Admin URL
    |
    */
    'admin_url' => env('KRATOS_ADMIN_URL', 'http://localhost:4434/'),

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
    | Scaffold user on valid session
    |--------------------------------------------------------------------------
    |
    | Map Ory Sessions (/sessions/whoami) into Authenticatable
    |
    */
    'user_scaffold' => static function (\Ory\Client\Model\Session $session) {
        return new \Illuminate\Auth\GenericUser([
            'id' => $session->getId(),
            // 'name' => $session->getIdentity()['name'],
        ]);
    },

    'guzzle_client' => new \GuzzleHttp\Client(),
];
