<?php

namespace Fmiqbal\KratosAuth;

use Closure;
use GuzzleHttp;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use InvalidArgumentException;
use Ory;

class ServiceProvider extends LaravelServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/kratos.php', 'kratos'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/kratos.php' => config_path('kratos.php'),
        ]);

        $this->configureOry();
        $this->configureGuard();
    }

    protected function configureOry(): void
    {
        $config = (new Ory\Client\Configuration())
            ->setHost(config('kratos.url'))
            ->setDebug(config('kratos.debug'));

        $this->app->singleton(Ory\Client\Api\FrontendApi::class, function () use ($config) {
            return new Ory\Client\Api\FrontendApi(
                $this->getClient(),
                $config
            );
        });
    }

    protected function configureGuard(): void
    {
        $this->app['auth']->extend('kratos', function (Application $app) {
            $frontendApi = $app->make(Ory\Client\Api\FrontendApi::class);

            $guard = new KratosGuard($app['request'], $frontendApi);

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }

    /**
     * @return GuzzleHttp\Client
     */
    protected function getClient(): GuzzleHttp\Client
    {
        $client = config('kratos.guzzle_client');

        if ($client instanceof Closure) {
            $client = $client();
        }

        if (! $client instanceof GuzzleHttp\Client) {
            throw new InvalidArgumentException("config('kratos.guzzle-client') should be instance of GuzzleHttp\\Client");
        }

        return $client;
    }

}
