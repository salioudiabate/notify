import { describe, it, expect, beforeEach } from 'vitest';
import { loadNotify } from './support/loadNotify.js';

/*
 * Livewire's wire:navigate (and Turbo, htmx boosting...) swap the whole <body>:
 * #notify-root and every stack/dialog cached by the runtime are then detached.
 */
function swapBody() {
  document.body.innerHTML = '<div id="notify-root"></div>';
}

describe('surviving a body swap (wire:navigate)', () => {
  beforeEach(() => {
    loadNotify();
  });

  it('renders toasts in the new #notify-root instead of a detached cached stack', () => {
    Notify.success('Before');
    swapBody();

    Notify.success('After');

    const card = document.querySelector('.notify-card');
    expect(card).not.toBeNull();
    expect(card.querySelector('.notify-card__message').textContent).toBe('After');
    expect(document.getElementById('notify-root').contains(card)).toBe(true);
  });

  it('opens a new dialog instead of queueing it behind one that left the page', () => {
    Notify.confirm({ title: 'First?' });
    swapBody();

    Notify.confirm({ title: 'Second?' });

    const dialogs = document.querySelectorAll('.notify-dialog');
    expect(dialogs).toHaveLength(1);
    expect(dialogs[0].textContent).toContain('Second?');
  });
});
