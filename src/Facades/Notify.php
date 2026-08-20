<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Facades;

use Illuminate\Support\Facades\Facade;
use Salioudiabate\Notify\Builders\AlertBuilder;
use Salioudiabate\Notify\Builders\ConfirmBuilder;
use Salioudiabate\Notify\Builders\DialogBuilder;
use Salioudiabate\Notify\Builders\ProgressBuilder;
use Salioudiabate\Notify\Builders\ToastBuilder;
use Salioudiabate\Notify\Builders\UpdateBuilder;
use Salioudiabate\Notify\Support\PendingNotification;

/**
 * @method static ToastBuilder toast()
 * @method static AlertBuilder alert()
 * @method static ConfirmBuilder confirm(?string $title = null, ?string $message = null, string|\Closure|null $onConfirm = null)
 * @method static DialogBuilder dialog()
 * @method static ProgressBuilder progress(?string $title = null)
 * @method static PendingNotification loading(?string $message = null)
 * @method static PendingNotification success(string $message, ?string $title = null)
 * @method static PendingNotification error(string $message, ?string $title = null)
 * @method static PendingNotification warning(string $message, ?string $title = null)
 * @method static PendingNotification info(string $message, ?string $title = null)
 * @method static UpdateBuilder update(string $id)
 * @method static PendingNotification exception(\Throwable $e)
 * @method static void clearGroup(string $group)
 *
 * @see \Salioudiabate\Notify\NotifyManager
 */
final class Notify extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'notify';
    }
}
