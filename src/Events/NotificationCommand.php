<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * BroadcastDriver's counterpart to the session-flash/Livewire "command" queue
 * entries (clearGroup/dismiss/clearAll) — same shape, delivered over a
 * private channel instead.
 */
final class NotificationCommand implements ShouldBroadcastNow
{
    use Dispatchable;

    /** @param  array<string, mixed>  $command */
    public function __construct(
        public readonly string $channel,
        public readonly array $command,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    public function broadcastAs(): string
    {
        return 'notify.command';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->command;
    }
}
