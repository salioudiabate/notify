<?php

declare(strict_types=1);

use Salioudiabate\Notify\Facades\Notify;

it('assigns the configured default duration per variant when none is set explicitly', function () {
    Notify::toast()->success()->message('ok')->send();

    expect(lastQueuedPayload()['duration'])->toBe(config('notify.duration.success'));
});

it('lets an explicit duration() override the configured default', function () {
    Notify::toast()->success()->message('ok')->duration(9999)->send();

    expect(lastQueuedPayload()['duration'])->toBe(9999);
});

it('never assigns an auto-dismiss duration once persistent() is set', function () {
    Notify::toast()->success()->message('ok')->persistent()->send();

    expect(lastQueuedPayload())
        ->duration->toBeNull()
        ->persistent->toBeTrue();
});

it('loading() renders as a non-dismissible, indeterminate toast with no duration', function () {
    Notify::toast()->loading()->message('...')->send();

    expect(lastQueuedPayload())
        ->variant->toBe('loading')
        ->dismissible->toBeFalse()
        ->duration->toBeNull();
});

it('error toasts default to no auto-dismiss duration, per config', function () {
    Notify::toast()->error()->message('oops')->send();

    expect(lastQueuedPayload()['duration'])->toBeNull();
});
