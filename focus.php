<?php
$page_title = 'Focus Mode';
require_once __DIR__ . '/includes/auth_check.php';

$uid = user_id();

$summary = focus_summary($uid);

$stmt = db()->prepare(
    "SELECT f.id, f.duration_minutes, f.session_date, f.created_at, t.title AS task_title
     FROM focus_sessions f
     LEFT JOIN tasks t ON t.id = f.task_id AND t.user_id = f.user_id
     WHERE f.user_id = ? AND f.completed = 1
     ORDER BY f.id DESC LIMIT 8"
);
$stmt->execute([$uid]);
$sessions = $stmt->fetchAll();

$stmt = db()->prepare(
    "SELECT id, title FROM tasks
     WHERE user_id = ? AND deleted_at IS NULL AND status <> 'Completed'
     ORDER BY due_date IS NULL, due_date ASC, created_at ASC"
);
$stmt->execute([$uid]);
$openTasks = $stmt->fetchAll();

$autoTask = (int) ($_GET['task'] ?? 0);
$auto = isset($_GET['auto']);

$preselect = null;
if ($autoTask) {
    foreach ($openTasks as $t) {
        if ((int) $t['id'] === $autoTask) { $preselect = $t; break; }
    }
}

$totalWeekMin = $summary['week'];
$page_data = [
    'summary'    => $summary,
    'preselect'  => $preselect ? ['id' => (int) $preselect['id'], 'title' => $preselect['title']] : null,
    'auto_start' => $auto && $preselect,
];
$page_scripts = ['focus.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="grid lg:grid-cols-3 gap-6">
  <!-- Timer -->
  <div class="lg:col-span-2 rounded-3xl bg-gradient-to-br from-slate-900 via-slate-900 to-indigo-950 text-white p-6 sm:p-10 shadow-soft relative overflow-hidden">
    <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-indigo-600/20 blur-3xl"></div>
    <div class="relative">
      <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-2.5">
          <span class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center"><i data-lucide="timer" class="w-4.5 h-4.5 text-indigo-300"></i></span>
          <div>
            <p class="text-[11px] font-bold tracking-[0.2em] text-slate-400" id="timerModeLabel">FOCUS SESSION</p>
            <p class="text-sm text-slate-300" id="workingOn">No task selected</p>
          </div>
        </div>
        <!-- Task select -->
        <select id="focusTaskSelect" class="rounded-xl bg-white/10 border border-white/15 text-white text-sm px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-400 max-w-[260px]">
          <option value="" class="text-slate-900">— No linked task —</option>
          <?php foreach ($openTasks as $t): ?>
            <option value="<?= (int) $t['id'] ?>" class="text-slate-900" <?= $preselect && (int) $preselect['id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Presets -->
      <div class="mt-8 flex justify-center gap-2" id="presetGroup">
        <?php foreach ([15, 25, 45, 60] as $m): ?>
          <button type="button" data-minutes="<?= $m ?>"
                  class="preset rounded-xl px-4 py-2 text-sm font-semibold border transition
                         <?= $m === 25 ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white/5 border-white/10 text-slate-300 hover:bg-white/10' ?>">
            <?= $m ?>m
          </button>
        <?php endforeach; ?>
        <button type="button" data-minutes="5" class="preset break-preset rounded-xl px-4 py-2 text-sm font-semibold border bg-white/5 border-white/10 text-slate-300 hover:bg-white/10 transition">Break 5m</button>
      </div>

      <!-- Ring -->
      <div class="mt-8 flex justify-center">
        <div class="relative w-64 h-64 sm:w-72 sm:h-72">
          <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
            <circle cx="50" cy="50" r="45" fill="none" stroke="currentColor" class="text-white/10" stroke-width="5"/>
            <circle id="timerRing" cx="50" cy="50" r="45" fill="none" stroke="currentColor"
                    class="text-indigo-500" stroke-width="5" stroke-linecap="round"
                    stroke-dasharray="282.7" stroke-dashoffset="0"/>
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center">
            <p class="text-5xl sm:text-6xl font-semibold tracking-tight tabular-nums" id="timerDisplay">25:00</p>
            <p class="mt-1 text-xs text-slate-400" id="timerSub">Focus · 25 minutes</p>
          </div>
          <div id="sessionDoneOverlay" class="hidden absolute inset-0 rounded-full bg-slate-950/80 backdrop-blur-sm flex flex-col items-center justify-center text-center p-6">
            <span class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center"><i data-lucide="party-popper" class="w-6 h-6"></i></span>
            <p class="mt-3 text-sm font-semibold text-white">Focus session completed!</p>
            <p class="text-xs text-slate-400 mt-1" id="sessionDoneMinutes">25 minutes saved</p>
          </div>
        </div>
      </div>

      <!-- Controls -->
      <div class="mt-8 flex items-center justify-center gap-3">
        <button type="button" id="btnReset" class="w-12 h-12 rounded-xl bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 flex items-center justify-center transition" title="Reset">
          <i data-lucide="rotate-ccw" class="w-5 h-5"></i>
        </button>
        <button type="button" id="btnStartPause"
                class="min-w-[170px] rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-7 py-3.5 inline-flex items-center justify-center gap-2 shadow-lg shadow-indigo-900/40 transition">
          <span id="btnIconWrap" class="inline-flex items-center justify-center w-5 h-5"><i data-lucide="play" class="w-5 h-5"></i></span><span id="btnLabel">Start Focus</span>
        </button>
        <button type="button" id="btnSkip" class="w-12 h-12 rounded-xl bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 flex items-center justify-center transition" title="Skip to end">
          <i data-lucide="skip-forward" class="w-5 h-5"></i>
        </button>
      </div>

      <div id="taskCompleteRow" class="hidden mt-6 flex justify-center">
        <button type="button" id="btnCompleteTask" class="rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold px-5 py-3 inline-flex items-center gap-2 transition">
          <i data-lucide="check-circle-2" class="w-4 h-4"></i> Mark linked task complete
        </button>
      </div>

      <p class="mt-6 text-center text-xs text-slate-500">Sessions are saved to your history and feed your productivity score.</p>
    </div>
  </div>

  <!-- Side -->
  <div class="space-y-6">
    <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
      <h3 class="font-semibold text-slate-900 dark:text-white">Focus time</h3>
      <div class="mt-4 grid grid-cols-3 gap-3 text-center">
        <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 py-3">
          <p class="text-[10px] uppercase tracking-wide text-slate-400">Today</p>
          <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white" id="statFocusToday"><?= format_minutes($summary['today']) ?></p>
        </div>
        <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 py-3">
          <p class="text-[10px] uppercase tracking-wide text-slate-400">This week</p>
          <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white" id="statFocusWeek"><?= format_minutes($summary['week']) ?></p>
        </div>
        <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 py-3">
          <p class="text-[10px] uppercase tracking-wide text-slate-400">Sessions</p>
          <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white" id="statFocusSessions"><?= $summary['sessions_week'] ?></p>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
      <div class="flex items-center justify-between">
        <h3 class="font-semibold text-slate-900 dark:text-white">Recent sessions</h3>
        <a href="analytics.php" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Analytics</a>
      </div>
      <ul class="mt-4 space-y-3" id="recentSessions">
        <?php foreach ($sessions as $s): ?>
          <li class="flex items-center gap-3">
            <span class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-500/15 text-violet-500 flex items-center justify-center shrink-0"><i data-lucide="brain" class="w-4 h-4"></i></span>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate"><?= e($s['task_title'] ?: 'Deep work') ?></p>
              <p class="text-xs text-slate-400"><?= e(date('d M', strtotime($s['session_date']))) ?> · <?= format_minutes((int) $s['duration_minutes']) ?></p>
            </div>
          </li>
        <?php endforeach; ?>
        <?php if (!$sessions): ?>
          <li class="text-sm text-slate-400 text-center py-4">No focus sessions yet — start your first one.</li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-indigo-50/60 dark:bg-indigo-500/10 p-6">
      <div class="flex items-center gap-2 text-indigo-600 dark:text-indigo-400">
        <i data-lucide="lightbulb" class="w-4 h-4"></i>
        <p class="text-sm font-semibold">Pomodoro tip</p>
      </div>
      <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Work 25 minutes, break 5. After four rounds, take a longer 15–30 minute break.</p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
