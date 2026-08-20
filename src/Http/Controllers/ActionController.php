<?php

declare(strict_types=1);

namespace Salioudiabate\Notify\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Salioudiabate\Notify\Support\CallbackAction;

/**
 * Executes a closure registered via CallbackAction::register() — reached
 * only through a signed, time-boxed, single-use URL (see config('notify.actions')).
 * The `signed` middleware (applied on the route) already rejects any request
 * whose signature doesn't match, so a missing/expired/already-used token is
 * the only failure mode left to handle here.
 */
final class ActionController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $callback = CallbackAction::resolve($token);

        abort_if($callback === null, 410, 'This action has already been used or has expired.');

        $callback($request);

        return response()->noContent();
    }
}
