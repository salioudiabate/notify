<?php

declare(strict_types=1);

it('serves the front-end JS with a no-cache header, never a silently-stale copy', function () {
    $this->get('/notify/notify.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
        ->assertHeader('Cache-Control', 'must-revalidate, no-cache, public'); // Symfony normalizes directive order alphabetically
});

it('serves the CSS the same way', function () {
    $this->get('/notify/notify.css')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=utf-8');
});
