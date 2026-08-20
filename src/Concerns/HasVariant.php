<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Concerns;

/**
 * Shared by every builder that has a semantic color state (toast, alert,
 * dialog, progress). Confirm is deliberately excluded — it only has a
 * neutral/danger() distinction, handled in ConfirmBuilder itself.
 */
trait HasVariant
{
    public function success(): static
    {
        $this->variant = 'success';

        return $this;
    }

    public function error(): static
    {
        $this->variant = 'error';

        return $this;
    }

    public function warning(): static
    {
        $this->variant = 'warning';

        return $this;
    }

    public function info(): static
    {
        $this->variant = 'info';

        return $this;
    }
}
