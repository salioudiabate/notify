<?php

declare(strict_types=1);

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Salioudiabate\Notify\Drivers\BroadcastDriver;
use Salioudiabate\Notify\Events\NotificationBroadcast;
use Salioudiabate\Notify\Events\NotificationCommand;
use Salioudiabate\Notify\Facades\Notify;

it('push() fires a NotificationBroadcast on the given private channel', function () {
    Event::fake([NotificationBroadcast::class]);

    Notify::toast()->asSuccess()->message('Export prêt.')->toUser(42)->send();

    Event::assertDispatched(NotificationBroadcast::class, function (NotificationBroadcast $event) {
        return $event->channel === 'notify.42'
            && $event->notification['message'] === 'Export prêt.'
            && $event->notification['variant'] === 'success';
    });
});

it('toUser() reads getKey() off an Eloquent-like object instead of stringifying it', function () {
    Event::fake([NotificationBroadcast::class]);

    $user = new class
    {
        public function getKey()
        {
            return 'user-uuid-123';
        }
    };

    Notify::toast()->asSuccess()->message('Bienvenue.')->toUser($user)->send();

    Event::assertDispatched(NotificationBroadcast::class, fn (NotificationBroadcast $e) => $e->channel === 'notify.user-uuid-123');
});

it('toChannel() uses the channel name verbatim, with no notify. prefix added', function () {
    Event::fake([NotificationBroadcast::class]);

    Notify::toast()->asSuccess()->message('...')->toChannel('team.acme.alerts')->send();

    Event::assertDispatched(NotificationBroadcast::class, fn (NotificationBroadcast $e) => $e->channel === 'team.acme.alerts');
});

it('a broadcast-bound PendingNotification keeps routing success()/error()/dismiss() through the same channel', function () {
    Event::fake([NotificationBroadcast::class, NotificationCommand::class]);

    // Notify::loading()/success()/... one-liners send() immediately, with no
    // window to call ->toUser() first — the full builder form is what
    // ->toUser()/->toChannel() need, same as any other builder-only feature
    // (->group(), ->template(), ...) the one-liners can't reach.
    $pending = Notify::toast()->loading()->message('Traitement…')->toUser(7)->send();
    $pending->success('Terminé.');

    Event::assertDispatched(NotificationBroadcast::class, function (NotificationBroadcast $e) use ($pending) {
        return $e->channel === 'notify.7'
            && $e->notification['id'] === $pending->id()
            && $e->notification['variant'] === 'success';
    });

    $pending->dismiss();

    Event::assertDispatched(NotificationCommand::class, function (NotificationCommand $e) use ($pending) {
        return $e->channel === 'notify.7'
            && $e->command === ['type' => 'command', 'command' => 'dismiss', 'id' => $pending->id()];
    });
});

it('does not touch the session queue at all when broadcasting — the two are mutually exclusive per call', function () {
    Event::fake([NotificationBroadcast::class]);

    Notify::toast()->asSuccess()->message('...')->toUser(1)->send();

    expect(session('notify.queue', []))->toBe([]);
});

it('BroadcastDriver::clearGroup()/clearAll() fire the matching NotificationCommand', function () {
    Event::fake([NotificationCommand::class]);

    $driver = new BroadcastDriver('notify.9');
    $driver->clearGroup('imports');
    $driver->clearAll();

    Event::assertDispatched(NotificationCommand::class, fn (NotificationCommand $e) => $e->command === ['type' => 'command', 'command' => 'clearGroup', 'group' => 'imports']);
    Event::assertDispatched(NotificationCommand::class, fn (NotificationCommand $e) => $e->command === ['type' => 'command', 'command' => 'clearAll']);
});

it('NotificationBroadcast/NotificationCommand broadcast on a private channel under the expected event names', function () {
    $broadcast = new NotificationBroadcast('notify.1', ['id' => 'x']);
    expect($broadcast->broadcastAs())->toBe('notify.push')
        ->and($broadcast->broadcastOn())->toHaveCount(1)
        ->and($broadcast->broadcastOn()[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($broadcast->broadcastWith())->toBe(['notification' => ['id' => 'x']]);

    $command = new NotificationCommand('notify.1', ['type' => 'command', 'command' => 'clearAll']);
    expect($command->broadcastAs())->toBe('notify.command')
        ->and($command->broadcastWith())->toBe(['type' => 'command', 'command' => 'clearAll']);
});
