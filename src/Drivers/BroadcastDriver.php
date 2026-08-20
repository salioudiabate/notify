<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Drivers;

use Salioudiabate\Notify\Contracts\NotificationDriver;
use Salioudiabate\Notify\Events\NotificationBroadcast;
use Salioudiabate\Notify\Events\NotificationCommand;
use Salioudiabate\Notify\Support\NotificationPayload;

/**
 * Only ever used when a builder called ->toUser()/->toChannel() explicitly —
 * unlike Session/Livewire, this driver is never auto-selected by
 * NotifyManager::resolveDriver(), since "broadcast to this specific user" is
 * always a deliberate choice, often made from outside any request at all (a
 * queued job, a console command). Requires the host app's own broadcasting
 * setup (a driver, Echo, and a routes/channels.php authorization for the
 * channel) — see README § Broadcasting to a specific user.
 */
final class BroadcastDriver implements NotificationDriver
{
    public function __construct(private readonly string $channel) {}

    public function push(NotificationPayload $payload): void
    {
        event(new NotificationBroadcast($this->channel, $payload->toArray()));
    }

    public function clearGroup(string $group): void
    {
        event(new NotificationCommand($this->channel, ['type' => 'command', 'command' => 'clearGroup', 'group' => $group]));
    }

    public function dismiss(string $id): void
    {
        event(new NotificationCommand($this->channel, ['type' => 'command', 'command' => 'dismiss', 'id' => $id]));
    }

    public function clearAll(): void
    {
        event(new NotificationCommand($this->channel, ['type' => 'command', 'command' => 'clearAll']));
    }
}
