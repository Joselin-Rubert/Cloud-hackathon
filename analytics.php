<?php
$page_title = 'Analytics';
require_once __DIR__ . '/includes/auth_check.php';

$uid = user_id();
$analytics = analytics_data($uid);
$p = $analytics['productivity'];
$riskCounts = $analytics['deadline_risk']['counts'] ?? ['critical' => 0, 'open' => 0];

$metricCards = [
    ['Task Completion', $analytics['tasks']['completion_rate'] . '%', 'circle-check', 'text-indigo-500', 'bg-indigo-50 dark:bg-indigo-500/15', $analytics['tasks']['status']['Completed'] . ' of ' . array_sum($analytics['tasks']['status']) . ' tasks'],
    ['Study Hours', $analytics['study']['total_hours'] . 'h', 'book-open', 'text-sky-500', 'bg-sky-50 dark:bg-sky-500/15', 'all time'],
    ['Focus Hours', $analytics['focus']['total_hours'] . 'h', 'timer', 'text-violet-500', 'bg-violet-50 dark:bg-violet-500/15', 'all time'],
    ['Habit Consistency', $analytics['habits']['consistency'] . '%', 'repeat', 'text-amber-500', 'bg-amber-50 dark:bg-amber-500/15', $analytics['habits']['count'] . ' active habit' . ($analytics['habits']['count'] === 1 ? '' : 's')],
    ['Goal Progress', $analytics['goals']['average'] . '%', 'target', 'text-emerald-500', 'bg-emerald-50 dark:bg-emerald-500/15', 'average'],
    ['Deadline Risk', (int) $riskCounts['critical'] . ' critical', 'triangle-alert', 'text-rose-500', 'bg-rose-50 dark:bg-rose-500/15', (int) $riskCounts['open'] . ' open tasks checked'],
    ['Productivity Score', $p['overall'] . '/100', 'gauge', 'text-rose-500', 'bg-rose-50 dark:bg-rose-500/15', 'weighted total'],
];

$page_scripts = ['charts.js', 'analytics.js'];
require __DIR__ . '/includes/header.php';
?>

<!-- Metrics -->
<div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
  <?php foreach ($metricCards as [$label, $value, $icon, $color, $bg, $footer]): ?>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-soft">
      <div class="flex items-center justify-between gap-2">
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 truncate"><?= $label ?></p>
        <span class="w-8 h-8 rounded-lg <?= $bg ?> <?= $color ?> flex items-center justify-center shrink-0"><i data-lucide="<?= $icon ?>" class="w-4 h-4"></i></span>
      </div>
      <p class="mt-2 text-xl sm:text-2xl font-semibold text-slate-900 dark:text-white"><?= e($value) ?></p>
      <p class="mt-1 text-[11px] text-slate-400 truncate"><?= e($footer) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<!-- Productivity breakdown -->
<div class="mt-6 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
  <div class="flex items-center justify-between">
    <div>
      <h3 class="font-semibold text-slate-900 dark:text-white">Student Productivity Score</h3>
      <p class="text-xs text-slate-400 mt-0.5">Tasks 30% · Study 25% · Habits 20% · Focus 15% · Goals 10%</p>
    </div>
    <span class="text-2xl font-semibold text-slate-900 dark:text-white"><?= (int) $p['overall'] ?><span class="text-sm text-slate-400"> / 100</span></span>
  </div>
  <div class="mt-5 grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
    <?php
    $parts = [
        ['Tasks', $p['tasks'], 'bg-indigo-500'],
        ['Study', $p['study'], 'bg-sky-500'],
        ['Habits', $p['habits'], 'bg-amber-500'],
        ['Focus', $p['focus'], 'bg-violet-500'],
        ['Goals', $p['goals'], 'bg-emerald-500'],
    ];
    foreach ($parts as [$label, $val, $color]): ?>
      <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/50 p-4">
        <div class="flex items-center justify-between text-xs mb-2">
          <span class="text-slate-500 dark:text-slate-400"><?= $label ?></span>
          <span class="font-semibold text-slate-800 dark:text-slate-200"><?= (int) $val ?>%</span>
        </div>
        <div class="h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
          <div class="h-full rounded-full <?= $color ?>" style="width:<?= (int) $val ?>%"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Charts -->
<div class="mt-6 grid lg:grid-cols-2 gap-6">
  <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft">
    <div class="flex items-center justify-between">
      <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Task completion — last 7 days</h3>
      <button type="button" id="refreshAnalytics" class="text-slate-400 hover:text-indigo-600 transition" title="Refresh"><i data-lucide="refresh-cw" class="w-4 h-4"></i></button>
    </div>
    <div class="mt-3 h-60 relative"><div class="chart-loading">Loading chart…</div><canvas id="taskChart"></canvas></div>
  </div>
  <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft">
    <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Weekly study hours</h3>
    <div class="mt-3 h-60 relative"><div class="chart-loading">Loading chart…</div><canvas id="studyChart"></canvas></div>
  </div>
  <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft">
    <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Weekly focus hours</h3>
    <div class="mt-3 h-60 relative"><div class="chart-loading">Loading chart…</div><canvas id="focusChart"></canvas></div>
  </div>
  <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft">
    <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Expense categories</h3>
    <div class="mt-3 h-60 relative"><div class="chart-loading">Loading chart…</div><canvas id="expenseChart"></canvas></div>
  </div>
  <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft">
    <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Habit consistency — last 7 days</h3>
    <div class="mt-3 h-60 relative"><div class="chart-loading">Loading chart…</div><canvas id="habitChart"></canvas></div>
  </div>
  <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft">
    <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Goal progress</h3>
    <div class="mt-3 h-60 relative"><div class="chart-loading">Loading chart…</div><canvas id="goalChart"></canvas></div>
  </div>
  <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft">
    <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Deadline risk</h3>
    <div class="mt-3 h-60 relative"><div class="chart-loading">Loading chart…</div><canvas id="riskChart"></canvas></div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
