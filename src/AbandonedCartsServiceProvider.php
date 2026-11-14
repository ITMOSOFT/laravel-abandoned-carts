<?php

namespace ItmoSoft\AbandonedCarts;

use Illuminate\Support\ServiceProvider;

class AbandonedCartsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/abandoned-carts.php', 'itmosoft-abandoned-carts');
        $this->app->singleton(AbandonedCartManager::class, fn ($app) => new AbandonedCartManager(
            $app['config']->get('itmosoft-abandoned-carts', [])
        ));
        $this->app->alias(AbandonedCartManager::class, 'itmosoft.abandoned-carts');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/abandoned-carts.php' => config_path('itmosoft-abandoned-carts.php'),
            ], 'itmosoft-abandoned-carts-config');
        }
    }
}
