<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Builders;

use Salioudiabate\Notify\Concerns\HasVariant;
use Salioudiabate\Notify\NotifyManager;

/**
 * Covers both the "important overlay notification" and the persistent
 * banner case — the only difference between the two is how many actions
 * you attach (button() alone vs. action() + button()). There is no separate
 * static, in-page Blade alert component today — every alert floats and goes
 * through this same payload/JS pipeline (see README roadmap).
 */
final class AlertBuilder extends NotificationBuilder
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

    public function button(string $label, string|array|null $color = null): static
    {
        return $this->action($label, null, 'primary', $color);
    }
}
