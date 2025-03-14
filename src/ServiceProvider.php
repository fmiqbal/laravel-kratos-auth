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

    protected function configureGuard(): void
    {
        $this->app['auth']->extend('kratos', function (Application $app) {
            $frontendApi = $app->make('fmiqbal.kratos_auth.frontendapi');

            $guard = new KratosGuard($app['request'], $frontendApi);

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }

    protected function configureOry()
    {
        $this->app->bind('fmiqbal.kratos_auth.guzzle_client', function () {
            $client = config('kratos.guzzle_client');

            if ($client instanceof Closure) {
                $client = $client();
            }

            if (! $client instanceof GuzzleHttp\Client) {
                throw new InvalidArgumentException("config('kratos.guzzle_client') should be instance of GuzzleHttp\\Client");
            }

            return $client;
        });

        $this->app->bind('fmiqbal.kratos_auth.frontendapi', function (Application $app) {
            $config = (new Ory\Client\Configuration())
                ->setHost(config('kratos.url'))
                ->setDebug(config('kratos.debug'));

            return new Ory\Client\Api\FrontendApi(
                $app->make('fmiqbal.kratos_auth.guzzle_client'),
                $config
            );
        });
    }
}
