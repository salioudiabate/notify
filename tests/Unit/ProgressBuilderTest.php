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
