<?php

declare(strict_types=1);

use Salioudiabate\Notify\Facades\Notify;

it('always flags replace: true, so the front-end swaps the card in place', function () {
    Notify::update('some-id')->success('Terminé.')->send();

    expect(lastQueuedPayload())
        ->id->toBe('some-id')
        ->replace->toBeTrue();
});

it('success()/error()/warning()/info() take an optional message/title directly, unlike the same methods on other builders', function () {
    Notify::update('x')->error('Ça a échoué.', 'Oups')->send();

    expect(lastQueuedPayload())
        ->variant->toBe('error')
        ->title->toBe('Oups')
        ->message->toBe('Ça a échoué.');
});

it('leaves title/message untouched when the optional arguments are omitted', function () {
    Notify::update('x')->title('Kept')->success()->send();

    expect(lastQueuedPayload()['title'])->toBe('Kept');
});

it('switches type to "progress" only once progress() is called, defaulting to "toast" otherwise', function () {
    Notify::update('x')->success('done')->send();
    expect(lastQueuedPayload()['type'])->toBe('toast');

    Notify::update('y')->progress(80)->send();
    expect(lastQueuedPayload())
        ->type->toBe('progress')
        ->progress->toBe(80);
});
