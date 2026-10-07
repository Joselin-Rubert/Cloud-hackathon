<?php
/** Top navigation bar for authenticated pages. */
$page_title = $page_title ?? 'Dashboard';
$unread = $unread ?? 0;
?>
<header class="sticky top-0 z-30 bg-white/85 dark:bg-slate-950/85 backdrop-blur border-b border-slate-200 dark:border-slate-800">
  <div class="h-16 px-4 sm:px-6 lg:px-8 max-w-[1500px] mx-auto flex items-center justify-between gap-4">
    <div class="flex items-center gap-3 min-w-0">
      <button id="sidebarToggle" type="button"
              class="lg:hidden w-10 h-10 -ml-2 rounded-xl flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
              aria-label="Open menu">
        <i data-lucide="menu" class="w-5 h-5"></i>
      </button>
      <h1 class="text-lg sm:text-xl font-semibold tracking-tight text-slate-900 dark:text-white truncate"><?= e($page_title) ?></h1>
    </div>

    <div class="flex items-center gap-1.5 sm:gap-2">
      <button type="button" data-theme-toggle
              class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
              aria-label="Toggle dark mode">
        <i data-lucide="moon" class="w-5 h-5 dark:hidden"></i>
        <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
      </button>

      <a href="notifications.php"
         class="relative w-10 h-10 rounded-xl flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
         aria-label="Notifications">
        <i data-lucide="bell" class="w-5 h-5"></i>
        <span data-unread-badge
              class="absolute top-1.5 right-1.5 min-w-[16px] h-4 px-1 rounded-full bg-rose-500 text-white text-[10px] font-semibold flex items-center justify-center <?= $unread ? '' : 'hidden' ?>">
          <?= $unread > 99 ? '99+' : $unread ?>
        </span>
      </a>

      <div class="relative" data-dropdown>
        <button type="button" data-dropdown-toggle
                class="flex items-center gap-2 h-10 pl-1 pr-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition" aria-label="Account menu">
          <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/../assets/images/uploads/' . $user['profile_image'])): ?>
            <img src="assets/images/uploads/<?= e($user['profile_image']) ?>" alt="" class="w-8 h-8 rounded-lg object-cover">
          <?php else: ?>
            <span class="w-8 h-8 rounded-lg bg-indigo-600 text-white text-sm font-semibold flex items-center justify-center"><?= e(strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
          <?php endif; ?>
          <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 hidden sm:block"></i>
        </button>
        <div data-dropdown-menu class="hidden absolute right-0 mt-2 w-52 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-soft py-2 z-50">
          <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-800">
            <p class="text-sm font-medium text-slate-900 dark:text-white truncate"><?= e($user['name']) ?></p>
            <p class="text-xs text-slate-500 truncate"><?= e($user['email']) ?></p>
          </div>
          <a href="profile.php" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"><i data-lucide="user" class="w-4 h-4"></i> Profile</a>
          <a href="settings.php" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"><i data-lucide="settings" class="w-4 h-4"></i> Settings</a>
          <a href="logout.php" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"><i data-lucide="log-out" class="w-4 h-4"></i> Log out</a>
        </div>
      </div>
    </div>
  </div>
</header>
