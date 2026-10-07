/* StudentFlow - Goals page */
(function () {
  'use strict';
  const SF = window.SF;
  let goals = (window.SF_DATA && window.SF_DATA.goals) || [];

  const statusStyle = {
    'On Track': 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
    'At Risk': 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400',
    'Completed': 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400',
  };
  const statusBar = { 'On Track': 'bg-emerald-500', 'At Risk': 'bg-rose-500', 'Completed': 'bg-indigo-500' };

  function daysText(g) {
    if (g.days_left === null || g.days_left === undefined) return '';
    if (g.days_left < 0) return 'overdue';
    if (g.days_left === 0) return 'due today';
    return g.days_left + ' day' + (g.days_left === 1 ? '' : 's') + ' left';
  }

  function dateText(d) {
    if (!d) return null;
    return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function cardHtml(g) {
    const dt = dateText(g.target_date);
    const days = daysText(g);
    const urgent = g.days_left !== null && g.days_left <= 7 && g.progress < 100;
    return (
      '<div class="goal-card rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft" data-goal-id="' + g.id + '">' +
      '<div class="flex items-start justify-between gap-3"><div class="min-w-0">' +
      '<span class="rounded-full px-2.5 py-0.5 text-[11px] font-medium ' + (statusStyle[g.status] || statusStyle['On Track']) + '">' + SF.esc(g.status) + '</span>' +
      '<h3 class="mt-2 font-semibold text-slate-900 dark:text-white">' + SF.esc(g.title) + '</h3>' +
      (g.description ? '<p class="mt-1 text-xs text-slate-400 line-clamp-2">' + SF.esc(g.description) + '</p>' : '') +
      (g.github_repo_url ? '<a href="' + SF.esc(/^https?:\/\//i.test(g.github_repo_url) ? g.github_repo_url : 'https://github.com/' + g.github_repo_url) + '" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline"><i data-lucide="github" class="w-3.5 h-3.5"></i>' + SF.esc(g.github_repo_url) + '</a>' : '') +
      '</div><div class="flex gap-1 shrink-0">' +
      '<button type="button" data-edit-goal class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15" title="Edit"><i data-lucide="pencil" class="w-4 h-4"></i></button>' +
      '<button type="button" data-delete-goal class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/15" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>' +
      '</div></div>' +
      '<div class="mt-4"><div class="flex items-center justify-between text-xs mb-1.5">' +
      '<span class="text-slate-500 dark:text-slate-400">' + SF.esc(g.category) + '</span>' +
      '<span class="font-semibold text-slate-800 dark:text-slate-200">' + g.progress + '%</span></div>' +
      '<div class="h-2.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">' +
      '<div class="h-full rounded-full ' + (statusBar[g.status] || statusBar['On Track']) + ' transition-all duration-700" style="width:' + g.progress + '%"></div></div></div>' +
      '<div class="mt-4 flex items-center justify-between text-xs text-slate-400">' +
      (dt
        ? '<span class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5"></i>' + dt + '</span>' +
          '<span class="' + (urgent ? 'text-rose-500 font-medium' : '') + '">' + days + '</span>'
        : '<span>No target date</span>') +
      '</div>' +
      '<div class="mt-4 flex gap-2">' +
      '<button type="button" data-progress-goal class="flex-1 rounded-xl border border-slate-200 dark:border-slate-700 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">Update progress</button>' +
      '<button type="button" data-edit-goal class="rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">Edit</button>' +
      '</div></div>'
    );
  }

  function renderStats() {
    let active = 0, risk = 0, completed = 0, sum = 0;
    goals.forEach(function (g) {
      if (g.status === 'Completed') completed++;
      else if (g.status === 'At Risk') risk++;
      else active++;
      sum += g.progress;
    });
    const avg = goals.length ? Math.round(sum / goals.length) : 0;
    setText('statActive', active);
    setText('statRisk', risk);
    setText('statCompleted', completed);
    setText('statAvg', avg + '%');
  }

  function setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  function render() {
    const grid = document.getElementById('goalGrid');
    const empty = document.getElementById('goalEmpty');
    if (grid) {
      grid.innerHTML = goals.map(cardHtml).join('');
      if (window.lucide) lucide.createIcons({ nodes: [grid] });
    }
    if (empty) empty.classList.toggle('hidden', goals.length > 0);
    renderStats();
  }

  function upsert(g) {
    const idx = goals.findIndex(function (x) { return x.id === g.id; });
    if (idx >= 0) goals[idx] = g;
    else goals.push(g);
    render();
  }

  /* ------------- goal modal ------------- */
  const form = document.getElementById('goalForm');
  const modalError = document.getElementById('goalModalError');
  const progress = document.getElementById('gf_progress');
  const progressValue = document.getElementById('gf_progress_value');

  function showError(msg) {
    if (!modalError) return;
    modalError.textContent = msg;
    modalError.classList.toggle('hidden', !msg);
  }

  if (progress) {
    progress.addEventListener('input', function () { progressValue.textContent = progress.value + '%'; });
  }

  function openNew() {
    form.reset();
    document.getElementById('gf_id').value = '';
    progress.value = '0';
    progressValue.textContent = '0%';
    document.getElementById('goalModalTitle').textContent = 'New Goal';
    showError('');
    SF.openModal('goalModal');
    setTimeout(function () { document.getElementById('gf_title').focus(); }, 50);
  }

  function openEdit(g) {
    document.getElementById('gf_id').value = g.id;
    document.getElementById('gf_title').value = g.title;
    document.getElementById('gf_desc').value = g.description || '';
    document.getElementById('gf_repo').value = g.github_repo_url || '';
    document.getElementById('gf_category').value = g.category;
    document.getElementById('gf_target').value = g.target_date || '';
    progress.value = String(g.progress);
    progressValue.textContent = g.progress + '%';
    document.getElementById('goalModalTitle').textContent = 'Edit Goal';
    showError('');
    SF.openModal('goalModal');
  }

  ['addGoalBtn', 'addGoalBtn2'].forEach(function (id) {
    const btn = document.getElementById(id);
    if (btn) btn.addEventListener('click', openNew);
  });

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const id = document.getElementById('gf_id').value;
      const fd = new FormData(form);
      const payload = {};
      fd.forEach(function (v, k) { payload[k] = v; });
      payload.action = id ? 'update' : 'create';
      if (!id) delete payload.id;

      SF.post('actions/goal_actions.php', payload).then(function (json) {
        if (!json.ok) {
          showError(json.message || 'Please check the form.');
          return;
        }
        SF.closeModal('goalModal');
        upsert(json.data.goal);
        SF.toast(json.message, 'success');
      });
    });
  }

  /* ------------- progress modal ------------- */
  const pForm = document.getElementById('progressForm');
  const pRange = document.getElementById('pm_progress');
  const pValue = document.getElementById('pm_progress_value');

  if (pRange) {
    pRange.addEventListener('input', function () { pValue.textContent = pRange.value + '%'; });
  }

  if (pForm) {
    pForm.addEventListener('submit', function (e) {
      e.preventDefault();
      SF.post('actions/goal_actions.php', { action: 'progress', id: document.getElementById('pm_id').value, progress: pRange.value }).then(function (json) {
        if (!json.ok) { SF.toast(json.message || 'Update failed.', 'error'); return; }
        SF.closeModal('progressModal');
        upsert(json.data.goal);
        SF.toast(json.message, 'success');
      });
    });
  }

  /* ------------- card actions ------------- */
  const grid = document.getElementById('goalGrid');
  if (grid) {
    grid.addEventListener('click', function (e) {
      const card = e.target.closest('[data-goal-id]');
      if (!card) return;
      const id = parseInt(card.getAttribute('data-goal-id'), 10);
      const goal = goals.find(function (g) { return g.id === id; });

      if (e.target.closest('[data-edit-goal]')) {
        if (goal) openEdit(goal);
        return;
      }
      if (e.target.closest('[data-progress-goal]')) {
        if (!goal) return;
        document.getElementById('pm_id').value = goal.id;
        document.getElementById('pm_goal_title').textContent = goal.title;
        pRange.value = String(goal.progress);
        pValue.textContent = goal.progress + '%';
        SF.openModal('progressModal');
        return;
      }
      if (e.target.closest('[data-delete-goal]')) {
        SF.confirm('This goal will be permanently removed.', 'Delete goal').then(function (ok) {
          if (!ok) return;
          SF.post('actions/goal_actions.php', { action: 'delete', id: id }).then(function (json) {
            if (!json.ok) return;
            goals = goals.filter(function (g) { return g.id !== id; });
            render();
            SF.toast(json.message, 'success');
          });
        });
      }
    });
  }

  render();
})();
