import { describe, it, expect, beforeEach, vi } from 'vitest';
import { loadNotify } from './support/loadNotify.js';

describe('color scheme', () => {
  beforeEach(() => {
    window.localStorage.clear();
    loadNotify();
  });

  it('defaults to following the OS/browser preference — no override attribute set', () => {
    expect(document.documentElement.getAttribute('data-notify-theme')).toBeNull();
    expect(Notify.getColorScheme()).toBe('system');
  });

  it('config(notify.color_scheme) sets the initial override', () => {
    loadNotify({ config: { colorScheme: 'dark' } });

    expect(document.documentElement.getAttribute('data-notify-theme')).toBe('dark');
    expect(Notify.getColorScheme()).toBe('dark');
  });

  it('setColorScheme() overrides it at runtime and persists to localStorage', () => {
    Notify.setColorScheme('dark');

    expect(document.documentElement.getAttribute('data-notify-theme')).toBe('dark');
    expect(window.localStorage.getItem('notify:color-scheme')).toBe('dark');

    Notify.setColorScheme('system');

    expect(document.documentElement.getAttribute('data-notify-theme')).toBeNull();
  });

  it('a stored localStorage choice wins over config(notify.color_scheme) on the next load', () => {
    window.localStorage.setItem('notify:color-scheme', 'light');

    loadNotify({ config: { colorScheme: 'dark' } });

    expect(document.documentElement.getAttribute('data-notify-theme')).toBe('light');
  });

  it('re-applies the forced scheme after a wire:navigate page swap resets <html> attributes', () => {
    loadNotify({ config: { colorScheme: 'light' } });

    // What Livewire's navigate does to the <html> element on the next page.
    document.documentElement.removeAttribute('data-notify-theme');
    document.dispatchEvent(new Event('livewire:navigated'));

    expect(document.documentElement.getAttribute('data-notify-theme')).toBe('light');
  });

  it('rejects an invalid scheme instead of silently doing nothing', () => {
    expect(() => Notify.setColorScheme('blue')).toThrow();
  });
});

describe('Livewire bridge', () => {
  it('unwraps the named-argument object Livewire.dispatch() delivers for notify:push', () => {
    const handlers = {};

    loadNotify({ livewire: { on: (name, cb) => { handlers[name] = cb; } } });

    handlers['notify:push']({ notification: { id: 'x', type: 'toast', variant: 'success', message: 'from Livewire' } });

    expect(document.querySelector('.notify-card__message').textContent).toBe('from Livewire');
  });

  it('unwraps notify:clear-group the same way', () => {
    const handlers = {};

    loadNotify({ livewire: { on: (name, cb) => { handlers[name] = cb; } } });

    Notify.toast({ message: 'x', group: 'imports' });
    handlers['notify:clear-group']({ group: 'imports' });

    expect(document.querySelector('.notify-card')).toBeNull();
  });

  it('notify:dismiss and notify:clear-all reach the store too', () => {
    const handlers = {};

    loadNotify({ livewire: { on: (name, cb) => { handlers[name] = cb; } } });

    const pending = Notify.success('x');
    handlers['notify:dismiss']({ id: pending.id() });
    expect(document.querySelector('.notify-card')).toBeNull();

    Notify.success('y');
    handlers['notify:clear-all']();
    expect(document.querySelector('.notify-card')).toBeNull();
  });

  it('binds immediately if window.Livewire already exists at load time', () => {
    const bound = [];

    loadNotify({ livewire: { on: (name) => bound.push(name) } });

    expect(bound).toContain('notify:push');
  });

  it('waits for livewire:init if window.Livewire is not present yet at load time', () => {
    loadNotify(); // Livewire absent — must not throw

    const handlers = {};
    window.Livewire = { on: (name, cb) => { handlers[name] = cb; } };
    document.dispatchEvent(new window.Event('livewire:init'));

    expect(handlers['notify:push']).toBeTypeOf('function');
  });
});

describe('Echo bridge', () => {
  function stubEcho(listeners) {
    return {
      private: () => ({
        listen(event, cb) {
          listeners[event] = cb;
          return this;
        },
      }),
    };
  }

  it('subscribes to CFG.broadcastChannel via Echo.private() and unwraps notify.push the same way as Livewire', () => {
    const listeners = {};

    loadNotify({ config: { broadcastChannel: 'notify.42' }, echo: stubEcho(listeners) });

    listeners['.notify.push']({ notification: { id: 'x', type: 'toast', variant: 'info', message: 'broadcasted' } });

    expect(document.querySelector('.notify-card__message').textContent).toBe('broadcasted');
  });

  it('routes notify.command events (dismiss/clearGroup/clearAll) straight to ingest()', () => {
    const listeners = {};

    loadNotify({ config: { broadcastChannel: 'notify.42' }, echo: stubEcho(listeners) });

    Notify.success('x');
    listeners['.notify.command']({ type: 'command', command: 'clearAll' });

    expect(document.querySelector('.notify-card')).toBeNull();
  });

  it('does nothing when broadcasting is configured but Echo is not loaded', () => {
    expect(() => loadNotify({ config: { broadcastChannel: 'notify.42' } })).not.toThrow();
  });

  it('does nothing when Echo is loaded but no channel was configured', () => {
    const privateSpy = vi.fn();

    loadNotify({ config: { broadcastChannel: null }, echo: { private: privateSpy } });

    expect(privateSpy).not.toHaveBeenCalled();
  });
});
