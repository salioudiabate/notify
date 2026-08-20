<?php

declare(strict_types=1);

use Salioudiabate\Notify\Support\Action;
use Salioudiabate\Notify\Support\NotificationPayload;

it('serializes every field, converting Action objects to arrays', function () {
    $action = new Action('Annuler', 'secondary', null);

    $payload = new NotificationPayload(
        id: 'abc123',
        type: 'toast',
        variant: 'success',
        title: 'Titre',
        message: 'Message',
        icon: 'check',
        duration: 4000,
        position: 'top-right',
        dismissible: true,
        persistent: false,
        group: 'users',
        actions: [$action],
        progress: 50,
        meta: ['status' => 'ok'],
        replace: true,
        template: 'brand',
    );

    expect($payload->toArray())->toBe([
        'id' => 'abc123',
        'type' => 'toast',
        'variant' => 'success',
        'title' => 'Titre',
        'message' => 'Message',
        'icon' => 'check',
        'duration' => 4000,
        'position' => 'top-right',
        'dismissible' => true,
        'persistent' => false,
        'group' => 'users',
        'actions' => [$action->toArray()],
        'url' => null,
        'progress' => 50,
        'meta' => ['status' => 'ok'],
        'replace' => true,
        'template' => 'brand',
    ]);

    expect($payload->jsonSerialize())->toBe($payload->toArray());
});

it('defaults to a neutral, non-persistent, dismissible toast', function () {
    $payload = new NotificationPayload(id: 'x', type: 'toast');

    expect($payload->variant)->toBe('neutral')
        ->and($payload->dismissible)->toBeTrue()
        ->and($payload->persistent)->toBeFalse()
        ->and($payload->replace)->toBeFalse()
        ->and($payload->template)->toBeNull();
});
