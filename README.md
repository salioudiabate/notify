# Notify

[![Tests](https://github.com/salioudiabate/notify/actions/workflows/tests.yml/badge.svg)](https://github.com/salioudiabate/notify/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/salioudiabate/notify.svg)](https://packagist.org/packages/salioudiabate/notify)
[![License](https://img.shields.io/packagist/l/salioudiabate/notify.svg)](LICENSE.md)

A unified notification & feedback UI system for Laravel — toasts, alerts, confirmations, dialogs, loading and progress, from one fluent API. **Livewire is optional.** A plain Laravel controller gets the full feature set through session flashing; the moment a Livewire component opts in, the same calls become instant, no-reload pushes — nothing to rewrite either way.

```php
Notify::success('Utilisateur créé avec succès.');

Notify::confirm('Supprimer ce client ?', 'Cette action est irréversible.')
    ->danger()
    ->onConfirm(fn () => $client->delete())
    ->show();

$import = Notify::loading('Import des données...');
// ... work happens ...
$import->success('Import terminé.');
```

```blade
{{-- once, in your main layout --}}
<x-notify::root />
```

## Table of contents

- [Why](#why)
- [Installation](#installation)
- [Quick start](#quick-start)
  - [Plain Laravel — no Livewire at all](#plain-laravel--no-livewire-at-all)
  - [Livewire — instant, no-reload](#livewire--instant-no-reload)
  - [Pure client-side JS](#pure-client-side-js)
- [API](#api)
- [Custom templates](#custom-templates)
- [Icons, text & button colors](#icons-text--button-colors)
- [Light & dark mode](#light--dark-mode)
- [Confirmations & server actions](#confirmations--server-actions)
- [Existing `->with('success', ...)` calls, validation & exceptions](#existing---withsuccess--calls-validation--exceptions)
- [Configuration](#configuration)
- [Security](#security)
- [Roadmap](#roadmap)

## Why

Most notification packages pick a side: either a Livewire-only toast component, or a session-flash Blade partial with no Livewire story. Notify is built the other way around — one declarative payload, one front-end store, three ways to feed it:

| Producer | When it's used | Round trip |
|---|---|---|
| **Session flash** | Any controller, form request, queued job, or plain Blade page — Livewire installed or not | Next full page load |
| **Livewire dispatch** | A component using `InteractsWithNotifications` | Instant, no reload |
| **`window.Notify`** | Any inline script, with zero backend involved | None |

The backend never renders HTML — it only ever produces a small JSON payload (`type`, `variant`, `title`, `message`, `actions`, ...). `resources/js/notify.js` is the only thing that turns that into a toast, an alert, a confirmation dialog or a progress card, so every surface — session-flashed, Livewire-pushed, or JS-triggered — renders through the exact same visual system.

## Installation

Requires PHP 8.3+, Laravel 11/12/13, and — only if you want the instant, no-reload upgrade — Livewire 3 or 4 (entirely optional, see [Why](#why)).

```bash
composer require salioudiabate/notify
php artisan vendor:publish --tag=notify-config
```

Add the root component once, in your main layout, right before `</body>`:

```blade
<x-notify::root />
```

That's it — the JS/CSS are served directly by the package (no build step, no `npm install`). If you'd rather bundle them through your own pipeline (Vite, etc.), publish them and set `assets.serve` to `false` in `config/notify.php`:

```bash
php artisan vendor:publish --tag=notify-assets
```

## Quick start

### Plain Laravel — no Livewire at all

```php
class ClientController extends Controller
{
    public function destroy(Client $client)
    {
        $client->delete();

        return Notify::success('Client supprimé.')->redirectTo(route('clients.index'));
    }
}
```

Works from anywhere synchronous in the request lifecycle — controllers, form requests, queued jobs before a redirect, console commands (where it's a documented no-op: there's no browser to render into).

### Livewire — instant, no-reload

```php
use Livewire\Component;
use Salioudiabate\Notify\Concerns\InteractsWithNotifications;

class DeleteClientButton extends Component
{
    use InteractsWithNotifications;

    public function delete(Client $client)
    {
        $client->delete();

        $this->notify('Client supprimé.');
    }

    public function askToDelete(Client $client)
    {
        $this->confirm(
            title: 'Supprimer ce client ?',
            message: 'Cette action est irréversible.',
            action: 'delete',
            params: ['client' => $client->id],
        );
    }
}
```

```blade
{{-- notifyAction(): loading -> Livewire call -> success/error, wired as one Alpine expression --}}
<button @click="{{ $this->notifyAction('delete', ['client' => $client->id], loading: 'Suppression…', success: 'Client supprimé.', error: 'Échec de la suppression.') }}">
    Supprimer
</button>
```

`InteractsWithNotifications` registers the component the moment it boots (`bootInteractsWithNotifications()` — a standard Livewire trait hook, nothing package-specific), so even a plain `Notify::success(...)` call made anywhere during that request — a service class, a job dispatched synchronously, not just `$this->notify()` — is upgraded to an instant push automatically for the rest of the request.

`notify()`/`confirm()` above are one-liners that send right away — they can't reach `->danger()`, `->group()`, `->confirmColor()`, or anything else that needs chaining before `->send()`. For that, use their builder-returning counterparts instead, still bound to the same component:

```php
$this->notifyBuilder()->error()->title('Oups')->message('...')->group('errors')->send();

$this->confirmBuilder('Supprimer définitivement ?', 'Cette action est irréversible.')
    ->danger()
    ->confirmColor('#dc2626')
    ->onConfirm('delete', ['client' => $client->id])
    ->send();
```

### Pure client-side JS

No backend call at all:

```js
Notify.success('Enregistré.');
Notify.error('Une erreur est survenue.');
Notify.warning('Vérifiez ces informations.');
Notify.info('Nouvelle version disponible.');
Notify.confirm({ title: 'Continuer ?', message: 'Cette action est définitive.', danger: true, onConfirm: () => doThing() });

// the escape hatch behind success()/error()/warning()/info() — every payload
// field, including which template renders it (see § Custom templates)
Notify.toast({ variant: 'success', title: '...', message: '...', duration: 6000 });

Notify.dismiss(id);
Notify.clear();
Notify.clearGroup('users');
```

## API

```php
Notify::success($message, $title = null);
Notify::error($message, $title = null);
Notify::warning($message, $title = null);
Notify::info($message, $title = null);

Notify::toast()->success()->title('Succès')->message('...')->duration(5000)->send();

// alert() covers both a one-off important notice (button() alone) and a
// persistent banner (add action() too) — same builder, more actions
Notify::alert()->warning()->title('Attention')->message('...')->button('Compris')->show();
Notify::alert()->info()->title('Maintenance programmée')->message('...')
    ->action('En savoir plus', 'https://...', 'link')->button('Fermer')->show(); // persists until dismissed

// one-liners for the common "banner with this message" case, mirroring
// success()/error()/warning()/info() above for toast() — use alert() directly
// for anything more custom (a button() with its own target, group(), ...)
Notify::alertSuccess($message, $title = null);
Notify::alertError($message, $title = null);
Notify::alertWarning($message, $title = null);
Notify::alertInfo($message, $title = null);

Notify::confirm('Titre', 'Message')->danger()->confirmText('Supprimer')->onConfirm(...)->show();

// dialog() is free-form (any number of action() buttons) — centered() switches
// to the single-button, celebratory layout; without it you get a regular modal
Notify::dialog()->success()->centered()->title('Paiement réussi')->message('...')->action('Continuer')->show();

// called from inside a Livewire component method: 'signOut' resolves to
// $this->signOut() on that same component — the same target resolution
// onConfirm() uses (a route name or URL always wins first, see below)
Notify::dialog()->info()->title('Session bientôt expirée')->message('...')
    ->action('Se déconnecter', 'signOut')->action('Rester connecté', null, 'primary')->show();

Notify::progress()->title('Importation')->progress(45)->status('Lot 4 sur 7')->send();

$pending = Notify::loading('Traitement...');
$pending->success('Terminé.');   // swaps the same card in place
$pending->progress(80);

Notify::update($id)->success('Terminé.')->send();
Notify::clearGroup('users');
Notify::dismiss($id);  // closes one already-rendered notification remotely
Notify::clearAll();    // closes every currently-rendered notification remotely
Notify::exception($e); // never leaks $e->getMessage() in production unless configured to

$pending->dismiss(); // same as Notify::dismiss($pending->id()), targeting the right component automatically
```

Shared fluent methods on every builder: `title()`, `message()`, `icon()`, `duration()`, `position()`, `dismissible()`, `persistent()`, `group()`, `id()`, `action($label, $target, $style, $color)`, `url($url, $label, $color)`, `template($name)`.

`position()` accepts `top-right` (default), `top-left`, `top-center`, `bottom-right`, `bottom-left`, `bottom-center`. `action()`'s `$target` resolves exactly like `onConfirm()` does (see [below](#confirmations--server-actions)) — a route name, a URL, a Livewire method name, or a `Closure` all work the same way on any toast/alert/dialog action, not just a confirmation's.

**Extending a builder.** None of the builders are `final`, and all of them inherit Laravel's `Macroable` trait — add your own fluent methods either by subclassing, or without subclassing at all:

```php
ToastBuilder::macro('forTenant', function (Tenant $tenant) {
    return $this->meta(['tenant' => $tenant->id]);
});

Notify::toast()->success()->message('...')->forTenant($tenant)->send();
```

## Custom templates

The built-in look ("default") is one entry in a template registry, not a hardcoded renderer — the backend only ever produces a payload (`type`, `variant`, `title`, `message`, `actions`, ...), and `notify.js` picks a template function to turn it into a visual. You can register your own and either use it for one notification or make it the default for the whole app, without touching the package.

```js
// resources/js/app.js — anywhere that runs before the notification fires
Notify.registerTemplate('brand', {
    toast(payload, h) {
        const card = h.el('div', 'my-toast my-toast--' + payload.variant);
        card.innerHTML = `
            <strong>${h.escapeHtml(payload.title ?? '')}</strong>
            <p>${h.escapeHtml(payload.message ?? '')}</p>
        `;
        card.appendChild(h.actions(payload)); // reuse built-in action-button wiring
        return card;
    },
    // alert / progress / dialog are omitted here — they keep rendering with
    // the built-in look until you define them too. Override only what you need.
});
```

`h` gives your template function everything the built-in ones use internally, so you don't have to reinvent action resolution or escaping:

| Helper | What it does |
|---|---|
| `h.el(tag, className?, innerHTML?)` | Create an element |
| `h.escapeHtml(value)` | HTML-escape a string |
| `h.icon(payload)` | Resolve the semantic icon markup for a variant |
| `h.actions(payload)` | Build the action-button row, already wired to `h.runAction` |
| `h.actionColor(button, color)` | Apply a per-button color override (string or `{bg,fg,border}`) to an element |
| `h.runAction(action, payload)` | Execute one action's target (url/route/Livewire/callback) and dismiss |
| `h.dismiss(id)` | Remove a notification by id |

Use it for one notification:

```php
Notify::toast()->success()->title('Fait')->message('...')->template('brand')->send();
```

...or make it the default for every notification in the app, in `config/notify.php`:

```php
'theme' => 'brand',
```

A `dialog` template handles both `confirm()` and `dialog()` payloads — it only needs to return the box itself; the backdrop, Esc-to-close, focus and queueing when a second dialog is requested while one is already open all stay in the package, since that's shared interaction plumbing rather than something a design should have to reimplement.

## Icons, text & button colors

Registering a whole template is the nuclear option — for smaller changes, icons, every piece of built-in UI chrome text, and button colors are all customizable on their own, without writing a single template function.

**Icons.** `->icon('name')` looks up a registry of built-in icons (`success`, `error`, `warning`, `info`, `neutral`, `trash`, `close`). Override one, several, or add new ones, globally:

```js
Notify.registerIcon('success', '<svg>...</svg>');
Notify.registerIcon({ success: '<svg>...</svg>', error: '<svg>...</svg>' }); // several at once
```

```php
// config/notify.php — same effect, applied server-side, no inline <script> needed
'icons' => [
    'success' => '<svg>...</svg>',
],
```

A one-off icon that isn't worth registering anywhere doesn't need either of those — pass raw markup straight to the call:

```php
Notify::success('Done.')->icon('<svg>...</svg>')->send();
```

**Text.** Every hardcoded piece of UI chrome — the close button's label, the "N more" overflow pill, the dialog's `Esc to close` hint, and `confirm()`/`cancel()`'s default button labels — is *not* part of any payload's own free-form title/message/action text (already customizable per call); it lives in one place so a translated or reworded app only has to set it once:

```js
Notify.setStrings({
    close: 'Close',
    moreSingular: 'more notification',
    morePlural: 'more notifications',
    escKey: 'Esc',
    escHint: 'to close',
    confirm: 'Confirm',
    cancel: 'Cancel',
    url: 'View',
    actionSuccess: 'Done.',
    actionError: 'Something went wrong.',
});
```

```php
// config/notify.php — same keys, applied globally without a <script> tag;
// leave any key out to keep its built-in French default
'strings' => [
    'close' => 'Close',
    'confirm' => 'Confirm',
    'cancel' => 'Cancel',
],
```

`ConfirmBuilder`'s default `->confirmText()`/`->cancelText()` and `->url()`'s default label read from these same `config('notify.strings.*')` keys server-side, so a single config change relabels both the PHP-built payloads and the pure-JS `Notify.confirm()` path consistently.

**Button colors.** Every button style (`primary`, `secondary`, `danger`, `ghost`, `link` — the same names `->action()`'s third argument accepts) has its own color, decoupled from card text/icon colors, so changing one never affects the other. Override one or more styles globally:

```js
Notify.setButtonColors({
    primary: { bg: '#7c3aed', fg: '#fff' },
    danger: { bg: '#dc2626' },
});
```

```php
// config/notify.php — same effect, applied server-side
'button_colors' => [
    'primary' => ['bg' => '#7c3aed', 'fg' => '#ffffff'],
],
```

Each style accepts `bg` (required to have any effect), plus optional `fg` (text) and `border`. Leave a style out to keep its built-in look.

A single button can go its own way regardless of the global setting — every button-producing method accepts an optional color, a plain string (background only) or a `['bg' => ..., 'fg' => ..., 'border' => ...]` array:

```php
Notify::toast()->success()->message('Done.')->action('Undo', fn () => $this->undo(), 'primary', '#7c3aed')->send();

Notify::confirm('Delete this?')
    ->confirmColor(['bg' => '#dc2626', 'fg' => '#fff'])
    ->cancelColor('#e5e7eb')
    ->onConfirm(fn () => $this->delete())
    ->send();
```

```js
Notify.confirm({
    title: 'Delete this?',
    confirmColor: '#dc2626',
    onConfirm: () => fetch('/delete', { method: 'POST' }),
});
```

## Light & dark mode

Every component follows `prefers-color-scheme` out of the box — no setup required. Force one globally, or let visitors flip it themselves:

```php
// config/notify.php — forces it site-wide regardless of the visitor's OS setting
'color_scheme' => 'dark', // null (default) | 'light' | 'dark'
```

```js
// wire this to your own app's existing dark-mode toggle button — Notify
// doesn't render one itself, it just needs to be told when yours is used
darkModeToggle.addEventListener('click', () => {
    Notify.setColorScheme(isDark ? 'light' : 'dark');
});

Notify.getColorScheme(); // 'light' | 'dark' | 'system'
```

`setColorScheme()` persists the choice in `localStorage`, so it survives reloads without any server round-trip, and overrides both `config('notify.color_scheme')` and the OS preference until `'system'` is passed again. If your app already has its own dark-mode cookie/session value, call `Notify.setColorScheme()` once on page load with that value instead of leaving it to `config()` — the two are independent, Notify doesn't read your app's own dark-mode flag automatically.

## Confirmations & server actions

`onConfirm()` accepts three kinds of targets, resolved automatically:

- **A route name or URL** — the confirm button submits/navigates there.
- **A Livewire method name** — only when the builder was created via `$this->confirm(...)` inside a component; resolved to a `Livewire.find(id).call(method, ...params)` client-side.
- **A `Closure`** — works even outside Livewire. The closure is serialized, cached server-side under a random single-use token, and exposed behind a *signed*, short-lived URL (`config('notify.actions.ttl')`, 5 minutes by default). Nothing about its contents ever reaches the client — only the opaque token does, and it's deleted from cache the moment it's read, so it can only fire once.

```php
Notify::confirm('Supprimer ce fichier ?')
    ->danger()
    ->onConfirm(fn () => Storage::delete($file))
    ->show();
```

Set `notify.actions.enabled` to `false` if you never pass raw closures (routes and Livewire methods don't need this endpoint at all).

## Existing `->with('success', ...)` calls, validation & exceptions

`<x-notify::root />` also picks up, on every request:

- **Flash keys** your app already sets — `redirect()->with('success', '...')`, Breeze/Jetstream's `status`, `error`, `warning`, `info` — mapped in `config('notify.session_bridge.keys')`. Existing controllers render as Notify toasts with zero code changes.
- **Validation failures**, as a dismissible error alert listing every message (`config('notify.validation')`) — skipped automatically on Livewire requests, since Livewire already renders its own inline field errors.

## Configuration

```bash
php artisan vendor:publish --tag=notify-config
```

See `config/notify.php` for the full reference: default position/duration per variant, `max_visible` before notifications collapse into a "N more" pill, the session-flash key bridge, validation/exception message policy, and the signed-action TTL.

## Security

- Signed, single-use, time-boxed callback URLs for `onConfirm(Closure ...)` — see [above](#confirmations--server-actions).
- `Notify::exception()` never surfaces `$e->getMessage()` outside `local`/`testing` environments unless you explicitly opt in via `config('notify.errors.local')`.
- All rendered strings (title, message, labels) go through the front-end's own escaping — nothing is ever injected as raw HTML from a payload.

## Roadmap

Database-backed persistent notifications, browser (native) notifications, presets, sound, and `Notify::dialog()->view()`/`->component()` for rendering arbitrary Blade or Livewire content inside the dialog shell — today `dialog()` supports title/message/icon/actions only, deliberately, rather than a half-finished remote-content pipeline.

## License

MIT — see [LICENSE.md](LICENSE.md).
