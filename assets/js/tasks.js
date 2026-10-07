/* StudentFlow - tasks page (filter, sort, CRUD without reload) */
(function () {
  'use strict';
  const SF = window.SF;
  let tasks = (window.SF_DATA && window.SF_DATA.tasks) || [];

  let activeTab = 'all';
  let search = '';
  let category = '';
  let priority = '';
  let risk = 'all';
  let sortBy = 'priority';

  const RISK_CLASS = {
    critical: 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400',
    approaching: 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
    safe: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
  };

  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function todayStr() {
    const d = new Date();
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }

  function sectionOf(t) {
    if (t.deleted) return 'trash';
    if (t.status === 'Completed') return 'completed';
    if (!t.due_date) return 'upcoming';
    const today = todayStr();
    if (t.due_date < today) return 'overdue';
    if (t.due_date === today) return 'today';
    return 'upcoming';
  }

  function matches(t) {
    const sec = sectionOf(t);
    if (activeTab === 'all') {
      if (sec === 'trash') return false;
    } else if (sec !== activeTab) return false;

    if (category && t.category !== category) return false;
    if (priority && t.priority !== priority) return false;
    if (risk !== 'all' && (!t.risk || t.risk !== risk)) return false;
    if (search) {
      const hay = (t.title + ' ' + (t.description || '')).toLowerCase();
      if (hay.indexOf(search) === -1) return false;
    }
    return true;
  }

  function sortTasks(list) {
    const arr = list.slice();
    if (sortBy === 'priority') {
      arr.sort(function (a, b) {
        if (b.score !== a.score) return b.score - a.score;
        return (a.due_date || '9999') < (b.due_date || '9999') ? -1 : 1;
      });
    } else if (sortBy === 'deadline') {
      arr.sort(function (a, b) {
        const ad = a.due_date || '9999-12-31';
        const bd = b.due_date || '9999-12-31';
        return ad < bd ? -1 : ad > bd ? 1 : 0;
      });
    } else {
      arr.sort(function (a, b) {
        return a.created_at < b.created_at ? 1 : a.created_at > b.created_at ? -1 : 0;
      });
    }
    return arr;
  }

  function rowHtml(t) {
    const esc = SF.esc;
    const prioClass =
      t.priority === 'High'
        ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400'
        : t.priority === 'Medium'
        ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400'
        : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400';
    const sec = sectionOf(t);
    const doneish = t.status === 'Completed' || t.deleted;
    const dueText = t.due_date
      ? new Date(t.due_date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) +
        (t.due_time ? ' · ' + formatTime(t.due_time) : '')
      : 'No deadline';

    let left;
    if (!t.deleted) {
      left =
        '<button type="button" data-toggle-complete title="' + (t.status === 'Completed' ? 'Reopen task' : 'Mark complete') + '" ' +
        'class="mt-0.5 w-5 h-5 rounded-md flex items-center justify-center shrink-0 border-2 transition ' +
        (t.status === 'Completed'
          ? 'bg-emerald-500 border-emerald-500'
          : t.status === 'In Progress'
          ? 'border-amber-400 bg-amber-400/30'
          : 'border-slate-300 dark:border-slate-600 hover:border-indigo-500') + '">' +
        '<i data-lucide="check" class="w-3 h-3 text-white ' + (t.status === 'Completed' ? '' : 'opacity-0') + '"></i></button>';
    } else {
      left = '<span class="mt-0.5 w-5 h-5 flex items-center justify-center shrink-0"><i data-lucide="trash-2" class="w-4 h-4 text-slate-400"></i></span>';
    }

    let actions;
    if (t.deleted) {
      actions = '<button type="button" data-restore class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15">Restore</button>';
    } else {
      actions =
        (t.status !== 'Completed'
          ? '<a href="focus.php?task=' + t.id + '&auto=1" title="Start focus session" class="hidden sm:flex w-8 h-8 items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15"><i data-lucide="timer" class="w-4 h-4"></i></a>'
          : '') +
        '<button type="button" data-edit class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15" title="Edit"><i data-lucide="pencil" class="w-4 h-4"></i></button>' +
        '<button type="button" data-delete class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/15" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>';
    }

    return (
      '<li class="task-row group flex items-start gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 hover:shadow-soft transition" data-id="' + t.id + '">' +
      left +
      '<div class="min-w-0 flex-1">' +
      '<p class="task-title text-sm font-medium ' + (doneish ? 'line-through text-slate-400' : 'text-slate-800 dark:text-slate-200') + '">' + esc(t.title) + '</p>' +
      (t.description ? '<p class="mt-0.5 text-xs text-slate-400 line-clamp-1">' + esc(t.description) + '</p>' : '') +
      '<div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">' +
      '<span class="rounded-full px-2 py-0.5 font-medium ' + prioClass + '">' + esc(t.priority) + '</span>' +
      '<span class="rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5">' + esc(t.category) + '</span>' +
      '<span class="rounded-full ' + (sec === 'overdue' ? 'bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400') + ' px-2 py-0.5">' + esc(dueText) + '</span>' +
      '<span class="text-slate-400">' + SF.fmtMinutes(t.estimated_minutes) + '</span>' +
      (t.risk && t.risk !== 'none' && !t.deleted && t.status !== 'Completed'
        ? '<span class="rounded-full px-2 py-0.5 font-medium ' + (RISK_CLASS[t.risk] || '') + '" title="' + esc(t.risk_message || '') + '">' + t.risk.toUpperCase() + '</span>'
        : '') +
      (t.status === 'In Progress' && !t.deleted
        ? '<span class="rounded-full bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 px-2 py-0.5 font-medium">In Progress</span>'
        : '') +
      '</div></div>' +
      '<div class="flex items-center gap-1 shrink-0">' + actions + '</div></li>'
    );
  }

  function formatTime(hhmm) {
    const parts = hhmm.split(':');
    let h = parseInt(parts[0], 10);
    const m = parts[1] || '00';
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return h + ':' + m + ' ' + ampm;
  }

  const sectionKeys = ['today', 'upcoming', 'overdue', 'completed', 'trash'];

  function render() {
    const visible = { today: [], upcoming: [], overdue: [], completed: [], trash: [] };
    tasks.forEach(function (t) {
      const sec = sectionOf(t);
      if (matches(t)) visible[sec].push(t);
    });

    let totalVisible = 0;
    sectionKeys.forEach(function (key) {
      const list = document.querySelector('[data-list="' + key + '"]');
      const section = document.querySelector('[data-section="' + key + '"]');
      if (!list || !section) return;
      const items = sortTasks(visible[key]);
      totalVisible += items.length;
      list.innerHTML = items.map(rowHtml).join('');

      const shown = activeTab === 'all' ? key !== 'trash' : key === activeTab;
      section.classList.toggle('hidden', !shown);

      const empty = section.querySelector('[data-empty="' + key + '"]');
      if (empty) empty.classList.toggle('hidden', !(shown && items.length === 0));
      const countEl = section.querySelector('[data-section-count="' + key + '"]');
      if (countEl) countEl.textContent = '(' + items.length + ')';

      if (window.lucide) lucide.createIcons({ nodes: [list] });
    });

    // tab counts
    const counts = { today: visible.today.length, upcoming: visible.upcoming.length, overdue: visible.overdue.length, completed: visible.completed.length, trash: visible.trash.length };
    counts.all = counts.today + counts.upcoming + counts.overdue;
    Object.keys(counts).forEach(function (key) {
      const el = document.querySelector('[data-tab-count="' + key + '"]');
      if (el) el.textContent = counts[key];
    });

    // deadline risk counts (open tasks only)
    const riskCounts = { all: 0, critical: 0, approaching: 0, safe: 0 };
    tasks.forEach(function (t) {
      if (t.deleted || t.status === 'Completed') return;
      riskCounts.all++;
      if (riskCounts[t.risk] !== undefined) riskCounts[t.risk]++;
    });
    const riskRoot = document.getElementById('riskFilters');
    Object.keys(riskCounts).forEach(function (key) {
      const el = riskRoot && riskRoot.querySelector('[data-risk-count="' + key + '"]');
      if (el) el.textContent = riskCounts[key];
    });

    const globalEmpty = document.getElementById('globalEmpty');
    if (globalEmpty) globalEmpty.classList.toggle('hidden', totalVisible > 0);
  }

  function upsert(task) {
    const idx = tasks.findIndex(function (t) { return t.id === task.id; });
    if (idx >= 0) tasks[idx] = task;
    else tasks.push(task);
  }

  /* ---------------- modal form ---------------- */
  const form = document.getElementById('taskForm');
  const modalTitle = document.getElementById('taskModalTitle');
  const modalError = document.getElementById('taskModalError');
  const modalRisk = document.getElementById('taskModalRisk');

  function showModalRisk(t) {
    if (!modalRisk) return;
    const info = t && t.risk ? RISK_CLASS[t.risk] : null;
    if (!info || t.risk === 'none') { modalRisk.classList.add('hidden'); return; }
    modalRisk.className = 'rounded-xl px-4 py-3 text-sm flex items-start gap-2.5 ' + info;
    modalRisk.innerHTML =
      '<i data-lucide="' + (t.risk === 'critical' ? 'triangle-alert' : t.risk === 'approaching' ? 'clock' : 'shield-check') + '" class="w-4 h-4 mt-0.5 shrink-0"></i>' +
      '<span><b>' + t.risk.toUpperCase() + '</b> — ' + SF.esc(t.risk_message || '') + '</span>';
    modalRisk.classList.remove('hidden');
    if (window.lucide) lucide.createIcons({ nodes: [modalRisk] });
  }

  function openNew() {
    form.reset();
    document.getElementById('tf_id').value = '';
    document.getElementById('tf_est').value = '60';
    document.getElementById('tf_status').value = 'Not Started';
    document.getElementById('tf_priority').value = 'Medium';
    if (modalRisk) modalRisk.classList.add('hidden');
    modalTitle.textContent = 'New Task';
    modalError.classList.add('hidden');
    SF.openModal('taskModal');
    setTimeout(function () { document.getElementById('tf_title').focus(); }, 50);
  }

  function openEdit(t) {
    document.getElementById('tf_id').value = t.id;
    document.getElementById('tf_title').value = t.title;
    document.getElementById('tf_description').value = t.description || '';
    document.getElementById('tf_category').value = t.category;
    document.getElementById('tf_priority').value = t.priority;
    document.getElementById('tf_due_date').value = t.due_date || '';
    document.getElementById('tf_due_time').value = t.due_time ? t.due_time.substring(0, 5) : '';
    document.getElementById('tf_est').value = t.estimated_minutes;
    document.getElementById('tf_status').value = t.status;
    modalTitle.textContent = 'Edit Task';
    modalError.classList.add('hidden');
    showModalRisk(t);
    SF.openModal('taskModal');
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const id = document.getElementById('tf_id').value;
      const fd = new FormData(form);
      const payload = {};
      fd.forEach(function (v, k) { payload[k] = v; });
      payload.action = id ? 'update' : 'create';
      if (!id) delete payload.id;

      SF.post('actions/task_actions.php', payload).then(function (json) {
        if (!json.ok) {
          modalError.textContent = json.message || 'Please check the form.';
          modalError.classList.remove('hidden');
          return;
        }
        upsert(json.data.task);
        SF.closeModal('taskModal');
        render();
        SF.toast(json.message, 'success');
      });
    });
  }

  document.addEventListener('click', function (e) {
    const newBtn = e.target.closest('[data-open-new]');
    if (newBtn) { openNew(); return; }

    const row = e.target.closest('.task-row');
    if (!row) return;
    const id = parseInt(row.getAttribute('data-id'), 10);

    if (e.target.closest('[data-toggle-complete]')) {
      const t = tasks.find(function (x) { return x.id === id; });
      if (!t) return;
      const next = t.status === 'Completed' ? 'Not Started' : 'Completed';
      SF.post('actions/task_actions.php', { action: 'status', id: id, status: next }).then(function (json) {
        if (!json.ok) return;
        upsert(json.data.task);
        render();
        SF.toast(json.message, 'success');
      });
      return;
    }
    if (e.target.closest('[data-edit]')) {
      const t = tasks.find(function (x) { return x.id === id; });
      if (t) openEdit(t);
      return;
    }
    if (e.target.closest('[data-delete]')) {
      SF.confirm('This task will be moved to trash. You can restore it later.', 'Move to trash').then(function (ok) {
        if (!ok) return;
        SF.post('actions/task_actions.php', { action: 'delete', id: id }).then(function (json) {
          if (!json.ok) return;
          upsert(json.data.task);
          render();
          SF.toast(json.message, 'success');
        });
      });
      return;
    }
    if (e.target.closest('[data-restore]')) {
      SF.post('actions/task_actions.php', { action: 'restore', id: id }).then(function (json) {
        if (!json.ok) return;
        upsert(json.data.task);
        render();
        SF.toast(json.message, 'success');
      });
    }
  });

  /* ---------------- toolbar ---------------- */
  const searchEl = document.getElementById('taskSearch');
  if (searchEl) searchEl.addEventListener('input', function () { search = this.value.trim().toLowerCase(); render(); });
  const catEl = document.getElementById('filterCategory');
  if (catEl) catEl.addEventListener('change', function () { category = this.value; render(); });
  const prioEl = document.getElementById('filterPriority');
  if (prioEl) prioEl.addEventListener('change', function () { priority = this.value; render(); });
  const riskRoot = document.getElementById('riskFilters');
  if (riskRoot) {
    riskRoot.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-risk-filter]');
      if (!btn) return;
      risk = btn.getAttribute('data-risk-filter');
      riskRoot.querySelectorAll('[data-risk-filter]').forEach(function (b) {
        const on = b === btn;
        b.className =
          'risk-chip flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium border transition ' +
          (on
            ? 'bg-slate-900 dark:bg-white border-slate-900 dark:border-white text-white dark:text-slate-900'
            : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800');
      });
      render();
    });
  }
  const sortEl = document.getElementById('sortBy');
  if (sortEl) sortEl.addEventListener('change', function () { sortBy = this.value; render(); });

  const tabs = document.getElementById('taskTabs');
  if (tabs) {
    tabs.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-tab]');
      if (!btn) return;
      activeTab = btn.getAttribute('data-tab');
      tabs.querySelectorAll('[data-tab]').forEach(function (b) {
        const on = b === btn;
        b.className =
          'task-tab shrink-0 rounded-xl px-3.5 py-2 text-sm font-medium border transition ' +
          (on ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800');
      });
      render();
    });
  }

  render();
})();
