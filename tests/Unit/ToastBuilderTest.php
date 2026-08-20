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

it('url() defaults its label to "Voir", overridable per call or globally via config(notify.strings.url)', function () {
    Notify::toast()->success()->message('ok')->url('/somewhere')->send();

    expect(lastQueuedPayload()['actions'][0])->toMatchArray(['label' => 'Voir', 'style' => 'link']);

    Notify::toast()->success()->message('ok')->url('/somewhere', 'Voir le détail')->send();

    expect(lastQueuedPayload()['actions'][0]['label'])->toBe('Voir le détail');

    config(['notify.strings' => ['url' => 'Ouvrir']]);
    Notify::toast()->success()->message('ok')->url('/somewhere')->send();

    expect(lastQueuedPayload()['actions'][0]['label'])->toBe('Ouvrir');
});

it('action()/url() attach a one-off color to that single button only', function () {
    Notify::toast()->success()->message('ok')
        ->action('Voir', '/somewhere', 'link', '#7c3aed')
        ->action('Ignorer', null, 'ghost', ['bg' => '#eee', 'fg' => '#111'])
        ->send();

    $actions = lastQueuedPayload()['actions'];

    expect($actions[0]['color'])->toBe(['bg' => '#7c3aed'])
        ->and($actions[1]['color'])->toBe(['bg' => '#eee', 'fg' => '#111']);
});
