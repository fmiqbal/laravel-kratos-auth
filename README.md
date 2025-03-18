# Laravel Kratos Auth

This package is to add Guard for [Ory Kratos](https://github.com/ory/kratos).
The guard will call Ory Kratos `/sessions/whoami` endpoint
and built an ephemeral user based on that.

## Installation

```bash
composer require fmiqbal/laravel-kratos-auth
```

## Quick Start

1. Add `kratos` driver to your auth guard in `config/auth.php` 

```php
        'web' => [
            'driver' => 'kratos',
        ],
```
> **Note:** This driver does **not** use Laravel native `UserProvider`,
> assuming that user data is managed externally in Ory Kratos.
> However, you can implement custom mapping (e.g., user discovery, database syncing).

2. Set the env var

Add the Kratos URL to your .env file.

```dotenv
KRATOS_URL=https://your-kratos-public-url:4445
```

> **Note:** You should use Kratos Public URL because this package only calls self-service not the admin Url.

3. Test Authentication

Create a test route to inspect the authenticated user:

```php
Route::middleware('auth')->get('/user', function (\Illuminate\Http\Request $request) {
    dd(
        $request->user(),
        \Illuminate\Support\Facades\Auth::user(),
    );
});
```
By default, the authenticated user does not persist in a database.
You can customize this behavior using `user_scaffold` in the configuration

## Configuration

You can publish the configuration file (config/kratos.php) using:

```bash
php artisan vendor:publish --provider "Fmiqbal\KratosAuth\ServiceProvider"
```

Most configuration options are available via environment variables (ENV).

### User Scaffolding

The user scaffold determines how the Auth::user() method constructs a user object.
By default, a generic Laravel Authenticatable instance is returned (`\Illuminate\Auth\GenericUser`)

However, **you should customize this function** to fit your needs.

#### Example 1: Creating Users in the Database

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

> **Warning:** this method will be called on **every request**, just like UserProvider.

#### Example 2: Using Cache for Performance
To avoid database hits on every request, you can cache user data:

```php
'user_scaffold' => static function (\Ory\Client\Model\Session $session) {
    $id = $session->getIdentity()->getId();

    return Cache::remember("user:$id", 300, function () use ($id) {
        return \App\Models\User::find($id);
    });
},
```
> **Note:** This caches user data for 5 minutes (300s) to reduce DB queries.


#### Example 3: Rejecting Unrecognized Users
If you want to reject users who are not found in your system, return null:

```php

'user_scaffold' => static function (\Ory\Client\Model\Session $session) {
    return \App\Models\User::find($session->getIdentity()?->getId());
},
```

> This will throw an `AuthenticationException` if the user does not exist.
> 
### Caching

This package supports session caching, which helps reduce Ory Kratos API calls.

 - Enable caching by setting:

```dotenv
KRATOS_CACHE_ENABLED=true
```
- Default TTL (Time-to-Live) is 300 seconds.
- Each session is keyed by the hashed session cookie.
- The cookie name (in case you change it from default `ory_kratos_session`) is available via:

```dotenv
KRATOS_SESSION_COOKIE_NAME=my_kratos_session
```

> **Note:** This does not cache users—you must implement that separately in user_scaffold.

### Customizing the Guzzle Client

If you need to modify the HTTP client (e.g., for Sentry tracing),
you can override the default Guzzle Client like this:

```php
    'guzzle_client' => static function () {
        $stack = new \GuzzleHttp\HandlerStack();
        $stack->setHandler(new \GuzzleHttp\Handler\CurlHandler());
        $stack->push(\Sentry\Tracing\GuzzleTracingMiddleware::trace());

        return new GuzzleHttp\Client(['handler' => $stack]);
    },
```

## Capability

This package support following `Auth::` common facade method, that extended from `GuardHelpers` + `logout()`

```php
Auth::id();
Auth::validate();
Auth::user();
Auth::logout();
```
