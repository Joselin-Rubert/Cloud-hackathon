<?php
$page_title = 'Tasks';
require_once __DIR__ . '/includes/auth_check.php';

$uid = user_id();
sync_notifications($uid);

$stmt = db()->prepare('SELECT * FROM tasks WHERE user_id = ? ORDER BY due_date IS NULL, due_date ASC, created_at DESC');
$stmt->execute([$uid]);
$all = $stmt->fetchAll();

$rows = [];
$counts = ['all' => 0, 'today' => 0, 'upcoming' => 0, 'overdue' => 0, 'completed' => 0, 'trash' => 0];
foreach ($all as $t) {
    $j = task_json($t);
    $rows[] = $j;
    if ($j['deleted']) { $counts['trash']++; continue; }
    if ($j['status'] === 'Completed') { $counts['completed']++; continue; }
    $counts['all']++;
    $counts[$j['section']]++;
}

$cats     = ['Assignment', 'Exam', 'Project', 'Study', 'Personal', 'Other'];
$prios    = ['High' => 'High', 'Medium' => 'Medium', 'Low' => 'Low'];
$sections = [
    'today'     => ['Today', 'sun'],
    'upcoming'  => ['Upcoming', 'calendar-clock'],
    'overdue'   => ['Overdue', 'triangle-alert'],
    'completed' => ['Completed', 'circle-check'],
    'trash'     => ['Trash', 'trash-2'],
];

// Deadline risk counts over open tasks (drives the filter chips)
$riskCounts = ['all' => 0, 'critical' => 0, 'approaching' => 0, 'safe' => 0];
foreach ($rows as $r) {
    if ($r['deleted'] || $r['status'] === 'Completed') continue;
    $riskCounts['all']++;
    if (isset($riskCounts[$r['risk']])) $riskCounts[$r['risk']]++;
}

$page_data = ['tasks' => $rows];
$page_scripts = ['tasks.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
  <div>
    <p class="text-sm text-slate-500 dark:text-slate-400">
      <span class="font-medium text-slate-800 dark:text-slate-200"><?= $counts['all'] ?></span> open task<?= $counts['all'] === 1 ? '' : 's' ?>
      <?php if ($counts['overdue']): ?>
        · <span class="text-rose-500 font-medium"><?= $counts['overdue'] ?> overdue</span>
      <?php endif; ?>
    </p>
  </div>
  <button type="button" data-open-new
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-2.5 shadow-soft transition">
    <i data-lucide="plus" class="w-4 h-4"></i> New Task
  </button>
</div>

<!-- Toolbar -->
<div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-soft">
  <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="relative lg:col-span-1">
      <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
      <input type="search" id="taskSearch" placeholder="Search tasks…"
             class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 pl-9 pr-3 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
    </div>
    <select id="filterCategory" class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
      <option value="">All categories</option>
      <?php foreach ($cats as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
    </select>
    <select id="filterPriority" class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
      <option value="">All priorities</option>
      <?php foreach ($prios as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
    </select>
    <select id="sortBy" class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
      <option value="priority">Sort: Smart priority</option>
      <option value="deadline">Sort: Deadline</option>
      <option value="created">Sort: Created date</option>
    </select>
  </div>

  <!-- Deadline risk filter -->
  <div class="mt-4 flex flex-wrap items-center gap-2" id="riskFilters">
    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide mr-1">Deadline risk:</span>
    <?php
    $riskBtns = [
        ['all', 'All', $riskCounts['all'], ''],
        ['safe', 'Safe', $riskCounts['safe'], 'bg-emerald-500'],
        ['approaching', 'Approaching', $riskCounts['approaching'], 'bg-amber-500'],
        ['critical', 'Critical', $riskCounts['critical'], 'bg-rose-500'],
    ];
    foreach ($riskBtns as $i => [$rkey, $rlabel, $rcnt, $rdot]): ?>
      <button type="button" data-risk-filter="<?= $rkey ?>"
              class="risk-chip flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium border transition
                     <?= $i === 0
                         ? 'bg-slate-900 dark:bg-white border-slate-900 dark:border-white text-white dark:text-slate-900'
                         : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800' ?>">
        <?php if ($rdot): ?><span class="w-1.5 h-1.5 rounded-full <?= $rdot ?>"></span><?php endif; ?>
        <?= $rlabel ?>
        <span class="text-[10px] opacity-70" data-risk-count="<?= $rkey ?>"><?= (int) $rcnt ?></span>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- Tabs -->
  <div class="mt-4 flex gap-2 overflow-x-auto pb-1 -mx-1 px-1" id="taskTabs">
    <?php
    $tabs = [
        ['all', 'All', $counts['all']],
        ['today', 'Today', $counts['today']],
        ['upcoming', 'Upcoming', $counts['upcoming']],
        ['overdue', 'Overdue', $counts['overdue']],
        ['completed', 'Completed', $counts['completed']],
        ['trash', 'Trash', $counts['trash']],
    ];
    foreach ($tabs as [$key, $label, $cnt]): ?>
      <button type="button" data-tab="<?= $key ?>"
              class="task-tab shrink-0 rounded-xl px-3.5 py-2 text-sm font-medium border transition
                     <?= $key === 'all'
                         ? 'bg-indigo-600 border-indigo-600 text-white'
                         : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' ?>">
        <?= $label ?> <span class="text-[11px] opacity-70" data-tab-count="<?= $key ?>"><?= $cnt ?></span>
      </button>
    <?php endforeach; ?>
  </div>
</div>

<!-- Task sections -->
<div class="mt-5 space-y-6" id="taskSections">
  <?php foreach ($sections as $key => [$label, $icon]): ?>
    <section data-section="<?= $key ?>" class="task-section <?= $key === 'trash' ? 'hidden' : '' ?>">
      <div class="flex items-center gap-2 mb-3">
        <i data-lucide="<?= $icon ?>" class="w-4 h-4 <?= $key === 'overdue' ? 'text-rose-500' : ($key === 'completed' ? 'text-emerald-500' : 'text-slate-400') ?>"></i>
        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= $label ?></h3>
        <span class="text-xs text-slate-400" data-section-count="<?= $key ?>"></span>
      </div>
      <ul class="space-y-2.5 task-list" data-list="<?= $key ?>">
        <?php foreach ($rows as $r): ?>
          <?php if ($r['section'] !== $key) continue; ?>
          <?php
          $prioStyle = $r['priority'] === 'High'
              ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400'
              : ($r['priority'] === 'Medium' ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400');
          ?>
          <li class="task-row group flex items-start gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 hover:shadow-soft transition"
              data-id="<?= $r['id'] ?>"
              data-title="<?= e($r['title']) ?>"
              data-description="<?= e($r['description']) ?>"
              data-category="<?= e($r['category']) ?>"
              data-priority="<?= e($r['priority']) ?>"
              data-due-date="<?= e($r['due_date'] ?? '') ?>"
              data-due-time="<?= e($r['due_time'] ?? '') ?>"
              data-estimated-minutes="<?= $r['estimated_minutes'] ?>"
              data-status="<?= e($r['status']) ?>"
              data-section="<?= $r['section'] ?>"
              data-score="<?= $r['score'] ?>"
              data-created-at="<?= e($r['created_at']) ?>"
              data-deleted="<?= $r['deleted'] ? '1' : '' ?>">
            <?php if (!$r['deleted']): ?>
              <button type="button" data-toggle-complete title="<?= $r['status'] === 'Completed' ? 'Reopen task' : 'Mark complete' ?>"
                      class="mt-0.5 w-5 h-5 rounded-md flex items-center justify-center shrink-0 border-2 transition
                             <?= $r['status'] === 'Completed'
                                 ? 'bg-emerald-500 border-emerald-500'
                                 : ($r['status'] === 'In Progress' ? 'border-amber-400 bg-amber-400/30' : 'border-slate-300 dark:border-slate-600 hover:border-indigo-500') ?>">
                <i data-lucide="check" class="w-3 h-3 text-white <?= $r['status'] === 'Completed' ? '' : 'opacity-0' ?>"></i>
              </button>
            <?php else: ?>
              <span class="mt-0.5 w-5 h-5 flex items-center justify-center shrink-0"><i data-lucide="trash-2" class="w-4 h-4 text-slate-400"></i></span>
            <?php endif; ?>

            <div class="min-w-0 flex-1">
              <p class="task-title text-sm font-medium <?= $r['status'] === 'Completed' || $r['deleted'] ? 'line-through text-slate-400' : 'text-slate-800 dark:text-slate-200' ?>">
                <?= e($r['title']) ?>
              </p>
              <?php if ($r['description']): ?>
                <p class="mt-0.5 text-xs text-slate-400 line-clamp-1"><?= e($r['description']) ?></p>
              <?php endif; ?>
              <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">
                <span class="rounded-full px-2 py-0.5 font-medium <?= $prioStyle ?>"><?= e($r['priority']) ?></span>
                <span class="rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-0.5"><?= e($r['category']) ?></span>
                <span class="rounded-full <?= $r['section'] === 'overdue' ? 'bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400' ?> px-2 py-0.5">
                  <?= $r['due_date'] ? e(date('d M', strtotime($r['due_date']))) . ($r['due_time'] ? ' · ' . e(date('h:i A', strtotime($r['due_time']))) : '') : 'No deadline' ?>
                </span>
                <span class="text-slate-400"><?= format_minutes($r['estimated_minutes']) ?></span>
                <?php if (!$r['deleted'] && $r['status'] !== 'Completed' && $r['risk'] !== 'none'): ?>
                  <span class="rounded-full px-2 py-0.5 font-medium <?= $r['risk'] === 'critical' ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400' : ($r['risk'] === 'approaching' ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400') ?>" title="<?= e($r['risk_message']) ?>"><?= strtoupper(e($r['risk'])) ?></span>
                <?php endif; ?>
                <?php if ($r['status'] === 'In Progress' && !$r['deleted']): ?>
                  <span class="rounded-full bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 px-2 py-0.5 font-medium">In Progress</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <?php if ($r['deleted']): ?>
                <button type="button" data-restore class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15">Restore</button>
              <?php else: ?>
                <?php if ($r['status'] !== 'Completed'): ?>
                  <a href="focus.php?task=<?= $r['id'] ?>&auto=1" title="Start focus session"
                     class="hidden sm:flex w-8 h-8 items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15"><i data-lucide="timer" class="w-4 h-4"></i></a>
                <?php endif; ?>
                <button type="button" data-edit class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15" title="Edit"><i data-lucide="pencil" class="w-4 h-4"></i></button>
                <button type="button" data-delete class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/15" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="section-empty hidden rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 p-8 text-center text-sm text-slate-400" data-empty="<?= $key ?>">
        No tasks in this section.
      </div>
    </section>
  <?php endforeach; ?>
</div>

<div id="globalEmpty" class="hidden mt-6 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 p-10 text-center">
  <span class="inline-flex w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 items-center justify-center"><i data-lucide="list-checks" class="w-6 h-6 text-slate-400"></i></span>
  <p class="mt-3 font-medium text-slate-700 dark:text-slate-300">No tasks match your filters</p>
  <p class="text-sm text-slate-400 mt-1">Try clearing the search or create a new task.</p>
</div>

<!-- Task modal -->
<div id="taskModal" class="hidden fixed inset-0 z-[60] p-4 overflow-y-auto">
  <div class="min-h-full flex items-center justify-center">
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" data-close-modal></div>
    <div class="relative w-full max-w-xl bg-white dark:bg-slate-900 rounded-2xl shadow-soft border border-slate-200 dark:border-slate-800 p-6">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white" id="taskModalTitle">New Task</h3>
        <button type="button" data-close-modal class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>

      <form id="taskForm" class="mt-5 space-y-4">
        <input type="hidden" name="id" id="tf_id">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Title *</label>
          <input name="title" id="tf_title" required maxlength="200" placeholder="e.g. Complete DBMS Assignment"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Description</label>
          <textarea name="description" id="tf_description" rows="2" placeholder="Optional details…"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Category</label>
            <select name="category" id="tf_category" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <?php foreach ($cats as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Priority</label>
            <select name="priority" id="tf_priority" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="High">High</option><option value="Medium" selected>Medium</option><option value="Low">Low</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Due date</label>
            <input type="date" name="due_date" id="tf_due_date"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Due time</label>
            <input type="time" name="due_time" id="tf_due_time"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Est. minutes</label>
            <input type="number" name="estimated_minutes" id="tf_est" min="5" max="1440" step="5" value="60"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Status</label>
            <select name="status" id="tf_status" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="Not Started">Not Started</option><option value="In Progress">In Progress</option><option value="Completed">Completed</option>
            </select>
          </div>
        </div>

        <div id="taskModalRisk" class="hidden rounded-xl px-4 py-3 text-sm flex items-start gap-2.5"></div>

        <div id="taskModalError" class="hidden rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 px-4 py-3 text-sm text-rose-600 dark:text-rose-400"></div>

        <div class="flex justify-end gap-3 pt-1">
          <button type="button" data-close-modal class="rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
          <button type="submit" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5 transition" id="taskSubmitBtn">Save Task</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
