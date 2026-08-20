<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by BroadcastDriver::push() — the counterpart of the session flash /
 * Livewire dispatch producers, for a notification aimed at a specific user
 * from outside the current request entirely (a queued job, a console
 * command, ...). ShouldBroadcastNow (not the queued ShouldBroadcast): a
 * notification package should feel instant, matching every other producer,
 * not wait on a queue worker.
 *
 * Only ever delivered if that user currently has a page open with
 * <x-notify::root /> and Laravel Echo connected — there's no persistence for
 * an offline recipient (see README roadmap: database-backed notifications).
 */
final class NotificationBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;

    /** @param  array<string, mixed>  $notification  A NotificationPayload, already ->toArray()'d. */
    public function __construct(
        public readonly string $channel,
        public readonly array $notification,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    public function broadcastAs(): string
    {
        return 'notify.push';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['notification' => $this->notification];
    }
}
