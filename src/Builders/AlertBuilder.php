<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Builders;

use Salioudiabate\Notify\Concerns\HasVariant;
use Salioudiabate\Notify\NotifyManager;

/**
 * Covers both "important overlay notification" (the brief's Notify::alert())
 * and the persistent banner case — the only difference between the two is
 * how many actions you attach. Inline, in-page alerts (the flat tinted
 * surface living inside a Blade view, no floating/dismiss lifecycle) are a
 * plain <x-notify::alert> Blade component instead, not part of this pipeline.
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

    public function button(string $label): static
    {
        return $this->action($label, null, 'primary');
    }
}
