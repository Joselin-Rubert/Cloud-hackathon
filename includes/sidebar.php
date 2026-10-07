<?php
/** Application sidebar (drawer on mobile, fixed on desktop). */

$nav_items = [
    ['dashboard.php',     'layout-dashboard', 'Dashboard'],
    ['tasks.php',         'list-checks',      'Tasks'],
    ['calendar.php',      'calendar-days',    'Calendar'],
    ['study-planner.php', 'book-open',        'Study Planner'],
    ['expenses.php',      'wallet',           'Expenses'],
    ['habits.php',        'repeat',           'Habits'],
    ['goals.php',         'target',           'Goals'],
    ['learning-resources.php', 'library',     'Learning Resources'],
    ['focus.php',         'timer',            'Focus Mode'],
    ['github-explorer.php', 'github',         'GitHub Explorer'],
    ['analytics.php',     'bar-chart-3',      'Analytics'],
    ['notifications.php', 'bell',             'Notifications'],
    ['profile.php',       'user',             'Profile'],
    ['settings.php',      'settings',         'Settings'],
];
$current = basename($_SERVER['PHP_SELF'] ?? '');
?>
<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 -translate-x-full lg:translate-x-0 transition-transform duration-200 flex flex-col">
  <div class="h-16 flex items-center gap-2.5 px-5 border-b border-slate-200 dark:border-slate-800 shrink-0">
    <span class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-soft">
      <i data-lucide="zap" class="w-5 h-5"></i>
    </span>
    <span class="font-semibold tracking-tight text-slate-900 dark:text-white text-lg">StudentFlow</span>
  </div>

  <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
    <?php foreach ($nav_items as [$href, $icon, $label]): ?>
      <?php $active = ($current === $href); ?>
      <a href="<?= e($href) ?>"
         class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
                <?= $active
                    ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300'
                    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white' ?>">
        <i data-lucide="<?= e($icon) ?>" class="w-[18px] h-[18px] shrink-0"></i>
        <span><?= e($label) ?></span>
        <?php if ($href === 'notifications.php' && $unread > 0): ?>
          <span class="ml-auto min-w-[20px] h-5 px-1.5 rounded-full bg-rose-500 text-white text-[11px] font-semibold flex items-center justify-center" data-unread-badge><?= $unread > 99 ? '99+' : $unread ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="p-4 border-t border-slate-200 dark:border-slate-800 shrink-0">
    <div class="flex items-center gap-3">
      <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/../assets/images/uploads/' . $user['profile_image'])): ?>
        <img src="assets/images/uploads/<?= e($user['profile_image']) ?>" alt="" class="w-9 h-9 rounded-full object-cover">
      <?php else: ?>
        <span class="w-9 h-9 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-sm font-semibold flex items-center justify-center"><?= e(strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
      <?php endif; ?>
      <div class="min-w-0">
        <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate"><?= e($user['name']) ?></p>
        <p class="text-xs text-slate-500 dark:text-slate-400 truncate"><?= e($user['department'] ?: $user['college'] ?: 'Student') ?></p>
      </div>
    </div>
  </div>
</aside>

<div id="sidebarOverlay" class="fixed inset-0 z-40 bg-slate-950/50 hidden lg:hidden"></div>
