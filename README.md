# Laravel Kratos Auth

This package is to add Guard for [Ory Kratos](https://github.com/ory/kratos), specifically
on calling /sessions/whoami and creating Session with it.

# Installation

_WIP_

```
composer require
```

# Quick Start

1. Add `kratos` driver to your auth guard

`config/auth.php` 

```php
        'web' => [
            'driver' => 'kratos',
        ],
```
> This driver doesn't leverage Laravel native `UserProvider` on the assumption that the user data exists on external system
(Ory Kratos). You can however make your own mapping (with user discovery and such)

2. Set the env var

```dotenv
KRATOS_URL=
```

3. Try your auth

```php
Route::middleware('auth')->get('/user', function (\Illuminate\Http\Request $request) {
    dd(
        $request->user(),
        \Illuminate\Support\Facades\Auth::user(),
    );
});
```

# Configuration

You can publish the configuration, that will publish to `config/kratos.php`

```bash
php83 artisan vendor:publish --provider "Fmiqbal\KratosAuth\ServiceProvider"
```

You can read the documentation on each configuration key, and mostly configurable by ENV var

## User Scaffolding

User scaffolding is the user generated when you call `Auth::user()`, by default it will create
some generic user from Authenticatable, you can (and advised) change this value to your necessity.

This user is ephemeral and only exists on that request. But you can make it behave like UserProvider,
which is finding the user from Database

```php
    'user_scaffold' => static function (\Ory\Client\Model\Session $session) {
        return \App\Models\User::unguarded(
            static fn() => \App\Models\User::firstOrCreate([
                'guid' => $session->getIdentity()?->getId(),
            ], [
                'name' => $session->getIdentity()?->getTraits()->name,
                'picture' => $session->getIdentity()?->getTraits()->picture,
                'email' => $session->getIdentity()?->getTraits()->email,
                'email_verified_at' => \Carbon\Carbon::now()->timestamp,
            ])
        );
    },
```

You can also return `null`, and it will throw Authentication Exception

```php
    'user_scaffold' => static function (\Ory\Client\Model\Session $session) {
        // Return null if user not found in database
        return \App\Models\User::find($session->getIdentity()?->getId());
    },
```

## Guzzle Client

Guzzle client is configurable if you happen to want to modify it, specifically for sentry, you want to make
your client looks like this

```php
    'guzzle_client' => static function () {
        $stack = new \GuzzleHttp\HandlerStack();
        $stack->setHandler(new \GuzzleHttp\Handler\CurlHandler());
        $stack->push(\Sentry\Tracing\GuzzleTracingMiddleware::trace());

        return new GuzzleHttp\Client(['handler' => $stack]);
    },
```
