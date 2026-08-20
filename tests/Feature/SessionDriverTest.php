<?php

declare(strict_types=1);

use Salioudiabate\Notify\Drivers\SessionDriver;
use Salioudiabate\Notify\Facades\Notify;

it('accumulates multiple pushes within the same request into one flashed queue', function () {
    Notify::success('Un.');
    Notify::error('Deux.');

    $queue = session('notify.queue');

    expect($queue)->toHaveCount(2)
        ->and($queue[0]['message'])->toBe('Un.')
        ->and($queue[1]['message'])->toBe('Deux.');
});

it('clearGroup() queues a command the front-end store recognizes, not a regular notification', function () {
    app(SessionDriver::class)->clearGroup('users');

    expect(session('notify.queue'))->toBe([
        ['type' => 'command', 'command' => 'clearGroup', 'group' => 'users'],
    ]);
});

it('dismiss() queues a command targeting one specific notification id', function () {
    app(SessionDriver::class)->dismiss('toast-42');

    expect(session('notify.queue'))->toBe([
        ['type' => 'command', 'command' => 'dismiss', 'id' => 'toast-42'],
    ]);
});

it('clearAll() queues a command with no extra arguments', function () {
    app(SessionDriver::class)->clearAll();

    expect(session('notify.queue'))->toBe([
        ['type' => 'command', 'command' => 'clearAll'],
    ]);
});

it('Notify::dismiss()/clearAll() go through the same driver resolution as every other call', function () {
    Notify::dismiss('toast-42');
    Notify::clearAll();

    expect(session('notify.queue'))->toBe([
        ['type' => 'command', 'command' => 'dismiss', 'id' => 'toast-42'],
        ['type' => 'command', 'command' => 'clearAll'],
    ]);
});
