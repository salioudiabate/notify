/*!
 * Notify — front-end runtime. No dependencies (not even Alpine): a plain
 * Laravel page with nothing but this script and notify.css already gets the
 * full toast/alert/confirm/dialog/progress system. If Livewire is present,
 * it's bridged automatically below; nothing here requires it.
 */
(function () {
  'use strict';

  var CFG = window.__NOTIFY_CONFIG__ || { position: 'top-right', maxVisible: 4, dismissible: true, animations: true, colorScheme: null, icons: {}, strings: {}, buttonColors: {}, broadcastChannel: null };

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
      actionColor: applyActionColor,
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

  // --------------------------------------------------------- color scheme --

  var COLOR_SCHEME_KEY = 'notify:color-scheme';
  var VALID_SCHEMES = ['light', 'dark', 'system'];

  /**
   * 'light'/'dark' force that palette everywhere (see the :root[data-notify-theme]
   * overrides in notify.css, which win over prefers-color-scheme regardless of
   * the OS setting); 'system' (or anything else) removes the override and goes
   * back to following the OS preference.
   */
  function applyColorScheme(scheme) {
    var root = document.documentElement;

    if (scheme === 'dark' || scheme === 'light') {
      root.setAttribute('data-notify-theme', scheme);
    } else {
      root.removeAttribute('data-notify-theme');
    }
  }

  function initColorScheme() {
    var stored = null;

    try {
      stored = window.localStorage.getItem(COLOR_SCHEME_KEY);
    } catch (e) {
      // localStorage unavailable (private mode, disabled) — fall back to config/system
    }

    applyColorScheme(stored || CFG.colorScheme || 'system');
  }

  // ---------------------------------------------------------- button colors --

  /**
   * style -> which CSS custom properties its bg/fg/border map to. Kept
   * separate from card/icon tokens (see notify.css) so overriding a button's
   * color never touches text or semantic-icon colors too.
   */
  var BUTTON_COLOR_VARS = {
    primary: { bg: '--notify-btn-primary-bg', fg: '--notify-btn-primary-fg' },
    secondary: { bg: '--notify-btn-secondary-bg', fg: '--notify-btn-secondary-fg', border: '--notify-btn-secondary-border' },
    danger: { bg: '--notify-btn-danger-bg', fg: '--notify-btn-danger-fg' },
    ghost: { bg: '--notify-btn-ghost-bg', fg: '--notify-btn-ghost-fg', border: '--notify-btn-ghost-border' },
    link: { fg: '--notify-btn-link-fg' },
  };

  /**
   * Applies a color globally for one or more button styles, via
   * config('notify.button_colors') at boot or Notify.setButtonColors() at
   * runtime — e.g. { primary: { bg: '#7c3aed', fg: '#fff' } }. A single
   * button can still override this on its own (see applyActionColor()).
   */
  function applyButtonColors(colors) {
    var root = document.documentElement.style;

    Object.keys(colors || {}).forEach(function (style) {
      var vars = BUTTON_COLOR_VARS[style];
      if (!vars) return;

      var value = colors[style] || {};
      Object.keys(vars).forEach(function (key) {
        if (value[key]) root.setProperty(vars[key], value[key]);
      });
    });
  }

  /** A color string sets only the background (fg/border stay whatever the
   *  style already uses); pass { bg, fg, border } for full control. Mirrors
   *  Action::toArray()'s normalization on the PHP side. */
  function normalizeColor(color) {
    if (!color) return null;

    return typeof color === 'string' ? { bg: color } : color;
  }

  /** Inline override for exactly one rendered button — ->action(..., color:)
   *  / ->confirmColor() / ->cancelColor() / a plain color passed to
   *  Notify.confirm({ confirmColor, cancelColor }). */
  function applyActionColor(btn, color) {
    var c = normalizeColor(color);
    if (!c) return;

    if (c.bg) btn.style.background = c.bg;
    if (c.fg) btn.style.color = c.fg;
    if (c.border) btn.style.borderColor = c.border;
  }

  // ------------------------------------------------------------- broadcast --

  var echoSubscribed = false;

  /**
   * BroadcastDriver's counterpart to bindLivewire() below — subscribes to
   * config('notify.broadcast.channel') (resolved server-side into
   * CFG.broadcastChannel, see <x-notify::root />) via Laravel Echo, so a
   * notification pushed to a specific user from outside the current
   * request (a queued job, a console command) still reaches this page in
   * real time. A no-op whenever Echo isn't loaded or broadcasting isn't
   * enabled — nothing here requires either.
   */
  function bindEcho() {
    if (echoSubscribed || !window.Echo || !CFG.broadcastChannel) return;
    echoSubscribed = true;

    window.Echo.private(CFG.broadcastChannel)
      .listen('.notify.push', function (e) { ingest(e.notification); })
      .listen('.notify.command', function (e) { ingest(e); });
  }

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

  // config('notify.icons') / Notify.registerIcon() overrides applied once at
  // boot, on top of the built-ins above — same registry ->icon('name') looks up.
  mergeIcons(CFG.icons);

  function mergeIcons(overrides) {
    Object.keys(overrides || {}).forEach(function (name) { ICONS[name] = overrides[name]; });
  }

  function iconMarkup(payload) {
    if (payload.variant === 'loading') {
      return '<span class="notify-spinner"></span>';
    }

    if (payload.icon && ICONS[payload.icon]) {
      return ICONS[payload.icon];
    }

    // Not a registered name: treat it as raw markup handed straight to
    // ->icon('<svg>...</svg>') — a one-off custom icon with nothing to
    // register up front. Trusted, developer-authored content, same as
    // every built-in ICONS entry above (already inserted via innerHTML).
    if (payload.icon && /^\s*</.test(payload.icon)) {
      return payload.icon;
    }

    return ICONS[payload.variant] || ICONS.neutral;
  }

  // --------------------------------------------------------------- strings --

  /**
   * Every hardcoded piece of UI chrome text that isn't part of a payload's
   * own free-form title/message/action labels (already customizable per
   * call). Override globally via config('notify.strings') or Notify.setStrings(),
   * or leave any key out to keep its built-in default.
   */
  var STRINGS = {
    close: 'Fermer',
    moreSingular: 'notification de plus',
    morePlural: 'notifications de plus',
    escKey: 'Esc',
    escHint: 'pour fermer',
    confirm: 'Confirmer',
    cancel: 'Annuler',
    url: 'Voir',
    actionSuccess: 'Terminé.',
    actionError: 'Une erreur est survenue.',
  };

  function mergeStrings(overrides) {
    Object.keys(overrides || {}).forEach(function (key) { STRINGS[key] = overrides[key]; });
  }

  mergeStrings(CFG.strings);

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

  /**
   * Everything we render is mounted inside #notify-root rather than
   * directly on <body> — that's what makes #notify-root's own font-family/
   * color declarations (and a custom template's un-styled markup) actually
   * take effect, instead of silently inheriting the host page's own text
   * color. Falls back to <body> only if the component was never included.
   */
  function mountRoot() {
    return document.getElementById('notify-root') || document.body;
  }

  function stackFor(position) {
    // A cached stack can be detached from the page when the body is swapped
    // (Livewire's wire:navigate, Turbo...): recreate it in the current root.
    if (stacks[position] && stacks[position].isConnected) return stacks[position];

    var node = el('div', 'notify-stack');
    node.setAttribute('data-position', position);
    mountRoot().appendChild(node);
    stacks[position] = node;

    return node;
  }

  function buildActions(payload) {
    var wrap = el('div', 'notify-card__actions');

    (payload.actions || []).forEach(function (action) {
      var btn = el('button', 'notify-btn notify-btn--' + (action.style || 'ghost'), escapeHtml(action.label));
      btn.type = 'button';
      applyActionColor(btn, action.color);
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
      close.setAttribute('aria-label', STRINGS.close);
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
      close.setAttribute('aria-label', STRINGS.close);
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
      var text = hidden + ' ' + (hidden > 1 ? STRINGS.morePlural : STRINGS.moreSingular);
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
      footer.appendChild(h.el('div', 'notify-kbd', '<span>' + h.escapeHtml(STRINGS.escKey) + '</span> ' + h.escapeHtml(STRINGS.escHint)));
    }

    var buttons = h.el('div', 'notify-card__actions');
    (payload.actions || []).forEach(function (action) {
      var btn = h.el('button', 'notify-btn notify-btn--' + (action.style || 'secondary'), h.escapeHtml(action.label));
      btn.type = 'button';
      h.actionColor(btn, action.color);
      btn.addEventListener('click', function () { h.runAction(action, payload); });
      buttons.appendChild(btn);
    });
    footer.appendChild(buttons);
    dialog.appendChild(footer);

    return dialog;
  }

  function showDialog(payload) {
    // Same for a dialog left open across a body swap: it's no longer on the page.
    if (openDialogEl && !openDialogEl.isConnected) {
      openDialogEl = null;
    }

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

    mountRoot().appendChild(backdrop);
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
    if (item.type === 'command') {
      if (item.command === 'clearGroup') clearGroup(item.group);
      else if (item.command === 'dismiss') dismiss(item.id);
      else if (item.command === 'clearAll') clearAll();

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
        { label: options.cancelText || STRINGS.cancel, style: 'secondary', target: null, closesDialog: true, color: normalizeColor(options.cancelColor) },
      ];
      actions.push({
        label: options.confirmText || STRINGS.confirm,
        style: options.danger ? 'danger' : 'primary',
        target: options.onConfirm ? { type: 'js', run: options.onConfirm } : null,
        closesDialog: true,
        color: normalizeColor(options.confirmColor),
      });

      ingest({
        id: id, type: 'confirm', variant: options.danger ? 'error' : 'neutral',
        title: options.title || null, message: options.message || null, icon: null,
        duration: null, position: CFG.position, dismissible: true, persistent: true,
        group: null, actions: actions, url: null, progress: null, meta: {}, replace: false,
        template: options.template || null,
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
    /**
     * Overrides (or adds) one or more named icons, on top of the built-ins
     * (success, error, warning, info, neutral, trash, close) — same registry
     * ->icon('name') looks up. Takes either a name + SVG markup pair, or a
     * whole map at once:
     *
     *   Notify.registerIcon('success', '<svg>...</svg>');
     *   Notify.registerIcon({ success: '<svg>...</svg>', error: '<svg>...</svg>' });
     *
     * For a one-off icon that isn't worth registering, skip this entirely and
     * pass the markup straight to a single notification instead:
     * ->icon('<svg>...</svg>').
     */
    registerIcon: function (name, svg) {
      if (name && typeof name === 'object') {
        mergeIcons(name);
      } else {
        ICONS[name] = svg;
      }
    },
    /**
     * Overrides one or more built-in UI chrome strings (close button label,
     * the "N more" pill, the dialog's Esc hint, confirm()/cancel() defaults,
     * notifyAction()'s fallback messages — never a payload's own free-form
     * title/message/action labels, which are already customizable per call).
     * Same keys as config('notify.strings'); anything left out keeps its
     * built-in French default:
     *
     *   Notify.setStrings({ close: 'Close', confirm: 'Confirm', cancel: 'Cancel' });
     */
    setStrings: function (overrides) {
      mergeStrings(overrides);
    },
    /**
     * Notify.setColorScheme('dark' | 'light' | 'system') — forces the
     * palette globally, persisted across reloads (localStorage), regardless
     * of the OS preference. Wire it to your own app's own dark-mode toggle:
     *
     *   darkModeToggle.addEventListener('click', () => {
     *     Notify.setColorScheme(isDark ? 'light' : 'dark');
     *   });
     *
     * The default for first-ever visits (before any toggle is used) comes
     * from config('notify.color_scheme') — 'system' (the default) just
     * follows prefers-color-scheme, same as if this was never called.
     */
    setColorScheme: function (scheme) {
      if (VALID_SCHEMES.indexOf(scheme) === -1) {
        throw new Error('Notify.setColorScheme() expects "light", "dark", or "system", got: ' + scheme);
      }

      applyColorScheme(scheme);

      try {
        window.localStorage.setItem(COLOR_SCHEME_KEY, scheme);
      } catch (e) {
        // localStorage unavailable — the scheme still applies for this page view
      }
    },
    getColorScheme: function () {
      return document.documentElement.getAttribute('data-notify-theme') || 'system';
    },
    /**
     * Sets the color of every button rendered with a given style, globally,
     * without writing CSS — pass only the styles you want to change:
     *
     *   Notify.setButtonColors({
     *     primary: { bg: '#7c3aed', fg: '#fff' },
     *     danger: { bg: '#dc2626' },
     *   });
     *
     * A single button can still override this on its own regardless — pass a
     * color to ->action()/->confirmColor()/->cancelColor() (PHP) or
     * Notify.confirm({ confirmColor, cancelColor }) (JS).
     */
    setButtonColors: function (colors) {
      applyButtonColors(colors);
    },
  };

  window.notifyAction = function ($wire, method, params, messages) {
    messages = messages || {};
    var pending = window.Notify.loading(messages.loading || '');
    var result = $wire.call.apply($wire, [method].concat(params || []));

    Promise.resolve(result).then(function () {
      pending.success(messages.success || STRINGS.actionSuccess);
    }).catch(function () {
      pending.error(messages.error || STRINGS.actionError);
    });
  };

  // ---------------------------------------------------------------- boot --

  function boot() {
    initColorScheme();
    applyButtonColors(CFG.buttonColors);

    (window.__NOTIFY_QUEUE__ || []).forEach(ingest);

    // Livewire's dispatch(name, notification: $payload) delivers named
    // arguments to JS as ONE object keyed by their names — { notification }
    // here, { group } for clear-group — never the bare payload itself.
    function bindLivewire() {
      window.Livewire.on('notify:push', function (e) { ingest(e.notification); });
      window.Livewire.on('notify:clear-group', function (e) { clearGroup(e.group); });
      window.Livewire.on('notify:dismiss', function (e) { dismiss(e.id); });
      window.Livewire.on('notify:clear-all', function () { clearAll(); });
    }

    if (window.Livewire) {
      bindLivewire();
    } else {
      document.addEventListener('livewire:init', bindLivewire);
    }

    bindEcho();
    // Echo has no equivalent of Livewire's 'livewire:init' event to wait on,
    // so this is a best-effort fallback for the case where the host app's own
    // bootstrap script (which creates window.Echo) hasn't run yet by the time
    // this deferred script does — harmless, and a no-op, if it already has.
    if (!echoSubscribed) {
      window.addEventListener('load', bindEcho);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
