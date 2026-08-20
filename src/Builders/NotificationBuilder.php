<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Builders;

use Closure;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Salioudiabate\Notify\NotifyManager;
use Salioudiabate\Notify\Support\Action;
use Salioudiabate\Notify\Support\CallbackAction;
use Salioudiabate\Notify\Support\NotificationPayload;
use Salioudiabate\Notify\Support\PendingNotification;

/**
 * Shared fluent surface for every notification type. Concrete builders only
 * add what makes them different (ToastBuilder's loading(), ConfirmBuilder's
 * onConfirm(), ...) — everything about title/message/actions/timing/target
 * resolution lives here once.
 */
abstract class NotificationBuilder
{
    protected string $id;

    protected string $variant = 'neutral';

    protected ?string $title = null;

    protected ?string $message = null;

    protected ?string $icon = null;

    protected ?int $duration = null;

    protected string $position;

    protected bool $dismissible = true;

    protected bool $persistent = false;

    protected ?string $group = null;

    /** @var array<int, Action> */
    protected array $actions = [];

    protected ?int $progress = null;

    protected array $meta = [];

    /** Front-end template name (see Notify.registerTemplate()); null falls
     *  back to config('notify.theme'), then to the built-in "default". */
    protected ?string $template = null;

    /** Set via forComponent() by InteractsWithNotifications, to force this
     *  notification to a specific Livewire component regardless of which
     *  component last booted the trait on the page. */
    protected ?object $component = null;

    public function __construct(protected readonly NotifyManager $manager)
    {
        $this->id = (string) Str::uuid();
        $this->position = (string) config('notify.position', 'top-right');
    }

    abstract protected function type(): string;

    public function id(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function title(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function message(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function duration(?int $milliseconds): static
    {
        $this->duration = $milliseconds;

        return $this;
    }

    public function position(string $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function dismissible(bool $dismissible = true): static
    {
        $this->dismissible = $dismissible;

        return $this;
    }

    public function persistent(bool $persistent = true): static
    {
        $this->persistent = $persistent;

        if ($persistent) {
            $this->duration = null;
        }

        return $this;
    }

    public function group(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    /** Render this one notification with a template registered via Notify.registerTemplate('name', {...}) instead of the default look. */
    public function template(string $name): static
    {
        $this->template = $name;

        return $this;
    }

    public function meta(array $meta): static
    {
        $this->meta = [...$this->meta, ...$meta];

        return $this;
    }

    /**
     * @param  string|Closure|null  $target  A route name, a plain URL, a
     *                                       Livewire method name (when built
     *                                       inside a component via the
     *                                       InteractsWithNotifications
     *                                       trait), or a Closure executed
     *                                       server-side through a signed,
     *                                       single-use callback URL.
     */
    public function action(string $label, string|Closure|null $target = null, string $style = 'ghost'): static
    {
        $this->actions[] = new Action($label, $style, $this->resolveTarget($target));

        return $this;
    }

    public function url(string $url, string $label = 'Voir'): static
    {
        return $this->action($label, $url, 'link');
    }

    /** @internal set by InteractsWithNotifications — not part of the public fluent API */
    public function forComponent(object $component): static
    {
        $this->component = $component;

        return $this;
    }

    public function send(): PendingNotification
    {
        $payload = $this->toPayload();

        $this->manager->driverFor($this->component)->push($payload);

        return new PendingNotification($this->manager, $payload->id, $this->component);
    }

    /** Alias kept for readability at call sites: ->confirm()->show(), ->dialog()->show() */
    public function show(): PendingNotification
    {
        return $this->send();
    }

    /**
     * Explicit signals (a real URL, a named route) always win over "maybe
     * this string is a Livewire method name" — only once neither applies do
     * we fall back to Livewire, using this builder's own ->forComponent()
     * target if set, otherwise whichever component the trait registered as
     * active for this request (see NotifyManager::activeLivewireComponent()).
     */
    protected function resolveTarget(string|Closure|null $target, array $params = []): ?array
    {
        $component = $this->component ?? $this->manager->activeLivewireComponent();

        return match (true) {
            $target === null => null,
            $target instanceof Closure => ['type' => 'callback', 'url' => CallbackAction::register($target)],
            $this->looksLikeUrl($target) => ['type' => 'url', 'url' => $target],
            Route::has($target) => ['type' => 'url', 'url' => route($target, $params)],
            $component !== null => ['type' => 'livewire', 'component' => $component->getId(), 'method' => $target, 'params' => $params],
            default => ['type' => 'url', 'url' => $target],
        };
    }

    private function looksLikeUrl(string $target): bool
    {
        return str_starts_with($target, '/') || str_contains($target, '://') || str_starts_with($target, 'mailto:');
    }

    protected function toPayload(): NotificationPayload
    {
        return new NotificationPayload(
            id: $this->id,
            type: $this->type(),
            variant: $this->variant,
            title: $this->title,
            message: $this->message,
            icon: $this->icon,
            duration: $this->duration,
            position: $this->position,
            dismissible: $this->dismissible,
            persistent: $this->persistent,
            group: $this->group,
            actions: $this->actions,
            progress: $this->progress,
            meta: $this->meta,
            template: $this->template,
        );
    }
}
