/* StudentFlow - Notifications page */
(function () {
  'use strict';
  const SF = window.SF;

  const typeMeta = {
    deadline: ['alarm-clock', 'bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400'],
    overdue: ['triangle-alert', 'bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400'],
    exam: ['graduation-cap', 'bg-violet-50 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400'],
    habit: ['flame', 'bg-orange-50 dark:bg-orange-500/15 text-orange-600 dark:text-orange-400'],
    goal: ['target', 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'],
    budget: ['wallet', 'bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400'],
    focus: ['timer', 'bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400'],
    system: ['bell', 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'],
  };

  let filter = 'all';

  const list = document.getElementById('notifList');
  const empty = document.getElementById('notifEmpty');

  function itemHtml(n) {
    const meta = typeMeta[n.type] || typeMeta.system;
    const read = n.is_read ? '1' : '0';
    return (
      '<li class="notif-item flex items-start gap-4 rounded-2xl border ' +
      (n.is_read
        ? 'border-slate-100 dark:border-slate-800 bg-white/60 dark:bg-slate-900/60 opacity-75'
        : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900') +
      ' p-4 shadow-soft transition" data-id="' + n.id + '" data-read="' + read + '">' +
      '<span class="w-10 h-10 rounded-xl ' + meta[1] + ' flex items-center justify-center shrink-0"><i data-lucide="' + meta[0] + '" class="w-5 h-5"></i></span>' +
      '<div class="min-w-0 flex-1"><div class="flex items-center gap-2 flex-wrap">' +
      '<p class="text-sm font-semibold text-slate-900 dark:text-white">' + SF.esc(n.title) + '</p>' +
      '<span class="rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 text-[10px] uppercase tracking-wide">' + SF.esc(n.type) + '</span>' +
      (!n.is_read ? '<span class="w-2 h-2 rounded-full bg-indigo-500"></span>' : '') +
      '</div>' +
      '<p class="mt-1 text-sm text-slate-600 dark:text-slate-400">' + SF.esc(n.message) + '</p>' +
      '<p class="mt-1.5 text-xs text-slate-400">' + SF.esc(n.time_ago || '') + '</p></div>' +
      '<div class="flex gap-1 shrink-0">' +
      (!n.is_read
        ? '<button type="button" data-mark-read class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15" title="Mark as read"><i data-lucide="check" class="w-4 h-4"></i></button>'
        : '') +
      '<button type="button" data-delete-notif class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/15" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>' +
      '</div></li>'
    );
  }

  function updateCounts(unread) {
    const label = document.getElementById('unreadLabel');
    if (label) label.textContent = unread;
    SF.setUnread(unread);
  }

  function applyFilter() {
    let visible = 0;
    list.querySelectorAll('.notif-item').forEach(function (li) {
      const show = filter === 'all' || li.getAttribute('data-read') === '0';
      li.classList.toggle('hidden', !show);
      if (show) visible++;
    });
    if (empty) {
      const noneAtAll = list.querySelectorAll('.notif-item').length === 0;
      empty.classList.toggle('hidden', !noneAtAll);
      if (!noneAtAll && visible === 0) {
        // filter hides everything — show a small inline hint instead
        empty.classList.remove('hidden');
      }
    }
  }

  function markReadItem(li) {
    li.setAttribute('data-read', '1');
    li.classList.add('border-slate-100', 'dark:border-slate-800', 'bg-white/60', 'dark:bg-slate-900/60', 'opacity-75');
    li.classList.remove('border-slate-200', 'bg-white', 'dark:bg-slate-900');
    const btn = li.querySelector('[data-mark-read]');
    if (btn) btn.remove();
    const dot = li.querySelector('.w-2.h-2.rounded-full.bg-indigo-500');
    if (dot) dot.remove();
  }

  function reload() {
    SF.get('api/notifications.php').then(function (json) {
      if (!json.ok) { SF.toast('Could not refresh notifications.', 'error'); return; }
      const d = json.data;
      list.innerHTML = d.notifications.map(itemHtml).join('');
      if (window.lucide) lucide.createIcons({ nodes: [list] });
      updateCounts(d.unread);
      applyFilter();
      SF.toast('Notifications refreshed.', 'success');
    });
  }

  /* ---- tabs ---- */
  document.getElementById('notifTabs').addEventListener('click', function (e) {
    const btn = e.target.closest('[data-nfilter]');
    if (!btn) return;
    filter = btn.getAttribute('data-nfilter');
    this.querySelectorAll('[data-nfilter]').forEach(function (b) {
      const on = b === btn;
      b.className =
        'nfilter rounded-xl px-4 py-2 text-sm font-medium ' +
        (on ? 'bg-indigo-600 text-white' : 'border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800');
    });
    applyFilter();
  });

  /* ---- item actions ---- */
  list.addEventListener('click', function (e) {
    const li = e.target.closest('.notif-item');
    if (!li) return;
    const id = li.getAttribute('data-id');

    if (e.target.closest('[data-mark-read]')) {
      SF.post('actions/notification_actions.php', { action: 'read', id: id }).then(function (json) {
        if (!json.ok) return;
        markReadItem(li);
        updateCounts(json.data.unread);
        if (filter === 'unread') applyFilter();
      });
      return;
    }
    if (e.target.closest('[data-delete-notif]')) {
      SF.post('actions/notification_actions.php', { action: 'delete', id: id }).then(function (json) {
        if (!json.ok) return;
        li.remove();
        updateCounts(json.data.unread);
        applyFilter();
        SF.toast(json.message, 'success');
      });
    }
  });

  /* ---- toolbar ---- */
  document.getElementById('markAllRead').addEventListener('click', function () {
    SF.post('actions/notification_actions.php', { action: 'read_all' }).then(function (json) {
      if (!json.ok) return;
      list.querySelectorAll('.notif-item').forEach(markReadItem);
      updateCounts(0);
      applyFilter();
      SF.toast(json.message, 'success');
    });
  });

  document.getElementById('clearRead').addEventListener('click', function () {
    SF.confirm('All notifications marked as read will be removed.', 'Clear read').then(function (ok) {
      if (!ok) return;
      SF.post('actions/notification_actions.php', { action: 'delete_all' }).then(function (json) {
        if (!json.ok) return;
        list.querySelectorAll('.notif-item[data-read="1"]').forEach(function (li) { li.remove(); });
        updateCounts(json.data.unread);
        applyFilter();
        SF.toast(json.message, 'success');
      });
    });
  });

  document.getElementById('refreshNotifs').addEventListener('click', reload);

  applyFilter();
})();
