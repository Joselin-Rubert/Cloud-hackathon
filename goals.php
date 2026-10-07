<?php
$page_title = 'Goals';
require_once __DIR__ . '/includes/auth_check.php';

$uid = user_id();
sync_notifications($uid);

$stmt = db()->prepare('SELECT * FROM goals WHERE user_id = ? ORDER BY progress ASC, target_date IS NULL, target_date ASC');
$stmt->execute([$uid]);
$goals = $stmt->fetchAll();

$rows = [];
$active = 0; $completed = 0; $risk = 0;
foreach ($goals as $g) {
    $status = goal_status($g);
    if ($status === 'Completed') $completed++;
    elseif ($status === 'At Risk') $risk++;
    else $active++;
    if (in_array($status, ['On Track', 'At Risk'], true) && $g['status'] !== $status) {
        db()->prepare('UPDATE goals SET status = ? WHERE id = ? AND user_id = ?')->execute([$status, $g['id'], $uid]);
    }
    $rows[] = [
        'id' => (int) $g['id'],
        'title' => $g['title'],
        'description' => $g['description'] ?? '',
        'github_repo_url' => $g['github_repo_url'] ?? null,
        'category' => $g['category'],
        'target_date' => $g['target_date'],
        'progress' => (int) $g['progress'],
        'status' => $status,
        'days_left' => $g['target_date'] ? date_diff_days($g['target_date']) : null,
    ];
}

$avg = $rows ? (int) round(array_sum(array_column($rows, 'progress')) / count($rows)) : 0;
$statusStyle = [
    'On Track'   => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
    'At Risk'    => 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400',
    'Completed'  => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400',
];
$statusBar = ['On Track' => 'bg-emerald-500', 'At Risk' => 'bg-rose-500', 'Completed' => 'bg-indigo-500'];

$page_data = ['goals' => $rows];
$page_scripts = ['goals.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
  <div class="flex flex-wrap gap-4 text-sm text-slate-500 dark:text-slate-400">
    <span><span class="font-semibold text-slate-800 dark:text-slate-200" id="statActive"><?= $active ?></span> active</span>
    <span><span class="font-semibold text-rose-500" id="statRisk"><?= $risk ?></span> at risk</span>
    <span><span class="font-semibold text-indigo-500" id="statCompleted"><?= $completed ?></span> completed</span>
    <span>Average progress <span class="font-semibold text-slate-800 dark:text-slate-200" id="statAvg"><?= $avg ?>%</span></span>
  </div>
  <button type="button" id="addGoalBtn"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-2.5 shadow-soft transition">
    <i data-lucide="plus" class="w-4 h-4"></i> New Goal
  </button>
</div>

<div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5" id="goalGrid">
  <?php foreach ($rows as $g): ?>
    <div class="goal-card rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft" data-goal-id="<?= $g['id'] ?>">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <span class="rounded-full px-2.5 py-0.5 text-[11px] font-medium <?= $statusStyle[$g['status']] ?>" data-goal-status><?= e($g['status']) ?></span>
          <h3 class="mt-2 font-semibold text-slate-900 dark:text-white"><?= e($g['title']) ?></h3>
          <?php if ($g['description']): ?>
            <p class="mt-1 text-xs text-slate-400 line-clamp-2"><?= e($g['description']) ?></p>
          <?php endif; ?>
          <?php if ($g['github_repo_url']): ?>
            <?php $gurl = preg_match('#^https?://#i', $g['github_repo_url']) ? $g['github_repo_url'] : 'https://github.com/' . $g['github_repo_url']; ?>
            <a href="<?= e($gurl) ?>" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
              <i data-lucide="github" class="w-3.5 h-3.5"></i> <?= e($g['github_repo_url']) ?>
            </a>
          <?php endif; ?>
        </div>
        <div class="flex gap-1 shrink-0">
          <button type="button" data-edit-goal class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15"><i data-lucide="pencil" class="w-4 h-4"></i></button>
          <button type="button" data-delete-goal class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/15"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
        </div>
      </div>

      <div class="mt-4">
        <div class="flex items-center justify-between text-xs mb-1.5">
          <span class="text-slate-500 dark:text-slate-400"><?= e($g['category']) ?></span>
          <span class="font-semibold text-slate-800 dark:text-slate-200" data-goal-progress-label><?= $g['progress'] ?>%</span>
        </div>
        <div class="h-2.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
          <div class="h-full rounded-full <?= $statusBar[$g['status']] ?> transition-all duration-700" style="width:<?= $g['progress'] ?>%" data-goal-bar></div>
        </div>
      </div>

      <div class="mt-4 flex items-center justify-between text-xs text-slate-400">
        <?php if ($g['target_date']): ?>
          <span class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5"></i><?= e(date('d M Y', strtotime($g['target_date']))) ?></span>
          <span data-goal-days class="<?= $g['days_left'] !== null && $g['days_left'] <= 7 && $g['progress'] < 100 ? 'text-rose-500 font-medium' : '' ?>">
            <?= $g['days_left'] === null ? '' : ($g['days_left'] < 0 ? 'overdue' : ($g['days_left'] === 0 ? 'due today' : $g['days_left'] . ' day' . ($g['days_left'] === 1 ? '' : 's') . ' left')) ?>
          </span>
        <?php else: ?>
          <span>No target date</span>
        <?php endif; ?>
      </div>

      <div class="mt-4 flex gap-2">
        <button type="button" data-progress-goal class="flex-1 rounded-xl border border-slate-200 dark:border-slate-700 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">Update progress</button>
        <button type="button" data-edit-goal class="rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">Edit</button>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div id="goalEmpty" class="hidden rounded-3xl border border-dashed border-slate-300 dark:border-slate-700 p-12 text-center">
  <span class="inline-flex w-14 h-14 rounded-2xl bg-violet-50 dark:bg-violet-500/15 text-violet-500 items-center justify-center"><i data-lucide="target" class="w-7 h-7"></i></span>
  <p class="mt-4 font-semibold text-slate-800 dark:text-slate-200">Set your first goal</p>
  <p class="text-sm text-slate-400 mt-1">Score 9+ GPA · Build portfolio · Complete internship — track progress automatically.</p>
  <button type="button" id="addGoalBtn2" class="mt-5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-3">Create goal</button>
</div>

<!-- Goal modal -->
<div id="goalModal" class="hidden fixed inset-0 z-[60] p-4">
  <div class="min-h-full flex items-center justify-center">
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" data-close-modal></div>
    <div class="relative w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-soft border border-slate-200 dark:border-slate-800 p-6">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white" id="goalModalTitle">New Goal</h3>
        <button type="button" data-close-modal class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <form id="goalForm" class="mt-5 space-y-4">
        <p id="goalModalError" class="hidden rounded-xl bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400 text-sm px-3.5 py-2.5"></p>
        <input type="hidden" name="id" id="gf_id">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Goal title *</label>
          <input name="title" id="gf_title" required maxlength="200" placeholder="Complete Java course"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Description</label>
          <textarea name="description" id="gf_desc" rows="2"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">GitHub repository (optional)</label>
          <input name="github_repo_url" id="gf_repo" maxlength="255" placeholder="username/repo or https://github.com/..." 
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Category</label>
            <select name="category" id="gf_category"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="Academic">Academic</option><option value="Career">Career</option><option value="Skill">Skill</option><option value="Health">Health</option><option value="Personal">Personal</option><option value="Other">Other</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Target date</label>
            <input type="date" name="target_date" id="gf_target"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Progress: <span id="gf_progress_value" class="font-semibold text-indigo-600">0%</span></label>
          <input type="range" name="progress" id="gf_progress" min="0" max="100" step="5" value="0" class="w-full accent-indigo-600">
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" data-close-modal class="rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
          <button type="submit" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Progress modal -->
<div id="progressModal" class="hidden fixed inset-0 z-[60] p-4">
  <div class="min-h-full flex items-center justify-center">
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" data-close-modal></div>
    <div class="relative w-full max-w-sm bg-white dark:bg-slate-900 rounded-2xl shadow-soft border border-slate-200 dark:border-slate-800 p-6">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Update progress</h3>
        <button type="button" data-close-modal class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <p class="mt-1 text-sm text-slate-500" id="pm_goal_title"></p>
      <form id="progressForm" class="mt-5">
        <input type="hidden" id="pm_id">
        <div class="flex items-center gap-4">
          <input type="range" id="pm_progress" min="0" max="100" step="5" value="0" class="flex-1 accent-indigo-600">
          <span class="text-lg font-semibold text-slate-900 dark:text-white w-14 text-right" id="pm_progress_value">0%</span>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <button type="button" data-close-modal class="rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
          <button type="submit" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5">Save progress</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
