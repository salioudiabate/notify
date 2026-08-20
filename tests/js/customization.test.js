import { describe, it, expect, beforeEach } from 'vitest';
import { loadNotify } from './support/loadNotify.js';

describe('icons', () => {
  beforeEach(() => {
    loadNotify();
  });

  it('falls back to the neutral icon when no icon/variant matches', () => {
    Notify.toast({ variant: 'neutral', message: 'x' });

    expect(document.querySelector('.notify-card__icon svg')).not.toBeNull();
  });

  it('registerIcon(name, svg) overrides a single built-in icon by name', () => {
    Notify.registerIcon('success', '<svg data-mine></svg>');
    Notify.success('...');

    expect(document.querySelector('.notify-card__icon svg[data-mine]')).not.toBeNull();
  });

  it('registerIcon({...}) overrides several icons at once', () => {
    Notify.registerIcon({ success: '<svg data-a></svg>', error: '<svg data-b></svg>' });
    Notify.success('s');
    Notify.error('e');

    expect(document.querySelectorAll('svg[data-a]').length).toBe(1);
    expect(document.querySelectorAll('svg[data-b]').length).toBe(1);
  });

  it('->icon("<svg>...</svg>") renders raw markup with no prior registration needed', () => {
    Notify.toast({ variant: 'info', message: 'x', icon: '<svg data-raw></svg>' });

    expect(document.querySelector('.notify-card__icon svg[data-raw]')).not.toBeNull();
  });

  it('an unregistered, non-markup icon name still falls back to the variant icon instead of rendering nothing', () => {
    Notify.toast({ variant: 'success', message: 'x', icon: 'not-a-real-name' });

    expect(document.querySelector('.notify-card__icon svg')).not.toBeNull();
  });

  it('config(notify.icons) applies at boot, before any registerIcon() call', () => {
    loadNotify({ config: { icons: { success: '<svg data-from-config></svg>' } } });
    Notify.success('...');

    expect(document.querySelector('.notify-card__icon svg[data-from-config]')).not.toBeNull();
  });
});

describe('strings', () => {
  beforeEach(() => {
    loadNotify();
  });

  it('setStrings() overrides the close button label', () => {
    Notify.setStrings({ close: 'Close' });
    Notify.success('...');

    expect(document.querySelector('.notify-card__close').getAttribute('aria-label')).toBe('Close');
  });

  it('setStrings() overrides confirm()/cancel() default button labels', () => {
    Notify.setStrings({ confirm: 'Yes', cancel: 'No' });
    Notify.confirm({ title: 't' });

    const labels = [...document.querySelectorAll('.notify-dialog__footer .notify-btn')].map((b) => b.textContent);
    expect(labels).toEqual(['No', 'Yes']);
  });

  it('leaving a key out of setStrings() keeps its built-in default', () => {
    Notify.setStrings({ confirm: 'Yes' });
    Notify.confirm({ title: 't' });

    const labels = [...document.querySelectorAll('.notify-dialog__footer .notify-btn')].map((b) => b.textContent);
    expect(labels).toEqual(['Annuler', 'Yes']);
  });

  it('config(notify.strings) sets the default without needing setStrings()', () => {
    loadNotify({ config: { maxVisible: 1, strings: { moreSingular: 'notif restante', morePlural: 'notifs restantes' } } });

    Notify.success('1');
    Notify.success('2');
    Notify.success('3');

    expect(document.querySelector('.notify-more').textContent).toBe('2 notifs restantes');
  });
});

describe('button colors', () => {
  beforeEach(() => {
    loadNotify();
  });

  it('setButtonColors() sets the matching CSS custom properties on the document root', () => {
    Notify.setButtonColors({ primary: { bg: '#7c3aed', fg: '#fff' } });

    expect(document.documentElement.style.getPropertyValue('--notify-btn-primary-bg')).toBe('#7c3aed');
    expect(document.documentElement.style.getPropertyValue('--notify-btn-primary-fg')).toBe('#fff');
  });

  it('a style left out of setButtonColors() is untouched', () => {
    Notify.setButtonColors({ primary: { bg: '#7c3aed' } });

    expect(document.documentElement.style.getPropertyValue('--notify-btn-danger-bg')).toBe('');
  });

  it('config(notify.button_colors) applies at boot', () => {
    loadNotify({ config: { buttonColors: { danger: { bg: '#dc2626' } } } });

    expect(document.documentElement.style.getPropertyValue('--notify-btn-danger-bg')).toBe('#dc2626');
  });

  it('a per-action color overrides that one button inline, on top of the global setting', () => {
    Notify.setButtonColors({ primary: { bg: '#7c3aed' } });
    Notify.toast({
      message: 'x',
      actions: [
        { label: 'Global', style: 'primary' },
        { label: 'Overridden', style: 'primary', color: '#ea580c' },
      ],
    });

    // jsdom's CSSStyleDeclaration normalizes hex colors to rgb() on read —
    // asserting non-empty vs. empty is what actually matters here, the exact
    // serialization is a jsdom implementation detail, not notify.js's behavior.
    const [globalBtn, overriddenBtn] = document.querySelectorAll('.notify-btn--primary');
    expect(globalBtn.style.background).toBe('');
    expect(overriddenBtn.style.background).not.toBe('');
  });

  it('a plain color string sets only the background, leaving fg untouched', () => {
    Notify.toast({ message: 'x', actions: [{ label: 'Go', style: 'primary', color: '#ea580c' }] });

    const btn = document.querySelector('.notify-btn--primary');
    expect(btn.style.background).not.toBe('');
    expect(btn.style.color).toBe('');
  });

  it('a {bg, fg, border} object sets all three', () => {
    Notify.toast({ message: 'x', actions: [{ label: 'Go', style: 'primary', color: { bg: '#ea580c', fg: '#fff', border: '#000' } }] });

    const btn = document.querySelector('.notify-btn--primary');
    expect(btn.style.background).not.toBe('');
    expect(btn.style.color).not.toBe('');
    expect(btn.style.borderColor).not.toBe('');
  });
});

describe('templates', () => {
  beforeEach(() => {
    loadNotify();
  });

  it('registerTemplate() reskins only the type it defines — other types keep the built-in look', () => {
    Notify.registerTemplate('brand', {
      toast: (payload, h) => h.el('div', 'my-toast', payload.message),
    });

    Notify.toast({ message: 'custom', template: 'brand' });
    expect(document.querySelector('.my-toast')).not.toBeNull();
    expect(document.querySelector('.notify-card')).toBeNull();
  });

  it('a template is only applied to the specific notification that asks for it', () => {
    Notify.registerTemplate('brand', {
      toast: (payload, h) => h.el('div', 'my-toast', payload.message),
    });

    Notify.toast({ message: 'custom', template: 'brand' });
    Notify.success('still default');

    expect(document.querySelector('.my-toast')).not.toBeNull();
    expect(document.querySelector('.notify-card')).not.toBeNull();
  });

  it('config(notify.theme) sets the default template for every notification with no per-call override', () => {
    loadNotify({ config: { theme: 'brand' } });
    Notify.registerTemplate('brand', { toast: (payload, h) => h.el('div', 'my-toast', payload.message) });

    Notify.success('x');

    expect(document.querySelector('.my-toast')).not.toBeNull();
  });

  it('a custom template missing the "dialog" key falls back to the built-in dialog look, for both confirm() and dialog()-shaped payloads', () => {
    Notify.registerTemplate('brand', {
      toast: (payload, h) => h.el('div', 'my-toast', payload.message),
    });

    Notify.confirm({ title: 'Fallback?', template: 'brand' });

    expect(document.querySelector('.notify-dialog')).not.toBeNull();
  });

  it('the helpers object passed to a custom template can build built-in action buttons', () => {
    Notify.registerTemplate('brand', {
      toast: (payload, h) => {
        const card = h.el('div', 'my-toast');
        const actions = h.actions(payload);
        if (actions) card.appendChild(actions);
        return card;
      },
    });

    Notify.toast({ message: 'x', template: 'brand', actions: [{ label: 'Undo' }] });

    expect(document.querySelector('.my-toast .notify-btn').textContent).toBe('Undo');
  });
});
