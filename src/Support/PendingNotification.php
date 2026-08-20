<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Support;

use Illuminate\Http\RedirectResponse;
use Salioudiabate\Notify\Builders\UpdateBuilder;
use Salioudiabate\Notify\NotifyManager;

/**
 * What every terminal Notify:: call (send()/show(), or the success()/error()/
 * warning()/info()/loading() shorthands) returns. Its own success()/error()/
 * warning()/info()/progress() don't create a new notification — they replace
 * this one in place, which is how `Notify::loading('Import...')->success('Terminé')`
 * and progress-bar updates by id both work.
 */
final class PendingNotification
{
    public function __construct(
        private readonly NotifyManager $manager,
        private readonly string $id,
        private readonly ?object $component = null,
        private readonly ?string $broadcastChannel = null,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function success(string $message, ?string $title = null): static
    {
        $this->update()->success($message, $title)->send();

        return $this;
    }

    public function error(string $message, ?string $title = null): static
    {
        $this->update()->error($message, $title)->send();

        return $this;
    }

    public function warning(string $message, ?string $title = null): static
    {
        $this->update()->warning($message, $title)->send();

        return $this;
    }

    public function info(string $message, ?string $title = null): static
    {
        $this->update()->info($message, $title)->send();

        return $this;
    }

    public function progress(int $percent, ?string $status = null): static
    {
        $this->update()->progress($percent)->message($status)->send();

        return $this;
    }

    public function redirectTo(string $url, int $status = 302): RedirectResponse
    {
        return redirect($url, $status);
    }

    /** Closes this specific notification remotely — e.g. a loading toast that
     *  turns out to need no further feedback at all, rather than a
     *  success()/error() state. Targets the same component/channel update() would. */
    public function dismiss(): static
    {
        $this->manager->driverFor($this->component, $this->broadcastChannel)->dismiss($this->id);

        return $this;
    }

    private function update(): UpdateBuilder
    {
        $builder = $this->manager->update($this->id);

        if ($this->component) {
            $builder = $builder->forComponent($this->component);
        }

        if ($this->broadcastChannel) {
            $builder = $builder->toChannel($this->broadcastChannel);
        }

        return $builder;
    }
}
