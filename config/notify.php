<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default driver
    |--------------------------------------------------------------------------
    |
    | "session" always works, in any Laravel app, with or without Livewire —
    | it flashes the notification and the root Blade component renders it on
    | the next response. It is upgraded automatically, per request, to a
    | real-time Livewire dispatch as soon as a component using the
    | InteractsWithNotifications trait has booted on the page.
    |
    */
    'default' => 'session',

    /*
    |--------------------------------------------------------------------------
    | Display defaults
    |--------------------------------------------------------------------------
    */
    'position' => 'top-right',

    'duration' => [
        'success' => 4000,
        'info' => 4000,
        'warning' => 6000,
        'error' => null, // null = stays until dismissed
        'loading' => null,
    ],

    'max_visible' => 4,
    'dismissible' => true,
    'animations' => true,

    /*
    |--------------------------------------------------------------------------
    | Template
    |--------------------------------------------------------------------------
    |
    | The built-in look ("default") is one of possibly several templates —
    | register your own front-end template with Notify.registerTemplate()
    | (see README § Custom templates) and set its name here to reskin every
    | notification globally, or call ->template('yours') on a single
    | builder to override it just for that one.
    |
    */
    'theme' => null,

    /*
    |--------------------------------------------------------------------------
    | Session flash bridge
    |--------------------------------------------------------------------------
    |
    | Existing `redirect()->with('success', '...')` calls already used across
    | most Laravel starter kits are picked up automatically and rendered as
    | Notify toasts — no code change required in projects that don't (yet)
    | want to adopt the Notify:: API directly.
    |
    */
    'session_bridge' => [
        'enabled' => true,
        'keys' => [
            'success' => 'success',
            'status' => 'success',
            'error' => 'error',
            'warning' => 'warning',
            'info' => 'info',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'enabled' => true,
        'message' => 'Veuillez corriger les erreurs du formulaire.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exceptions
    |--------------------------------------------------------------------------
    */
    'errors' => [
        'production' => 'Une erreur est survenue.',
        'local' => true, // true = show $e->getMessage() locally, string = override
    ],

    /*
    |--------------------------------------------------------------------------
    | Confirm / server actions
    |--------------------------------------------------------------------------
    |
    | Lets Notify::confirm()->onConfirm(fn () => ...) work even outside a
    | Livewire component: the closure is signed and cached server-side for a
    | short, single-use window, and executed through the package's own
    | signed route when the user confirms. Disable if you never pass raw
    | closures (routes/Livewire methods don't need this at all).
    |
    */
    'actions' => [
        'enabled' => true,
        'ttl' => 300, // seconds
        'cache_store' => null, // null = default cache store
    ],

    /*
    |--------------------------------------------------------------------------
    | Assets
    |--------------------------------------------------------------------------
    |
    | Served directly from the package by default (zero build-step setup).
    | Run `php artisan vendor:publish --tag=notify-assets` to self-host them
    | through your own asset pipeline instead, then set `serve` to false.
    |
    */
    'assets' => [
        'serve' => true,
    ],
];
