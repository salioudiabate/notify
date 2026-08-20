<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Builders;

use Salioudiabate\Notify\NotifyManager;

final class ProgressBuilder extends NotificationBuilder
{
    public function __construct(NotifyManager $manager)
    {
        parent::__construct($manager);

        $this->persistent = true;
    }

    protected function type(): string
    {
        return 'progress';
    }

    public function progress(int $percent): static
    {
        $this->progress = $percent;

        return $this;
    }

    /** Footer status line, e.g. "Processing batch 4 of 7 · about 40 seconds left". */
    public function status(string $status): static
    {
        return $this->meta(['status' => $status]);
    }
}
