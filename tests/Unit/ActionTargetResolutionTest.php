<?php

declare(strict_types=1);

use Livewire\Component;
use Salioudiabate\Notify\Facades\Notify;
use Salioudiabate\Notify\NotifyManager;

it('leaves a null target as null — a plain dismiss action', function () {
    Notify::toast()->success()->action('OK')->send();

    expect(lastQueuedPayload()['actions'][0]['target'])->toBeNull();
});

it('treats an absolute URL or a root-relative path as a literal URL, verbatim', function () {
    Notify::toast()->success()->action('Voir', 'https://example.com')->send();
    expect(lastQueuedPayload()['actions'][0]['target'])->toBe(['type' => 'url', 'url' => 'https://example.com']);

    Notify::toast()->success()->action('Voir', '/relative/path')->send();
    expect(lastQueuedPayload()['actions'][0]['target'])->toBe(['type' => 'url', 'url' => '/relative/path']);
});

it('resolves a matching named route before ever considering a Livewire method call', function () {
    // 'clients.index' is registered once for every test, in TestCase::defineRoutes()
    Notify::toast()->success()->action('Voir', 'clients.index')->send();

    expect(lastQueuedPayload()['actions'][0]['target'])->toBe([
        'type' => 'url',
        'url' => route('clients.index'),
    ]);
});

it('resolves a bare method name to the active Livewire component once one is registered', function () {
    $component = new class extends Component
    {
        public array $dispatched = [];

        public function dispatch($event, ...$params)
        {
            $this->dispatched[] = ['event' => $event, 'params' => $params];

            return parent::dispatch($event, ...$params);
        }
    };
    $component->setId('fake-component-id');

    app(NotifyManager::class)->registerLivewireComponent($component);

    Notify::toast()->success()->action('Restaurer', 'restore')->send();

    expect($component->dispatched)->toHaveCount(1);

    $notification = $component->dispatched[0]['params']['notification'];

    expect($notification['actions'][0]['target'])->toBe([
        'type' => 'livewire',
        'component' => 'fake-component-id',
        'method' => 'restore',
        'params' => [],
    ]);
});

it('falls back to treating an unrecognized string as a literal URL when no component is active', function () {
    Notify::toast()->success()->action('Go', 'notARouteOrMethod')->send();

    expect(lastQueuedPayload()['actions'][0]['target'])->toBe([
        'type' => 'url',
        'url' => 'notARouteOrMethod',
    ]);
});
