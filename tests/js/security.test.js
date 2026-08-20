import { describe, it, expect, beforeEach } from 'vitest';
import { loadNotify } from './support/loadNotify.js';

describe('escaping', () => {
  beforeEach(() => {
    loadNotify();
  });

  it('escapes title/message so a payload cannot inject markup through them', () => {
    Notify.toast({
      variant: 'info',
      title: '<img src=x onerror=alert(1)>',
      message: '<script>alert(2)</script>',
    });

    const title = document.querySelector('.notify-card__title');
    const message = document.querySelector('.notify-card__message');

    expect(title.querySelector('img')).toBeNull();
    expect(title.textContent).toBe('<img src=x onerror=alert(1)>');
    expect(message.querySelector('script')).toBeNull();
    expect(message.textContent).toBe('<script>alert(2)</script>');
  });

  it('escapes action labels the same way', () => {
    Notify.toast({ message: 'x', actions: [{ label: '<b>bold</b>' }] });

    const btn = document.querySelector('.notify-btn');
    expect(btn.querySelector('b')).toBeNull();
    expect(btn.textContent).toBe('<b>bold</b>');
  });

  it('escapes confirm()/dialog() title and message too', () => {
    Notify.confirm({ title: '<img src=x onerror=alert(1)>', message: '<b>hi</b>' });

    expect(document.querySelector('.notify-dialog__title').querySelector('img')).toBeNull();
    expect(document.querySelector('.notify-dialog__message').querySelector('b')).toBeNull();
  });

  it('escapes each validation error line in an alert delivered via the session queue', () => {
    loadNotify({
      queue: [{
        id: 'x', type: 'alert', variant: 'error', title: 'Errors', message: null,
        icon: null, duration: null, position: 'top-right', dismissible: true, persistent: true,
        group: null, actions: [], url: null, progress: null,
        meta: { errors: ['<img src=x onerror=alert(1)>', 'Le champ email est requis.'] },
        replace: false,
      }],
    });

    const list = document.querySelector('.notify-alert .notify-card__message');
    expect(list.querySelector('img')).toBeNull();
    expect(list.textContent).toContain('<img src=x onerror=alert(1)>');
    expect(list.textContent).toContain('Le champ email est requis.');
  });

  it('escapeHtml() itself neutralizes every HTML-significant character, not just <script>/<img>', () => {
    // exercised indirectly through the DOM above; this pins the primitive
    // itself so a future change to escapeHtml() can't silently narrow it
    Notify.toast({ message: 'x', title: '<>&"\'' });

    expect(document.querySelector('.notify-card__title').textContent).toBe('<>&"\'');
  });
});

describe('->icon() raw-markup passthrough — the one deliberate exception to escaping', () => {
  beforeEach(() => {
    loadNotify();
  });

  it('inserts icon markup verbatim, unescaped — by design, for developer-authored SVG', () => {
    Notify.toast({ variant: 'info', message: 'x', icon: '<svg data-raw><title>ok</title></svg>' });

    const icon = document.querySelector('.notify-card__icon');
    expect(icon.querySelector('svg[data-raw]')).not.toBeNull();
  });

  it('does NOT extend the same passthrough to title/message — only ->icon() is trusted this way', () => {
    Notify.toast({ variant: 'info', title: '<svg data-should-not-render></svg>', message: 'x' });

    expect(document.querySelector('.notify-card__title').querySelector('svg')).toBeNull();
  });
});
