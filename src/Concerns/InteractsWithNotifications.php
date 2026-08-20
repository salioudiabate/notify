<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Concerns;

use Closure;
use Salioudiabate\Notify\Builders\ConfirmBuilder;
use Salioudiabate\Notify\Builders\ToastBuilder;
use Salioudiabate\Notify\Facades\Notify;
use Salioudiabate\Notify\NotifyManager;
use Salioudiabate\Notify\Support\PendingNotification;

/**
 * Add to any Livewire component to get instant, no-reload notifications
 * instead of the session-flash fallback every plain Laravel app already
 * gets for free from Notify:: alone.
 *
 * Livewire calls every `boot{TraitName}()` method automatically, on every
 * request, for components using that trait — this is how the component
 * registers itself as the active dispatch target without Notify touching
 * any Livewire internals.
 */
trait InteractsWithNotifications
{
    public function bootInteractsWithNotifications(): void
    {
        app(NotifyManager::class)->registerLivewireComponent($this);
    }

    /**
     * $this->notify('Utilisateur créé.') sends a success toast immediately.
     * $this->notify() returns the builder for the advanced fluent form:
     * $this->notify()->asError()->title('Oups')->message('...')->send().
     */
    public function notify(?string $message = null, ?string $title = null): ToastBuilder|PendingNotification
    {
        if ($message === null) {
            return Notify::toast()->forComponent($this);
        }

        return Notify::toast()->asSuccess()->title($title)->message($message)->forComponent($this)->send();
    }

    public function confirm(string $title, string $message, string|Closure $action, array $params = []): PendingNotification
    {
        return Notify::confirm($title, $message)
            ->forComponent($this)
            ->onConfirm($action, $params)
            ->send();
    }

    /**
     * Same target-binding as notify()/confirm() above, but always returns the
     * builder, unambiguously — for the static-analysis-friendly alternative
     * to notify()'s "sends immediately unless $message is omitted" overload.
     * Use this when you need to chain anything notify() itself can't reach,
     * e.g. ->danger()/->action()/->group() before ->send().
     */
    public function notifyBuilder(): ToastBuilder
    {
        return Notify::toast()->forComponent($this);
    }

    /**
     * confirm()'s builder-returning counterpart: confirm() above always sends
     * right away, so it can't reach ->danger(), ->confirmColor()/->cancelColor(),
     * ->confirmText()/->cancelText(), or a custom ->icon() — get the builder
     * here instead, then finish with ->onConfirm(...)->send() yourself:
     *
     *   $this->confirmBuilder('Delete this?', 'This cannot be undone.')
     *       ->danger()
     *       ->onConfirm('delete', ['id' => $item->id])
     *       ->send();
     */
    public function confirmBuilder(?string $title = null, ?string $message = null): ConfirmBuilder
    {
        return Notify::confirm($title, $message)->forComponent($this);
    }

    /**
     * Renders as an Alpine click handler: shows a loading toast, calls the
     * Livewire method, then swaps the same card to success/error depending
     * on the outcome — the click → loading → result sequence from the
     * original brief, with no manual wiring.
     *
     * <button @click="{{ $this->notifyAction('delete', loading: 'Suppression…', success: 'Supprimé.', error: 'Échec de la suppression.') }}">
     *
     * Relies on Alpine's $wire magic, so it only ever appears on an
     *
     * @click of a Livewire component's own template — Alpine ships with
     * Livewire itself, nothing extra to load.
     */
    public function notifyAction(string $method, array $params = [], ?string $loading = null, ?string $success = null, ?string $error = null): string
    {
        return sprintf(
            'notifyAction($wire, %s, %s, %s)',
            json_encode($method),
            json_encode(array_values($params)),
            json_encode(['loading' => $loading, 'success' => $success, 'error' => $error])
        );
    }
}
