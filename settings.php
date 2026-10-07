<?php
$page_title = 'Settings';
require_once __DIR__ . '/includes/auth_check.php';
$page_scripts = ['settings.js'];

$uid = user_id();
$s = get_settings($uid);

function switch_row(string $name, string $label, string $desc, $checked, string $id): void
{
    ?>
    <div class="flex items-start justify-between gap-6 py-4 border-b border-slate-100 dark:border-slate-800 last:border-0">
      <div>
        <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($label) ?></p>
        <p class="text-xs text-slate-400 mt-0.5 max-w-md"><?= e($desc) ?></p>
      </div>
      <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5">
        <input type="checkbox" name="<?= e($name) ?>" id="<?= e($id) ?>" value="1" class="sr-only peer" <?= $checked ? 'checked' : '' ?>>
        <span class="w-11 h-6 bg-slate-200 dark:bg-slate-700 rounded-full peer-checked:bg-indigo-600 transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-transform peer-checked:after:translate-x-5"></span>
      </label>
    </div>
    <?php
}
?>

<div class="max-w-3xl space-y-6">
  <form id="settingsForm" class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
    <div class="flex items-center gap-3">
      <span class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center"><i data-lucide="settings" class="w-5 h-5"></i></span>
      <div>
        <h3 class="font-semibold text-slate-900 dark:text-white">Preferences</h3>
        <p class="text-xs text-slate-400">Saved to your account and applied everywhere.</p>
      </div>
    </div>

    <div class="mt-5">
      <?php switch_row('dark_mode', 'Dark mode', 'Switch the interface to a dark theme.', $s['dark_mode'], 'setDark'); ?>
    </div>

    <div class="mt-5 grid sm:grid-cols-2 gap-5">
      <div>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Daily study target (minutes)</label>
        <input type="number" name="daily_study_target" id="setTarget" min="15" max="960" step="15" value="<?= (int) $s['daily_study_target'] ?>"
               class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <p class="mt-1.5 text-xs text-slate-400">Used for your study score and reminders. Default: 120.</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Monthly budget (₹)</label>
        <input type="number" name="monthly_budget" id="setBudget" min="0" step="100" value="<?= rtrim(rtrim(number_format((float) $s['monthly_budget'], 2, '.', ''), '0'), '.') ?>"
               class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <p class="mt-1.5 text-xs text-slate-400">0 disables budget tracking and warnings.</p>
      </div>
    </div>

    <div class="mt-6">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1">Reminders & notifications</p>
      <?php
      switch_row('task_reminders', 'Task reminders', 'Notify me about due and overdue tasks.', $s['task_reminders'], 'setTaskRem');
      switch_row('habit_reminders', 'Habit reminders', 'Nudge me when I have not checked in a habit today.', $s['habit_reminders'], 'setHabitRem');
      switch_row('email_notifications', 'Email notifications', 'Weekly summary of productivity by email (demo flag).', $s['email_notifications'], 'setEmail');
      ?>
    </div>

    <div class="mt-6 flex justify-end gap-3">
      <button type="reset" class="rounded-xl border border-slate-300 dark:border-slate-700 px-5 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Reset</button>
      <button type="submit" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5 transition">Save settings</button>
    </div>
  </form>

  <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
    <div class="flex items-center gap-3">
      <span class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400 flex items-center justify-center"><i data-lucide="log-out" class="w-5 h-5"></i></span>
      <div class="flex-1">
        <h3 class="font-semibold text-slate-900 dark:text-white">Session</h3>
        <p class="text-xs text-slate-400">Log out of StudentFlow on this device.</p>
      </div>
      <a href="logout.php" class="rounded-xl border border-rose-200 dark:border-rose-500/30 text-rose-600 dark:text-rose-400 px-4 py-2.5 text-sm font-semibold hover:bg-rose-50 dark:hover:bg-rose-500/10 transition">Log out</a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
