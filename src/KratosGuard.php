<?php

namespace Fmiqbal\KratosAuth;

use Closure;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Ory\Client\Api\FrontendApi;
use Ory\Client\ApiException;
use Ory\Client\Model\Session;

class KratosGuard implements Guard
{
    use GuardHelpers;

    public function __construct(
        protected Request     $request,
        protected FrontendApi $frontendApi,
    )
    {
    }

    /**
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function validate(array $credentials = []): bool
    {
        return ! is_null($this->user());
    }

    /**
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function user(): ?Authenticatable
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        try {
            $cookieName = config('kratos.session_cookie_name');
            $cookie = $this->request->cookie($cookieName);

            if (empty($cookie)) {
                throw new AuthenticationException();
            }

            $session = config('kratos.cache.enabled')
                ? $this->getCachedSession($cookieName, $cookie)
                : $this->getSession($cookieName, $cookie);
        } catch (Exception $exception) {
            if (in_array($exception->getCode(), [401, 403], true)) {
                throw new AuthenticationException();
            }

            throw $exception;
        }

        $this->user = $this->makeUser($session);

        return $this->user;
    }

    /**
     * @throws ApiException
     */
    protected function getSession(string $cookieName, string $cookie): Session
    {
        return $this->frontendApi->toSession(cookie: "$cookieName=$cookie");
    }

    protected function getCachedSession(string $cookieName, string $cookie): Session
    {
        $key = "kratos:cookie:" . hash_hmac('sha256', $cookie, config('app.key'));
        $ttl = config('kratos.cache.ttl');

        return Cache::remember($key, $ttl, fn() => $this->getSession($cookieName, $cookie));
    }

    protected function makeUser(Session $session): ?Authenticatable
    {
        $scaffoldFunction = config('kratos.user_scaffold');

        if (! $scaffoldFunction instanceof Closure) {
            throw new InvalidArgumentException('User scaffold is not a function');
        }

        return $scaffoldFunction($session);
    }
}
