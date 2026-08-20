<?php

declare(strict_types=1);

use Salioudiabate\Notify\Facades\Notify;

it('is persistent by default, unlike a toast', function () {
    Notify::alert()->warning()->title('Attention')->message('...')->send();

    expect(lastQueuedPayload())
        ->type->toBe('alert')
        ->persistent->toBeTrue()
        ->duration->toBeNull();
});

it('button() adds a single primary, target-less action', function () {
    Notify::alert()->warning()->message('...')->button('Compris')->send();

    expect(lastQueuedPayload()['actions'])->toBe([
        ['label' => 'Compris', 'style' => 'primary', 'target' => null, 'closesDialog' => true, 'color' => null],
    ]);
});

it('button() accepts a one-off color override, same as action()', function () {
    Notify::alert()->warning()->message('...')->button('Compris', '#7c3aed')->send();

    expect(lastQueuedPayload()['actions'][0]['color'])->toBe(['bg' => '#7c3aed']);
});

it('combines button() with a regular action() for a persistent-banner-style pair', function () {
    Notify::alert()->info()->message('...')
        ->action('En savoir plus', 'https://example.com', 'link')
        ->button('Fermer')
        ->send();

    $actions = lastQueuedPayload()['actions'];

    expect($actions)->toHaveCount(2)
        ->and($actions[0]['target'])->toBe(['type' => 'url', 'url' => 'https://example.com'])
        ->and($actions[1]['label'])->toBe('Fermer');
});
