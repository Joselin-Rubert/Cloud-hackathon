<?php
$page_title = 'Habits';
require_once __DIR__ . '/includes/auth_check.php';

$uid  = user_id();
$today = date('Y-m-d');

$stmt = db()->prepare('SELECT * FROM habits WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$uid]);
$habits = $stmt->fetchAll();

// logs for the last 30 days
$since = date('Y-m-d', strtotime('-29 days'));
$stmt = db()->prepare('SELECT habit_id, log_date FROM habit_logs WHERE user_id = ? AND completed = 1 AND log_date >= ?');
$stmt->execute([$uid, $since]);
$logs = [];
foreach ($stmt->fetchAll() as $l) $logs[$l['habit_id']][$l['log_date']] = true;

$stmt = db()->prepare('SELECT COUNT(*) FROM habit_logs WHERE user_id = ? AND log_date = ? AND completed = 1');
$stmt->execute([$uid, $today]);
$doneToday = (int) $stmt->fetchColumn();

$freqLabel = ['daily' => 'Daily', 'weekly' => 'Weekly'];

$habitRows = [];
foreach ($habits as $h) {
    $hid = (int) $h['id'];
    $habitRows[] = [
        'id' => $hid,
        'name' => $h['name'],
        'description' => $h['description'],
        'frequency' => $h['frequency'],
        'target' => (int) $h['target'],
        'streak' => habit_streak($uid, $hid),
        'longest' => habit_longest_streak($uid, $hid),
        'weekly' => habit_completion_rate($uid, $hid, $h['frequency'], (int) $h['target'], 7),
        'monthly' => habit_completion_rate($uid, $hid, $h['frequency'], (int) $h['target'], (int) date('j')),
        'done_today' => isset($logs[$hid][$today]),
        'logs' => $logs[$hid] ?? [],
    ];
}

$page_data = ['habits' => $habitRows, 'done_today' => $doneToday, 'total' => count($habits), 'best_streak' => best_streak($uid), 'today' => $today];
$page_scripts = ['habits.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="grid sm:grid-cols-3 gap-4 mb-6">
  <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft">
    <div class="flex items-center justify-between"><p class="text-sm text-slate-500 dark:text-slate-400">Completed today</p>
      <span class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-500/15 text-emerald-500 flex items-center justify-center"><i data-lucide="circle-check" class="w-4 h-4"></i></span></div>
    <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white"><span id="statDoneToday"><?= $doneToday ?></span> <span class="text-base font-normal text-slate-400">/ <span id="statTotal"><?= count($habits) ?></span></span></p>
  </div>
  <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft">
    <div class="flex items-center justify-between"><p class="text-sm text-slate-500 dark:text-slate-400">Best streak</p>
      <span class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-500/15 text-amber-500 flex items-center justify-center"><i data-lucide="flame" class="w-4 h-4"></i></span></div>
    <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white" id="statBestStreak"><?= best_streak($uid) ?> <span class="text-base font-normal text-slate-400">days</span></p>
  </div>
  <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft flex items-center justify-between">
    <div>
      <p class="text-sm text-slate-500 dark:text-slate-400">Active habits</p>
      <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white" id="statActiveHabits"><?= count($habits) ?></p>
    </div>
    <button type="button" id="addHabitBtn" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-2.5 transition">
      <i data-lucide="plus" class="w-4 h-4"></i> New Habit
    </button>
  </div>
</div>

<div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5" id="habitGrid">
  <?php foreach ($habitRows as $h): ?>
    <div class="habit-card rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft flex flex-col" data-habit-id="<?= $h['id'] ?>">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <h3 class="font-semibold text-slate-900 dark:text-white truncate"><?= e($h['name']) ?></h3>
            <span class="shrink-0 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 text-[10px] font-medium"><?= $freqLabel[$h['frequency']] ?? 'Daily' ?><?= $h['frequency'] === 'weekly' ? ' ×' . $h['target'] : '' ?></span>
          </div>
          <?php if ($h['description']): ?>
            <p class="mt-0.5 text-xs text-slate-400 line-clamp-1"><?= e($h['description']) ?></p>
          <?php endif; ?>
        </div>
        <div class="flex gap-1 shrink-0">
          <button type="button" data-edit-habit class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15"><i data-lucide="pencil" class="w-4 h-4"></i></button>
          <button type="button" data-delete-habit class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/15"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
        </div>
      </div>

      <button type="button" data-toggle-habit
              class="mt-4 w-full rounded-2xl border-2 <?= $h['done_today'] ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-500/10' : 'border-slate-200 dark:border-slate-700 hover:border-indigo-400' ?> px-4 py-3 flex items-center justify-center gap-2 text-sm font-semibold transition"
              data-done-label>
        <i data-lucide="check-circle-2" class="w-4.5 h-4.5 <?= $h['done_today'] ? 'text-emerald-500' : 'text-slate-300 dark:text-slate-600' ?>"></i>
        <span class="<?= $h['done_today'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400' ?>"><?= $h['done_today'] ? 'Completed today' : 'Mark complete' ?></span>
      </button>

      <div class="mt-4 grid grid-cols-3 gap-2 text-center">
        <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 py-2">
          <p class="text-[10px] uppercase tracking-wide text-slate-400">Streak</p>
          <p class="text-sm font-semibold text-slate-900 dark:text-white" data-habit-streak><?= $h['streak'] ?>d</p>
        </div>
        <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 py-2">
          <p class="text-[10px] uppercase tracking-wide text-slate-400">Weekly</p>
          <p class="text-sm font-semibold text-slate-900 dark:text-white" data-habit-weekly><?= $h['weekly'] ?>%</p>
        </div>
        <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 py-2">
          <p class="text-[10px] uppercase tracking-wide text-slate-400">Monthly</p>
          <p class="text-sm font-semibold text-slate-900 dark:text-white" data-habit-monthly><?= $h['monthly'] ?>%</p>
        </div>
      </div>

      <!-- 30 day calendar -->
      <div class="mt-4">
        <p class="text-[10px] uppercase tracking-wide text-slate-400 mb-1.5">Last 30 days <span data-streak-fire class="<?= $h['streak'] >= 3 ? '' : 'hidden' ?>">· 🔥 <span data-streak-fire-count><?= $h['streak'] ?></span>-day streak</span></p>
        <div class="flex flex-wrap gap-1" data-habit-calendar>
          <?php for ($i = 29; $i >= 0; $i--):
            $d = date('Y-m-d', strtotime('-' . $i . ' days'));
            $on = isset($h['logs'][$d]); ?>
            <span class="w-2.5 h-2.5 rounded-[3px] <?= $on ? 'bg-emerald-500' : 'bg-slate-100 dark:bg-slate-800' ?>" title="<?= e(date('d M', strtotime($d))) ?>"></span>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div id="habitEmpty" class="hidden rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 p-12 text-center">
  <span class="inline-flex w-14 h-14 rounded-2xl bg-amber-50 dark:bg-amber-500/15 text-amber-500 items-center justify-center"><i data-lucide="repeat" class="w-7 h-7"></i></span>
  <p class="mt-4 font-semibold text-slate-800 dark:text-slate-200">Build your first habit</p>
  <p class="text-sm text-slate-400 mt-1">Study 2 hours · Drink water · Exercise — small wins, every day.</p>
  <button type="button" id="addHabitBtn2" class="mt-5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-3">Create habit</button>
</div>

<!-- Habit modal -->
<div id="habitModal" class="hidden fixed inset-0 z-[60] p-4">
  <div class="min-h-full flex items-center justify-center">
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" data-close-modal></div>
    <div class="relative w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-soft border border-slate-200 dark:border-slate-800 p-6">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white" id="habitModalTitle">New Habit</h3>
        <button type="button" data-close-modal class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <form id="habitForm" class="mt-5 space-y-4">
        <p id="habitModalError" class="hidden rounded-xl bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400 text-sm px-3.5 py-2.5"></p>
        <input type="hidden" name="id" id="hf_id">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Name *</label>
          <input name="name" id="hf_name" required maxlength="120" placeholder="Study 2 hours"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Description</label>
          <input name="description" id="hf_desc" placeholder="Optional"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Frequency</label>
            <select name="frequency" id="hf_freq"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="daily">Daily</option><option value="weekly">Weekly</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Target / week</label>
            <input type="number" name="target" id="hf_target" min="1" max="30" value="1"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" data-close-modal class="rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
          <button type="submit" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
