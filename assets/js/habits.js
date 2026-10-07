/* StudentFlow - Habits page */
(function () {
  'use strict';
  const SF = window.SF;
  let habits = (window.SF_DATA && window.SF_DATA.habits) || [];
  let stats = {
    done_today: (window.SF_DATA && window.SF_DATA.done_today) || 0,
    total: (window.SF_DATA && window.SF_DATA.total) || 0,
    best_streak: (window.SF_DATA && window.SF_DATA.best_streak) || 0,
  };
  const today = (window.SF_DATA && window.SF_DATA.today) || todayStr();

  function todayStr() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function pad(n) { return n < 10 ? '0' + n : '' + n; }

  function freqLabel(h) {
    return h.frequency === 'weekly' ? 'Weekly ×' + h.target : 'Daily';
  }

  function cardHtml(h) {
    const logs = h.logs || {};
    let cal = '';
    for (let i = 29; i >= 0; i--) {
      const dt = new Date();
      dt.setDate(dt.getDate() - i);
      const key = dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate());
      const on = !!logs[key];
      cal += '<span class="w-2.5 h-2.5 rounded-[3px] ' + (on ? 'bg-emerald-500' : 'bg-slate-100 dark:bg-slate-800') +
        '" title="' + pad(dt.getDate()) + ' ' + dt.toLocaleDateString('en-GB', { month: 'short' }) + '"></span>';
    }
    return (
      '<div class="habit-card rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft flex flex-col" data-habit-id="' + h.id + '">' +
      '<div class="flex items-start justify-between gap-3">' +
      '<div class="min-w-0"><div class="flex items-center gap-2">' +
      '<h3 class="font-semibold text-slate-900 dark:text-white truncate">' + SF.esc(h.name) + '</h3>' +
      '<span class="shrink-0 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 text-[10px] font-medium">' + freqLabel(h) + '</span></div>' +
      (h.description ? '<p class="mt-0.5 text-xs text-slate-400 line-clamp-1">' + SF.esc(h.description) + '</p>' : '') +
      '</div><div class="flex gap-1 shrink-0">' +
      '<button type="button" data-edit-habit class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15" title="Edit"><i data-lucide="pencil" class="w-4 h-4"></i></button>' +
      '<button type="button" data-delete-habit class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/15" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>' +
      '</div></div>' +
      '<button type="button" data-toggle-habit class="mt-4 w-full rounded-2xl border-2 ' +
      (h.done_today ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-500/10' : 'border-slate-200 dark:border-slate-700 hover:border-indigo-400') +
      ' px-4 py-3 flex items-center justify-center gap-2 text-sm font-semibold transition">' +
      '<i data-lucide="check-circle-2" class="w-4.5 h-4.5 ' + (h.done_today ? 'text-emerald-500' : 'text-slate-300 dark:text-slate-600') + '"></i>' +
      '<span class="' + (h.done_today ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400') + '">' +
      (h.done_today ? 'Completed today' : 'Mark complete') + '</span></button>' +
      '<div class="mt-4 grid grid-cols-3 gap-2 text-center">' +
      '<div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 py-2"><p class="text-[10px] uppercase tracking-wide text-slate-400">Streak</p>' +
      '<p class="text-sm font-semibold text-slate-900 dark:text-white">' + h.streak + 'd</p></div>' +
      '<div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 py-2"><p class="text-[10px] uppercase tracking-wide text-slate-400">Weekly</p>' +
      '<p class="text-sm font-semibold text-slate-900 dark:text-white">' + h.weekly + '%</p></div>' +
      '<div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 py-2"><p class="text-[10px] uppercase tracking-wide text-slate-400">Monthly</p>' +
      '<p class="text-sm font-semibold text-slate-900 dark:text-white">' + h.monthly + '%</p></div>' +
      '</div>' +
      '<div class="mt-4"><p class="text-[10px] uppercase tracking-wide text-slate-400 mb-1.5">Last 30 days ' +
      '<span class="' + (h.streak >= 3 ? '' : 'hidden') + '">· 🔥 <span>' + h.streak + '</span>-day streak</span></p>' +
      '<div class="flex flex-wrap gap-1">' + cal + '</div></div></div>'
    );
  }

  function renderStats() {
    setText('statDoneToday', stats.done_today);
    setText('statTotal', stats.total);
    setText('statActiveHabits', stats.total);
    const best = document.getElementById('statBestStreak');
    if (best) best.innerHTML = stats.best_streak + ' <span class="text-base font-normal text-slate-400">days</span>';
  }

  function setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  function render() {
    const grid = document.getElementById('habitGrid');
    const empty = document.getElementById('habitEmpty');
    if (grid) {
      grid.innerHTML = habits.map(cardHtml).join('');
      if (window.lucide) lucide.createIcons({ nodes: [grid] });
    }
    if (empty) empty.classList.toggle('hidden', habits.length > 0);
    renderStats();
  }

  function applyPayload(p) {
    if (p.habit) {
      const idx = habits.findIndex(function (h) { return h.id === p.habit.id; });
      if (idx >= 0) {
        // keep calendar logs (payload doesn't include them)
        p.habit.logs = habits[idx].logs || {};
        habits[idx] = p.habit;
      } else {
        p.habit.logs = {};
        habits.push(p.habit);
      }
    }
    if (p.done_today !== undefined) stats.done_today = p.done_today;
    if (p.total !== undefined) stats.total = p.total;
    if (p.best_streak !== undefined) stats.best_streak = p.best_streak;
    render();
  }

  /* ------------- modal ------------- */
  const form = document.getElementById('habitForm');
  const modalError = document.getElementById('habitModalError');

  function showError(msg) {
    if (!modalError) return;
    modalError.textContent = msg;
    modalError.classList.toggle('hidden', !msg);
  }

  function openNew() {
    form.reset();
    document.getElementById('hf_id').value = '';
    document.getElementById('hf_target').value = '1';
    document.getElementById('habitModalTitle').textContent = 'New Habit';
    showError('');
    SF.openModal('habitModal');
    setTimeout(function () { document.getElementById('hf_name').focus(); }, 50);
  }

  function openEdit(h) {
    document.getElementById('hf_id').value = h.id;
    document.getElementById('hf_name').value = h.name;
    document.getElementById('hf_desc').value = h.description || '';
    document.getElementById('hf_freq').value = h.frequency;
    document.getElementById('hf_target').value = h.target;
    document.getElementById('habitModalTitle').textContent = 'Edit Habit';
    showError('');
    SF.openModal('habitModal');
  }

  ['addHabitBtn', 'addHabitBtn2'].forEach(function (id) {
    const btn = document.getElementById(id);
    if (btn) btn.addEventListener('click', openNew);
  });

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const id = document.getElementById('hf_id').value;
      const fd = new FormData(form);
      const payload = {};
      fd.forEach(function (v, k) { payload[k] = v; });
      payload.action = id ? 'update' : 'create';
      if (!id) delete payload.id;

      SF.post('actions/habit_actions.php', payload).then(function (json) {
        if (!json.ok) {
          showError(json.message || 'Please check the form.');
          return;
        }
        SF.closeModal('habitModal');
        applyPayload(json.data);
        SF.toast(json.message, 'success');
      });
    });
  }

  const grid = document.getElementById('habitGrid');
  if (grid) {
    grid.addEventListener('click', function (e) {
      const card = e.target.closest('[data-habit-id]');
      if (!card) return;
      const id = parseInt(card.getAttribute('data-habit-id'), 10);
      const habit = habits.find(function (h) { return h.id === id; });

      if (e.target.closest('[data-toggle-habit]')) {
        SF.post('actions/habit_actions.php', { action: 'toggle', id: id }).then(function (json) {
          if (!json.ok) return;
          // sync today's log locally
          const h = habits.find(function (x) { return x.id === id; });
          if (h) {
            h.logs = h.logs || {};
            if (json.data.habit && json.data.habit.done_today) h.logs[today] = true;
            else delete h.logs[today];
          }
          applyPayload(json.data);
          SF.toast(json.message, json.message.indexOf('unchecked') > -1 ? 'info' : 'success');
        });
        return;
      }
      if (e.target.closest('[data-edit-habit]')) {
        if (habit) openEdit(habit);
        return;
      }
      if (e.target.closest('[data-delete-habit]')) {
        SF.confirm('This habit and its history will be removed.', 'Delete habit').then(function (ok) {
          if (!ok) return;
          SF.post('actions/habit_actions.php', { action: 'delete', id: id }).then(function (json) {
            if (!json.ok) return;
            habits = habits.filter(function (h) { return h.id !== id; });
            applyPayload(json.data);
            SF.toast(json.message, 'success');
          });
        });
      }
    });
  }

  render();
})();
