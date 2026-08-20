<?php

declare(strict_types=1);

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
    | Color scheme
    |--------------------------------------------------------------------------
    |
    | null (default) follows the visitor's OS/browser preference
    | (prefers-color-scheme), light/dark either way. Set to 'light' or
    | 'dark' to force it site-wide regardless of the OS setting — or leave
    | this alone and call Notify.setColorScheme('dark'|'light'|'system')
    | client-side (e.g. from your own app's dark-mode toggle); that call
    | persists across reloads via localStorage and takes precedence.
    |
    */
    'color_scheme' => null,

    /*
    |--------------------------------------------------------------------------
    | Icons
    |--------------------------------------------------------------------------
    |
    | Overrides the built-in SVG for any named icon (success, error, warning,
    | info, neutral, trash, close) or registers new ones, globally, without an
    | inline <script> — same registry ->icon('name') looks up and the same
    | effect as calling Notify.registerIcon() in JS. A one-off icon doesn't
    | need registering at all: pass raw markup straight to a single
    | notification with ->icon('<svg>...</svg>').
    |
    */
    'icons' => [
        // 'success' => '<svg ...></svg>',
    ],

    /*
    |--------------------------------------------------------------------------
    | Button colors
    |--------------------------------------------------------------------------
    |
    | Overrides the color of every button rendered with a given style
    | (primary/secondary/danger/ghost/link — the same names ->action()'s third
    | argument accepts), globally, without writing any CSS. Same effect as
    | Notify.setButtonColors() client-side. A single button can still go its
    | own way regardless of this: pass a color straight to that one call
    | (->action($label, $target, $style, '#7c3aed'), ->confirmColor(), ...).
    |
    | Each style accepts 'bg' (required to have any effect), plus optional
    | 'fg' (text) and 'border'. Leave a style out entirely to keep its
    | built-in look.
    |
    */
    'button_colors' => [
        // 'primary' => ['bg' => '#7c3aed', 'fg' => '#ffffff'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Text / copy
    |--------------------------------------------------------------------------
    |
    | Every hardcoded piece of UI chrome text — never a payload's own free-form
    | title/message/action labels, which are already fully customizable per
    | call — lives here, so it can be translated or reworded globally without
    | touching the JS. Same keys as Notify.setStrings() client-side; anything
    | left unset keeps its built-in French default.
    |
    */
    'strings' => [
        // 'close' => 'Close',
        // 'moreSingular' => 'more notification',
        // 'morePlural' => 'more notifications',
        // 'escKey' => 'Esc',
        // 'escHint' => 'to close',
        // 'confirm' => 'Confirm',
        // 'cancel' => 'Cancel',
        // 'url' => 'View',
        // 'actionSuccess' => 'Done.',
        // 'actionError' => 'Something went wrong.',
    ],

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
