<?php

declare(strict_types=1);

namespace Salioudiabate\Notify;

use Illuminate\Contracts\Container\Container;
use Livewire\Component;
use Salioudiabate\Notify\Builders\AlertBuilder;
use Salioudiabate\Notify\Builders\ConfirmBuilder;
use Salioudiabate\Notify\Builders\DialogBuilder;
use Salioudiabate\Notify\Builders\ProgressBuilder;
use Salioudiabate\Notify\Builders\ToastBuilder;
use Salioudiabate\Notify\Builders\UpdateBuilder;
use Salioudiabate\Notify\Contracts\NotificationDriver;
use Salioudiabate\Notify\Drivers\BroadcastDriver;
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
    public function __construct(private readonly Container $container) {}

    public function toast(): ToastBuilder
    {
        return new ToastBuilder($this);
    }

    public function alert(): AlertBuilder
    {
        return new AlertBuilder($this);
    }

    /**
     * One-liner shorthands for the common "banner with this message" case —
     * mirrors success()/error()/warning()/info() below, which do the same
     * for toast(). Use alert() directly for anything more custom (a button(),
     * a longer-lived group(), ...).
     */
    public function alertSuccess(string $message, ?string $title = null): PendingNotification
    {
        return $this->alert()->asSuccess()->title($title)->message($message)->send();
    }

    public function alertError(string $message, ?string $title = null): PendingNotification
    {
        return $this->alert()->asError()->title($title)->message($message)->send();
    }

    public function alertWarning(string $message, ?string $title = null): PendingNotification
    {
        return $this->alert()->asWarning()->title($title)->message($message)->send();
    }

    public function alertInfo(string $message, ?string $title = null): PendingNotification
    {
        return $this->alert()->asInfo()->title($title)->message($message)->send();
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
        return $this->toast()->asSuccess()->title($title)->message($message)->send();
    }

    public function error(string $message, ?string $title = null): PendingNotification
    {
        return $this->toast()->asError()->title($title)->message($message)->send();
    }

    public function warning(string $message, ?string $title = null): PendingNotification
    {
        return $this->toast()->asWarning()->title($title)->message($message)->send();
    }

    public function info(string $message, ?string $title = null): PendingNotification
    {
        return $this->toast()->asInfo()->title($title)->message($message)->send();
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

    /** PHP-side counterpart of the front-end's Notify.dismiss(id) — closes an
     *  already-rendered notification remotely, without a page reload when
     *  called from a Livewire component. */
    public function dismiss(string $id): void
    {
        $this->resolveDriver()->dismiss($id);
    }

    /** PHP-side counterpart of the front-end's Notify.clear(). */
    public function clearAll(): void
    {
        $this->resolveDriver()->clearAll();
    }

    /**
     * Called from InteractsWithNotifications::bootInteractsWithNotifications().
     * The last component to boot on a page "wins" as the active target for
     * calls made through the plain facade during that request; a component
     * that needs a guaranteed target should call $this->notify(...) instead,
     * which always attaches to itself explicitly.
     */
    public function registerLivewireComponent(Component $component): void
    {
        $this->container->instance('notify.livewire.active', $component);
    }

    /**
     * @param  object|null  $component  A Livewire\Component instance, passed by
     *                                  the InteractsWithNotifications trait.
     *                                  Left untyped so this signature never
     *                                  forces a class load when Livewire isn't
     *                                  installed at all.
     * @param  string|null  $broadcastChannel  Set by ->toUser()/->toChannel() —
     *                                         wins over everything else, since
     *                                         it's always a deliberate,
     *                                         explicit target rather than
     *                                         something to auto-detect.
     */
    public function driverFor(?object $component = null, ?string $broadcastChannel = null): NotificationDriver
    {
        if ($broadcastChannel !== null) {
            return new BroadcastDriver($broadcastChannel);
        }

        if ($component) {
            return new LivewireDriver($component);
        }

        return $this->resolveDriver();
    }

    public function resolveDriver(): NotificationDriver
    {
        if ($component = $this->activeLivewireComponent()) {
            return new LivewireDriver($component);
        }

        return $this->container->make(SessionDriver::class);
    }

    /**
     * Exposed so NotificationBuilder::resolveTarget() can resolve a bare
     * method-name action to the same component a plain Notify:: call would
     * already be pushing to — otherwise ->action('Label', 'someMethod')
     * would only resolve correctly when chained after an explicit
     * ->forComponent($this), which is inconsistent with how push() itself
     * auto-upgrades to Livewire the moment a trait-using component is on
     * the page.
     */
    public function activeLivewireComponent(): ?object
    {
        return $this->container->bound('notify.livewire.active')
            ? $this->container->make('notify.livewire.active')
            : null;
    }
}
