<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Support;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
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

        $resolve = function () use ($store, $key): ?Closure {
            /** @var SerializableClosure|null $wrapped */
            $wrapped = $store->get($key);
            $store->forget($key);

            return $wrapped?->getClosure();
        };

        // get() then forget() isn't atomic on its own — two concurrent
        // requests for the same token (a double-click, a deliberate replay
        // race) could both read the closure before either deletes it,
        // breaking "single-use" and potentially firing a destructive action
        // twice. The lock closes that window; the second request waits
        // briefly, then correctly finds nothing left to resolve. Every
        // built-in cache driver (file, database, redis, memcached, dynamodb,
        // array) supports this — only an exotic custom driver wouldn't,
        // hence the instanceof check rather than assuming it unconditionally.
        $underlyingStore = $store->getStore();

        if (! $underlyingStore instanceof LockProvider) {
            return $resolve();
        }

        try {
            return $underlyingStore->lock("notify.action.lock.$token", 10)->block(
                config('notify.actions.lock_wait', 5),
                $resolve
            );
        } catch (LockTimeoutException) {
            return null;
        }
    }
}
