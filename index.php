<?php
require_once __DIR__ . '/config/auth.php';
$page_title = 'Smart Student Life Assistant';
$dark = false;
$logged = is_logged_in();
require __DIR__ . '/includes/head.php';
?>
<!-- Nav -->
<header class="sticky top-0 z-40 bg-white/85 dark:bg-slate-950/85 backdrop-blur border-b border-slate-200 dark:border-slate-800">
  <div class="max-w-7xl mx-auto px-5 h-16 flex items-center justify-between">
    <a href="index.php" class="flex items-center gap-2.5">
      <span class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center"><i data-lucide="zap" class="w-5 h-5"></i></span>
      <span class="font-semibold tracking-tight text-slate-900 dark:text-white text-lg">StudentFlow</span>
    </a>
    <nav class="flex items-center gap-2 sm:gap-3">
      <?php if ($logged): ?>
        <a href="dashboard.php" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-2.5 transition">Open Dashboard</a>
      <?php else: ?>
        <a href="login.php" class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white px-3 py-2">Login</a>
        <a href="register.php" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-2.5 transition">Get Started</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<!-- Hero -->
<section class="relative overflow-hidden">
  <div class="absolute inset-0 bg-gradient-to-b from-indigo-50/70 via-slate-50 to-slate-50 dark:from-slate-900 dark:via-slate-950 dark:to-slate-950"></div>
  <div class="relative max-w-7xl mx-auto px-5 pt-16 pb-20 sm:pt-24 text-center">
    <span class="inline-flex items-center gap-2 rounded-full border border-indigo-200 dark:border-indigo-500/30 bg-white dark:bg-slate-900 px-4 py-1.5 text-xs font-medium text-indigo-700 dark:text-indigo-300 shadow-sm">
      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> DATA → ANALYSIS → PRIORITY → ACTION
    </span>
    <h1 class="mt-6 text-4xl sm:text-6xl font-semibold tracking-tight text-slate-900 dark:text-white leading-[1.08]">
      Your Student Life.<br class="hidden sm:block"> One Smart Dashboard.
    </h1>
    <p class="mt-5 max-w-2xl mx-auto text-lg text-slate-600 dark:text-slate-400 leading-relaxed">
      Plan your studies, manage tasks, track expenses, build habits and stay focused — all in one place.
    </p>
    <div class="mt-9 flex flex-col sm:flex-row items-center justify-center gap-3">
      <a href="register.php" class="w-full sm:w-auto rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-7 py-3.5 shadow-soft transition">Get Started</a>
      <a href="login.php" class="w-full sm:w-auto rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 font-semibold px-7 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-800 transition">Login</a>
    </div>

    <!-- Dashboard mockup -->
    <div class="mt-14 max-w-5xl mx-auto text-left">
      <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xl shadow-slate-900/10 overflow-hidden">
        <div class="flex items-center gap-2 px-5 h-12 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60">
          <span class="w-3 h-3 rounded-full bg-rose-400"></span>
          <span class="w-3 h-3 rounded-full bg-amber-400"></span>
          <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
          <span class="ml-3 text-xs text-slate-400 font-mono">localhost/studentflow/dashboard.php</span>
        </div>
        <div class="grid sm:grid-cols-3 gap-4 p-5 sm:p-6 bg-slate-50 dark:bg-slate-950/40">
          <!-- What should I do now -->
          <div class="sm:col-span-2 rounded-2xl bg-indigo-600 text-white p-5 shadow-soft">
            <p class="text-[11px] font-semibold tracking-[0.18em] text-indigo-200">WHAT SHOULD I DO NOW?</p>
            <p class="mt-2 text-xl font-semibold">Complete DBMS Assignment</p>
            <div class="mt-3 flex flex-wrap gap-2 text-xs">
              <span class="rounded-full bg-white/15 px-2.5 py-1">Due tomorrow</span>
              <span class="rounded-full bg-white/15 px-2.5 py-1">High priority</span>
              <span class="rounded-full bg-white/15 px-2.5 py-1">Est. 2 hours</span>
              <span class="rounded-full bg-amber-400/90 text-slate-900 px-2.5 py-1 font-semibold">Score 95</span>
            </div>
            <span class="mt-4 inline-flex items-center gap-2 rounded-xl bg-white text-indigo-700 text-sm font-semibold px-4 py-2.5">
              <i data-lucide="play" class="w-4 h-4"></i> START TASK
            </span>
          </div>
          <!-- Productivity score -->
          <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5">
            <p class="text-[11px] font-semibold tracking-[0.18em] text-slate-400">PRODUCTIVITY SCORE</p>
            <div class="mt-3 flex items-center gap-4">
              <svg viewBox="0 0 36 36" class="w-16 h-16 -rotate-90">
                <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" class="text-slate-100 dark:text-slate-800" stroke-width="3.4"/>
                <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" class="text-indigo-600" stroke-width="3.4" stroke-linecap="round" stroke-dasharray="84 100"/>
              </svg>
              <div>
                <p class="text-2xl font-semibold text-slate-900 dark:text-white">84<span class="text-sm text-slate-400"> / 100</span></p>
                <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">+6 this week</p>
              </div>
            </div>
            <div class="mt-4 space-y-2">
              <?php foreach ([['Tasks', 90], ['Study', 82], ['Habits', 75]] as [$lbl, $val]): ?>
                <div class="flex items-center gap-2 text-[11px]">
                  <span class="w-12 text-slate-500"><?= $lbl ?></span>
                  <span class="flex-1 h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden"><span class="block h-full rounded-full bg-indigo-500" style="width:<?= $val ?>%"></span></span>
                  <span class="text-slate-400 w-6 text-right"><?= $val ?>%</span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <!-- Stats -->
          <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <div class="flex items-center justify-between"><p class="text-sm text-slate-500">Today's Tasks</p><i data-lucide="list-checks" class="w-4 h-4 text-indigo-500"></i></div>
            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">6</p>
          </div>
          <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <div class="flex items-center justify-between"><p class="text-sm text-slate-500">Habit Streak</p><i data-lucide="flame" class="w-4 h-4 text-amber-500"></i></div>
            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">5 <span class="text-sm font-normal text-slate-400">days</span></p>
          </div>
          <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <div class="flex items-center justify-between"><p class="text-sm text-slate-500">Monthly Spending</p><i data-lucide="wallet" class="w-4 h-4 text-emerald-500"></i></div>
            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">₹3,420</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Features -->
<section class="max-w-7xl mx-auto px-5 py-20">
  <div class="max-w-2xl">
    <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 tracking-wide">FEATURES</p>
    <h2 class="mt-2 text-3xl sm:text-4xl font-semibold tracking-tight text-slate-900 dark:text-white">Everything a student needs, nothing they don't.</h2>
    <p class="mt-3 text-slate-600 dark:text-slate-400">Replace scattered notes, reminders and spreadsheets with one prioritized daily view.</p>
  </div>

  <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
    <?php
    $features = [
        ['sparkles', 'Smart Task Prioritization', 'A rule-based score for every task. The system always knows your most important next action.'],
        ['book-open', 'Study Planner', 'Enter subject, topic, difficulty and exam date — get a structured session-by-session plan.'],
        ['wallet', 'Expense Tracker', 'Daily, weekly and monthly spending with charts and budget insights.'],
        ['repeat', 'Habit Tracker', 'Streaks, completion rates and a visual habit calendar that keeps you honest.'],
        ['target', 'Goal Tracking', 'Progress bars, days remaining and automatic On Track / At Risk status.'],
        ['timer', 'Focus Mode', 'Distraction-free Pomodoro timer linked to your tasks, saved to your history.'],
        ['bar-chart-3', 'Analytics', 'Real charts built from your own data — tasks, study, focus, habits and spending.'],
        ['layout-dashboard', 'One Dashboard', 'Daily brief, priorities, notifications and a productivity score in one place.'],
    ];
    foreach ($features as [$icon, $title, $text]): ?>
      <div class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 hover:shadow-soft hover:border-indigo-200 dark:hover:border-indigo-500/30 transition">
        <span class="w-11 h-11 rounded-xl bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:scale-105 transition">
          <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
        </span>
        <h3 class="mt-4 font-semibold text-slate-900 dark:text-white"><?= $title ?></h3>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed"><?= $text ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Pipeline -->
<section class="border-y border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/50">
  <div class="max-w-7xl mx-auto px-5 py-16 text-center">
    <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">The StudentFlow loop</h2>
    <p class="mt-3 text-slate-600 dark:text-slate-400 max-w-xl mx-auto">Every screen answers one question: <span class="font-medium text-slate-900 dark:text-white">what should I do now?</span></p>
    <div class="mt-9 flex flex-wrap items-center justify-center gap-3 text-sm font-medium">
      <?php
      $steps = ['DATA', 'ANALYSIS', 'PRIORITY', 'RECOMMENDATION', 'ACTION', 'TRACKING', 'INSIGHT'];
      foreach ($steps as $i => $s): ?>
        <span class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-4 py-2.5 text-slate-700 dark:text-slate-300"><?= $s ?></span>
        <?php if ($i < count($steps) - 1): ?>
          <i data-lucide="arrow-right" class="w-4 h-4 text-indigo-500"></i>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="max-w-7xl mx-auto px-5 py-20">
  <div class="rounded-3xl bg-indigo-600 text-white px-6 sm:px-12 py-14 text-center shadow-soft">
    <h2 class="text-3xl sm:text-4xl font-semibold tracking-tight">Ready to know exactly what to do next?</h2>
    <p class="mt-3 text-indigo-100 max-w-xl mx-auto">Join StudentFlow and turn scattered student activities into one prioritized daily action.</p>
    <a href="register.php" class="mt-8 inline-block rounded-xl bg-white text-indigo-700 font-semibold px-8 py-3.5 hover:bg-indigo-50 transition">Get Started — it's free</a>
  </div>
</section>

<footer class="border-t border-slate-200 dark:border-slate-800">
  <div class="max-w-7xl mx-auto px-5 py-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-slate-500 dark:text-slate-400">
    <p class="flex items-center gap-2"><i data-lucide="zap" class="w-4 h-4 text-indigo-500"></i> StudentFlow — Smart Student Life Assistant</p>
    <p>© <?= date('Y') ?> · Built for students, with PHP · MySQL · Tailwind</p>
  </div>
</footer>

<script>lucide && lucide.createIcons();</script>
</body>
</html>
