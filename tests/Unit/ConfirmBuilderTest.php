<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Salioudiabate\Notify\Facades\Notify;

it('defaults to Annuler/Confirmer and renders exactly a Cancel + Confirm pair', function () {
    Notify::confirm('Titre', 'Message')->send();

    $actions = lastQueuedPayload()['actions'];

    expect($actions)->toHaveCount(2)
        ->and($actions[0])->toMatchArray(['label' => 'Annuler', 'style' => 'secondary'])
        ->and($actions[1])->toMatchArray(['label' => 'Confirmer', 'style' => 'primary']);
});

it('config(notify.strings.confirm/cancel) overrides the default labels globally', function () {
    config(['notify.strings' => ['confirm' => 'Supprimer définitivement', 'cancel' => 'Non merci']]);

    Notify::confirm('Titre', 'Message')->send();

    $actions = lastQueuedPayload()['actions'];

    expect($actions[0]['label'])->toBe('Non merci')
        ->and($actions[1]['label'])->toBe('Supprimer définitivement');
});

it('danger() switches the variant to error and the confirm button style to danger', function () {
    Notify::confirm('Titre', 'Message')->danger()->send();

    expect(lastQueuedPayload()['variant'])->toBe('error')
        ->and(lastQueuedPayload()['actions'][1]['style'])->toBe('danger');
});

it('custom confirmText()/cancelText() relabel the two buttons', function () {
    Notify::confirm('Titre', 'Message')->confirmText('Supprimer')->cancelText('Non')->send();

    $actions = lastQueuedPayload()['actions'];

    expect($actions[0]['label'])->toBe('Non')
        ->and($actions[1]['label'])->toBe('Supprimer');
});

it('onConfirm() with a named route resolves the confirm action to that route\'s URL', function () {
    // 'clients.show' is registered once for every test, in TestCase::defineRoutes()
    Notify::confirm('Titre')->onConfirm('clients.show', ['client' => 42])->send();

    expect(lastQueuedPayload()['actions'][1]['target'])->toBe([
        'type' => 'url',
        'url' => route('clients.show', ['client' => 42]),
    ]);
});

it('onConfirm() with a Closure resolves to a signed, single-use callback URL', function () {
    Notify::confirm('Titre')->onConfirm(fn () => null)->send();

    $target = lastQueuedPayload()['actions'][1]['target'];

    expect($target['type'])->toBe('callback')
        ->and($target['url'])->toContain('/notify/actions/')
        ->and(URL::hasValidSignature(
            Request::create($target['url'])
        ))->toBeTrue();
});

it('onConfirm() with a bare, unrecognized string falls back to treating it as a literal URL outside any Livewire context', function () {
    Notify::confirm('Titre')->onConfirm('someMethodName')->send();

    expect(lastQueuedPayload()['actions'][1]['target'])->toBe([
        'type' => 'url',
        'url' => 'someMethodName',
    ]);
});
