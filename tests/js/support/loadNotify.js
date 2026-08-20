import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const NOTIFY_JS_PATH = path.resolve(__dirname, '../../../resources/js/notify.js');
const SOURCE = readFileSync(NOTIFY_JS_PATH, 'utf8');

/**
 * notify.js is a plain IIFE (no build step, no ES module — see the README)
 * that reads window.__NOTIFY_CONFIG__/window.__NOTIFY_QUEUE__ once at
 * evaluation time and keeps all its state (items, rendered nodes, ICONS,
 * STRINGS, templates, the open dialog...) in closures with no way to reset
 * them from outside. So every test gets a clean slate by resetting the DOM
 * and globals, then re-evaluating the script itself — exactly what a fresh
 * <script> tag on a fresh page load would do.
 *
 * animations defaults to false here (unlike the real default of true) so
 * dismiss() removes nodes synchronously — tests that specifically want to
 * exercise the leaving-class/timeout behavior can still opt back in.
 */
export function loadNotify({ config = {}, queue = [], livewire, echo } = {}) {
  document.documentElement.removeAttribute('data-notify-theme');
  document.body.innerHTML = '<div id="notify-root"></div>';

  delete window.Notify;
  delete window.notifyAction;
  // Livewire/Echo are stubbed per-test via the livewire/echo options above,
  // never by setting window.Livewire/window.Echo directly before calling
  // this — that would already be wiped by the resets below, since Vitest's
  // jsdom window is shared across every test in the same file.
  delete window.Livewire;
  delete window.Echo;
  if (livewire !== undefined) window.Livewire = livewire;
  if (echo !== undefined) window.Echo = echo;

  window.__NOTIFY_CONFIG__ = {
    position: 'top-right',
    maxVisible: 4,
    dismissible: true,
    animations: false,
    theme: null,
    colorScheme: null,
    icons: {},
    strings: {},
    buttonColors: {},
    broadcastChannel: null,
    ...config,
  };
  window.__NOTIFY_QUEUE__ = queue;

  // Indirect eval forces global scope — window/document below resolve to
  // this test's jsdom globals, exactly like a real <script> tag would,
  // rather than this module's own local scope.
  (0, eval)(SOURCE);

  return window.Notify;
}
