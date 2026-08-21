# Changelog

All notable changes to `salioudiabate/notify` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) — one entry per tag, newest first.

## [Unreleased]

Nothing yet.

## [1.0.0] - 2026-08-21

Initial release.

### Added

- Core notification types from one fluent `Notify::` API: `toast()`, `alert()`, `confirm()`, `dialog()`, `progress()`, plus `update($id)` to replace an existing one in place.
- Three delivery paths sharing a single JSON payload and front-end renderer: session flash (any plain Laravel app, Livewire or not), Livewire dispatch (instant, no reload) via the `InteractsWithNotifications` trait, and direct `window.Notify` calls with zero backend round-trip.
- One-liners for the common cases: `Notify::success()/error()/warning()/info()`, `Notify::alertSuccess()/alertError()/alertWarning()/alertInfo()`, `Notify::loading()`.
- `Notify::dismiss($id)`, `Notify::clearAll()`, `Notify::clearGroup($group)`, and `PendingNotification::dismiss()` — remote control over already-rendered notifications, including from a Livewire component with no page reload.
- `InteractsWithNotifications::notify()`/`confirm()` for the instant one-liner form, and `notifyBuilder()`/`confirmBuilder()` when the full fluent builder is needed instead (`->danger()`, `->group()`, `->confirmColor()`, ...). `notifyAction()` wires a loading → Livewire call → success/error sequence into a single Alpine expression.
- `onConfirm()` accepts a route name, a URL, a Livewire method name, or a raw `Closure` — closures run through a signed, single-use, lock-guarded callback URL so they work even in a plain Laravel app with no Livewire at all. `config('notify.actions.middleware')` adds extra middleware (e.g. `auth`) as defense in depth on top of the signature.
- `->toUser($user)` / `->toChannel($channel)`: push a notification to a specific user from outside the current request entirely (a queued job, a console command), over Laravel Echo — opt-in via `config('notify.broadcast')`.
- Fully pluggable rendering, none of it requiring a build step:
  - `Notify.registerTemplate()` / `->template()` / `config('notify.theme')` — reskin any notification type, per call or globally.
  - `Notify.registerIcon()` / `config('notify.icons')` — override or add named icons; `->icon('<svg>...</svg>')` accepts raw markup directly for a one-off icon.
  - `Notify.setStrings()` / `config('notify.strings')` — every piece of built-in UI chrome text (close label, "N more" pill, Esc hint, confirm/cancel defaults), separate from a payload's own free-form content.
  - `Notify.setButtonColors()` / `config('notify.button_colors')` — color per button style, globally or overridden for a single button via `->action()`/`->confirmColor()`/`->cancelColor()`/`AlertBuilder::button()`.
- Light/dark mode following `prefers-color-scheme` by default, overridable via `config('notify.color_scheme')` or `Notify.setColorScheme()`/`getColorScheme()` (persisted in `localStorage`).
- Automatic bridging of existing `redirect()->with('success', ...)` flashes (and Breeze/Jetstream's `status`) into toasts, and validation errors into a dismissible alert — zero code changes for apps that don't (yet) call `Notify::` directly.
- Every builder (`ToastBuilder`, `AlertBuilder`, `ConfirmBuilder`, `DialogBuilder`, `ProgressBuilder`, `UpdateBuilder`) is non-`final` and uses `Macroable`, so the PHP API is extensible without forking the package.
- Full test suite: 83 Pest tests (PHP) and 68 Vitest/jsdom tests (`resources/js/notify.js`), Larastan level 5, Laravel Pint — all running in CI across PHP 8.3 × Laravel 11/12/13.

### Fixed

- Naming collision: `success()`/`error()`/`warning()`/`info()` meant three different things depending on which class you called them on. Builder-side color setters (`ToastBuilder`, `AlertBuilder`, `DialogBuilder`, `ProgressBuilder`) are now `asSuccess()`/`asError()`/`asWarning()`/`asInfo()`, unambiguous from the message-taking, (near-)terminal methods of the same short names on `NotifyManager`/`PendingNotification`/`UpdateBuilder`.
- `AlertBuilder::button()` could previously only ever dismiss the alert — it now accepts a target and a color like `->action()` does.
- `config('notify.strings')`'s keys are now snake_case, consistent with every other key in the file (previously a lone camelCase island mirroring the JS side directly).

### Security

- `CallbackAction::resolve()`'s single-use guarantee is now enforced by an atomic lock (`config('notify.actions.lock_wait')`) — `get()` + `forget()` alone weren't atomic, so two near-simultaneous requests for the same signed token could both read the closure before either deleted it.
- Documented prominently (docblocks, config comments, README) that a signed callback URL alone doesn't authenticate anyone — a `Closure` performing a sensitive or destructive action should re-check authorization itself when it runs.

[Unreleased]: https://github.com/salioudiabate/notify/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/salioudiabate/notify/releases/tag/v1.0.0
