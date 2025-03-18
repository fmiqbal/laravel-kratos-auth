<?php

namespace Tests\Unit;

use Fmiqbal\KratosAuth\KratosGuard;
use GuzzleHttp;
use InvalidArgumentException;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use Ory;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    use WithWorkbench;

    protected $enablesPackageDiscoveries = true;

    #[Test] public function guard_should_be_instantiated_properly(): void
    {
        config()->set('auth.guards.kratos', ['driver' => 'kratos']);

        $guard = app('auth')->guard('kratos');

        $this->assertInstanceOf(KratosGuard::class, $guard);
    }

    #[Test] public function frontend_api_should_be_instantiated_properly(): void
    {
        config()->set('kratos.url', 'https://kratos');
        config()->set('kratos.debug', true);

        $frontendApi = app('fmiqbal.kratos_auth.frontendapi');

        $this->assertInstanceOf(Ory\Client\Api\FrontendApi::class, $frontendApi);
        $this->assertEquals("https://kratos", $frontendApi->getConfig()->getHost());
        $this->assertTrue($frontendApi->getConfig()->getDebug());
    }

    #[Test] public function guzzle_client_can_be_closure(): void
    {
        $config = config();

        $this->assertThrows(function () use ($config) {
            $config->set('kratos.guzzle_client', function () {
                return null;
            });

            app('fmiqbal.kratos_auth.guzzle_client');

        }, InvalidArgumentException::class);

        $config->set('kratos.guzzle_client', function () {
            return new GuzzleHttp\Client();
        });

        $client = app('fmiqbal.kratos_auth.guzzle_client');

        $this->assertInstanceOf(GuzzleHttp\Client::class, $client);
    }
}
