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
    }

    private function registerRoutes(): void
    {
        Route::middleware('web')->group(function (): void {
            if (config('notify.actions.enabled', true)) {
                Route::post('/notify/actions/{token}', ActionController::class)
                    ->middleware('signed')
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
