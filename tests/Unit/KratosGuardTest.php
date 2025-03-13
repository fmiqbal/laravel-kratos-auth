<?php

namespace Unit;

use Fmiqbal\KratosAuth\KratosGuard;
use GuzzleHttp;
use GuzzleHttp\HandlerStack;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use Ory;
use Ory\Client\Model\Session;
use PHPUnit\Framework\Attributes\Test;
use Request;

class KratosGuardTest extends TestCase
{
    use WithWorkbench;

    protected $enablesPackageDiscoveries = true;
    private KratosGuard $guard;

    public function setUp(): void
    {
        parent::setUp();

        config()->set('auth.guards.kratos', ['driver' => 'kratos']);
    }

    /**
     * @throws AuthenticationException
     * @throws Ory\Client\ApiException
     * @throws Random\RandomException
     * @throws JsonException
     */
    #[Test] public function valid_session_should_create_user(): void
    {
        $newId = Str::uuid();

        $session = $this->getSession();
        $session->getIdentity()->setId($newId->toString());
        $this->setGuzzleResponse(
            new GuzzleHttp\Psr7\Response(body: json_encode($session->jsonSerialize(), JSON_THROW_ON_ERROR))
        );

        $guard = $this->getGuard();
        $valid = $guard->validate();
        $user = $guard->user();

        $this->assertEquals($newId->toString(), $user->getAuthIdentifier());
        $this->assertTrue($valid);

        // Check for second call, since we only have 1 Guzzle Response stack, it will throw error if FrontendApi try
        // to call again
        try {
            $guard->user();
        } catch (Exception) {
            $this->fail('User should call from existing if called more than once');
        }
    }

    /**
     * @throws JsonException
     */
    #[Test] public function user_scaffold_should_be_a_closure(): void
    {
        config()->set('kratos.user_scaffold', '');

        $this->setGuzzleResponse(
            new GuzzleHttp\Psr7\Response(body: json_encode($this->getSession()->jsonSerialize(), JSON_THROW_ON_ERROR))
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
        $this->assertThrows(
            fn() => $this->getGuard()->user(),
            AuthenticationException::class
        );
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
     * @throws Ory\Client\ApiException
     * @throws AuthenticationException
     * @throws JsonException
     */
    #[Test] public function cache_is_called_if_cache_enabled(): void
    {
        config()->set('kratos.cache.enabled', true);

        $session = $this->getSession();
        $this->setGuzzleResponse(
            new GuzzleHttp\Psr7\Response(body: json_encode($session->jsonSerialize(), JSON_THROW_ON_ERROR))
        );
        $guard = $this->getGuard();

        Cache::shouldReceive('remember')
            ->once()
            ->andReturn($session);

        $guard->user();
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
     * @throws JsonException
     */
    protected function getSession(): Session
    {
        $originalSession = json_decode(file_get_contents(__DIR__ . '/mocks/sessions_whoami.json'), true, 512, JSON_THROW_ON_ERROR);

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

    protected function setGuzzleResponse(GuzzleHttp\Psr7\Response $response): void
    {
        $this->app->bind('fmiqbal.kratos_auth.guzzle_client', function () use ($response) {
            return new GuzzleHttp\Client([
                'handler' => HandlerStack::create(
                    new GuzzleHttp\Handler\MockHandler([$response])
                ),
            ]);
        });
    }
}
