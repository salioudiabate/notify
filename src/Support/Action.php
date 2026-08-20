<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Support;

/**
 * One button on a toast/alert/dialog. `target` shape tells the front-end how
 * to execute it — a URL, a Livewire method call, or a signed server action —
 * without the backend ever knowing which one until send() resolves it.
 */
final class Action
{
    public function __construct(
        public string $label,
        public string $style = 'ghost', // primary | secondary | danger | ghost | link
        public ?array $target = null,   // ['type' => 'url'|'livewire'|'callback', ...]
        public bool $closesDialog = true,
    ) {}

    public static function url(string $label, string $url, string $style = 'ghost'): self
    {
        return new self($label, $style, ['type' => 'url', 'url' => $url]);
    }

    public static function livewire(string $label, string $componentId, string $method, array $params = [], string $style = 'primary'): self
    {
        return new self($label, $style, ['type' => 'livewire', 'component' => $componentId, 'method' => $method, 'params' => $params]);
    }

    public static function callback(string $label, string $url, string $style = 'primary'): self
    {
        return new self($label, $style, ['type' => 'callback', 'url' => $url]);
    }

    public static function dismiss(string $label, string $style = 'ghost'): self
    {
        return new self($label, $style, null);
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'style' => $this->style,
            'target' => $this->target,
            'closesDialog' => $this->closesDialog,
        ];
    }
}
