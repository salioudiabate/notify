<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Builders;

use Closure;
use Salioudiabate\Notify\Concerns\HasVariant;
use Salioudiabate\Notify\NotifyManager;

/**
 * Covers both the "important overlay notification" and the persistent
 * banner case — the only difference between the two is how many actions
 * you attach (button() alone vs. action() + button()). There is no separate
 * static, in-page Blade alert component today — every alert floats and goes
 * through this same payload/JS pipeline (see README roadmap).
 */
class AlertBuilder extends NotificationBuilder
{
    use HasVariant;

    public function __construct(NotifyManager $manager)
    {
        parent::__construct($manager);

        $this->persistent = true;
    }

    protected function type(): string
    {
        return 'alert';
    }

    /**
     * @param  string|array|null  $color  $color before $target, not after
     *                                    like ->action(), so that existing
     *                                    ->button($label, $color) calls keep
     *                                    working unchanged now that $target
     *                                    exists.
     * @param  string|Closure|null  $target  A route name, a plain URL, a
     *                                       Livewire method name, or a
     *                                       Closure — same resolution as
     *                                       ->action(). Leave null for a
     *                                       button that only dismisses the
     *                                       alert, same as before.
     */
    public function button(string $label, string|array|null $color = null, string|Closure|null $target = null): static
    {
        return $this->action($label, $target, 'primary', $color);
    }
}
