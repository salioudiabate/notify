<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Builders;

use Salioudiabate\Notify\Concerns\HasVariant;
use Salioudiabate\Notify\Support\NotificationPayload;

/**
 * Free-form modal: any number of buttons via the inherited action(), unlike
 * ConfirmBuilder's fixed Cancel+Confirm pair. centered() switches to the
 * celebratory layout (large centered icon/title/body, single full-width
 * button) used for a "Payment successful"-style dialog.
 */
class DialogBuilder extends NotificationBuilder
{
    use HasVariant;

    private bool $centered = false;

    protected function type(): string
    {
        return 'dialog';
    }

    public function centered(bool $centered = true): static
    {
        $this->centered = $centered;

        return $this;
    }

    protected function toPayload(): NotificationPayload
    {
        $payload = parent::toPayload();
        $payload->meta = [...$payload->meta, 'centered' => $this->centered];

        return $payload;
    }
}
