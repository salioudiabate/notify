<?php

declare(strict_types=1);

use Salioudiabate\Notify\Facades\Notify;

it('is not centered by default', function () {
    Notify::dialog()->title('t')->send();

    expect(lastQueuedPayload()['meta']['centered'])->toBeFalse();
});

it('centered() flags the payload for the celebratory single-button layout', function () {
    Notify::dialog()->asSuccess()->centered()->title('Paiement réussi')->action('Continuer')->send();

    expect(lastQueuedPayload())
        ->meta->toMatchArray(['centered' => true])
        ->and(lastQueuedPayload()['actions'])->toHaveCount(1);
});

it('accepts any number of action() buttons, unlike ConfirmBuilder\'s fixed pair', function () {
    Notify::dialog()->asInfo()->title('t')
        ->action('Un')
        ->action('Deux')
        ->action('Trois')
        ->send();

    expect(lastQueuedPayload()['actions'])->toHaveCount(3);
});
