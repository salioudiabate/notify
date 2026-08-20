<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Salioudiabate\Notify\Facades\Notify;
use Salioudiabate\Notify\NotifyServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [NotifyServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Notify' => Notify::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('session.driver', 'array');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }

    /**
     * Routes added via the Route:: facade mid-test aren't reliably picked
     * up by Route::has()/route() once the router has already booted — this
     * hook runs early enough that they are. A couple of fixture routes,
     * shared by every test that needs to prove "a named route always wins".
     */
    protected function defineRoutes($router): void
    {
        $router->get('/clients', fn () => 'ok')->name('clients.index');
        $router->get('/clients/{client}', fn ($client) => $client)->name('clients.show');
    }
}
