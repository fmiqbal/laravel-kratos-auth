<?php

namespace Fmiqbal\KratosAuth;

use Fmiqbal\KratosAuth\Exceptions\KratosNotReadyException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
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

        $this->configureGuard();
    }

    protected function configureGuard()
    {
        $ory = Ory\Client\Configuration::getDefaultConfiguration()
            ->setHost(config('kratos.admin_url'))
            ->setDebug(config('kratos.debug'));

        $readyUrl = $ory->getHost() . '/health/ready';

        $health = Http::get($readyUrl);

        if ($health->status() !== 200 || ($health->json()['status'] ?? null) !== 'ok') {
            throw new KratosNotReadyException();
        }

        $this->app['auth']->extend('kratos', function (Application $app, string $name, array $config) use ($ory) {
            $guard = new KratosGuard($app['request'], $ory);

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }


}
