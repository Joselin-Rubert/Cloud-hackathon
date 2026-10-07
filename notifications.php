<?php
$page_title = 'Notifications';
require_once __DIR__ . '/includes/auth_check.php';

$uid = user_id();
sync_notifications($uid);

$stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100');
$stmt->execute([$uid]);
$notifications = $stmt->fetchAll();
$unread = unread_notification_count($uid);

$typeMeta = [
    'deadline' => ['alarm-clock', 'bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400'],
    'overdue'  => ['triangle-alert', 'bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400'],
    'exam'     => ['graduation-cap', 'bg-violet-50 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400'],
    'habit'    => ['flame', 'bg-orange-50 dark:bg-orange-500/15 text-orange-600 dark:text-orange-400'],
    'goal'     => ['target', 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'],
    'budget'   => ['wallet', 'bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400'],
    'focus'    => ['timer', 'bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400'],
    'system'   => ['bell', 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'],
];

$page_data = ['unread' => $unread];
$page_scripts = ['notifications.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
  <p class="text-sm text-slate-500 dark:text-slate-400">
    <span class="font-medium text-slate-800 dark:text-slate-200" id="unreadLabel"><?= $unread ?></span> unread notification<?= $unread === 1 ? '' : 's' ?>
  </p>
  <div class="flex gap-2">
    <button type="button" id="markAllRead" class="rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 inline-flex items-center gap-2">
      <i data-lucide="check-check" class="w-4 h-4"></i> Mark all read
    </button>
    <button type="button" id="clearRead" class="rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 inline-flex items-center gap-2 text-slate-500">
      <i data-lucide="trash-2" class="w-4 h-4"></i> Clear read
    </button>
    <button type="button" id="refreshNotifs" class="rounded-xl border border-slate-300 dark:border-slate-700 w-10 h-10 flex items-center justify-center hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-500" title="Refresh">
      <i data-lucide="refresh-cw" class="w-4 h-4"></i>
    </button>
  </div>
</div>

<div class="flex gap-2 mb-5" id="notifTabs">
  <button type="button" data-nfilter="all" class="nfilter rounded-xl px-4 py-2 text-sm font-medium bg-indigo-600 text-white">All</button>
  <button type="button" data-nfilter="unread" class="nfilter rounded-xl px-4 py-2 text-sm font-medium border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800">Unread</button>
</div>

<ul class="space-y-3" id="notifList">
  <?php foreach ($notifications as $n):
      [$icon, $cls] = $typeMeta[$n['type']] ?? $typeMeta['system']; ?>
    <li class="notif-item flex items-start gap-4 rounded-2xl border <?= $n['is_read'] ? 'border-slate-100 dark:border-slate-800 bg-white/60 dark:bg-slate-900/60 opacity-75' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900' ?> p-4 shadow-soft transition"
        data-id="<?= (int) $n['id'] ?>" data-read="<?= $n['is_read'] ? '1' : '0' ?>">
      <span class="w-10 h-10 rounded-xl <?= $cls ?> flex items-center justify-center shrink-0"><i data-lucide="<?= $icon ?>" class="w-5 h-5"></i></span>
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 flex-wrap">
          <p class="text-sm font-semibold text-slate-900 dark:text-white"><?= e($n['title']) ?></p>
          <span class="rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5 text-[10px] uppercase tracking-wide"><?= e($n['type']) ?></span>
          <?php if (!$n['is_read']): ?><span class="w-2 h-2 rounded-full bg-indigo-500"></span><?php endif; ?>
        </div>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400"><?= e($n['message']) ?></p>
        <p class="mt-1.5 text-xs text-slate-400" data-time><?= e(time_ago($n['created_at'])) ?></p>
      </div>
      <div class="flex gap-1 shrink-0">
        <?php if (!$n['is_read']): ?>
          <button type="button" data-mark-read class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15" title="Mark as read"><i data-lucide="check" class="w-4 h-4"></i></button>
        <?php endif; ?>
        <button type="button" data-delete-notif class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/15" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
      </div>
    </li>
  <?php endforeach; ?>
</ul>

<div id="notifEmpty" class="hidden rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 p-12 text-center">
  <span class="inline-flex w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 items-center justify-center"><i data-lucide="bell-off" class="w-7 h-7 text-slate-400"></i></span>
  <p class="mt-4 font-semibold text-slate-800 dark:text-slate-200">You're all caught up</p>
  <p class="text-sm text-slate-400 mt-1">Deadline reminders, streaks and budget alerts will appear here.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
