<?php

declare(strict_types=1);

use Salioudiabate\Notify\Support\CallbackAction;

it('executes a registered closure exactly once, through its signed URL', function () {
    $calls = 0;
    $url = CallbackAction::register(function () use (&$calls) {
        $calls++;
    });

    $this->post($url)->assertNoContent();

    expect($calls)->toBe(1);
});

it('rejects a second use of the same signed URL — single-use, not just time-boxed', function () {
    $url = CallbackAction::register(fn () => null);

    $this->post($url)->assertNoContent();
    $this->post($url)->assertGone(); // 410 — CallbackAction::resolve() already forgot the token
});

it('rejects a tampered signature outright, before the token is even looked up', function () {
    $url = CallbackAction::register(fn () => null);

    $tampered = $url.'0'; // corrupt the signature

    $this->post($tampered)->assertForbidden();
});
