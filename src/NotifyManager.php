<?php

declare(strict_types=1);

namespace Salioudiabate\Notify;

use Illuminate\Contracts\Container\Container;
use Salioudiabate\Notify\Builders\AlertBuilder;
use Salioudiabate\Notify\Builders\ConfirmBuilder;
use Salioudiabate\Notify\Builders\DialogBuilder;
use Salioudiabate\Notify\Builders\ProgressBuilder;
use Salioudiabate\Notify\Builders\ToastBuilder;
use Salioudiabate\Notify\Builders\UpdateBuilder;
use Salioudiabate\Notify\Contracts\NotificationDriver;
use Salioudiabate\Notify\Drivers\LivewireDriver;
use Salioudiabate\Notify\Drivers\SessionDriver;
use Salioudiabate\Notify\Support\PendingNotification;

/**
 * The single entry point behind the Notify facade. Its only real job is
 * `resolveDriver()`: every app gets SessionDriver by default (always safe,
 * always works, no Livewire required), and it is upgraded to LivewireDriver
 * for the duration of a request the moment any component using
 * InteractsWithNotifications boots — see that trait for how it registers
 * itself with `registerLivewireComponent()` below.
 */
final class NotifyManager
{
    public function __construct(private readonly Container $container)
    {
    }

    public function toast(): ToastBuilder
    {
        return new ToastBuilder($this);
    }

    public function alert(): AlertBuilder
    {
        return new AlertBuilder($this);
    }

    public function confirm(?string $title = null, ?string $message = null, string|\Closure|null $onConfirm = null): ConfirmBuilder
    {
        $builder = (new ConfirmBuilder($this))->title($title)->message($message);

        return $onConfirm ? $builder->onConfirm($onConfirm) : $builder;
    }

    public function dialog(): DialogBuilder
    {
        return new DialogBuilder($this);
    }

    public function progress(?string $title = null): ProgressBuilder
    {
        return (new ProgressBuilder($this))->title($title);
    }

    public function loading(?string $message = null): PendingNotification
    {
        return $this->toast()->loading()->message($message)->send();
    }

    public function success(string $message, ?string $title = null): PendingNotification
    {
        return $this->toast()->success()->title($title)->message($message)->send();
    }

    public function error(string $message, ?string $title = null): PendingNotification
    {
        return $this->toast()->error()->title($title)->message($message)->send();
    }

    public function warning(string $message, ?string $title = null): PendingNotification
    {
        return $this->toast()->warning()->title($title)->message($message)->send();
    }

    public function info(string $message, ?string $title = null): PendingNotification
    {
        return $this->toast()->info()->title($title)->message($message)->send();
    }

    public function update(string $id): UpdateBuilder
    {
        return new UpdateBuilder($this, $id);
    }

    /**
     * Never exposes $e->getMessage() in production unless you explicitly
     * opt in via config('notify.errors.local') — see config/notify.php.
     */
    public function exception(\Throwable $e): PendingNotification
    {
        $isLocal = app()->environment('local', 'testing');
        $localSetting = config('notify.errors.local', true);

        $message = match (true) {
            $isLocal && $localSetting === true => $e->getMessage(),
            $isLocal && is_string($localSetting) => $localSetting,
            default => (string) config('notify.errors.production', 'Une erreur est survenue.'),
        };

        return $this->error($message);
    }

    public function clearGroup(string $group): void
    {
        $this->resolveDriver()->clearGroup($group);
    }

    /**
     * Called from InteractsWithNotifications::bootInteractsWithNotifications().
     * The last component to boot on a page "wins" as the active target for
     * calls made through the plain facade during that request; a component
     * that needs a guaranteed target should call $this->notify(...) instead,
     * which always attaches to itself explicitly.
     */
    public function registerLivewireComponent(\Livewire\Component $component): void
    {
        $this->container->instance('notify.livewire.active', $component);
    }

    /**
     * @param  object|null  $component  A Livewire\Component instance, passed by
     *                                  the InteractsWithNotifications trait.
     *                                  Left untyped so this signature never
     *                                  forces a class load when Livewire isn't
     *                                  installed at all.
     */
    public function driverFor(?object $component = null): NotificationDriver
    {
        if ($component) {
            return new LivewireDriver($component);
        }

        return $this->resolveDriver();
    }

    public function resolveDriver(): NotificationDriver
    {
        if ($this->container->bound('notify.livewire.active')) {
            return new LivewireDriver($this->container->make('notify.livewire.active'));
        }

        return $this->container->make(SessionDriver::class);
    }
}
