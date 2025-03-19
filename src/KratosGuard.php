<?php

namespace Fmiqbal\KratosAuth;

use Closure;
use Exception;
use Fmiqbal\KratosAuth\Exceptions\RedirectUsingException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Ory\Kratos\Client\Api\FrontendApi;
use Ory\Kratos\Client\ApiException;
use Ory\Kratos\Client\Model\Session;

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
     * @throws AuthenticationException
     */
    public function validate(array $credentials = []): bool
    {
        return ! is_null($this->user());
    }

    /**
     * @throws AuthenticationException
     */
    public function user(): ?Authenticatable
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        $cookieName = config('kratos.session_cookie_name');
        $cookie = $this->request->cookie($cookieName);

        if (empty($cookie)) {
            throw new AuthenticationException();
        }

        $this->user = null;
        try {
            $session = config('kratos.cache.enabled')
                ? $this->getCachedSession($cookieName, $cookie)
                : $this->getSession($cookieName, $cookie);
        } catch (Exception $exception) {
            if (in_array($exception->getCode(), [401, 403], true)) {
                throw new AuthenticationException();
            }

            $this->report($exception);
        }

        if (isset($session)) {
            $this->user = $this->makeUser($session);
        }

        return $this->user;
    }

    /**
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function logout(): void
    {
        $cookieName = config('kratos.session_cookie_name');
        $cookie = $this->request->cookie($cookieName);

        $frontendApi = $this->frontendApi;

        try {
            $logoutFlow = $frontendApi->createBrowserLogoutFlow(
                cookie: "$cookieName=$cookie",
                returnTo: config('kratos.logout_return_to'),
            );
        } catch (ApiException $exception) {
            if (in_array($exception->getCode(), [401, 403], true)) {
                throw new AuthenticationException();
            }

            $this->report($exception);

            throw new AuthenticationException();
        }

        if (config('kratos.cache.enabled')) {
            try {
                Cache::forget($this->getCacheKey($cookie));
            } catch (Exception $exception) {
                $this->report($exception);
            }
        }

        throw new RedirectUsingException($logoutFlow->getLogoutUrl());
    }

    /**
     * @param Exception $exception
     * @return void
     */
    protected function report(Exception $exception): void
    {
        if (! app()->bound('reported_auth_exception')) {
            app()->instance('reported_auth_exception', true);

            report($exception); // Report the exception without triggering another context loop
        }
    }

    protected function getCacheKey(string $cookie): string
    {
        return "kratos:cookie:" . hash_hmac('sha256', $cookie, config('app.key'));
    }

    /**
     * @throws ApiException
     */
    protected function getSession(string $cookieName, string $cookie): Session
    {
        return $this->frontendApi->toSession(cookie: "$cookieName=$cookie");
    }

    /**
     * @throws ApiException
     */
    protected function getCachedSession(string $cookieName, string $cookie): Session
    {
        $key = $this->getCacheKey($cookie);
        $ttl = config('kratos.cache.ttl');

        try {
            $session = Cache::remember($key, $ttl, fn() => $this->getSession($cookieName, $cookie));
        } catch (Exception $exception) {
            $this->report($exception);

            $session = $this->getSession($cookieName, $cookie);
        }

        return $session;
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
