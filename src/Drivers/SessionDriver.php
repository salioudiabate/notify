<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Drivers;

use Illuminate\Contracts\Session\Session;
use Salioudiabate\Notify\Contracts\NotificationDriver;
use Salioudiabate\Notify\Support\NotificationPayload;

/**
 * Works in every Laravel app, Livewire or not: the payload rides the flash
 * bag to the next response, and the <x-notify::root /> Blade component
 * (placed once in the layout) hands it to the front-end store on load. This
 * is what makes a controller redirect, a form request, a queued job, or a
 * plain Blade page all "just work" with Notify:: — no Livewire required.
 */
final class SessionDriver implements NotificationDriver
{
    public function __construct(protected Session $session)
    {
    }

    public function push(NotificationPayload $payload): void
    {
        $queue = $this->session->get('notify.queue', []);
        $queue[] = $payload->toArray();

        // re-flash on every push within the same request so multiple
        // Notify:: calls before a single redirect all survive together
        $this->session->flash('notify.queue', $queue);
    }

    public function clearGroup(string $group): void
    {
        $queue = $this->session->get('notify.queue', []);
        $queue[] = ['type' => 'command', 'command' => 'clearGroup', 'group' => $group];
        $this->session->flash('notify.queue', $queue);
    }
}
