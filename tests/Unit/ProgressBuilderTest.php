<?php

declare(strict_types=1);

use Salioudiabate\Notify\Facades\Notify;

it('is persistent by default and carries the percent and status', function () {
    Notify::progress()->title('Import')->progress(45)->status('Lot 4 sur 7')->send();

    expect(lastQueuedPayload())
        ->type->toBe('progress')
        ->persistent->toBeTrue()
        ->progress->toBe(45)
        ->and(lastQueuedPayload()['meta']['status'])->toBe('Lot 4 sur 7');
});

it('accepts success()/error()/warning()/info() like Toast/Alert/Dialog, via the same HasVariant trait', function () {
    Notify::progress()->error()->progress(100)->status('Échec')->send();

    expect(lastQueuedPayload())
        ->type->toBe('progress')
        ->variant->toBe('error');
});
