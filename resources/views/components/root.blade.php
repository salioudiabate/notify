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
    <script defer src="{{ route('notify.assets.js') }}"></script>
@endif
