<?php

declare(strict_types=1);

namespace Salioudiabate\Notify;

use Illuminate\Support\Facades\Route;
use Salioudiabate\Notify\Drivers\SessionDriver;
use Salioudiabate\Notify\Http\Controllers\ActionController;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class NotifyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('notify')
            ->hasConfigFile('notify')
            ->hasViews('notify');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SessionDriver::class);
        $this->app->singleton('notify', static fn ($app) => new NotifyManager($app));
    }

    public function packageBooted(): void
    {
        $this->registerRoutes();
        $this->publishAssets();
    }

    /**
     * Backs the `php artisan vendor:publish --tag=notify-assets` command
     * documented in the README, for teams that want to self-host the JS/CSS
     * through their own build pipeline instead of the package serving them
     * directly (see config('notify.assets.serve')).
     */
    private function publishAssets(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../resources/js/notify.js' => public_path('vendor/notify/notify.js'),
            __DIR__.'/../resources/css/notify.css' => public_path('vendor/notify/notify.css'),
        ], 'notify-assets');
    }

    private function registerRoutes(): void
    {
        Route::middleware('web')->group(function (): void {
            if (config('notify.actions.enabled', true)) {
                // 'signed' alone only proves the URL is untampered and not
                // expired — never who's making the request right now (see
                // config('notify.actions.middleware') and README § Security).
                Route::post('/notify/actions/{token}', ActionController::class)
                    ->middleware(['signed', ...config('notify.actions.middleware', [])])
                    ->name('notify.action');
            }

            if (config('notify.assets.serve', true)) {
                // no-cache (not no-store): the browser still validates every
                // time via the file's Last-Modified/ETag, so an edit during
                // active development is never masked by a silently-stale
                // heuristic cache — a 304 round trip is negligible either way.
                $assetHeaders = ['Cache-Control' => 'no-cache, must-revalidate', 'Pragma' => 'no-cache'];

                Route::get('/notify/notify.js', fn () => response()
                    ->file(__DIR__.'/../resources/js/notify.js', $assetHeaders + ['Content-Type' => 'application/javascript; charset=utf-8']))
                    ->name('notify.assets.js');

                Route::get('/notify/notify.css', fn () => response()
                    ->file(__DIR__.'/../resources/css/notify.css', $assetHeaders + ['Content-Type' => 'text/css; charset=utf-8']))
                    ->name('notify.assets.css');
            }
        });
    }
}
