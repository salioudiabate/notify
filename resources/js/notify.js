/*!
 * Notify — front-end runtime. No dependencies (not even Alpine): a plain
 * Laravel page with nothing but this script and notify.css already gets the
 * full toast/alert/confirm/dialog/progress system. If Livewire is present,
 * it's bridged automatically below; nothing here requires it.
 */
(function () {
  'use strict';

  var CFG = window.__NOTIFY_CONFIG__ || { position: 'top-right', maxVisible: 4, dismissible: true, animations: true };

  /** @type {Array<object>} every currently-tracked payload, rendered or queued behind max_visible */
  var items = [];
  var rendered = Object.create(null); // id -> DOM node
  var timers = Object.create(null);   // id -> setTimeout handle
  var stacks = Object.create(null);   // position -> stack element
  var dialogQueue = [];
  var openDialogEl = null;

  // ------------------------------------------------------------- templates --

  /**
   * name -> { toast?, alert?, progress?, dialog? }, each (payload, helpers) => HTMLElement.
   * "default" is registered near the bottom, once its builder functions
   * exist. A custom template only needs to define the types it wants to
   * reskin — anything it omits falls back to "default" automatically (see
   * resolveRenderer()). Registered via Notify.registerTemplate() — see
   * README § Custom templates.
   */
  var templates = Object.create(null);

  function resolveRenderer(payload, type) {
    var name = payload.template || CFG.theme || 'default';
    var tpl = templates[name] || templates.default;

    return tpl[type] || templates.default[type];
  }

  function helpers() {
    return {
      el: el,
      escapeHtml: escapeHtml,
      icon: iconMarkup,
      actions: buildActions,
      runAction: runAction,
      dismiss: dismiss,
    };
  }

  // registered here (not where the functions are defined below) so it reads
  // as the seam it is; safe because function declarations are hoisted.
  templates.default = {
    toast: buildCard,
    alert: buildAlertCard,
    progress: buildProgressCard,
    dialog: defaultDialogTemplate,
  };

  // ---------------------------------------------------------------- icons --

  var ICONS = {
    success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9"/><path d="M12 8v5" stroke-linecap="round"/><circle cx="12" cy="16" r="1" fill="currentColor" stroke="none"/></svg>',
    warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M12 3 2 20h20L12 3Z" stroke-linejoin="round"/><path d="M12 10v4" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="currentColor" stroke="none"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9"/><path d="M12 11v5" stroke-linecap="round"/><circle cx="12" cy="8" r="1" fill="currentColor" stroke="none"/></svg>',
    neutral: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9"/></svg>',
    trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-9 0 1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/></svg>',
  };

  function iconMarkup(payload) {
    if (payload.variant === 'loading') {
      return '<span class="notify-spinner"></span>';
    }

    return ICONS[payload.icon] || ICONS[payload.variant] || ICONS.neutral;
  }

  // ------------------------------------------------------------- security --

  function csrfHeaders() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.content : null;

    if (!token) {
      var match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
      token = match ? decodeURIComponent(match[1]) : null;
    }

    var headers = { 'X-Requested-With': 'XMLHttpRequest' };
    if (token) {
      headers['X-CSRF-TOKEN'] = token;
      headers['X-XSRF-TOKEN'] = token;
    }

    return headers;
  }

  // ------------------------------------------------------------------ dom --

  function el(tag, className, html) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (html !== undefined) node.innerHTML = html;
    return node;
  }

  function stackFor(position) {
    if (stacks[position]) return stacks[position];

    var node = el('div', 'notify-stack');
    node.setAttribute('data-position', position);
    document.body.appendChild(node);
    stacks[position] = node;

    return node;
  }

  function buildActions(payload) {
    var wrap = el('div', 'notify-card__actions');

    (payload.actions || []).forEach(function (action) {
      var btn = el('button', 'notify-btn notify-btn--' + (action.style || 'ghost'), escapeHtml(action.label));
      btn.type = 'button';
      btn.addEventListener('click', function () {
        runAction(action, payload);
      });
      wrap.appendChild(btn);
    });

    return wrap.childNodes.length ? wrap : null;
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function runAction(action, payload) {
    var target = action.target;

    if (target) {
      if (target.type === 'js' && typeof target.run === 'function') {
        target.run();
      } else if (target.type === 'url') {
        window.location.href = target.url;
      } else if (target.type === 'callback') {
        fetch(target.url, { method: 'POST', headers: csrfHeaders() }).catch(function () {});
      } else if (target.type === 'livewire' && window.Livewire) {
        var component = window.Livewire.find(target.component);
        if (component) {
          component.call.apply(component, [target.method].concat(target.params || []));
        }
      }
    }

    if (action.closesDialog !== false) {
      dismiss(payload.id);
    }
  }

  // --------------------------------------------------------- toast/alert --

  function buildCard(payload) {
    var card = el('div', 'notify-card notify-variant-' + payload.variant);
    card.setAttribute('data-notify-id', payload.id);

    var body = el('div', 'notify-card__body');
    body.appendChild(el('span', 'notify-card__icon', iconMarkup(payload)));

    var content = el('div', 'notify-card__content');
    if (payload.title) content.appendChild(el('div', 'notify-card__title', escapeHtml(payload.title)));
    if (payload.message) content.appendChild(el('div', 'notify-card__message', escapeHtml(payload.message)));
    var actions = buildActions(payload);
    if (actions) content.appendChild(actions);
    body.appendChild(content);

    if (payload.dismissible !== false) {
      var close = el('button', 'notify-card__close', ICONS.close);
      close.type = 'button';
      close.setAttribute('aria-label', 'Fermer');
      close.addEventListener('click', function () { dismiss(payload.id); });
      body.appendChild(close);
    }

    card.appendChild(body);

    if (payload.duration) {
      var track = el('div', 'notify-track');
      var fill = el('div', 'notify-track__fill');
      fill.style.animationDuration = payload.duration + 'ms';
      track.appendChild(fill);
      card.appendChild(track);
    }

    return card;
  }

  function buildAlertCard(payload) {
    var card = el('div', 'notify-alert');
    card.setAttribute('data-notify-id', payload.id);
    card.setAttribute('data-variant', payload.variant);

    card.appendChild(el('span', 'notify-card__icon', iconMarkup(payload)));

    var content = el('div', 'notify-card__content');
    if (payload.title) content.appendChild(el('div', 'notify-card__title', escapeHtml(payload.title)));
    if (payload.message) content.appendChild(el('div', 'notify-card__message', escapeHtml(payload.message)));

    if (payload.meta && Array.isArray(payload.meta.errors) && payload.meta.errors.length) {
      var list = el('div', 'notify-card__message');
      list.innerHTML = payload.meta.errors.map(function (e) { return '&middot; ' + escapeHtml(e); }).join('<br>');
      content.appendChild(list);
    }

    var actions = buildActions(payload);
    if (actions) content.appendChild(actions);
    card.appendChild(content);

    if (payload.dismissible !== false) {
      var close = el('button', 'notify-card__close', ICONS.close);
      close.type = 'button';
      close.addEventListener('click', function () { dismiss(payload.id); });
      card.appendChild(close);
    }

    return card;
  }

  function buildProgressCard(payload) {
    var card = el('div', 'notify-card');
    card.setAttribute('data-notify-id', payload.id);

    var head = el('div', 'notify-progress__head');
    head.appendChild(el('span', 'notify-progress__icon', iconMarkup(payload)));

    var text = el('div', 'notify-progress__text');
    if (payload.title) text.appendChild(el('div', 'notify-card__title', escapeHtml(payload.title)));
    if (payload.message) text.appendChild(el('div', 'notify-card__message', escapeHtml(payload.message)));
    head.appendChild(text);

    if (typeof payload.progress === 'number') {
      head.appendChild(el('div', 'notify-progress__percent', payload.progress + '%'));
    }
    card.appendChild(head);

    var bar = el('div', 'notify-progress__bar');
    var fill = el('div', 'notify-progress__bar-fill');
    fill.style.width = (payload.progress || 0) + '%';
    bar.appendChild(fill);
    card.appendChild(bar);

    var foot = el('div', 'notify-progress__foot');
    var status = el('span', null, escapeHtml((payload.meta && payload.meta.status) || ''));
    foot.appendChild(status);
    var actions = buildActions(payload);
    if (actions) foot.appendChild(actions);
    card.appendChild(foot);

    return card;
  }

  function buildFor(payload) {
    return resolveRenderer(payload, payload.type)(payload, helpers());
  }

  function isStackable(payload) {
    return payload.type === 'toast' || payload.type === 'alert' || payload.type === 'progress';
  }

  function visibleFor(position) {
    return items.filter(function (p) { return p.position === position && isStackable(p); });
  }

  function refreshMorePill(position) {
    var stack = stackFor(position);
    var pill = stack.querySelector('.notify-more');
    var list = visibleFor(position);
    var hidden = list.length - Object.keys(rendered).filter(function (id) {
      var p = list.find(function (x) { return x.id === id; });
      return !!p;
    }).length;

    if (pill) pill.remove();

    if (hidden > 0) {
      var text = hidden + (hidden > 1 ? ' notifications de plus' : ' notification de plus');
      stack.appendChild(el('div', 'notify-more', text));
    }
  }

  function scheduleAutoDismiss(payload) {
    if (!payload.duration) return;
    clearTimeout(timers[payload.id]);
    timers[payload.id] = setTimeout(function () { dismiss(payload.id); }, payload.duration);
  }

  // ---------------------------------------------------------------- push --

  function push(payload) {
    if (payload.replace) {
      var existingIndex = items.findIndex(function (p) { return p.id === payload.id; });

      if (existingIndex !== -1) {
        items[existingIndex] = payload;

        if (rendered[payload.id]) {
          var fresh = buildFor(payload);
          rendered[payload.id].replaceWith(fresh);
          rendered[payload.id] = fresh;
          scheduleAutoDismiss(payload);
        }

        return;
      }
    }

    items.push(payload);

    if (payload.type === 'confirm' || payload.type === 'dialog') {
      showDialog(payload);
      return;
    }

    var max = CFG.maxVisible || 4;
    var currentlyVisible = visibleFor(payload.position).filter(function (p) { return rendered[p.id]; }).length;

    if (currentlyVisible < max) {
      var node = buildFor(payload);
      stackFor(payload.position).appendChild(node);
      rendered[payload.id] = node;
      scheduleAutoDismiss(payload);
    }

    refreshMorePill(payload.position);
  }

  function dismiss(id) {
    var index = items.findIndex(function (p) { return p.id === id; });
    if (index === -1) return;

    var payload = items[index];
    items.splice(index, 1);
    clearTimeout(timers[id]);
    delete timers[id];

    var node = rendered[id];
    delete rendered[id];

    if (payload.type === 'confirm' || payload.type === 'dialog') {
      closeDialog();
      return;
    }

    if (node) {
      if (CFG.animations) {
        node.classList.add('notify-leaving');
        setTimeout(function () { node.remove(); }, 160);
      } else {
        node.remove();
      }
    }

    // promote the next queued item for this position, if any
    var next = visibleFor(payload.position).find(function (p) { return !rendered[p.id]; });
    if (next) {
      var freshNode = buildFor(next);
      stackFor(next.position).appendChild(freshNode);
      rendered[next.id] = freshNode;
      scheduleAutoDismiss(next);
    }

    refreshMorePill(payload.position);
  }

  function clearGroup(group) {
    items.filter(function (p) { return p.group === group; })
      .map(function (p) { return p.id; })
      .forEach(dismiss);
  }

  function clearAll() {
    items.slice().forEach(function (p) { dismiss(p.id); });
  }

  // --------------------------------------------------------------- dialog --

  /**
   * The default look for both 'confirm' and 'dialog' payloads. A custom
   * template can override just this one key ({ dialog: fn }) and everything
   * else (toast/alert/progress) keeps rendering with the built-in look.
   * Backdrop, queueing, focus and Esc-to-close stay in showDialog() below —
   * that's shared plumbing, not "design".
   */
  function defaultDialogTemplate(payload, h) {
    var dialog = h.el('div', 'notify-dialog');
    dialog.setAttribute('data-centered', !!(payload.meta && payload.meta.centered));

    var iconWrap = h.el('div', 'notify-dialog__icon', h.icon({ variant: payload.variant, icon: payload.icon || (payload.variant === 'error' ? 'trash' : payload.variant) }));
    iconWrap.setAttribute('data-variant', payload.variant);
    dialog.appendChild(iconWrap);

    if (payload.title) dialog.appendChild(h.el('div', 'notify-dialog__title', h.escapeHtml(payload.title)));
    if (payload.message) dialog.appendChild(h.el('div', 'notify-dialog__message', h.escapeHtml(payload.message)));

    var footer = h.el('div', 'notify-dialog__footer');
    if (!(payload.meta && payload.meta.centered)) {
      footer.appendChild(h.el('div', 'notify-kbd', '<span>Esc</span> pour fermer'));
    }

    var buttons = h.el('div', 'notify-card__actions');
    (payload.actions || []).forEach(function (action) {
      var btn = h.el('button', 'notify-btn notify-btn--' + (action.style || 'secondary'), h.escapeHtml(action.label));
      btn.type = 'button';
      btn.addEventListener('click', function () { h.runAction(action, payload); });
      buttons.appendChild(btn);
    });
    footer.appendChild(buttons);
    dialog.appendChild(footer);

    return dialog;
  }

  function showDialog(payload) {
    if (openDialogEl) {
      dialogQueue.push(payload);
      return;
    }

    var renderDialog = resolveRenderer(payload, 'dialog');
    var dialog = renderDialog(payload, helpers());

    var backdrop = el('div', 'notify-backdrop');
    backdrop.setAttribute('data-notify-id', payload.id);
    backdrop.appendChild(dialog);
    backdrop.addEventListener('mousedown', function (e) {
      if (e.target === backdrop) dismiss(payload.id);
    });

    document.body.appendChild(backdrop);
    openDialogEl = backdrop;
    rendered[payload.id] = backdrop;

    var firstButton = backdrop.querySelector('button');
    if (firstButton) firstButton.focus();
  }

  function closeDialog() {
    if (openDialogEl) {
      openDialogEl.remove();
      openDialogEl = null;
    }

    var next = dialogQueue.shift();
    if (next) showDialog(next);
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && openDialogEl) {
      dismiss(openDialogEl.getAttribute('data-notify-id'));
    }
  });

  // ------------------------------------------------------------ ingestion --

  function ingest(item) {
    if (item.type === 'command' && item.command === 'clearGroup') {
      clearGroup(item.group);
      return;
    }

    push(item);
  }

  function uuid() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = (Math.random() * 16) | 0, v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  }

  // -------------------------------------------------------------- Notify --

  function makePending(id) {
    // replace payloads fully replace the tracked item (see push()), so every
    // patch here is expanded into a complete, well-formed payload rather
    // than relying on fields carrying over from whatever this id used to be.
    function updateWith(patch) {
      ingest(Object.assign({
        id: id, replace: true, type: patch.progress !== undefined ? 'progress' : 'toast',
        icon: null, position: CFG.position, dismissible: true, persistent: false,
        group: null, actions: [], url: null, progress: null, meta: {},
      }, patch));

      return pending;
    }

    var pending = {
      id: function () { return id; },
      success: function (message, title) { return updateWith({ variant: 'success', message: message, title: title || null, duration: 4000 }); },
      error: function (message, title) { return updateWith({ variant: 'error', message: message, title: title || null, duration: null, persistent: true }); },
      warning: function (message, title) { return updateWith({ variant: 'warning', message: message, title: title || null, duration: 6000 }); },
      info: function (message, title) { return updateWith({ variant: 'info', message: message, title: title || null, duration: 4000 }); },
      progress: function (percent, status) { return updateWith({ variant: 'neutral', progress: percent, meta: { status: status || '' } }); },
    };

    return pending;
  }

  function basicToast(variant, message, title, duration) {
    var id = uuid();
    ingest({
      id: id, type: 'toast', variant: variant, title: title || null, message: message,
      icon: null, duration: duration, position: CFG.position, dismissible: true,
      persistent: false, group: null, actions: [], url: null, progress: null, meta: {}, replace: false,
    });
    return makePending(id);
  }

  window.Notify = {
    /**
     * The escape hatch behind success()/error()/warning()/info(): every
     * field a toast payload supports, including which template renders it —
     * matching PHP's Notify::toast()->template('x') for pages with no
     * backend call at all.
     */
    toast: function (options) {
      options = options || {};
      var id = uuid();
      ingest({
        id: id, type: 'toast', variant: options.variant || 'neutral',
        title: options.title || null, message: options.message || null,
        icon: options.icon || null, duration: options.duration !== undefined ? options.duration : 4000,
        position: options.position || CFG.position, dismissible: options.dismissible !== false,
        persistent: !!options.persistent, group: options.group || null, actions: options.actions || [],
        url: null, progress: options.progress != null ? options.progress : null,
        meta: options.meta || {}, replace: false, template: options.template || null,
      });
      return makePending(id);
    },
    success: function (message, title) { return basicToast('success', message, title, 4000); },
    error: function (message, title) { return basicToast('error', message, title, null); },
    warning: function (message, title) { return basicToast('warning', message, title, 6000); },
    info: function (message, title) { return basicToast('info', message, title, 4000); },
    loading: function (message) {
      var id = uuid();
      ingest({
        id: id, type: 'toast', variant: 'loading', title: null, message: message,
        icon: null, duration: null, position: CFG.position, dismissible: false,
        persistent: true, group: null, actions: [], url: null, progress: null, meta: {}, replace: false,
      });
      return makePending(id);
    },
    confirm: function (options) {
      options = options || {};
      var id = uuid();
      var actions = [
        { label: options.cancelText || 'Annuler', style: 'secondary', target: null, closesDialog: true },
      ];
      actions.push({
        label: options.confirmText || 'Confirmer',
        style: options.danger ? 'danger' : 'primary',
        target: options.onConfirm ? { type: 'js', run: options.onConfirm } : null,
        closesDialog: true,
      });

      ingest({
        id: id, type: 'confirm', variant: options.danger ? 'error' : 'neutral',
        title: options.title || null, message: options.message || null, icon: null,
        duration: null, position: CFG.position, dismissible: true, persistent: true,
        group: null, actions: actions, url: null, progress: null, meta: {}, replace: false,
      });

      return id;
    },
    dismiss: dismiss,
    clear: clearAll,
    clearGroup: clearGroup,
    /**
     * Reskin one or more notification types. Omit a key to keep the
     * built-in look for that type — only what you define is overridden.
     *
     *   Notify.registerTemplate('brand', {
     *     toast: (payload, h) => {
     *       const card = h.el('div', 'my-toast');
     *       card.textContent = payload.title;
     *       return card;
     *     },
     *   });
     *
     * Then either set it globally (config('notify.theme') = 'brand', or
     * window.__NOTIFY_CONFIG__.theme = 'brand' before this script runs), or
     * per notification: Notify::toast()->template('brand')->...
     */
    registerTemplate: function (name, handlers) {
      templates[name] = handlers || {};
    },
  };

  window.notifyAction = function ($wire, method, params, messages) {
    messages = messages || {};
    var pending = window.Notify.loading(messages.loading || '');
    var result = $wire.call.apply($wire, [method].concat(params || []));

    Promise.resolve(result).then(function () {
      pending.success(messages.success || 'Terminé.');
    }).catch(function () {
      pending.error(messages.error || 'Une erreur est survenue.');
    });
  };

  // ---------------------------------------------------------------- boot --

  function boot() {
    (window.__NOTIFY_QUEUE__ || []).forEach(ingest);

    if (window.Livewire) {
      window.Livewire.on('notify:push', ingest);
      window.Livewire.on('notify:clear-group', function (payload) { clearGroup(payload.group); });
    } else {
      document.addEventListener('livewire:init', function () {
        window.Livewire.on('notify:push', ingest);
        window.Livewire.on('notify:clear-group', function (payload) { clearGroup(payload.group); });
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
