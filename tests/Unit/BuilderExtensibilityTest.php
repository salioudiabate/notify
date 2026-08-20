<?php

declare(strict_types=1);

use Salioudiabate\Notify\Builders\ToastBuilder;
use Salioudiabate\Notify\Facades\Notify;
use Salioudiabate\Notify\NotifyManager;

it('lets a custom method be registered on a builder via Macroable, without subclassing', function () {
    ToastBuilder::macro('forTenant', function (string $tenant) {
        /** @var ToastBuilder $this */
        return $this->meta(['tenant' => $tenant]);
    });

    Notify::toast()->success()->message('...')->forTenant('acme')->send();

    expect(lastQueuedPayload()['meta'])->toBe(['tenant' => 'acme']);

    ToastBuilder::flushMacros();
});

it('lets a builder be subclassed, since none of them are final anymore', function () {
    $subclass = new class(app(NotifyManager::class)) extends ToastBuilder
    {
        public function loud(): static
        {
            return $this->title('!!!');
        }
    };

    $subclass->success()->loud()->message('...')->send();

    expect(lastQueuedPayload()['title'])->toBe('!!!');
});
