<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Support;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The declarative, framework-agnostic state of one notification. The backend
 * never renders HTML — it only ever produces one of these, which drivers
 * hand to the browser as JSON and the front-end store turns into a visual.
 */
final class NotificationPayload implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $id,
        public string $type,       // toast | alert | confirm | dialog | progress
        public string $variant = 'neutral', // success | error | warning | info | neutral | loading
        public ?string $title = null,
        public ?string $message = null,
        public ?string $icon = null,
        public ?int $duration = null,
        public string $position = 'top-right',
        public bool $dismissible = true,
        public bool $persistent = false,
        public ?string $group = null,
        public array $actions = [],
        public ?string $url = null,
        public ?int $progress = null,
        public array $meta = [],
        public bool $replace = false,
        public ?string $template = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'variant' => $this->variant,
            'title' => $this->title,
            'message' => $this->message,
            'icon' => $this->icon,
            'duration' => $this->duration,
            'position' => $this->position,
            'dismissible' => $this->dismissible,
            'persistent' => $this->persistent,
            'group' => $this->group,
            'actions' => array_map(fn (Action $a) => $a->toArray(), $this->actions),
            'url' => $this->url,
            'progress' => $this->progress,
            'meta' => $this->meta,
            'replace' => $this->replace,
            'template' => $this->template,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
