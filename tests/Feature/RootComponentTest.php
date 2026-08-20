<?php

declare(strict_types=1);
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

// $errors is normally shared by Illuminate\View\Middleware\ShareErrorsFromSession,
// which only runs for a real HTTP request — rendering the view directly in a
// test bypasses that middleware entirely, so it's shared by hand here instead.
beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
    $this->app['session']->start(); // no real HTTP request here, so nothing else starts it
});

it('embeds config(notify.color_scheme) and the other JS config values in the rendered page', function () {
    config(['notify.color_scheme' => 'dark']);

    $html = (string) view('notify::components.root')->render();

    expect($html)
        ->toContain('"colorScheme":"dark"')
        ->toContain('id="notify-root"')
        ->not->toContain('data-notify-position'); // dead attribute, removed — position is per-stack now
});

function renderedQueue(): array
{
    $html = (string) view('notify::components.root')->render();

    // json_encode() escapes accented characters as \uXXXX — decoding back to
    // PHP values, rather than substring-matching the raw HTML, is what
    // actually verifies the content survived the round trip.
    return json_decode(Str::between($html, 'window.__NOTIFY_QUEUE__ = ', ';'), true);
}

it('bridges an existing redirect()->with(\'success\', ...) flash into the JS queue, with zero Notify:: calls', function () {
    session()->flash('success', 'Enregistré via with().');

    expect(renderedQueue()[0])
        ->variant->toBe('success')
        ->message->toBe('Enregistré via with().');
});

it('bridges Breeze/Jetstream\'s "status" key to a success toast too', function () {
    session()->flash('status', 'Profil mis à jour.');

    expect(renderedQueue()[0])
        ->variant->toBe('success')
        ->message->toBe('Profil mis à jour.');
});

it('does not queue anything for a flash key with no session value set', function () {
    expect(renderedQueue())->toBe([]);
});
