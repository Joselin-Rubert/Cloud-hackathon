/* StudentFlow - Study Planner */
(function () {
  'use strict';
  const SF = window.SF;

  const phaseClass = {
    'concept': 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400',
    'examples': 'bg-sky-50 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400',
    'problems': 'bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400',
    'revision': 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
  };

  function phaseKey(phase) {
    const p = (phase || '').toLowerCase();
    if (p.indexOf('concept') > -1) return 'concept';
    if (p.indexOf('example') > -1) return 'examples';
    if (p.indexOf('problem') > -1) return 'problems';
    if (p.indexOf('revision') > -1) return 'revision';
    return 'concept';
  }

  function fmtDate(d) {
    return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short' });
  }

  /* ------------ generate ------------ */
  const form = document.getElementById('planForm');
  let planCtx = null;

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const submit = form.querySelector('button[type="submit"]');
      submit.disabled = true;
      const fd = new FormData(form);
      const payload = {};
      fd.forEach(function (v, k) { payload[k] = v; });
      payload.action = 'generate';

      SF.post('actions/study_actions.php', payload).then(function (json) {
        submit.disabled = false;
        if (!json.ok) { SF.toast(json.message || 'Could not generate a plan.', 'error'); return; }
        planCtx = {
          subject: json.data.subject,
          topic: json.data.topic,
          difficulty: json.data.difficulty,
          exam_date: json.data.exam_date,
          sessions: json.data.plan,
        };
        renderPreview(json.data.plan, json.data);
        SF.toast(json.message, 'success');
      });
    });
  }

  function renderPreview(sessions, meta) {
    const box = document.getElementById('planPreview');
    if (!box) return;
    document.getElementById('pv_title').textContent = meta.subject + ' — ' + meta.topic;
    const total = sessions.reduce(function (a, s) { return a + s.duration; }, 0);
    document.getElementById('pv_note').textContent =
      sessions.length + ' sessions · ' + SF.fmtMinutes(total) + ' total · ' +
      meta.difficulty + ' difficulty' + (meta.exam_date ? ' · exam ' + fmtDate(meta.exam_date) : ' · no exam date (7-day plan)');

    document.getElementById('pv_list').innerHTML = sessions
      .map(function (s) {
        const key = phaseKey(s.phase);
        return (
          '<li class="flex items-start gap-3 rounded-2xl border border-slate-100 dark:border-slate-800 p-3.5">' +
          '<span class="rounded-full px-2 py-0.5 text-[10px] font-semibold shrink-0 ' + phaseClass[key] + '">' + SF.esc(s.phase) + '</span>' +
          '<div class="min-w-0 flex-1"><p class="text-sm font-medium text-slate-800 dark:text-slate-200">' + SF.esc(s.topic) + '</p>' +
          '<p class="text-xs text-slate-400 mt-0.5">' + fmtDate(s.date) + ' · ' + s.time + ' · ' + SF.fmtMinutes(s.duration) + '</p></div>' +
          '</li>'
        );
      })
      .join('');
    box.classList.remove('hidden');
    if (window.lucide) lucide.createIcons({ nodes: [box] });
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  const btnDiscard = document.getElementById('pv_discard');
  if (btnDiscard) {
    btnDiscard.addEventListener('click', function () {
      document.getElementById('planPreview').classList.add('hidden');
      planCtx = null;
    });
  }

  const btnSave = document.getElementById('pv_save');
  if (btnSave) {
    btnSave.addEventListener('click', function () {
      if (!planCtx) return;
      btnSave.disabled = true;
      SF.post('actions/study_actions.php', {
        action: 'save',
        subject: planCtx.subject,
        topic: planCtx.topic,
        difficulty: planCtx.difficulty,
        exam_date: planCtx.exam_date || '',
        sessions: JSON.stringify(planCtx.sessions),
      }).then(function (json) {
        btnSave.disabled = false;
        if (!json.ok) { SF.toast(json.message || 'Save failed.', 'error'); return; }
        SF.flashAndReload(json.message);
      });
    });
  }

  /* ------------ session edit/delete ------------ */
  const sessionForm = document.getElementById('sessionForm');
  const grid = document.getElementById('sessionGroups');
  if (grid) {
    grid.addEventListener('click', function (e) {
      const row = e.target.closest('[data-session-id]');
      if (!row) return;
      const id = row.getAttribute('data-session-id');

      if (e.target.closest('[data-edit-session]')) {
        document.getElementById('sf_id').value = id;
        document.getElementById('sf_topic').value = row.getAttribute('data-topic') || '';
        document.getElementById('sf_dur').value = row.getAttribute('data-duration') || '30';
        document.getElementById('sf_date').value = row.getAttribute('data-date') || '';
        document.getElementById('sf_notes').value = row.getAttribute('data-notes') || '';
        SF.openModal('sessionModal');
        return;
      }
      if (e.target.closest('[data-delete-session]')) {
        SF.confirm('This study session will be removed from your plan.', 'Delete session').then(function (ok) {
          if (!ok) return;
          SF.post('actions/study_actions.php', { action: 'session_delete', id: id }).then(function (json) {
            if (!json.ok) { SF.toast(json.message || 'Delete failed.', 'error'); return; }
            SF.flashAndReload(json.message);
          });
        });
      }
    });
  }

  if (sessionForm) {
    sessionForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const fd = new FormData(sessionForm);
      const payload = {};
      fd.forEach(function (v, k) { payload[k] = v; });
      payload.action = 'session_update';
      SF.post('actions/study_actions.php', payload).then(function (json) {
        if (!json.ok) { SF.toast(json.message || 'Update failed.', 'error'); return; }
        SF.closeModal('sessionModal');
        SF.flashAndReload(json.message);
      });
    });
  }

  /* ------------ subject delete ------------ */
  const subjectList = document.getElementById('subjectList');
  if (subjectList) {
    subjectList.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-delete-subject]');
      if (!btn) return;
      const row = e.target.closest('[data-subject-id]');
      if (!row) return;
      SF.confirm('This subject will be removed (saved sessions stay).', 'Delete subject').then(function (ok) {
        if (!ok) return;
        SF.post('actions/study_actions.php', { action: 'subject_delete', id: row.getAttribute('data-subject-id') }).then(function (json) {
          if (!json.ok) { SF.toast(json.message || 'Delete failed.', 'error'); return; }
          SF.flashAndReload(json.message);
        });
      });
    });
  }
})();
