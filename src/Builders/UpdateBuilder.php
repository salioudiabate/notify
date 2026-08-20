<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Builders;

use Salioudiabate\Notify\NotifyManager;
use Salioudiabate\Notify\Support\NotificationPayload;

/**
 * Notify::update($id) — re-sends a payload carrying the same id with
 * `replace: true`, which the front-end store swaps in place instead of
 * appending a new card. Used directly, and internally by
 * PendingNotification's success()/error()/warning()/info()/progress().
 *
 * success()/error()/warning()/info() take an optional message/title
 * directly (unlike the same-named methods on Toast/Alert/Dialog builders,
 * which only flip the variant and expect a separate ->message() call) —
 * an update is almost always "swap to this state with this message" in one
 * move, so that's the ergonomic default here.
 */
class UpdateBuilder extends NotificationBuilder
{
    private string $kind = 'toast';

    public function __construct(NotifyManager $manager, string $id)
    {
        parent::__construct($manager);

        $this->id($id);
    }

    public function success(?string $message = null, ?string $title = null): static
    {
        return $this->applyVariant('success', $message, $title);
    }

    public function error(?string $message = null, ?string $title = null): static
    {
        return $this->applyVariant('error', $message, $title);
    }

    public function warning(?string $message = null, ?string $title = null): static
    {
        return $this->applyVariant('warning', $message, $title);
    }

    public function info(?string $message = null, ?string $title = null): static
    {
        return $this->applyVariant('info', $message, $title);
    }

    public function progress(int $percent): static
    {
        $this->kind = 'progress';
        $this->progress = $percent;

        return $this;
    }

    protected function type(): string
    {
        return $this->kind;
    }

    protected function toPayload(): NotificationPayload
    {
        $payload = parent::toPayload();
        $payload->replace = true;

        return $payload;
    }

    private function applyVariant(string $variant, ?string $message, ?string $title): static
    {
        $this->variant = $variant;

        if ($message !== null) {
            $this->message = $message;
        }

        if ($title !== null) {
            $this->title = $title;
        }

        return $this;
    }
}
