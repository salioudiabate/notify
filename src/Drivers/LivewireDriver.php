<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Drivers;

use Livewire\Component;
use Salioudiabate\Notify\Contracts\NotificationDriver;
use Salioudiabate\Notify\Support\NotificationPayload;

/**
 * Only ever instantiated when a component using InteractsWithNotifications
 * has booted on the current page (see NotifyManager::registerLivewireComponent),
 * so Livewire is guaranteed to be installed whenever this class is loaded.
 *
 * Uses only Component::dispatch(), Livewire's public browser-event API —
 * stable and identical across Livewire 3.x and 4.x — so nothing here reaches
 * into Livewire internals that could change between versions.
 */
final class LivewireDriver implements NotificationDriver
{
    public function __construct(protected Component $component) {}

    public function push(NotificationPayload $payload): void
    {
        $this->component->dispatch('notify:push', notification: $payload->toArray());
    }

    public function clearGroup(string $group): void
    {
        $this->component->dispatch('notify:clear-group', group: $group);
    }
}
