<?php

declare(strict_types=1);

use Salioudiabate\Notify\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/** The most recently ->send()/->show()'n payload, as flashed by SessionDriver (the default outside any Livewire component). */
function lastQueuedPayload(): array
{
    return collect(session('notify.queue'))->last();
}
