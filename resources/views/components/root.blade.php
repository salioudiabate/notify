@php
    /**
     * Placed once in the main layout. Three producers feed the same store:
     * this component reads whatever SessionDriver flashed (plain Laravel),
     * notify.js listens for Livewire's `notify:push` browser event
     * (LivewireDriver), and window.Notify.* can be called directly from any
     * inline script with no backend round-trip at all.
     */
    $queue = session('notify.queue', []);

    if (config('notify.session_bridge.enabled', true)) {
        foreach (config('notify.session_bridge.keys', []) as $sessionKey => $variant) {
            if (session()->has($sessionKey)) {
                $queue[] = [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'type' => 'toast',
                    'variant' => $variant,
                    'title' => null,
                    'message' => session($sessionKey),
                    'icon' => null,
                    'duration' => config("notify.duration.$variant"),
                    'position' => config('notify.position', 'top-right'),
                    'dismissible' => true,
                    'persistent' => false,
                    'group' => null,
                    'actions' => [],
                    'url' => null,
                    'progress' => null,
                    'meta' => [],
                    'replace' => false,
                ];
            }
        }
    }

    $showValidationAlert = config('notify.validation.enabled', true)
        && $errors->any()
        && ! request()->hasHeader('X-Livewire');

    if ($showValidationAlert) {
        $queue[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'alert',
            'variant' => 'error',
            'title' => config('notify.validation.message'),
            'message' => null,
            'icon' => null,
            'duration' => null,
            'position' => config('notify.position', 'top-right'),
            'dismissible' => true,
            'persistent' => true,
            'group' => null,
            'actions' => [],
            'url' => null,
            'progress' => null,
            'meta' => ['errors' => $errors->all()],
            'replace' => false,
        ];
    }

    // config('notify.strings') is snake_case, like every other key in that
    // file — notify.js's own STRINGS object uses the camelCase spelling of
    // the same keys (a plain JS object, not a Laravel config array), so this
    // translates between the two rather than passing the array through as-is.
    $stringsConfig = config('notify.strings', []);
    $notifyStrings = [];
    foreach ([
        'close' => 'close',
        'more_singular' => 'moreSingular',
        'more_plural' => 'morePlural',
        'esc_key' => 'escKey',
        'esc_hint' => 'escHint',
        'confirm' => 'confirm',
        'cancel' => 'cancel',
        'url' => 'url',
        'action_success' => 'actionSuccess',
        'action_error' => 'actionError',
    ] as $configKey => $jsKey) {
        if (array_key_exists($configKey, $stringsConfig)) {
            $notifyStrings[$jsKey] = $stringsConfig[$configKey];
        }
    }

    // Resolved once per page load: a closure gets the request, a plain
    // string is used verbatim, and leaving it null auto-resolves to this
    // visitor's own channel when logged in — skipped for a guest, and
    // skipped entirely unless broadcasting is explicitly turned on (see
    // config('notify.broadcast') — disabled by default, since a plain app
    // with no broadcasting driver at all has no websocket to reach).
    $broadcastChannel = null;

    if (config('notify.broadcast.enabled', false)) {
        $channelConfig = config('notify.broadcast.channel');

        $broadcastChannel = match (true) {
            $channelConfig instanceof \Closure => $channelConfig(request()),
            is_string($channelConfig) => $channelConfig,
            \Illuminate\Support\Facades\Auth::check() => 'notify.'.\Illuminate\Support\Facades\Auth::id(),
            default => null,
        };
    }

    // built up-front, as a plain variable: @json() with a nested multi-call
    // array expression inline can truncate mid-expression during Blade
    // compilation — a bare variable reference is unambiguous.
    $notifyJsConfig = [
        'position' => config('notify.position', 'top-right'),
        'maxVisible' => config('notify.max_visible', 4),
        'dismissible' => config('notify.dismissible', true),
        'animations' => config('notify.animations', true),
        'theme' => config('notify.theme'),
        'colorScheme' => config('notify.color_scheme'),
        'icons' => config('notify.icons', []),
        'strings' => $notifyStrings,
        'buttonColors' => config('notify.button_colors', []),
        'broadcastChannel' => $broadcastChannel,
    ];
    $notifyJsQueue = array_values($queue);
@endphp

{{-- Real mount point, not decorative: every toast/alert/dialog/progress card
     is appended inside this element (see notify.js's mountRoot()), which is
     what makes its font-family/color rules in notify.css actually apply. --}}
<div id="notify-root"></div>

<script>
    window.__NOTIFY_CONFIG__ = @json($notifyJsConfig);
    window.__NOTIFY_QUEUE__ = @json($notifyJsQueue);
</script>

@if (config('notify.assets.serve', true) && \Illuminate\Support\Facades\Route::has('notify.assets.css'))
    <link rel="stylesheet" href="{{ route('notify.assets.css') }}">
@endif

@if (config('notify.assets.serve', true) && \Illuminate\Support\Facades\Route::has('notify.assets.js'))
    {{-- data-navigate-once: wire:navigate re-runs body scripts on every page change, which would boot
         the runtime again and subscribe to notify:push once more per navigation (one dialog/toast per boot). --}}
    <script defer src="{{ route('notify.assets.js') }}" data-navigate-once></script>
@endif
