<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Contracts;

use Salioudiabate\Notify\Support\NotificationPayload;

interface NotificationDriver
{
    /**
     * Send a payload to the browser. `$payload->replace` tells the front-end
     * store to merge it into an existing card with the same id instead of
     * appending a new one — this is how Notify::update($id) works, it's just
     * a regular push() with that flag set (see Builders\UpdateBuilder).
     */
    public function push(NotificationPayload $payload): void;

    public function clearGroup(string $group): void;
}
