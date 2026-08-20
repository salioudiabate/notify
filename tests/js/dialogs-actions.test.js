import { describe, it, expect, beforeEach, vi } from 'vitest';
import { loadNotify } from './support/loadNotify.js';

describe('confirm()/dialog()', () => {
  beforeEach(() => {
    loadNotify();
  });

  it('confirm() renders exactly a Cancel + Confirm pair by default, in French', () => {
    Notify.confirm({ title: 'Sure?' });

    const labels = [...document.querySelectorAll('.notify-dialog__footer .notify-btn')].map((b) => b.textContent);
    expect(labels).toEqual(['Annuler', 'Confirmer']);
  });

  it('onConfirm runs the given function and dismisses the dialog', () => {
    let ran = false;
    Notify.confirm({ title: 'Sure?', onConfirm: () => { ran = true; } });

    document.querySelector('.notify-dialog__footer .notify-btn--primary').click();

    expect(ran).toBe(true);
    expect(document.querySelector('.notify-dialog')).toBeNull();
  });

  it('cancel dismisses without running onConfirm', () => {
    let ran = false;
    Notify.confirm({ title: 'Sure?', onConfirm: () => { ran = true; } });

    document.querySelector('.notify-dialog__footer .notify-btn--secondary').click();

    expect(ran).toBe(false);
    expect(document.querySelector('.notify-dialog')).toBeNull();
  });

  it('danger() styles the confirm button as danger instead of primary', () => {
    Notify.confirm({ title: 'Delete?', danger: true });

    expect(document.querySelector('.notify-dialog__footer .notify-btn--danger')).not.toBeNull();
    expect(document.querySelector('.notify-dialog__footer .notify-btn--primary')).toBeNull();
  });

  it('queues a second dialog behind the first, showing it only once the first closes', () => {
    Notify.confirm({ title: 'First' });
    Notify.confirm({ title: 'Second' });

    expect(document.querySelector('.notify-dialog__title').textContent).toBe('First');
    expect(document.querySelectorAll('.notify-backdrop').length).toBe(1);

    document.querySelector('.notify-dialog__footer .notify-btn--secondary').click(); // cancel the first

    expect(document.querySelector('.notify-dialog__title').textContent).toBe('Second');
  });

  it('Escape closes the currently open dialog', () => {
    Notify.confirm({ title: 'Sure?' });
    expect(document.querySelector('.notify-dialog')).not.toBeNull();

    document.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape' }));

    expect(document.querySelector('.notify-dialog')).toBeNull();
  });

  it('clicking the backdrop (not the dialog itself) dismisses it', () => {
    Notify.confirm({ title: 'Sure?' });

    const backdrop = document.querySelector('.notify-backdrop');
    backdrop.dispatchEvent(new window.MouseEvent('mousedown', { bubbles: true }));

    expect(document.querySelector('.notify-dialog')).toBeNull();
  });

  it('shows the Esc hint by default, but the centered layout omits it', () => {
    Notify.confirm({ title: 'Sure?' });
    expect(document.querySelector('.notify-kbd')).not.toBeNull();

    document.querySelector('.notify-dialog__footer .notify-btn--secondary').click();

    // dialog() (unlike confirm()) has no direct JS entry point — it only ever
    // arrives as a raw payload, same as an alert, via the session queue or a
    // Livewire dispatch.
    loadNotify({ queue: [{
      id: 'x', type: 'dialog', variant: 'success', title: 't', message: null, icon: null,
      duration: null, position: 'top-right', dismissible: true, persistent: true,
      group: null, actions: [], url: null, progress: null, meta: { centered: true }, replace: false,
    }] });

    expect(document.querySelector('.notify-kbd')).toBeNull();
  });
});

describe('action targets', () => {
  beforeEach(() => {
    loadNotify();
  });

  it('a "callback" target POSTs to the given URL with CSRF headers', () => {
    const fetchMock = vi.fn().mockResolvedValue({});
    window.fetch = fetchMock;

    Notify.toast({ message: 'x', actions: [{ label: 'Go', target: { type: 'callback', url: '/notify/actions/abc' } }] });
    document.querySelector('.notify-btn').click();

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock.mock.calls[0][0]).toBe('/notify/actions/abc');
    expect(fetchMock.mock.calls[0][1].method).toBe('POST');
  });

  it('a "livewire" target calls Livewire.find(component).call(method, ...params)', () => {
    const calls = [];
    window.Livewire = { find: (id) => ({ call: (...args) => calls.push([id, ...args]) }) };

    Notify.toast({ message: 'x', actions: [{ label: 'Go', target: { type: 'livewire', component: 'abc', method: 'restore', params: [1, 2] } }] });
    document.querySelector('.notify-btn').click();

    expect(calls).toEqual([['abc', 'restore', 1, 2]]);
  });

  it('a "js" target (used internally by confirm()) just runs the given function', () => {
    let ran = false;
    Notify.toast({ message: 'x', actions: [{ label: 'Go', target: { type: 'js', run: () => { ran = true; } } }] });

    document.querySelector('.notify-btn').click();

    expect(ran).toBe(true);
  });

  it('a null target is a plain dismiss button — no action, just closes', () => {
    Notify.toast({ message: 'x', actions: [{ label: 'OK', target: null }] });

    document.querySelector('.notify-btn').click();

    expect(document.querySelector('.notify-card')).toBeNull();
  });

  it('closesDialog: false keeps the notification visible after the action runs', () => {
    Notify.toast({ message: 'x', actions: [{ label: 'Go', target: { type: 'js', run: () => {} }, closesDialog: false }] });

    document.querySelector('.notify-btn').click();

    expect(document.querySelector('.notify-card')).not.toBeNull();
  });
});

describe('notifyAction()', () => {
  beforeEach(() => {
    loadNotify();
  });

  it('shows a loading toast immediately and calls $wire.call with the given method/params', () => {
    const $wire = { call: vi.fn().mockResolvedValue(undefined) };

    window.notifyAction($wire, 'delete', [1], { loading: 'Deleting…', success: 'Deleted.' });

    expect(document.querySelector('.notify-card__message').textContent).toBe('Deleting…');
    expect($wire.call).toHaveBeenCalledWith('delete', 1);
  });

  it('swaps the loading toast to success once $wire.call resolves', async () => {
    const $wire = { call: vi.fn().mockResolvedValue(undefined) };

    window.notifyAction($wire, 'delete', [], { success: 'Deleted.' });

    await vi.waitFor(() => {
      expect(document.querySelector('.notify-card__message').textContent).toBe('Deleted.');
    });
    expect(document.querySelector('.notify-card').className).toContain('notify-variant-success');
  });

  it('swaps to error with the built-in default message if $wire.call rejects and none is given', async () => {
    const $wire = { call: vi.fn().mockRejectedValue(new Error('nope')) };

    window.notifyAction($wire, 'delete', [], {});

    await vi.waitFor(() => {
      expect(document.querySelector('.notify-card__message').textContent).toBe('Une erreur est survenue.');
    });
  });
});
