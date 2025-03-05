<?php

namespace Fmiqbal\KratosAuth;

use Closure;
use Fmiqbal\KratosAuth\Exceptions\UserScaffoldIsNotClosure;
use GuzzleHttp;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Ory\Client\Api\FrontendApi;
use Ory\Client\ApiException;
use Ory\Client\Configuration;
use Ory\Client\Model\Session;
use Throwable;

class KratosGuard implements Guard
{
    use GuardHelpers;

    protected Request $request;
    protected Configuration $ory;

    public function __construct(
        Request       $request,
        Configuration $ory
    )
    {
        $this->request = $request;
        $this->ory = $ory;
    }

    public function validate(array $credentials = []): bool
    {
        return ! is_null($this->user());
    }

    public function user(): ?Authenticatable
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        $client = $this->getClient();

        $frontendApi = new FrontendApi($client, $this->ory);

        try {
            $session = $frontendApi->toSession(
                cookie: $this->request->header('Cookie')
            );
        } catch (Throwable $exception) {
            // If not 401 Unauthorized, its probably something wrong with Kratos
            if ($exception instanceof ApiException && $exception->getCode() !== 401) {
                report($exception);
            }

            return null;
        }

        return $this->makeUser($session);
    }

    /**
     * @return GuzzleHttp\Client
     */
    public function getClient(): GuzzleHttp\Client
    {
        $client = config('kratos.guzzle_client');

        if ($client instanceof Closure) {
            $client = $client();
        }

        if (! $client instanceof GuzzleHttp\Client) {
            throw new InvalidArgumentException("config('guzzle_client') should be instance of GuzzleHttp\\Client");
        }

        return $client;
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
