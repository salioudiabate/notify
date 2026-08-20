<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\SerializableClosure\SerializableClosure;

/**
 * Lets a plain Laravel app (no Livewire) use Notify::confirm()->onConfirm(
 * fn () => ...) even though a PHP closure can't be sent to the browser.
 *
 * The closure is serialized and cached server-side under a random,
 * single-use token, then exposed behind a short-lived *signed* URL. The
 * signature makes the URL tamper-proof and time-boxed; the cache entry is
 * deleted the moment it's read, so a token only ever fires once. Nothing
 * about the closure's contents is ever exposed to the client — only the
 * opaque token is.
 */
final class CallbackAction
{
    public static function register(Closure $callback): string
    {
        $token = Str::random(40);

        Cache::store(config('notify.actions.cache_store'))->put(
            "notify.action.$token",
            new SerializableClosure($callback),
            config('notify.actions.ttl', 300)
        );

        return URL::temporarySignedRoute(
            'notify.action',
            now()->addSeconds(config('notify.actions.ttl', 300)),
            ['token' => $token]
        );
    }

    public static function resolve(string $token): ?Closure
    {
        $store = Cache::store(config('notify.actions.cache_store'));
        $key = "notify.action.$token";

        /** @var SerializableClosure|null $wrapped */
        $wrapped = $store->get($key);
        $store->forget($key);

        return $wrapped?->getClosure();
    }
}
