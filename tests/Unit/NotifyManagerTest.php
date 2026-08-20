<?php

declare(strict_types=1);

use Salioudiabate\Notify\Facades\Notify;

it('shows the real exception message under a local-like environment by default', function () {
    // Testbench's app environment is "testing", which config('notify.errors.local')
    // treats the same as "local" by default (true).
    Notify::exception(new RuntimeException('Disque plein.'));

    expect(lastQueuedPayload()['message'])->toBe('Disque plein.');
});

it('masks the message behind the production string once notify.errors.local is disabled', function () {
    config(['notify.errors.local' => false]);

    Notify::exception(new RuntimeException('Détail sensible.'));

    expect(lastQueuedPayload()['message'])->toBe(config('notify.errors.production'));
});

it('lets notify.errors.local be a custom override string instead of true/false', function () {
    config(['notify.errors.local' => 'Oups, réessayez.']);

    Notify::exception(new RuntimeException('Détail sensible.'));

    expect(lastQueuedPayload()['message'])->toBe('Oups, réessayez.');
});

it('loading() returns a pending notification whose success()/error() replace it in place', function () {
    $pending = Notify::loading('Traitement…');

    expect(lastQueuedPayload())
        ->variant->toBe('loading')
        ->id->toBe($pending->id());

    $pending->success('Terminé.');

    expect(lastQueuedPayload())
        ->id->toBe($pending->id())
        ->replace->toBeTrue()
        ->variant->toBe('success')
        ->message->toBe('Terminé.');
});

it('update($id)->progress() reaches the front-end as a progress-type replace payload', function () {
    Notify::update('import-1')->progress(80)->send();

    expect(lastQueuedPayload())
        ->id->toBe('import-1')
        ->type->toBe('progress')
        ->progress->toBe(80)
        ->replace->toBeTrue();
});

it('a PendingNotification can dismiss() the notification it refers to', function () {
    $pending = Notify::loading('Traitement…');

    $pending->dismiss();

    expect(session('notify.queue'))->toHaveCount(2)
        ->and(session('notify.queue')[1])->toBe([
            'type' => 'command', 'command' => 'dismiss', 'id' => $pending->id(),
        ]);
});

it('alertSuccess()/alertError()/alertWarning()/alertInfo() are one-liners for alert(), mirroring the toast shorthands', function () {
    Notify::alertSuccess('Sauvegardé.', 'Fait');

    expect(lastQueuedPayload())
        ->type->toBe('alert')
        ->variant->toBe('success')
        ->title->toBe('Fait')
        ->message->toBe('Sauvegardé.')
        ->persistent->toBeTrue();

    Notify::alertError('Erreur.');
    expect(lastQueuedPayload())->type->toBe('alert')->variant->toBe('error');

    Notify::alertWarning('Attention.');
    expect(lastQueuedPayload())->type->toBe('alert')->variant->toBe('warning');

    Notify::alertInfo('Info.');
    expect(lastQueuedPayload())->type->toBe('alert')->variant->toBe('info');
});
