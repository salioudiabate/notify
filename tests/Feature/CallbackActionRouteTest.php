<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Salioudiabate\Notify\Support\CallbackAction;

it('executes a registered closure exactly once, through its signed URL', function () {
    $calls = 0;
    $url = CallbackAction::register(function () use (&$calls) {
        $calls++;
    });

    $this->post($url)->assertNoContent();

    expect($calls)->toBe(1);
});

it('rejects a second use of the same signed URL — single-use, not just time-boxed', function () {
    $url = CallbackAction::register(fn () => null);

    $this->post($url)->assertNoContent();
    $this->post($url)->assertGone(); // 410 — CallbackAction::resolve() already forgot the token
});

it('rejects a tampered signature outright, before the token is even looked up', function () {
    $url = CallbackAction::register(fn () => null);

    $tampered = $url.'0'; // corrupt the signature

    $this->post($tampered)->assertForbidden();
});

it('resolve() backs off instead of double-resolving when another request is already mid-resolve() for the same token', function () {
    config(['notify.actions.lock_wait' => 1]); // default is 5s in production — kept short here so the test stays fast

    $url = CallbackAction::register(fn () => 'ran');
    $token = basename((string) parse_url($url, PHP_URL_PATH));

    // Simulates a concurrent request that got there first: it holds the
    // per-token lock without releasing it, the same way resolve() itself
    // would for the (near-instant) duration of its own get()+forget().
    $store = Cache::store(config('notify.actions.cache_store'));
    $contendingLock = $store->getStore()->lock("notify.action.lock.$token", 10);
    expect($contendingLock->get())->toBeTrue();

    // Can't acquire the lock within lock_wait — backs off cleanly (null)
    // instead of racing the other holder to read+forget the same entry.
    expect(CallbackAction::resolve($token))->toBeNull();

    $contendingLock->release();

    // Lock free again: resolves normally, exactly once, same as any other call.
    expect(CallbackAction::resolve($token))->toBeInstanceOf(Closure::class);
});

it('registers the callback route with only the signed middleware by default', function () {
    // config('notify.actions.middleware') defaults to [] — this pins that
    // ['signed', ...config(...)] doesn't silently pick up anything extra
    // when nothing was configured; the "an app-configured extra middleware
    // like 'auth' actually reaches the route" half needs the app to boot
    // with that config already set (a defineEnvironment()-level override),
    // not something a mid-test config() call can affect after routes are
    // already registered.
    expect(Route::getRoutes()->getByName('notify.action')->middleware())->toBe(['web', 'signed']);
});
