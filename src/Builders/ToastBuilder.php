<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Builders;

use Salioudiabate\Notify\Concerns\HasVariant;
use Salioudiabate\Notify\Support\NotificationPayload;

final class ToastBuilder extends NotificationBuilder
{
    use HasVariant;

    protected function type(): string
    {
        return 'toast';
    }

    /** Indeterminate spinner instead of a semantic icon; stays until update()'d to success()/error(). */
    public function loading(): static
    {
        $this->variant = 'loading';
        $this->dismissible = false;

        return $this;
    }

    protected function toPayload(): NotificationPayload
    {
        if ($this->duration === null && ! $this->persistent && $this->variant !== 'loading') {
            $this->duration = config("notify.duration.{$this->variant}");
        }

        return parent::toPayload();
    }
}
