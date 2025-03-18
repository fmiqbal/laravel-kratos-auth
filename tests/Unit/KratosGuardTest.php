<?php

namespace Tests\Unit;

use Closure;
use Exception;
use Fmiqbal\KratosAuth\Exceptions\RedirectUsingException;
use Fmiqbal\KratosAuth\KratosGuard;
use GuzzleHttp;
use GuzzleHttp\HandlerStack;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use Ory;
use Ory\Client\ApiException;
use Ory\Client\Model\Session;
use PHPUnit\Framework\Attributes\Test;
use Random;
use Request;

class KratosGuardTest extends TestCase
{
    use WithWorkbench;

    protected $enablesPackageDiscoveries = true;

    public function setUp(): void
    {
        parent::setUp();

        config()->set('auth.guards.kratos', ['driver' => 'kratos']);
    }

    /**
     * @throws AuthenticationException
     * @throws Random\RandomException
     */
    #[Test] public function valid_session_should_create_user(): void
    {
        $newId = Str::uuid();

        $session = $this->getSession();
        $session->getIdentity()->setId($newId->toString());
        $this->setGuzzleResponse(
            new GuzzleHttp\Psr7\Response(body: json_encode($session->jsonSerialize()))
        );

        $guard = $this->getGuard();
        $check = $guard->check();
        $user = $guard->user();

        $this->assertEquals($newId->toString(), $user->getAuthIdentifier());
        $this->assertTrue($check);

        // Check for second call; since we only have 1 Guzzle Response stack, it will throw error if FrontendApi try
        // to call again
        try {
            $guard->user();
        } catch (Exception) {
            $this->fail('User should call from existing if called more than once');
        }
    }

    #[Test] public function non_auth_error_from_ory_should_be_reported()
    {
        $this->setGuzzleResponse(
            new GuzzleHttp\Psr7\Response(status: 500)
        );

        $this->assertReportCalled(fn($e) => $e instanceof ApiException);

        $this->getGuard()->user();
    }

    #[Test] public function user_scaffold_should_be_a_closure(): void
    {
        config()->set('kratos.user_scaffold', '');

        $this->setGuzzleResponse(
            new GuzzleHttp\Psr7\Response(body: json_encode($this->getSession()->jsonSerialize()))
        );

        $this->assertThrows(fn() => $this->getGuard()->user(), InvalidArgumentException::class);
    }

    #[Test] public function unauthenticated_from_ory_should_throw_authentication_exception(): void
    {
        $this->setGuzzleResponse(new GuzzleHttp\Psr7\Response(401));
        $this->assertThrows(
            fn() => $this->getGuard()->user(),
            AuthenticationException::class
        );

        $this->setGuzzleResponse(new GuzzleHttp\Psr7\Response(403));
        $this->assertThrows(fn() => $this->getGuard()->user(), AuthenticationException::class);
    }

    #[Test] public function empty_cookies_should_throw_authentication_exception(): void
    {
        $frontendApi = app('fmiqbal.kratos_auth.frontendapi');

        $guard = new KratosGuard(Request::create(uri: '/'), $frontendApi);

        $this->assertThrows(
            fn() => $guard->user(),
            AuthenticationException::class
        );
    }

    /**
     * @throws Random\RandomException
     * @throws AuthenticationException
     */
    #[Test] public function cache_is_called_if_cache_enabled(): void
    {
        config()->set('kratos.cache.enabled', true);

        $session = $this->getSession();
        $this->setGuzzleResponse(
            new GuzzleHttp\Psr7\Response(body: json_encode($session->jsonSerialize()))
        );

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn($session);

        $this->getGuard()->user();
    }

    /**
     * @throws Random\RandomException
     * @throws AuthenticationException
     */
    #[Test] public function cache_is_bypassed_if_it_error(): void
    {
        config()->set('kratos.cache.enabled', true);
        config()->set('cache.default', 'database');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', '/dev/null');

        $session = $this->getSession();
        $this->setGuzzleResponse(
            new GuzzleHttp\Psr7\Response(body: json_encode($session->jsonSerialize()))
        );

        $this->assertReportCalled(fn($e) => $e instanceof QueryException);

        $this->getGuard()->user();
    }

    #[Test] public function logout_success_will_forget_cache(): void
    {
        config()->set('kratos.cache.enabled', true);

        $this->setGuzzleResponse(new GuzzleHttp\Psr7\Response(body: json_encode([])));

        Cache::shouldReceive('forget')
            ->once();

        $this->assertThrows(fn() => $this->getGuard()->logout(), RedirectUsingException::class);
    }

    #[Test] public function logout_cache_error_should_be_reported(): void
    {
        // Enable cache but make it invalid
        config()->set('kratos.cache.enabled', true);
        config()->set('cache.default', 'database');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', '/dev/null');

        $this->assertReportCalled(fn($e) => $e instanceof QueryException);

        $this->setGuzzleResponse(new GuzzleHttp\Psr7\Response(body: json_encode([])));

        $this->assertThrows(fn() => $this->getGuard()->logout(), RedirectUsingException::class);
    }

    #[Test] public function logout_success(): void
    {
        $logoutToken = Str::random(40);
        $this->setGuzzleResponse(
            new GuzzleHttp\Psr7\Response(body: json_encode([
                'logout_token' => $logoutToken,
                'logout_url' => "https://localhost:80001/logout?return_to=$logoutToken",
            ]))
        );

        $this->assertThrows(fn() => $this->getGuard()->logout(), RedirectUsingException::class);
    }

    #[Test] public function logout_throw_authentication_on_flow_error(): void
    {
        $this->setGuzzleResponse(new GuzzleHttp\Psr7\Response(status: 401));
        $this->assertThrows(fn() => $this->getGuard()->logout(), AuthenticationException::class);
    }

    #[Test] public function logout_report_exception(): void
    {
        $this->setGuzzleResponse(new GuzzleHttp\Psr7\Response(status: 500));
        $this->assertReportCalled(fn($e) => $e instanceof ApiException);
        $this->assertThrows(fn() => $this->getGuard()->logout(), AuthenticationException::class);
    }

    /**
     * @param Closure $exceptionHandler
     * @return KratosGuardTest
     * @see https://gist.github.com/scrubmx/7571e9663963e33d17b7c5dcede11e75
     */
    protected function assertReportCalled(Closure $exceptionHandler): static
    {
        $this->partialMock(ExceptionHandler::class)
            ->shouldReceive('report')
            ->withArgs($exceptionHandler)
            ->once();

        return $this;
    }

    /**
     * @return KratosGuard
     * @throws Random\RandomException
     */
    protected function getGuard(): KratosGuard
    {
        $frontendApi = app('fmiqbal.kratos_auth.frontendapi');
        $cookieName = config('kratos.session_cookie_name');
        $cookie = base64_encode(random_bytes(300));

        return new KratosGuard(Request::create(uri: '/', cookies: [$cookieName => $cookie]), $frontendApi);
    }

    /**
     * @return Session
     */
    protected function getSession(): Session
    {
        $originalSession = json_decode(file_get_contents(__DIR__ . '/mocks/sessions_whoami.json'), true);

        $session = new Ory\Client\Model\Session($originalSession);
        $session->setIdentity(new Ory\Client\Model\Identity($originalSession['identity']))
            ->setAuthenticationMethods(array_map(static fn($method) => new Ory\Client\Model\SessionAuthenticationMethod($method), $originalSession['authentication_methods']))
            ->setDevices($originalSession['devices']);

        $session->getIdentity()
            ->setTraits($originalSession['identity']['traits'])
            ->setVerifiableAddresses($originalSession['identity']['verifiable_addresses'])
            ->setRecoveryAddresses($originalSession['identity']['recovery_addresses']);

        return $session;
    }

    protected function setGuzzleResponse(GuzzleHttp\Psr7\Response ...$response): void
    {
        $this->app->bind('fmiqbal.kratos_auth.guzzle_client', function () use ($response) {
            return new GuzzleHttp\Client([
                'handler' => HandlerStack::create(
                    new GuzzleHttp\Handler\MockHandler(Arr::wrap($response))
                ),
            ]);
        });
    }
}
