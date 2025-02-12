<?php

namespace Fmiqbal\KratosAuth;

use Closure;
use Fmiqbal\KratosAuth\Exceptions\UserScaffoldIsNotClosure;
use GuzzleHttp;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Ory\Client\Api\FrontendApi;
use Ory\Client\ApiException;
use Ory\Client\Configuration;
use Ory\Client\Model\Session;
use RuntimeException;

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

    public function user(): ?Authenticatable
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        $frontendApi = new FrontendApi(new GuzzleHttp\Client(), $this->ory);

        try {
            $session = $frontendApi->toSession(
                cookie: $this->request->header('Cookie')
            );
        } catch (ApiException $exception) {
            // If not 401 Unauthorized, its probably something wrong with Kratos
            if ($exception->getCode() !== 401) {
                Log::error($exception);
            }

            return null;
        }

        return $this->makeUser($session);
    }

    public function validate(array $credentials = []): bool
    {
        return ! is_null($this->user());
    }

    protected function makeUser(Session $session): Authenticatable
    {
        $scaffoldFunction = config('kratos.user_scaffold');

        if (! $scaffoldFunction instanceof Closure) {
            throw new RuntimeException(new UserScaffoldIsNotClosure());
        }

        return config('kratos.user_scaffold')($session);
    }
}
