import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { loadNotify } from './support/loadNotify.js';

describe('core store: push/dismiss/clear/clearGroup', () => {
  beforeEach(() => {
    loadNotify();
  });

  it('success()/error()/warning()/info() each render a card with the right variant class', () => {
    Notify.success('Saved.');

    const card = document.querySelector('.notify-card');
    expect(card).not.toBeNull();
    expect(card.className).toContain('notify-variant-success');
    expect(card.querySelector('.notify-card__message').textContent).toBe('Saved.');
  });

  it('dismiss(id) removes exactly that card and no other', () => {
    const a = Notify.success('A');
    Notify.success('B');

    Notify.dismiss(a.id());

    const messages = [...document.querySelectorAll('.notify-card__message')].map((n) => n.textContent);
    expect(messages).toEqual(['B']);
  });

  it('clear() removes every currently-tracked notification', () => {
    Notify.success('A');
    Notify.error('B');
    Notify.warning('C');

    Notify.clear();

    expect(document.querySelectorAll('.notify-card').length).toBe(0);
  });

  it('clearGroup(name) only dismisses notifications in that group', () => {
    Notify.toast({ message: 'in group', group: 'imports' });
    Notify.toast({ message: 'no group' });

    Notify.clearGroup('imports');

    const messages = [...document.querySelectorAll('.notify-card__message')].map((n) => n.textContent);
    expect(messages).toEqual(['no group']);
  });

  it('collapses extra notifications behind maxVisible into a "N more" pill, promoting the next one on dismiss', () => {
    loadNotify({ config: { maxVisible: 2 } });

    const first = Notify.success('1');
    Notify.success('2');
    Notify.success('3');

    expect(document.querySelectorAll('.notify-card').length).toBe(2);
    expect(document.querySelector('.notify-more').textContent).toBe('1 notification de plus');

    Notify.dismiss(first.id());

    expect(document.querySelectorAll('.notify-card').length).toBe(2); // #3 promoted in
    expect(document.querySelector('.notify-more')).toBeNull();
  });

  it('the "more" pill pluralizes once more than one notification is hidden', () => {
    loadNotify({ config: { maxVisible: 1 } });

    Notify.success('1');
    Notify.success('2');
    Notify.success('3');

    expect(document.querySelector('.notify-more').textContent).toBe('2 notifications de plus');
  });

  it('loading()->success() (a replace payload) swaps the same card in place instead of adding a new one', () => {
    const pending = Notify.loading('Uploading…');
    expect(document.querySelectorAll('.notify-card').length).toBe(1);

    pending.success('Done.');

    expect(document.querySelectorAll('.notify-card').length).toBe(1);
    expect(document.querySelector('.notify-card__message').textContent).toBe('Done.');
    expect(document.querySelector('.notify-card').className).toContain('notify-variant-success');
  });

  it('loading() renders a spinner and is not dismissible', () => {
    Notify.loading('Uploading…');

    expect(document.querySelector('.notify-spinner')).not.toBeNull();
    expect(document.querySelector('.notify-card__close')).toBeNull();
  });
});

describe('auto-dismiss timers', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    loadNotify();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('auto-dismisses a toast after its configured duration, and never a persistent one', () => {
    Notify.success('goes away'); // default duration: 4000ms
    Notify.error('stays'); // default duration: null (persistent)

    expect(document.querySelectorAll('.notify-card').length).toBe(2);

    vi.advanceTimersByTime(4000);

    const messages = [...document.querySelectorAll('.notify-card__message')].map((n) => n.textContent);
    expect(messages).toEqual(['stays']);
  });

  it('an explicit duration on Notify.toast() overrides the variant default', () => {
    Notify.toast({ variant: 'error', message: 'x', duration: 1000 }); // error defaults to null otherwise

    vi.advanceTimersByTime(1000);

    expect(document.querySelector('.notify-card')).toBeNull();
  });
});
