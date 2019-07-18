<?php

namespace Hafael\LaravelSSLClient;

use Illuminate\Support\ServiceProvider;
use Hafael\LaravelSSLClient\Console\Commands\RegisterLetsEncryptAccount;
use Hafael\LaravelSSLClient\Console\Commands\IssueLetsEncryptCertificates;
use Hafael\LaravelSSLClient\Console\Commands\ValidateLetsEncryptCertificate;
use Hafael\LaravelSSLClient\Console\Commands\FetchSSLCertificates;

class LaravelSSLClientServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->publishes([
            __DIR__.'/config/ssl.php' => config_path('ssl.php'),
        ], "laravel-ssl-config");

        $this->publishes([
            __DIR__.'/migrations' => database_path('migrations'),
        ], "laravel-ssl-migrations");
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->commands([
            RegisterLetsEncryptAccount::class,
            IssueLetsEncryptCertificates::class,
            ValidateLetsEncryptCertificate::class,
            FetchSSLCertificates::class,
        ]);

        if(config('ssl.database'))
        {
            $this->loadMigrationsFrom(__DIR__.'/migrations');
        }
    }
}
