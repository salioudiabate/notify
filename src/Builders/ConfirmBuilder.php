<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Builders;

use Closure;
use Salioudiabate\Notify\NotifyManager;
use Salioudiabate\Notify\Support\Action;
use Salioudiabate\Notify\Support\NotificationPayload;

/**
 * Always renders exactly Cancel + one primary action, matching the design
 * system's dialog footer — free-form button counts belong to DialogBuilder.
 */
class ConfirmBuilder extends NotificationBuilder
{
    private string $confirmText;

    private string $cancelText;

    private bool $danger = false;

    private string|array|null $confirmColor = null;

    private string|array|null $cancelColor = null;

    private string|Closure|null $onConfirm = null;

    private array $onConfirmParams = [];

    public function __construct(NotifyManager $manager)
    {
        parent::__construct($manager);

        // Default labels come from config('notify.strings') so a translated
        // app only has to set them once — ->confirmText()/->cancelText() still
        // override per call, same as before.
        $this->confirmText = (string) config('notify.strings.confirm', 'Confirmer');
        $this->cancelText = (string) config('notify.strings.cancel', 'Annuler');
    }

    protected function type(): string
    {
        return 'confirm';
    }

    public function confirmText(string $label): static
    {
        $this->confirmText = $label;

        return $this;
    }

    public function cancelText(string $label): static
    {
        $this->cancelText = $label;

        return $this;
    }

    public function danger(bool $danger = true): static
    {
        $this->danger = $danger;
        $this->variant = $danger ? 'error' : 'neutral';

        return $this;
    }

    /** One-off override for this dialog's confirm button only — a color
     *  string (background), or ['bg' => ..., 'fg' => ..., 'border' => ...].
     *  Leave unset to use config('notify.button_colors')/Notify.setButtonColors(). */
    public function confirmColor(string|array $color): static
    {
        $this->confirmColor = $color;

        return $this;
    }

    /** Same as confirmColor(), for the cancel button. */
    public function cancelColor(string|array $color): static
    {
        $this->cancelColor = $color;

        return $this;
    }

    /**
     * @param  string|Closure  $action  A route name, a plain URL, a Livewire
     *                                  method name (resolved automatically
     *                                  when built via $this->confirm() inside
     *                                  a component using
     *                                  InteractsWithNotifications), or a
     *                                  Closure — executed through a signed,
     *                                  single-use server action, so this
     *                                  works in a plain Laravel app too.
     */
    public function onConfirm(string|Closure $action, array $params = []): static
    {
        $this->onConfirm = $action;
        $this->onConfirmParams = $params;

        return $this;
    }

    protected function toPayload(): NotificationPayload
    {
        $this->actions = [
            new Action($this->cancelText, 'secondary', color: $this->cancelColor),
            new Action(
                $this->confirmText,
                $this->danger ? 'danger' : 'primary',
                $this->resolveTarget($this->onConfirm, $this->onConfirmParams),
                color: $this->confirmColor,
            ),
        ];

        return parent::toPayload();
    }
}
