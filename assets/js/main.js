/* StudentFlow - core interactions (toasts, modals, sidebar, theme, AJAX) */
(function () {
  'use strict';

  const SF = (window.SF = window.SF || {});

  SF.csrf =
    (document.getElementById('csrfToken') && document.getElementById('csrfToken').value) ||
    (document.querySelector('meta[name="csrf"]') && document.querySelector('meta[name="csrf"]').content) ||
    '';

  /* ---------------- helpers ---------------- */
  SF.esc = function (value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  SF.fmtMinutes = function (min) {
    min = Math.max(0, Math.round(Number(min) || 0));
    if (min <= 0) return '0m';
    const h = Math.floor(min / 60);
    const m = min % 60;
    if (h && m) return h + 'h ' + m + 'm';
    if (h) return h + 'h';
    return m + 'm';
  };

  SF.fmtMoney = function (n) {
    n = Number(n) || 0;
    const decimals = n % 1 === 0 ? 0 : 2;
    return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: 2 });
  };

  /* ---------------- toasts ---------------- */
  SF.toast = function (message, type) {
    type = type || 'success';
    const root = document.getElementById('toastRoot');
    if (!root) return;
    const colors = {
      success: 'border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950',
      error: 'border-rose-200 dark:border-rose-500/30 text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950',
      info: 'border-indigo-200 dark:border-indigo-500/30 text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950',
    };
    const icons = { success: 'check-circle-2', error: 'circle-alert', info: 'info' };
    const el = document.createElement('div');
    el.className =
      'toast flex items-start gap-3 rounded-xl border px-4 py-3 shadow-soft text-sm font-medium backdrop-blur ' +
      (colors[type] || colors.info);
    el.innerHTML =
      '<i data-lucide="' +
      (icons[type] || icons.info) +
      '" class="w-4.5 h-4.5 mt-0.5 shrink-0"></i><span class="flex-1">' +
      SF.esc(message) +
      '</span><button type="button" class="shrink-0 opacity-60 hover:opacity-100" aria-label="Dismiss"><i data-lucide="x" class="w-4 h-4"></i></button>';
    root.appendChild(el);
    if (window.lucide) lucide.createIcons({ nodes: [el] });
    const remove = function () {
      el.classList.add('toast-out');
      setTimeout(function () { el.remove(); }, 250);
    };
    el.querySelector('button').addEventListener('click', remove);
    setTimeout(remove, 4200);
  };

  /* ---------------- flash messages ---------------- */
  function showStoredFlash() {
    try {
      const stored = sessionStorage.getItem('sf_flash');
      if (stored) {
        sessionStorage.removeItem('sf_flash');
        const f = JSON.parse(stored);
        SF.toast(f.message, f.type);
      }
    } catch (e) { /* ignore */ }
  }

  SF.flashAndReload = function (message, type) {
    try {
      sessionStorage.setItem('sf_flash', JSON.stringify({ message: message, type: type || 'success' }));
    } catch (e) { /* ignore */ }
    location.reload();
  };

  /* ---------------- AJAX ---------------- */
  SF.post = function (url, data, silent) {
    const fd = new FormData();
    Object.keys(data || {}).forEach(function (key) {
      const val = data[key];
      if (val !== undefined && val !== null) fd.append(key, val);
    });
    fd.append('csrf_token', SF.csrf);
    return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) {
        return res.json().catch(function () {
          return { ok: false, message: 'Unexpected server response.' };
        });
      })
      .then(function (json) {
        if (!json.ok && !silent && json.message) SF.toast(json.message, 'error');
        return json;
      })
      .catch(function () {
        if (!silent) SF.toast('Network error. Please try again.', 'error');
        return { ok: false, message: 'Network error.' };
      });
  };

  SF.get = function (url) {
    return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
      .then(function (res) { return res.json().catch(function () { return { ok: false }; }); })
      .catch(function () { return { ok: false }; });
  };

  /* FormData upload (files) */
  SF.postForm = function (url, fd, silent) {
    fd.append('csrf_token', SF.csrf);
    return fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (res) {
        return res.json().catch(function () {
          return { ok: false, message: 'Unexpected server response.' };
        });
      })
      .then(function (json) {
        if (!json.ok && !silent && json.message) SF.toast(json.message, 'error');
        return json;
      })
      .catch(function () {
        if (!silent) SF.toast('Network error. Please try again.', 'error');
        return { ok: false, message: 'Network error.' };
      });
  };

  /* ---------------- modals ---------------- */
  SF.openModal = function (id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
  };
  SF.closeModal = function (id) {
    const m = typeof id === 'string' ? document.getElementById(id) : id;
    if (m) m.classList.add('hidden');
    if (!document.querySelector('[id$="Modal"]:not(.hidden), #modalRoot:not(.hidden)')) {
      document.body.style.overflow = '';
    }
  };
  SF.closeAllModals = function () {
    document.querySelectorAll('[id$="Modal"]').forEach(function (m) {
      if (m.classList.contains('fixed')) m.classList.add('hidden');
    });
    const root = document.getElementById('modalRoot');
    if (root) root.classList.add('hidden');
    document.body.style.overflow = '';
  };

  /* ---------------- confirmation dialog ---------------- */
  SF.confirm = function (message, confirmLabel) {
    confirmLabel = confirmLabel || 'Confirm';
    return new Promise(function (resolve) {
      const root = document.getElementById('modalRoot');
      const panel = document.getElementById('modalPanel');
      if (!root || !panel) { resolve(window.confirm(message)); return; }
      panel.innerHTML =
        '<h3 class="text-lg font-semibold text-slate-900 dark:text-white">Are you sure?</h3>' +
        '<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">' + SF.esc(message) + '</p>' +
        '<div class="mt-6 flex justify-end gap-3">' +
        '<button type="button" data-c="0" class="rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>' +
        '<button type="button" data-c="1" class="rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-sm font-semibold px-5 py-2.5">' + SF.esc(confirmLabel) + '</button>' +
        '</div>';
      root.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      const done = function (val) {
        root.classList.add('hidden');
        document.body.style.overflow = '';
        resolve(val);
      };
      panel.querySelectorAll('[data-c]').forEach(function (btn) {
        btn.addEventListener('click', function () { done(btn.dataset.c === '1'); });
      });
      document.getElementById('modalBackdrop').onclick = function () { done(false); };
    });
  };

  /* ---------------- theme ---------------- */
  SF.isDark = function () { return document.documentElement.classList.contains('dark'); };
  SF.applyTheme = function (dark) {
    document.documentElement.classList.toggle('dark', dark);
    window.dispatchEvent(new Event('sf:theme'));
  };
  SF.toggleTheme = function () {
    const dark = !SF.isDark();
    SF.applyTheme(dark);
    SF.post('actions/settings_actions.php', { dark_mode: dark ? 1 : 0 }, true)
      .then(function (json) {
        if (json.ok) { try { localStorage.removeItem('sf_theme'); } catch (e) {} }
        else { try { localStorage.setItem('sf_theme', dark ? '1' : '0'); } catch (e) {} }
      });
  };

  function restoreThemeFallback() {
    let stored = null;
    try { stored = localStorage.getItem('sf_theme'); } catch (e) {}
    if (stored !== null) {
      SF.applyTheme(stored === '1');
    }
  }

  /* ---------------- unread badge ---------------- */
  SF.setUnread = function (count) {
    document.querySelectorAll('[data-unread-badge]').forEach(function (el) {
      if (count > 0) {
        el.classList.remove('hidden');
        el.textContent = count > 99 ? '99+' : String(count);
        if (el.tagName === 'SPAN' && el.parentElement && el.parentElement.tagName === 'A') el.style.display = '';
      } else {
        el.classList.add('hidden');
      }
    });
    const label = document.getElementById('unreadLabel');
    if (label) label.textContent = String(count);
  };

  function pollNotifications() {
    if (!document.getElementById('csrfToken')) return;
    SF.get('api/notifications.php').then(function (json) {
      if (json.ok) SF.setUnread(json.data.unread);
    });
  }

  /* ---------------- boot ---------------- */
  function boot() {
    if (window.lucide) lucide.createIcons();

    restoreThemeFallback();
    showStoredFlash();

    // sidebar drawer
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('sidebarToggle');
    const setSidebar = function (open) {
      if (!sidebar) return;
      sidebar.classList.toggle('-translate-x-full', !open);
      if (overlay) overlay.classList.toggle('hidden', !open);
    };
    if (toggle) toggle.addEventListener('click', function () { setSidebar(sidebar.classList.contains('-translate-x-full')); });
    if (overlay) overlay.addEventListener('click', function () { setSidebar(false); });

    // theme toggles
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      btn.addEventListener('click', SF.toggleTheme);
    });

    // dropdowns
    document.querySelectorAll('[data-dropdown-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const menu = btn.parentElement.querySelector('[data-dropdown-menu]');
        if (menu) menu.classList.toggle('hidden');
      });
    });
    document.addEventListener('click', function () {
      document.querySelectorAll('[data-dropdown-menu]').forEach(function (m) { m.classList.add('hidden'); });
    });

    // generic modal closers
    document.addEventListener('click', function (e) {
      const closer = e.target.closest('[data-close-modal]');
      if (closer) {
        const modal = closer.closest('[id$="Modal"]') || closer.closest('.fixed');
        if (modal) { modal.classList.add('hidden'); document.body.style.overflow = ''; }
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') SF.closeAllModals();
    });

    // flash from previous request
    if (window.SF_FLASH && Array.isArray(window.SF_FLASH)) {
      window.SF_FLASH.forEach(function (f) { SF.toast(f.message, f.type); });
      window.SF_FLASH = null;
    }

    // notification badge polling
    pollNotifications();
    setInterval(pollNotifications, 60000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
