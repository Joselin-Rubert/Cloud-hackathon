/* StudentFlow - dashboard (live stats, recommendation, daily brief) */
(function () {
  'use strict';
  const SF = window.SF;
  const data = window.SF_DATA || {};

  function renderStats(stats, display, footers) {
    Object.keys(display || {}).forEach(function (key) {
      const el = document.querySelector('[data-stat="' + key + '"]');
      if (el) el.textContent = display[key];
      const f = document.querySelector('[data-stat-footer="' + key + '"]');
      if (f && footers && footers[key] !== undefined) f.textContent = footers[key];
    });
    const overdueEl = document.querySelector('[data-stat="overdue"]');
    if (overdueEl && stats) {
      const card = overdueEl.closest('.rounded-2xl');
      if (card) {
        const iconWrap = card.querySelector('[class*="bg-"]');
        const footer = card.querySelector('[data-stat-footer="overdue"]');
        const isOver = stats.overdue > 0;
        if (iconWrap) {
          iconWrap.className = 'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ' +
            (isOver ? 'bg-rose-50 dark:bg-rose-500/15 text-rose-500' : 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-500');
        }
        if (overdueEl) overdueEl.className = 'mt-2 text-xl sm:text-2xl font-semibold ' + (isOver ? 'text-rose-500' : 'text-slate-900 dark:text-white');
        if (footer) footer.className = 'mt-1 text-[11px] ' + (isOver ? 'text-rose-400' : 'text-slate-400 dark:text-slate-500');
      }
    }
  }

  function renderScore(p) {
    const value = document.querySelector('[data-score-value]');
    const ring = document.querySelector('[data-score-ring]');
    if (value) value.textContent = p.overall;
    if (ring) ring.setAttribute('stroke-dasharray', p.overall + ' 100');
    ['tasks', 'study', 'habits', 'focus', 'goals'].forEach(function (key) {
      const label = document.querySelector('[data-breakdown="' + key + '"]');
      const bar = document.querySelector('[data-breakdown-bar="' + key + '"]');
      if (label) label.textContent = p[key] + '%';
      if (bar) bar.style.width = p[key] + '%';
    });
  }

  function renderLifeScore(s) {
    const value = document.querySelector('[data-ls-value]');
    const ring = document.querySelector('[data-ls-ring]');
    const label = document.querySelector('[data-ls-label]');
    if (value) value.textContent = s.overall;
    if (ring) {
      ring.setAttribute('stroke-dasharray', s.overall + ' 100');
      const color = s.overall >= 75 ? 'text-emerald-500' : s.overall >= 60 ? 'text-indigo-600' : s.overall >= 40 ? 'text-amber-500' : 'text-rose-500';
      ring.className = color;
    }
    if (label) label.textContent = s.label;
    const changeEl = document.querySelector('[data-ls-change]');
    if (changeEl) changeEl.textContent = s.change > 0 ? '+' + s.change : s.change < 0 ? '−' + Math.abs(s.change) : '±0';
    const changeWrap = document.querySelector('[data-ls-change]') ? changeEl.closest('p') : null;
    if (changeWrap) {
      const icon = changeWrap.querySelector('i');
      if (icon) {
        const name = s.change > 0 ? 'arrow-up' : s.change < 0 ? 'arrow-down' : 'minus';
        const color = s.change > 0 ? 'text-emerald-600 dark:text-emerald-400' : s.change < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400';
        icon.setAttribute('data-lucide', name);
        changeWrap.className = 'mt-1 inline-flex items-center gap-1 text-xs font-medium ' + color;
        if (window.lucide) lucide.createIcons({ nodes: [icon] });
      }
    }
    (s.categories || []).forEach(function (c) {
      const val = document.querySelector('[data-ls-val="' + c.key + '"]');
      const bar = document.querySelector('[data-ls-bar="' + c.key + '"]');
      if (val) val.textContent = c.percent + '%';
      if (bar) bar.style.width = c.percent + '%';
    });
  }

  function renderRisk(r) {
    const counts = (r && r.counts) || {};
    const open = document.querySelector('[data-risk-open]');
    if (open) open.textContent = counts.open || 0;
    ['critical', 'approaching', 'safe'].forEach(function (level) {
      const el = document.querySelector('[data-risk-count="' + level + '"]');
      if (el) el.textContent = counts[level] || 0;
    });
    const list = document.querySelector('[data-risk-top]');
    if (!list) return;
    const top = (r && r.top) || [];
    if (!top.length) {
      list.innerHTML = '<li class="text-xs text-slate-400 py-1">No critical tasks — nice work 🎉</li>';
      return;
    }
    list.innerHTML = top.map(function (t) {
      return '<li class="flex items-start gap-2 text-xs">' +
        '<span class="mt-1 w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0"></span>' +
        '<span class="min-w-0"><span class="text-slate-700 dark:text-slate-200 line-clamp-1">' + SF.esc(t.title) + '</span>' +
        '<span class="text-slate-400">' + (t.due_date ? new Date(t.due_date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) : '') +
        (t.due_time ? ' · ' + t.due_time.substring(0, 5) : '') + '</span></span></li>';
    }).join('');
  }

  function renderBrief(lines) {
    const list = document.querySelector('[data-brief-list]');
    if (!list) return;
    if (!lines || !lines.length) {
      list.innerHTML = '<li class="text-sm text-slate-400">Add tasks, expenses and habits to receive your personalized daily brief.</li>';
      return;
    }
    list.innerHTML = lines
      .map(function (line) {
        return '<li class="flex items-start gap-2.5 text-sm text-slate-600 dark:text-slate-300">' +
          '<span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span><span>' + SF.esc(line) + '</span></li>';
      })
      .join('');
  }

  function recommendationHtml(rec) {
    if (!rec) {
      return '<div class="h-full rounded-3xl border-2 border-dashed border-slate-300 dark:border-slate-700 p-8 text-center flex flex-col items-center justify-center">' +
        '<span class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-500/15 text-indigo-500 flex items-center justify-center"><i data-lucide="sparkles" class="w-7 h-7"></i></span>' +
        '<p class="mt-4 text-[11px] font-bold tracking-[0.22em] text-slate-400">WHAT SHOULD I DO NOW?</p>' +
        '<h3 class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">Nothing is queued yet</h3>' +
        '<p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-sm">All caught up! Add a new task to get your next recommendation.</p>' +
        '<a href="tasks.php" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-3 transition"><i data-lucide="plus" class="w-4 h-4"></i> Add a task</a>' +
        '</div>';
    }
    const reasons = (rec.reasons || []).slice(0, 4)
      .map(function (r) {
        return '<li class="flex items-start gap-2"><i data-lucide="chevron-right" class="w-4 h-4 mt-0.5 text-indigo-300 shrink-0"></i><span>' + SF.esc(r) + '</span></li>';
      });
    if (rec.due_date) {
      const d = new Date(rec.due_date + 'T00:00:00');
      const due = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
      reasons.push('<li class="flex items-start gap-2"><i data-lucide="chevron-right" class="w-4 h-4 mt-0.5 text-indigo-300 shrink-0"></i><span>Due ' + due + '</span></li>');
    }
    return '<div class="h-full rounded-3xl bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-700 text-white p-6 sm:p-8 shadow-soft relative overflow-hidden">' +
      '<div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-white/10"></div>' +
      '<div class="absolute -right-6 top-24 w-32 h-32 rounded-full bg-white/10"></div>' +
      '<div class="relative">' +
      '<div class="flex items-center justify-between gap-3">' +
      '<p class="text-[11px] sm:text-xs font-bold tracking-[0.22em] text-indigo-200">WHAT SHOULD I DO NOW?</p>' +
      '<span class="rounded-full bg-white/15 backdrop-blur px-3 py-1 text-xs font-semibold">Priority score: <span>' + rec.score + '</span></span></div>' +
      '<h3 class="mt-4 text-2xl sm:text-3xl font-semibold tracking-tight">' + SF.esc(rec.title) + '</h3>' +
      '<ul class="mt-4 space-y-2 text-sm text-indigo-100">' + reasons.join('') + '</ul>' +
      '<p class="mt-4 text-sm text-indigo-100/90 italic">“' + SF.esc(rec.why || '') + '”</p>' +
      '<div class="mt-6 flex flex-wrap items-center gap-3">' +
      '<a href="focus.php?task=' + rec.id + '&auto=1" class="inline-flex items-center gap-2 rounded-xl bg-white text-indigo-700 font-bold px-6 py-3 text-sm hover:bg-indigo-50 transition shadow-lg shadow-indigo-900/20"><i data-lucide="play" class="w-4 h-4"></i> START TASK</a>' +
      '<a href="tasks.php" class="inline-flex items-center gap-2 rounded-xl bg-white/10 border border-white/20 text-white font-medium px-5 py-3 text-sm hover:bg-white/15 transition">View all tasks</a>' +
      '</div></div></div>';
  }

  function renderRecommendation(rec) {
    const section = document.querySelector('[data-card="recommendation"]');
    if (!section) return;
    section.innerHTML = recommendationHtml(rec);
    if (window.lucide) lucide.createIcons({ nodes: [section] });
    const action = document.querySelector('[data-brief-action]');
    if (action) action.textContent = rec ? 'Complete your ' + rec.title + ' first.' : 'Nothing urgent — pick any open task or start a focus session.';
  }

  function priorityItemHtml(t) {
    const prioClass =
      t.priority === 'High'
        ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400'
        : t.priority === 'Medium'
        ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400'
        : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400';
    const due =
      t.section === 'overdue'
        ? '<span class="rounded-full bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400 px-2 py-0.5 font-medium">Overdue</span>'
        : t.due_date
        ? '<span class="text-slate-400">Due ' + new Date(t.due_date + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) + '</span>'
        : '';
    return (
      '<li class="flex items-start gap-3 rounded-2xl border border-slate-100 dark:border-slate-800 p-3 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition" data-task-id="' + t.id + '">' +
      '<button type="button" data-complete-task="' + t.id + '" class="mt-0.5 w-5 h-5 rounded-md border-2 ' +
      (t.status === 'In Progress' ? 'border-amber-400 bg-amber-400/30' : 'border-slate-300 dark:border-slate-600') +
      ' hover:border-indigo-500 flex items-center justify-center shrink-0 transition" title="Mark complete">' +
      '<i data-lucide="check" class="w-3 h-3 text-white opacity-0"></i></button>' +
      '<div class="min-w-0 flex-1"><p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">' + SF.esc(t.title) + '</p>' +
      '<div class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px]">' +
      '<span class="rounded-full px-2 py-0.5 font-medium ' + prioClass + '">' + SF.esc(t.priority) + '</span>' +
      due +
      '<span class="text-slate-400">· ' + SF.fmtMinutes(t.estimated_minutes) + '</span></div></div>' +
      '<span class="text-[10px] font-bold text-indigo-500 bg-indigo-50 dark:bg-indigo-500/15 rounded-lg px-2 py-1 shrink-0">' + t.score + '</span></li>'
    );
  }

  function renderPriorities(tasks) {
    const list = document.querySelector('[data-priority-list]');
    if (!list) return;
    if (!tasks || !tasks.length) {
      list.innerHTML = '<li class="text-sm text-slate-400 text-center py-6">No open tasks — you\'re all caught up 🎉</li>';
      return;
    }
    list.innerHTML = tasks.map(priorityItemHtml).join('');
    if (window.lucide) lucide.createIcons({ nodes: [list] });
  }

  function refresh() {
    return SF.get('api/dashboard.php').then(function (json) {
      if (!json.ok) return;
      const d = json.data;
      renderStats(d.stats, d.display, d.footers);
      renderScore(d.productivity);
      renderLifeScore(d.life_score);
      renderRisk(d.risk_summary);
      renderBrief(d.brief);
      renderRecommendation(d.recommendation);
      renderPriorities(d.priorities);
      SF.setUnread(d.unread);
    });
  }

  function bind() {
    document.addEventListener('click', function (e) {
      const help = e.target.closest('[data-ls-help]');
      if (help) { SF.openModal('lifeScoreModal'); return; }

      const btn = e.target.closest('[data-complete-task]');
      if (!btn) return;
      const id = btn.getAttribute('data-complete-task');
      btn.disabled = true;
      SF.post('actions/task_actions.php', { action: 'status', id: id, status: 'Completed' }).then(function (json) {
        if (!json.ok) { btn.disabled = false; return; }
        SF.toast(json.message, 'success');
        const li = btn.closest('li[data-task-id]');
        if (li) li.remove();
        if (!document.querySelector('[data-priority-list] li')) {
          renderPriorities([]);
        }
        refresh();
      });
    });
  }

  bind();
  // expose for other scripts
  window.SFDashboardRefresh = refresh;
})();
