<?php

namespace App\Providers;

use App\Session\TenantDatabaseSessionHandler;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class TenantSessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Session::extend('tenant_database', function ($app) {
            $table = $app['config']['session.table'];
            $lifetime = $app['config']['session.lifetime'];
            $connection = $app['config']['session.connection'];

            return new TenantDatabaseSessionHandler(
                $app['db']->connection($connection),
                $table,
                $lifetime,
                $app
            );
        });
    }
}
