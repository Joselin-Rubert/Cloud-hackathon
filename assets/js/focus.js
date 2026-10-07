/* StudentFlow - Focus Mode (Pomodoro timer) */
(function () {
  'use strict';
  const SF = window.SF;
  const data = window.SF_DATA || {};

  const display = document.getElementById('timerDisplay');
  if (!display) return;

  const CIRC = 282.7;
  let mode = 'focus';
  let presetMin = 25;
  let total = presetMin * 60;
  let remaining = total;
  let running = false;
  let interval = null;
  let elapsed = 0;
  let iconState = null;

  const el = {
    sub: document.getElementById('timerSub'),
    ring: document.getElementById('timerRing'),
    modeLabel: document.getElementById('timerModeLabel'),
    workingOn: document.getElementById('workingOn'),
    btnLabel: document.getElementById('btnLabel'),
    select: document.getElementById('focusTaskSelect'),
    overlay: document.getElementById('sessionDoneOverlay'),
    doneMinutes: document.getElementById('sessionDoneMinutes'),
    completeRow: document.getElementById('taskCompleteRow'),
    statToday: document.getElementById('statFocusToday'),
    statWeek: document.getElementById('statFocusWeek'),
    statSessions: document.getElementById('statFocusSessions'),
    recent: document.getElementById('recentSessions'),
  };

  function fmt(sec) {
    sec = Math.max(0, sec);
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  function render() {
    display.textContent = fmt(remaining);
    const progress = total > 0 ? remaining / total : 0;
    if (el.ring) {
      el.ring.setAttribute('stroke-dashoffset', String(CIRC * (1 - progress)));
      el.ring.setAttribute('class', mode === 'focus' ? 'text-indigo-500' : 'text-emerald-500');
    }
    if (el.sub) el.sub.textContent = (mode === 'focus' ? 'Focus · ' : 'Break · ') + Math.round(total / 60) + ' minutes';
    if (el.modeLabel) el.modeLabel.textContent = mode === 'focus' ? 'FOCUS SESSION' : 'BREAK TIME';
    if (el.btnLabel) {
      el.btnLabel.textContent = running ? 'Pause' : elapsed > 0 && mode === 'focus' ? 'Resume' : (mode === 'focus' ? 'Start Focus' : 'Start Break');
    }
    // swap play/pause icon only when the state actually changes
    if (running !== iconState) {
      iconState = running;
      const wrap = document.getElementById('btnIconWrap');
      if (wrap) {
        wrap.innerHTML = '<i data-lucide="' + (running ? 'pause' : 'play') + '" class="w-5 h-5"></i>';
        if (window.lucide) lucide.createIcons({ nodes: [wrap] });
      }
    }
  }

  function stop() {
    running = false;
    if (interval) { clearInterval(interval); interval = null; }
  }

  function beep() {
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      const ctx = new Ctx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = 660;
      gain.gain.setValueAtTime(0.12, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.8);
      osc.start();
      osc.stop(ctx.currentTime + 0.8);
    } catch (e) { /* no audio */ }
  }

  function currentTaskId() {
    return el.select ? el.select.value : '';
  }

  function saveSession(minutes) {
    const payload = { duration_minutes: minutes, completed: 1 };
    const tid = currentTaskId();
    if (tid) payload.task_id = tid;
    SF.post('actions/focus_actions.php', payload).then(function (json) {
      if (!json.ok) return;
      const s = json.data.summary;
      if (el.statToday) el.statToday.textContent = SF.fmtMinutes(s.today);
      if (el.statWeek) el.statWeek.textContent = SF.fmtMinutes(s.week);
      if (el.statSessions) el.statSessions.textContent = s.sessions_week;
      if (el.recent && !el.recent.querySelector('[data-new-session]')) {
        const empty = el.recent.querySelector('li.text-slate-400');
        if (empty) empty.remove();
        const li = document.createElement('li');
        li.setAttribute('data-new-session', '1');
        li.className = 'flex items-center gap-3';
        li.innerHTML =
          '<span class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-500/15 text-violet-500 flex items-center justify-center shrink-0"><i data-lucide="brain" class="w-4 h-4"></i></span>' +
          '<div class="min-w-0 flex-1"><p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">' +
          SF.esc(json.data.task_title || 'Deep work') +
          '</p><p class="text-xs text-slate-400">Just now · ' + SF.fmtMinutes(minutes) + '</p></div>';
        el.recent.prepend(li);
        if (window.lucide) lucide.createIcons({ nodes: [li] });
      }
    });
  }

  function finishFocus() {
    stop();
    const minutes = Math.round(elapsed / 60);
    if (minutes >= 1) saveSession(minutes);

    beep();
    if (el.overlay) {
      el.overlay.classList.remove('hidden');
      el.overlay.classList.add('flex');
      if (el.doneMinutes) el.doneMinutes.textContent = SF.fmtMinutes(Math.max(minutes, 1)) + ' saved to your history';
    }
    if (currentTaskId() && el.completeRow) el.completeRow.classList.remove('hidden');
    SF.toast('Focus session completed!', 'success');

    // switch to break mode
    mode = 'break';
    total = 5 * 60;
    remaining = total;
    elapsed = 0;
    render();
  }

  function finishBreak() {
    stop();
    beep();
    SF.toast('Break finished — ready for another round.', 'info');
    mode = 'focus';
    total = presetMin * 60;
    remaining = total;
    elapsed = 0;
    render();
  }

  function tick() {
    remaining--;
    if (mode === 'focus') elapsed++;
    if (remaining <= 0) {
      remaining = 0;
      render();
      if (mode === 'focus') finishFocus();
      else finishBreak();
      return;
    }
    render();
  }

  function start() {
    if (running) return;
    if (remaining <= 0) remaining = total;
    running = true;
    interval = setInterval(tick, 1000);
    render();
  }

  function pause() {
    stop();
    render();
  }

  function reset() {
    stop();
    elapsed = 0;
    remaining = total;
    if (el.overlay) { el.overlay.classList.add('hidden'); el.overlay.classList.remove('flex'); }
    if (el.completeRow) el.completeRow.classList.add('hidden');
    render();
  }

  function setMinutes(mins, isBreak) {
    stop();
    elapsed = 0;
    mode = isBreak ? 'break' : 'focus';
    if (!isBreak) presetMin = mins;
    total = mins * 60;
    remaining = total;
    if (el.overlay) { el.overlay.classList.add('hidden'); el.overlay.classList.remove('flex'); }
    if (el.completeRow) el.completeRow.classList.add('hidden');
    document.querySelectorAll('.preset').forEach(function (b) {
      const on = parseInt(b.getAttribute('data-minutes'), 10) === mins && (isBreak ? b.classList.contains('break-preset') : !b.classList.contains('break-preset'));
      b.className =
        'preset rounded-xl px-4 py-2 text-sm font-semibold border transition ' +
        (b.classList.contains('break-preset') ? 'break-preset ' : '') +
        (on ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white/5 border-white/10 text-slate-300 hover:bg-white/10');
    });
    render();
  }

  /* ---- controls ---- */
  document.getElementById('btnStartPause').addEventListener('click', function () {
    if (running) pause();
    else start();
  });
  document.getElementById('btnReset').addEventListener('click', reset);
  document.getElementById('btnSkip').addEventListener('click', function () {
    if (!running) { SF.toast('Start the timer first.', 'info'); return; }
    remaining = 1;
    tick();
  });
  if (el.overlay) {
    el.overlay.addEventListener('click', function () {
      el.overlay.classList.add('hidden');
      el.overlay.classList.remove('flex');
    });
  }

  document.querySelectorAll('.preset').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setMinutes(parseInt(btn.getAttribute('data-minutes'), 10), btn.classList.contains('break-preset'));
    });
  });

  if (el.select) {
    el.select.addEventListener('change', function () {
      const opt = el.select.options[el.select.selectedIndex];
      el.workingOn.textContent = el.select.value ? 'Working on: ' + opt.text : 'No task selected';
      if (el.completeRow) el.completeRow.classList.add('hidden');
    });
    if (data.preselect) {
      el.workingOn.textContent = 'Working on: ' + data.preselect.title;
    }
  }

  const btnComplete = document.getElementById('btnCompleteTask');
  if (btnComplete) {
    btnComplete.addEventListener('click', function () {
      const tid = currentTaskId();
      if (!tid) return;
      btnComplete.disabled = true;
      SF.post('actions/task_actions.php', { action: 'status', id: tid, status: 'Completed' }).then(function (json) {
        btnComplete.disabled = false;
        if (!json.ok) return;
        SF.toast(json.message, 'success');
        const opt = el.select.options[el.select.selectedIndex];
        if (opt) opt.remove();
        el.select.value = '';
        el.workingOn.textContent = 'No task selected';
        el.completeRow.classList.add('hidden');
      });
    });
  }

  // auto-start when arriving from "START TASK"
  if (data.auto_start) {
    history.replaceState(null, '', 'focus.php');
    setTimeout(function () { start(); }, 400);
  }

  render();
})();
