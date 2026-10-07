<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/auth_check.php';

$uid = user_id();
sync_notifications($uid);
require_once __DIR__ . '/services/GitHubService.php';
require_once __DIR__ . '/services/StudentLifeScoreService.php';

$stats     = dashboard_stats($uid);
$rec       = recommendation($uid);
$score     = productivity_score($uid);
$brief     = daily_brief($uid);
$settings  = get_settings($uid);
$user      = current_user();
$lifeScore = StudentLifeScoreService::compute($uid);
$riskSummary = DeadlineRiskService::summary($uid);

// GitHub Explorer snapshot (cached data; never hits the API on page load)
$githubSnapshot = GitHubService::dashboardSnapshot($uid);

// Today's priorities: top open tasks by smart score
$stmt = db()->prepare(
    "SELECT * FROM tasks
     WHERE user_id = ? AND deleted_at IS NULL AND status <> 'Completed'
     ORDER BY due_date IS NULL, due_date ASC, created_at ASC"
);
$stmt->execute([$uid]);
$openTasks = $stmt->fetchAll();
usort($openTasks, function ($a, $b) {
    [$sa] = task_score($a);
    [$sb] = task_score($b);
    return $sb <=> $sa;
});
$priorities = array_slice($openTasks, 0, 5);

// Recent notifications
$stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 4');
$stmt->execute([$uid]);
$recentNotifs = $stmt->fetchAll();

// Greeting
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
$firstName = explode(' ', trim($user['name']))[0];

$statCards = [
    ['today_tasks',   'list-checks', 'Today\'s Tasks', (string) $stats['today_tasks'], $stats['completed_today'] . ' completed today', 'text-indigo-500', 'bg-indigo-50 dark:bg-indigo-500/15'],
    ['overdue',       'triangle-alert', 'Overdue', (string) $stats['overdue'], $stats['overdue'] > 0 ? 'needs attention' : 'all caught up', $stats['overdue'] > 0 ? 'text-rose-500' : 'text-emerald-500', $stats['overdue'] > 0 ? 'bg-rose-50 dark:bg-rose-500/15' : 'bg-emerald-50 dark:bg-emerald-500/15'],
    ['study_today',   'book-open', 'Study Time', format_minutes($stats['study_today']), 'target ' . format_minutes((int) $settings['daily_study_target']) . '/day', 'text-sky-500', 'bg-sky-50 dark:bg-sky-500/15'],
    ['month_spent',   'wallet', 'Monthly Spending', format_money($stats['month_spent']), $settings['monthly_budget'] > 0 ? 'budget ' . format_money((float) $settings['monthly_budget']) : 'no budget set', 'text-emerald-500', 'bg-emerald-50 dark:bg-emerald-500/15'],
    ['habit_streak',  'flame', 'Habit Streak', $stats['habit_streak'] . ' day' . ($stats['habit_streak'] === 1 ? '' : 's'), $stats['habits_done'] . '/' . $stats['habits_total'] . ' habits today', 'text-amber-500', 'bg-amber-50 dark:bg-amber-500/15'],
    ['goals_progress','target', 'Goals Progress', $stats['goals_progress'] . '%', 'average of active goals', 'text-violet-500', 'bg-violet-50 dark:bg-violet-500/15'],
];

$page_data = [
    'stats'          => $stats,
    'recommendation' => $rec ? task_json($rec) + ['why' => $rec['why']] : null,
    'brief'          => $brief,
    'productivity'   => $score,
    'priorities'     => array_map('task_json', $priorities),
];
$page_scripts = ['dashboard.js'];

require __DIR__ . '/includes/header.php';
?>

<!-- Greeting -->
<div class="mb-6">
  <h2 class="text-2xl sm:text-[28px] font-semibold tracking-tight text-slate-900 dark:text-white"><?= e($greeting) ?>, <?= e($firstName) ?> 👋</h2>
  <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Here's what needs your attention today.</p>
</div>

<!-- Stat cards -->
<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
  <?php foreach ($statCards as [$key, $icon, $label, $value, $footer, $iconColor, $iconBg]): ?>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-soft hover:-translate-y-0.5 transition">
      <div class="flex items-center justify-between gap-2">
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 truncate"><?= e($label) ?></p>
        <span class="w-8 h-8 rounded-lg <?= $iconBg ?> <?= $iconColor ?> flex items-center justify-center shrink-0"><i data-lucide="<?= $icon ?>" class="w-4 h-4"></i></span>
      </div>
      <p class="mt-2 text-xl sm:text-2xl font-semibold text-slate-900 dark:text-white" data-stat="<?= $key ?>"><?= e($value) ?></p>
      <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500 truncate" data-stat-footer="<?= $key ?>"><?= e($footer) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<!-- Life Score · Deadline Risk · Quick actions -->
<?php
$ls = $lifeScore;
$lsColor = $ls['overall'] >= 75 ? 'text-emerald-500' : ($ls['overall'] >= 60 ? 'text-indigo-600' : ($ls['overall'] >= 40 ? 'text-amber-500' : 'text-rose-500'));
$lsBadge = $ls['change'] > 0
    ? ['text-emerald-600 dark:text-emerald-400', 'arrow-up', '+' . $ls['change']]
    : ($ls['change'] < 0
        ? ['text-rose-600 dark:text-rose-400', 'arrow-down', '−' . abs($ls['change'])]
        : ['text-slate-400', 'minus', '±0']);
$rk = $riskSummary['counts'];
?>
<div class="mt-6 grid lg:grid-cols-3 gap-6">
  <!-- Student Life Score -->
  <section class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft" data-card="life-score">
    <div class="flex items-center justify-between">
      <p class="text-[11px] font-bold tracking-[0.18em] text-slate-400">STUDENT LIFE SCORE</p>
      <span class="w-8 h-8 rounded-lg bg-violet-50 dark:bg-violet-500/15 text-violet-500 flex items-center justify-center"><i data-lucide="sparkles" class="w-4 h-4"></i></span>
    </div>

    <div class="mt-5 flex items-center gap-5">
      <div class="relative w-24 h-24 shrink-0">
        <svg viewBox="0 0 36 36" class="w-24 h-24 -rotate-90">
          <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" class="text-slate-100 dark:text-slate-800" stroke-width="3.2"/>
          <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" class="<?= $lsColor ?>" stroke-width="3.2" stroke-linecap="round"
                  stroke-dasharray="<?= (int) $ls['overall'] ?> 100" data-ls-ring/>
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
          <span class="text-2xl font-semibold text-slate-900 dark:text-white" data-ls-value><?= (int) $ls['overall'] ?></span>
          <span class="text-[10px] text-slate-400">/ 100</span>
        </div>
      </div>
      <div class="min-w-0">
        <p class="text-sm font-medium text-slate-800 dark:text-slate-200" data-ls-label><?= e($ls['label']) ?></p>
        <p class="mt-1 inline-flex items-center gap-1 text-xs font-medium <?= $lsBadge[0] ?>">
          <i data-lucide="<?= $lsBadge[1] ?>" class="w-3.5 h-3.5"></i><span data-ls-change><?= $lsBadge[2] ?></span>
          <span class="text-slate-400 font-normal">vs last week</span>
        </p>
      </div>
    </div>

    <div class="mt-5 space-y-3">
      <?php $lsColors = ['bg-indigo-500', 'bg-sky-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500', 'bg-violet-500']; ?>
      <?php foreach ($ls['categories'] as $i => $cat): ?>
        <?php $cprog = min(100, (int) $cat['percent']); ?>
        <div>
          <div class="flex items-center justify-between text-xs mb-1">
            <span class="text-slate-500 dark:text-slate-400"><?= e($cat['name']) ?> <span class="text-slate-400 dark:text-slate-600">(<?= (int) $cat['weight'] ?>%)</span></span>
            <span class="font-medium text-slate-700 dark:text-slate-300" data-ls-val="<?= $cat['key'] ?>"><?= $cprog ?>%</span>
          </div>
          <div class="h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
            <div class="h-full rounded-full <?= $lsColors[$i % 6] ?> transition-all duration-700" style="width:<?= $cprog ?>%" data-ls-bar="<?= $cat['key'] ?>"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <button type="button" data-ls-help class="mt-5 w-full inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
      <i data-lucide="info" class="w-3.5 h-3.5"></i> How is my score calculated?
    </button>
  </section>

  <!-- Deadline risk -->
  <section class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft" data-card="risk-summary">
    <div class="flex items-center justify-between">
      <p class="text-[11px] font-bold tracking-[0.18em] text-slate-400">DEADLINE RISK</p>
      <span class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-500/15 text-rose-500 flex items-center justify-center"><i data-lucide="triangle-alert" class="w-4 h-4"></i></span>
    </div>

    <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
      <span class="font-semibold text-slate-800 dark:text-slate-200" data-risk-open><?= (int) $rk['open'] ?></span> open task<?= $rk['open'] === 1 ? '' : 's' ?> checked
    </p>

    <div class="mt-4 space-y-2.5" data-risk-counts>
      <div class="flex items-center gap-2 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 px-3.5 py-2.5">
        <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
        <span class="text-xs font-medium text-rose-600 dark:text-rose-400">Critical</span>
        <span class="ml-auto text-sm font-semibold text-rose-600 dark:text-rose-400" data-risk-count="critical"><?= (int) $rk['critical'] ?></span>
      </div>
      <div class="flex items-center gap-2 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-100 dark:border-amber-500/20 px-3.5 py-2.5">
        <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0"></span>
        <span class="text-xs font-medium text-amber-600 dark:text-amber-400">Approaching</span>
        <span class="ml-auto text-sm font-semibold text-amber-600 dark:text-amber-400" data-risk-count="approaching"><?= (int) $rk['approaching'] ?></span>
      </div>
      <div class="flex items-center gap-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20 px-3.5 py-2.5">
        <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">On track</span>
        <span class="ml-auto text-sm font-semibold text-emerald-600 dark:text-emerald-400" data-risk-count="safe"><?= (int) $rk['safe'] ?></span>
      </div>
    </div>

    <ul class="mt-4 space-y-2" data-risk-top>
      <?php foreach ($riskSummary['top'] as $c): ?>
        <li class="flex items-start gap-2 text-xs">
          <span class="mt-1 w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0"></span>
          <span class="min-w-0">
            <span class="text-slate-700 dark:text-slate-200 line-clamp-1"><?= e($c['title']) ?></span>
            <span class="text-slate-400"><?= e(date('d M', strtotime($c['due_date']))) ?><?= $c['due_time'] ? ' · ' . e(date('h:i A', strtotime($c['due_time']))) : '' ?></span>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>

    <a href="tasks.php" class="mt-5 inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
      Review all tasks <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
    </a>
  </section>

  <!-- Learning resources shortcut -->
  <section class="rounded-3xl bg-gradient-to-br from-sky-600 via-sky-600 to-indigo-700 text-white p-6 shadow-soft relative overflow-hidden">
    <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full bg-white/10"></div>
    <div class="relative flex flex-col h-full">
      <span class="w-9 h-9 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center"><i data-lucide="library" class="w-4.5 h-4.5"></i></span>
      <h3 class="mt-4 font-semibold">Learning Resources</h3>
      <p class="mt-1 text-sm text-sky-100">Find books and study material for Java, DBMS, web development and more.</p>
      <div class="mt-auto pt-5 flex items-center gap-3">
        <a href="learning-resources.php" class="inline-flex items-center gap-2 rounded-xl bg-white text-sky-700 font-bold px-5 py-2.5 text-sm hover:bg-sky-50 transition shadow-lg shadow-sky-900/20">
          <i data-lucide="search" class="w-4 h-4"></i> Search resources
        </a>
        <a href="github-explorer.php" class="inline-flex items-center gap-2 rounded-xl border border-white/30 text-white font-medium px-4 py-2.5 text-sm hover:bg-white/10 transition">
          GitHub
        </a>
      </div>
    </div>
  </section>
</div>

<!-- Signature + side cards -->
<div class="mt-6 grid lg:grid-cols-3 gap-6">
  <!-- WHAT SHOULD I DO NOW? -->
  <section class="lg:col-span-2" data-card="recommendation">
    <?php if ($rec): ?>
      <div class="h-full rounded-3xl bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-700 text-white p-6 sm:p-8 shadow-soft relative overflow-hidden">
        <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-white/10"></div>
        <div class="absolute -right-6 top-24 w-32 h-32 rounded-full bg-white/10"></div>
        <div class="relative">
          <div class="flex items-center justify-between gap-3">
            <p class="text-[11px] sm:text-xs font-bold tracking-[0.22em] text-indigo-200">WHAT SHOULD I DO NOW?</p>
            <span class="rounded-full bg-white/15 backdrop-blur px-3 py-1 text-xs font-semibold">Priority score: <span data-rec-score><?= (int) $rec['score'] ?></span></span>
          </div>

          <h3 class="mt-4 text-2xl sm:text-3xl font-semibold tracking-tight" data-rec-title><?= e($rec['title']) ?></h3>

          <ul class="mt-4 space-y-2 text-sm text-indigo-100" data-rec-reasons>
            <?php foreach (array_slice($rec['reasons'], 0, 4) as $reason): ?>
              <li class="flex items-start gap-2"><i data-lucide="chevron-right" class="w-4 h-4 mt-0.5 text-indigo-300 shrink-0"></i><span><?= e($reason) ?></span></li>
            <?php endforeach; ?>
            <?php if ($rec['due_date']): ?>
              <li class="flex items-start gap-2"><i data-lucide="chevron-right" class="w-4 h-4 mt-0.5 text-indigo-300 shrink-0"></i><span>Due <?= e(date('d M Y', strtotime($rec['due_date']))) ?><?= $rec['due_time'] ? ' at ' . e(date('h:i A', strtotime($rec['due_time']))) : '' ?></span></li>
            <?php endif; ?>
          </ul>

          <p class="mt-4 text-sm text-indigo-100/90 italic" data-rec-why>“<?= e($rec['why']) ?>”</p>

          <div class="mt-6 flex flex-wrap items-center gap-3">
            <a href="focus.php?task=<?= (int) $rec['id'] ?>&auto=1" data-rec-start
               class="inline-flex items-center gap-2 rounded-xl bg-white text-indigo-700 font-bold px-6 py-3 text-sm hover:bg-indigo-50 transition shadow-lg shadow-indigo-900/20">
              <i data-lucide="play" class="w-4 h-4"></i> START TASK
            </a>
            <a href="tasks.php" class="inline-flex items-center gap-2 rounded-xl bg-white/10 border border-white/20 text-white font-medium px-5 py-3 text-sm hover:bg-white/15 transition">
              View all tasks
            </a>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="h-full rounded-3xl border-2 border-dashed border-slate-300 dark:border-slate-700 p-8 text-center flex flex-col items-center justify-center">
        <span class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-500/15 text-indigo-500 flex items-center justify-center"><i data-lucide="sparkles" class="w-7 h-7"></i></span>
        <p class="mt-4 text-[11px] font-bold tracking-[0.22em] text-slate-400">WHAT SHOULD I DO NOW?</p>
        <h3 class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">Nothing is queued yet</h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-sm">Add your first task and the system will instantly rank it and recommend your next action.</p>
        <a href="tasks.php" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-3 transition">
          <i data-lucide="plus" class="w-4 h-4"></i> Add a task
        </a>
      </div>
    <?php endif; ?>
  </section>

  <!-- Productivity score -->
  <section class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft" data-card="productivity">
    <div class="flex items-center justify-between">
      <p class="text-[11px] font-bold tracking-[0.18em] text-slate-400">PRODUCTIVITY SCORE</p>
      <span class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-500/15 text-indigo-500 flex items-center justify-center"><i data-lucide="gauge" class="w-4 h-4"></i></span>
    </div>

    <div class="mt-5 flex items-center gap-5">
      <div class="relative w-24 h-24 shrink-0">
        <svg viewBox="0 0 36 36" class="w-24 h-24 -rotate-90">
          <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" class="text-slate-100 dark:text-slate-800" stroke-width="3.2"/>
          <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" class="text-indigo-600" stroke-width="3.2" stroke-linecap="round"
                  stroke-dasharray="<?= (int) $score['overall'] ?> 100" data-score-ring/>
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
          <span class="text-2xl font-semibold text-slate-900 dark:text-white" data-score-value><?= (int) $score['overall'] ?></span>
          <span class="text-[10px] text-slate-400">/ 100</span>
        </div>
      </div>
      <div class="min-w-0">
        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
          <?= $score['overall'] >= 80 ? 'Excellent momentum' : ($score['overall'] >= 50 ? 'Solid progress' : ($score['overall'] > 0 ? 'Room to grow' : 'Start your first task')) ?>
        </p>
        <p class="mt-1 text-xs text-slate-400">Weighted across tasks, study, habits, focus and goals.</p>
      </div>
    </div>

    <div class="mt-5 space-y-3" data-score-breakdown>
      <?php
      $breakdown = [
          ['Tasks', $score['tasks'], 30, 'bg-indigo-500'],
          ['Study', $score['study'], 25, 'bg-sky-500'],
          ['Habits', $score['habits'], 20, 'bg-amber-500'],
          ['Focus', $score['focus'], 15, 'bg-violet-500'],
          ['Goals', $score['goals'], 10, 'bg-emerald-500'],
      ];
      foreach ($breakdown as [$label, $val, $weight, $color]): ?>
        <div>
          <div class="flex items-center justify-between text-xs mb-1">
            <span class="text-slate-500 dark:text-slate-400"><?= $label ?> <span class="text-slate-400 dark:text-slate-600">(<?= $weight ?>%)</span></span>
            <span class="font-medium text-slate-700 dark:text-slate-300" data-breakdown="<?= strtolower($label) ?>"><?= (int) $val ?>%</span>
          </div>
          <div class="h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
            <div class="h-full rounded-full <?= $color ?> transition-all duration-700" style="width:<?= (int) $val ?>%" data-breakdown-bar="<?= strtolower($label) ?>"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<!-- Brief + priorities -->
<div class="mt-6 grid lg:grid-cols-3 gap-6">
  <!-- Daily brief -->
  <section class="lg:col-span-2 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft" data-card="brief">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <span class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center"><i data-lucide="newspaper" class="w-4.5 h-4.5"></i></span>
        <div>
          <h3 class="font-semibold text-slate-900 dark:text-white">StudentFlow Daily Brief</h3>
          <p class="text-xs text-slate-400"><?= date('l, d F Y') ?></p>
        </div>
      </div>
    </div>

    <ul class="mt-5 space-y-2.5" data-brief-list>
      <?php foreach ($brief as $line): ?>
        <li class="flex items-start gap-2.5 text-sm text-slate-600 dark:text-slate-300">
          <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
          <span><?= e($line) ?></span>
        </li>
      <?php endforeach; ?>
      <?php if (!$brief): ?>
        <li class="text-sm text-slate-400">Add tasks, expenses and habits to receive your personalized daily brief.</li>
      <?php endif; ?>
    </ul>

    <?php if ($rec): ?>
      <div class="mt-5 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20 p-4">
        <p class="text-[11px] font-bold tracking-[0.18em] text-indigo-500">RECOMMENDED ACTION</p>
        <p class="mt-1.5 text-sm font-medium text-slate-800 dark:text-slate-200" data-brief-action>Complete your <?= e($rec['title']) ?> first.</p>
      </div>
    <?php endif; ?>
  </section>

  <!-- Today's priorities -->
  <section class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
    <div class="flex items-center justify-between">
      <h3 class="font-semibold text-slate-900 dark:text-white">Today's Priorities</h3>
      <a href="tasks.php" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">View all</a>
    </div>

    <ul class="mt-4 space-y-2.5" data-priority-list>
      <?php foreach ($priorities as $t): ?>
        <?php [$sc] = task_score($t); $sec = task_section($t); ?>
        <li class="flex items-start gap-3 rounded-2xl border border-slate-100 dark:border-slate-800 p-3 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition" data-task-id="<?= (int) $t['id'] ?>">
          <button type="button" data-complete-task="<?= (int) $t['id'] ?>"
                  class="mt-0.5 w-5 h-5 rounded-md border-2 <?= $t['status'] === 'In Progress' ? 'border-amber-400 bg-amber-400/30' : 'border-slate-300 dark:border-slate-600' ?> hover:border-indigo-500 flex items-center justify-center shrink-0 transition"
                  title="Mark complete">
            <i data-lucide="check" class="w-3 h-3 text-white opacity-0"></i>
          </button>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate"><?= e($t['title']) ?></p>
            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px]">
              <span class="rounded-full px-2 py-0.5 font-medium <?= $t['priority'] === 'High' ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400' : ($t['priority'] === 'Medium' ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400') ?>"><?= e($t['priority']) ?></span>
              <?php if ($sec === 'overdue'): ?>
                <span class="rounded-full bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400 px-2 py-0.5 font-medium">Overdue</span>
              <?php elseif ($t['due_date']): ?>
                <span class="text-slate-400">Due <?= e(date('d M', strtotime($t['due_date']))) ?></span>
              <?php endif; ?>
              <span class="text-slate-400">· <?= format_minutes((int) $t['estimated_minutes']) ?></span>
            </div>
          </div>
          <span class="text-[10px] font-bold text-indigo-500 bg-indigo-50 dark:bg-indigo-500/15 rounded-lg px-2 py-1 shrink-0"><?= $sc ?></span>
        </li>
      <?php endforeach; ?>
      <?php if (!$priorities): ?>
        <li class="text-sm text-slate-400 text-center py-6">No open tasks — you're all caught up 🎉</li>
      <?php endif; ?>
    </ul>
  </section>
</div>

<!-- Developer Snapshot (GitHub Explorer) -->
<section class="mt-6 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2.5">
      <span class="w-9 h-9 rounded-xl bg-slate-950 dark:bg-slate-800 text-white dark:text-slate-200 flex items-center justify-center"><i data-lucide="github" class="w-4.5 h-4.5"></i></span>
      <div>
        <h3 class="font-semibold text-slate-900 dark:text-white">Developer Snapshot</h3>
        <p class="text-xs text-slate-400">Public GitHub profile connected to your account.</p>
      </div>
    </div>
    <a href="github-explorer.php" class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
      Open GitHub Explorer <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
    </a>
  </div>

  <?php if ($githubSnapshot && !empty($githubSnapshot['profile']['username'])): ?>
    <?php $gp = $githubSnapshot['profile']; $gstats = $githubSnapshot['stats'] ?? []; ?>
    <div class="mt-5 grid lg:grid-cols-4 gap-5 items-center">
      <div class="flex items-center gap-3 min-w-0">
        <?php if (!empty($gp['avatar_url'])): ?>
          <img src="<?= e($gp['avatar_url']) ?>" alt="" class="w-14 h-14 rounded-2xl object-cover border border-slate-200 dark:border-slate-700 shrink-0">
        <?php else: ?>
          <span class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 font-semibold flex items-center justify-center shrink-0"><?= e(strtoupper(mb_substr($gp['name'] ?: $gp['username'], 0, 1))) ?></span>
        <?php endif; ?>
        <div class="min-w-0">
          <p class="text-sm font-semibold text-slate-900 dark:text-white truncate"><?= e($gp['name'] ?: $gp['username']) ?></p>
          <p class="text-xs text-indigo-600 dark:text-indigo-400 truncate">@<?= e($gp['username']) ?></p>
          <p class="mt-0.5 text-[11px] text-slate-400 truncate">Last synced <?= e(time_ago($gp['last_synced'])) ?></p>
        </div>
      </div>

      <div class="lg:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-3 text-center">
        <?php
        $snap = [
            ['Public Repos', number_format((int) $gp['public_repos'])],
            ['Followers', number_format((int) $gp['followers'])],
            ['Total Stars', number_format((int) ($gstats['total_stars'] ?? 0))],
            ['Top Language', e($gp['top_language'] ?: '—')],
            ['Recent Activity', e($gp['recent_activity'] ?: '—')],
            ['Activity Count', number_format((int) $gp['recent_activity_count'])],
        ];
        foreach ($snap as [$lbl, $val]): ?>
          <div class="rounded-2xl border border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40 px-3 py-2.5">
            <p class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase"><?= $lbl ?></p>
            <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-200 truncate" title="<?= e($val) ?>"><?= $val ?></p>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="flex lg:flex-col items-center gap-3 justify-center">
        <a href="<?= e($gp['github_profile_url']) ?>" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 rounded-xl border border-indigo-200 dark:border-indigo-500/40 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 text-sm font-semibold px-4 py-2.5 transition">
          View GitHub <i data-lucide="external-link" class="w-4 h-4"></i>
        </a>
        <a href="github-explorer.php" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-2.5 transition">
          Explore
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="mt-4 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <p class="text-sm text-slate-500 dark:text-slate-400">Connect a public GitHub username to see developer activity.</p>
      <a href="github-explorer.php"
         class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5 shadow-soft transition">
        <i data-lucide="github" class="w-4 h-4"></i> Explore GitHub
      </a>
    </div>
  <?php endif; ?>
</section>

<!-- Recent notifications -->
<?php if ($recentNotifs): ?>
<div class="mt-6 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
  <div class="flex items-center justify-between">
    <h3 class="font-semibold text-slate-900 dark:text-white">Recent notifications</h3>
    <a href="notifications.php" class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Open center</a>
  </div>
  <div class="mt-4 grid sm:grid-cols-2 xl:grid-cols-4 gap-3">
    <?php foreach ($recentNotifs as $n): ?>
      <div class="rounded-2xl border border-slate-100 dark:border-slate-800 p-3.5 <?= $n['is_read'] ? 'opacity-70' : '' ?>">
        <div class="flex items-center justify-between gap-2">
          <span class="text-[10px] font-semibold uppercase tracking-wide text-indigo-500"><?= e($n['type']) ?></span>
          <span class="text-[10px] text-slate-400"><?= e(time_ago($n['created_at'])) ?></span>
        </div>
        <p class="mt-1.5 text-sm text-slate-700 dark:text-slate-300 line-clamp-2"><?= e($n['message']) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Life score explanation modal -->
<div id="lifeScoreModal" class="hidden fixed inset-0 z-[60] p-4 overflow-y-auto">
  <div class="min-h-full flex items-center justify-center">
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" data-close-modal></div>
    <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-soft border border-slate-200 dark:border-slate-800 p-6">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Student Life Score</h3>
        <button type="button" data-close-modal class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
        A 0–100 balance metric built from six everyday activities. It updates automatically as you work — no data entry needed.
      </p>

      <div class="mt-5 space-y-2.5">
        <?php
        $pillars = [
            ['Task Completion', 30, 'Share of tasks you have finished.'],
            ['Study Sessions', 20, 'Minutes studied this week vs your daily target.'],
            ['Goal Progress', 15, 'Average progress across all your goals.'],
            ['Habit Consistency', 15, 'Habits checked off in the last 7 days.'],
            ['Deadline Management', 10, 'How many open tasks are still comfortably doable.'],
            ['Budget Management', 10, 'Spending this month vs your budget.'],
        ];
        foreach ($pillars as [$name, $weight, $desc]): ?>
          <div class="flex items-start gap-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 px-3.5 py-3">
            <span class="mt-0.5 w-8 h-8 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold flex items-center justify-center shrink-0"><?= $weight ?>%</span>
            <div class="min-w-0">
              <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= $name ?></p>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5"><?= $desc ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="mt-5 rounded-2xl border border-violet-200 dark:border-violet-500/30 bg-violet-50 dark:bg-violet-500/10 px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
        <p class="font-semibold text-violet-700 dark:text-violet-300 mb-1.5">Score levels</p>
        <div class="grid grid-cols-2 gap-x-4 gap-y-1">
          <span>90+ — Outstanding</span><span>75–89 — Excellent</span>
          <span>60–74 — Good</span><span>40–59 — Getting Started</span>
          <span>0–39 — Needs Improvement</span>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
