<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Concerns;

/**
 * Shared by every builder that has a semantic color state (toast, alert,
 * dialog, progress). Confirm is deliberately excluded — it only has a
 * neutral/danger() distinction, handled in ConfirmBuilder itself.
 *
 * Named asSuccess()/asError()/asWarning()/asInfo() — not success()/error()/...
 * — specifically to never collide with the unrelated, terminal, message-taking
 * methods of the same short names on NotifyManager (Notify::success($msg)),
 * PendingNotification, and UpdateBuilder. Those send/replace a notification
 * right away; these only flip this builder's color and return $this.
 */
trait HasVariant
{
    public function asSuccess(): static
    {
        $this->variant = 'success';

        return $this;
    }

    public function asError(): static
    {
        $this->variant = 'error';

        return $this;
    }

    public function asWarning(): static
    {
        $this->variant = 'warning';

        return $this;
    }

    public function asInfo(): static
    {
        $this->variant = 'info';

        return $this;
    }
}
