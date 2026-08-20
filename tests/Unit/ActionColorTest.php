<?php

declare(strict_types=1);

use Salioudiabate\Notify\Support\Action;

it('leaves color null when none is given', function () {
    expect((new Action('Label'))->toArray()['color'])->toBeNull();
});

it('normalizes a plain color string to a background-only array', function () {
    $action = new Action('Label', 'primary', null, color: '#7c3aed');

    expect($action->toArray()['color'])->toBe(['bg' => '#7c3aed']);
});

it('passes a full bg/fg/border color array through unchanged', function () {
    $color = ['bg' => '#7c3aed', 'fg' => '#ffffff', 'border' => '#5b21b6'];
    $action = new Action('Label', 'primary', null, color: $color);

    expect($action->toArray()['color'])->toBe($color);
});
