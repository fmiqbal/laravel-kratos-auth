<?php

namespace Fmiqbal\KratosAuth;

use Closure;
use Exception;
use Fmiqbal\KratosAuth\Exceptions\UserScaffoldIsNotClosure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

            $session = $this->getSession($cookieName);
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
     * @param mixed $cookieName
     * @return Session
     * @throws ApiException
     * @throws AuthenticationException
     */
    protected function getSession(mixed $cookieName): Session
    {
        $cookie = $this->request->cookie($cookieName);

        if (empty($cookie)) {
            throw new AuthenticationException();
        }

        if (! config('kratos.cache.enabled')) {
            return $this->frontendApi->toSession(cookie: sprintf("$cookieName=%s", $cookie));
        }

        $key = "kratos:cookie:" . hash_hmac('sha256', $cookie, config('app.key'));
        $ttl = config('kratos.cache.ttl');

        return Cache::remember($key, $ttl, fn() => $this->frontendApi->toSession(
            cookie: sprintf("$cookieName=%s", $cookie)
        ));
    }

    protected function makeUser(Session $session): Authenticatable
    {
        $scaffoldFunction = config('kratos.user_scaffold');

        if (! $scaffoldFunction instanceof Closure) {
            throw new UserScaffoldIsNotClosure();
        }

        return $scaffoldFunction($session);
    }
}
