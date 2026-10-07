<?php
$page_title = 'Profile';
require_once __DIR__ . '/includes/auth_check.php';

$uid = user_id();
$user = current_user();

$stmt = db()->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND deleted_at IS NULL AND status = 'Completed'");
$stmt->execute([$uid]);
$tasksDone = (int) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT COALESCE(SUM(duration_minutes),0) FROM study_sessions WHERE user_id = ?');
$stmt->execute([$uid]);
$studyHours = round(((int) $stmt->fetchColumn()) / 60, 1);

$streak = best_streak($uid);

$stmt = db()->prepare("SELECT COUNT(*) FROM goals WHERE user_id = ? AND progress >= 100");
$stmt->execute([$uid]);
$goalsDone = (int) $stmt->fetchColumn();

$stats = [
    ['Tasks completed', (string) $tasksDone, 'circle-check', 'text-indigo-500', 'bg-indigo-50 dark:bg-indigo-500/15'],
    ['Study hours', $studyHours . 'h', 'book-open', 'text-sky-500', 'bg-sky-50 dark:bg-sky-500/15'],
    ['Current streak', $streak . 'd', 'flame', 'text-amber-500', 'bg-amber-50 dark:bg-amber-500/15'],
    ['Goals completed', (string) $goalsDone, 'target', 'text-emerald-500', 'bg-emerald-50 dark:bg-emerald-500/15'],
];

$page_scripts = ['profile.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="grid lg:grid-cols-3 gap-6">
  <!-- Identity card -->
  <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft h-fit">
    <div class="flex flex-col items-center text-center">
      <div class="relative">
        <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/assets/images/uploads/' . $user['profile_image'])): ?>
          <img src="assets/images/uploads/<?= e($user['profile_image']) ?>" alt="<?= e($user['name']) ?>" class="w-24 h-24 rounded-2xl object-cover" id="avatarPreview">
        <?php else: ?>
          <span class="w-24 h-24 rounded-2xl bg-indigo-600 text-white text-3xl font-semibold flex items-center justify-center" id="avatarPreviewText"><?= e(strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
          <img src="" alt="" class="w-24 h-24 rounded-2xl object-cover hidden" id="avatarPreview">
        <?php endif; ?>
        <label class="absolute -bottom-2 -right-2 w-9 h-9 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-soft flex items-center justify-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700" title="Change photo">
          <i data-lucide="camera" class="w-4 h-4 text-slate-500"></i>
          <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" id="avatarInput">
        </label>
      </div>
      <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white" data-user-name><?= e($user['name']) ?></h2>
      <p class="text-sm text-slate-500 dark:text-slate-400" data-user-email><?= e($user['email']) ?></p>
      <div class="mt-3 flex flex-wrap justify-center gap-2 text-xs">
        <?php if ($user['college']): ?><span class="rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-1" data-user-college><?= e($user['college']) ?></span><?php endif; ?>
        <?php if ($user['department']): ?><span class="rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-1" data-user-department><?= e($user['department']) ?></span><?php endif; ?>
        <?php if ($user['year']): ?><span class="rounded-full bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 px-2.5 py-1" data-user-year><?= e($user['year']) ?></span><?php endif; ?>
      </div>
      <p class="mt-4 text-xs text-slate-400">Member since <?= e(date('d M Y', strtotime($user['created_at']))) ?></p>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-3">
      <?php foreach ($stats as [$label, $value, $icon, $color, $bg]): ?>
        <div class="rounded-2xl bg-slate-50 dark:bg-slate-800/60 p-3.5 text-center">
          <span class="w-8 h-8 mx-auto rounded-lg <?= $bg ?> <?= $color ?> flex items-center justify-center"><i data-lucide="<?= $icon ?>" class="w-4 h-4"></i></span>
          <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-white"><?= e($value) ?></p>
          <p class="text-[10px] uppercase tracking-wide text-slate-400"><?= e($label) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Forms -->
  <div class="lg:col-span-2 space-y-6">
    <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
      <h3 class="font-semibold text-slate-900 dark:text-white">Edit profile</h3>
      <p id="pfError" class="hidden mt-3 rounded-xl bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400 text-sm px-3.5 py-2.5"></p>
      <form id="profileForm" class="mt-5 grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Full name</label>
          <input name="name" id="pf_name" required value="<?= e($user['name']) ?>"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Email</label>
          <input name="email" type="email" required value="<?= e($user['email']) ?>"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">College</label>
          <input name="college" value="<?= e($user['college']) ?>"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Department</label>
          <input name="department" value="<?= e($user['department']) ?>"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Year</label>
          <select name="year" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">Select year</option>
            <?php foreach (['1st Year', '2nd Year', '3rd Year', '4th Year', 'Postgraduate', 'Other'] as $y): ?>
              <option value="<?= $y ?>" <?= $user['year'] === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sm:col-span-2 flex justify-end">
          <button type="submit" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5 transition">Save changes</button>
        </div>
      </form>
    </div>

    <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
      <h3 class="font-semibold text-slate-900 dark:text-white">Change password</h3>
      <p id="pwError" class="hidden mt-3 rounded-xl bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400 text-sm px-3.5 py-2.5"></p>
      <form id="passwordForm" class="mt-5 grid sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Current password</label>
          <input type="password" name="current_password" required autocomplete="current-password"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">New password</label>
          <input type="password" name="new_password" required minlength="8" autocomplete="new-password"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Confirm new</label>
          <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="sm:col-span-3 flex justify-end">
          <button type="submit" class="rounded-xl border border-slate-300 dark:border-slate-700 px-5 py-2.5 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition">Update password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
