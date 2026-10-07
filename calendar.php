<?php
$page_title = 'Calendar';
require_once __DIR__ . '/includes/auth_check.php';

$uid = user_id();

// Month navigation
$year  = (int) ($_GET['year'] ?? date('Y'));
$month = (int) ($_GET['month'] ?? date('n'));
if ($month < 1 || $month > 12) $month = (int) date('n');
if ($year < 2000 || $year > 2100) $year = (int) date('Y');

$firstDay   = new DateTime(sprintf('%04d-%02d-01', $year, $month));
$daysInMonth = (int) $firstDay->format('t');
$startWd    = (int) $firstDay->format('N'); // 1 = Monday
$daysInPrev = (int) (new DateTime(sprintf('%04d-%02d-01', $month === 1 ? $year - 1 : $year, $month === 1 ? 12 : $month - 1)))->format('t');

// ---- Collect items per date -----------------------------------
$byDate = [];

$stmt = db()->prepare('SELECT * FROM events WHERE user_id = ? AND event_date BETWEEN ? AND ? ORDER BY start_time');
$stmt->execute([$uid, date('Y-m-d', strtotime('-40 days', $firstDay->getTimestamp())), date('Y-m-d', strtotime("+{$daysInMonth} days", $firstDay->getTimestamp()))]);
foreach ($stmt->fetchAll() as $ev) {
    $byDate[$ev['event_date']][] = [
        'kind'   => 'event',
        'id'     => (int) $ev['id'],
        'title'  => $ev['title'],
        'time'   => $ev['start_time'] ? date('h:i A', strtotime($ev['start_time'])) : '',
        'color'  => ['Assignment' => 'bg-amber-500', 'Exam' => 'bg-rose-500', 'Project' => 'bg-violet-500', 'Study' => 'bg-sky-500', 'Personal' => 'bg-emerald-500', 'Other' => 'bg-slate-400'][$ev['category']] ?? 'bg-slate-400',
        'desc'   => $ev['description'] ?? '',
        'cat'    => $ev['category'],
        'start'  => $ev['start_time'] ?: '',
        'end'    => $ev['end_time'] ?: '',
    ];
}

$stmt = db()->prepare(
    "SELECT id, title, priority, due_date, due_time, estimated_minutes, status FROM tasks
     WHERE user_id = ? AND deleted_at IS NULL AND status <> 'Completed'
       AND due_date BETWEEN ? AND ?"
);
$stmt->execute([$uid, date('Y-m-d', strtotime('-40 days', $firstDay->getTimestamp())), date('Y-m-d', strtotime("+{$daysInMonth} days", $firstDay->getTimestamp()))]);
foreach ($stmt->fetchAll() as $t) {
    $risk = DeadlineRiskService::risk($t);
    $byDate[$t['due_date']][] = [
        'kind'  => 'task',
        'id'    => (int) $t['id'],
        'title' => $t['title'],
        'time'  => $t['due_time'] ? date('h:i A', strtotime($t['due_time'])) : '',
        'color' => $risk['level'] === 'critical' ? 'bg-rose-500' : ($risk['level'] === 'approaching' ? 'bg-amber-500' : 'bg-emerald-500'),
        'desc'  => 'Task · ' . $t['priority'] . ' priority · ' . $risk['label'] . ' — ' . $risk['message'],
        'cat'   => 'Task',
        'risk'  => $risk['level'],
    ];
}

$stmt = db()->prepare('SELECT id, name, exam_date FROM subjects WHERE user_id = ? AND exam_date BETWEEN ? AND ?');
$stmt->execute([$uid, date('Y-m-d', strtotime('-40 days', $firstDay->getTimestamp())), date('Y-m-d', strtotime("+{$daysInMonth} days", $firstDay->getTimestamp()))]);
foreach ($stmt->fetchAll() as $s) {
    $byDate[$s['exam_date']][] = [
        'kind'  => 'exam',
        'id'    => (int) $s['id'],
        'title' => 'Exam: ' . $s['name'],
        'time'  => '',
        'color' => 'bg-rose-600',
        'desc'  => 'Exam date',
        'cat'   => 'Exam',
    ];
}

$stmt = db()->prepare(
    'SELECT ss.id, ss.topic, ss.duration_minutes, ss.session_date, ss.notes, s.name AS subject
     FROM study_sessions ss LEFT JOIN subjects s ON s.id = ss.subject_id
     WHERE ss.user_id = ? AND ss.session_date BETWEEN ? AND ?'
);
$stmt->execute([$uid, date('Y-m-d', strtotime('-40 days', $firstDay->getTimestamp())), date('Y-m-d', strtotime("+{$daysInMonth} days", $firstDay->getTimestamp()))]);
foreach ($stmt->fetchAll() as $ss) {
    $byDate[$ss['session_date']][] = [
        'kind'  => 'study',
        'id'    => (int) $ss['id'],
        'title' => $ss['topic'],
        'time'  => ($ss['notes'] ? str_replace('Preferred time: ', '', $ss['notes']) : '') . ' · ' . format_minutes((int) $ss['duration_minutes']),
        'color' => 'bg-sky-500',
        'desc'  => ($ss['subject'] ? $ss['subject'] . ' — ' : '') . 'Study session',
        'cat'   => 'Study',
    ];
}

// Build grid cells (include leading/trailing days)
$cells = [];
for ($i = 1; $i <= $daysInPrev; $i++) {
    $pm = $month === 1 ? 12 : $month - 1;
    $py = $month === 1 ? $year - 1 : $year;
    $cells[] = ['day' => $i, 'date' => sprintf('%04d-%02d-%02d', $py, $pm, $i), 'out' => true];
}
for ($d = 1; $d <= $daysInMonth; $d++) {
    $cells[] = ['day' => $d, 'date' => sprintf('%04d-%02d-%02d', $year, $month, $d), 'out' => false];
}
while (count($cells) % 7 !== 0) {
    $nm = $month === 12 ? 1 : $month + 1;
    $ny = $month === 12 ? $year + 1 : $year;
    $d = count($cells) - ($daysInPrev + $daysInMonth) + 1;
    $cells[] = ['day' => $d, 'date' => sprintf('%04d-%02d-%02d', $ny, $nm, $d), 'out' => true];
}

$prevM = $month === 1 ? 12 : $month - 1;
$prevY = $month === 1 ? $year - 1 : $year;
$nextM = $month === 12 ? 1 : $month + 1;
$nextY = $month === 12 ? $year + 1 : $year;
$monthName = $firstDay->format('F Y');
$today = date('Y-m-d');

$upcoming = [];
foreach ($byDate as $date => $items) {
    if ($date >= $today) {
        foreach ($items as $it) $upcoming[] = $it + ['date' => $date];
    }
}
usort($upcoming, fn($a, $b) => strcmp($a['date'], $b['date']));
$upcoming = array_slice($upcoming, 0, 6);

$page_scripts = ['calendar.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
  <div class="flex items-center gap-2">
    <a href="?year=<?= $prevM === 12 ? $year - 1 : $year ?>&month=<?= $prevM ?>" class="w-9 h-9 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-center hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
    <h2 class="text-lg font-semibold text-slate-900 dark:text-white w-40 text-center"><?= e($monthName) ?></h2>
    <a href="?year=<?= $nextY ?>&month=<?= $nextM ?>" class="w-9 h-9 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-center hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
    <a href="calendar.php" class="ml-1 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 text-xs font-medium hover:bg-slate-100 dark:hover:bg-slate-800">Today</a>
  </div>
  <div class="flex flex-wrap items-center gap-3 text-[11px] text-slate-500 dark:text-slate-400">
    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-500"></span> Deadline/Exam</span>
    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Approaching</span>
    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-sky-500"></span> Study</span>
    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Safe/Personal</span>
    <button type="button" id="addEventBtn" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-2.5 transition ml-auto">
      <i data-lucide="plus" class="w-4 h-4"></i> Add Event
    </button>
  </div>
</div>

<div class="grid lg:grid-cols-4 gap-6">
  <div class="lg:col-span-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-soft overflow-hidden">
    <div class="grid grid-cols-7 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
      <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?>
        <div class="px-2 py-3 text-center"><?= $d ?></div>
      <?php endforeach; ?>
    </div>
    <div class="grid grid-cols-7">
      <?php foreach ($cells as $cell): ?>
        <?php
        $items = $byDate[$cell['date']] ?? [];
        $isToday = $cell['date'] === $today;
        ?>
        <div class="min-h-[92px] sm:min-h-[112px] border-r border-b border-slate-100 dark:border-slate-800 p-1.5 sm:p-2 <?= $cell['out'] ? 'bg-slate-50/70 dark:bg-slate-950/40' : '' ?> <?= $isToday ? 'ring-2 ring-inset ring-indigo-500 bg-indigo-50/50 dark:bg-indigo-500/10' : '' ?>">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium <?= $cell['out'] ? 'text-slate-300 dark:text-slate-600' : ($isToday ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300') ?>">
              <?= $cell['day'] ?>
            </span>
            <?php if (count($items) > 0): ?>
              <span class="hidden sm:block w-1.5 h-1.5 rounded-full <?= in_array('exam', array_column($items, 'kind'), true) || in_array('task', array_column($items, 'kind'), true) ? 'bg-rose-500' : 'bg-indigo-400' ?>"></span>
            <?php endif; ?>
          </div>
          <div class="mt-1 space-y-1">
            <?php foreach (array_slice($items, 0, 3) as $it): ?>
              <button type="button" data-cal-item
                      data-kind="<?= e($it['kind']) ?>"
                      data-title="<?= e($it['title']) ?>"
                      data-time="<?= e($it['time']) ?>"
                      data-desc="<?= e($it['desc']) ?>"
                      data-cat="<?= e($it['cat']) ?>"
                      data-date="<?= e($cell['date']) ?>"
                      <?php if ($it['kind'] === 'event'): ?>
                        data-event-id="<?= $it['id'] ?>" data-start="<?= e($it['start']) ?>" data-end="<?= e($it['end']) ?>"
                      <?php endif; ?>
                      class="w-full text-left rounded-md px-1.5 py-1 text-[10px] sm:text-[11px] font-medium text-white <?= e($it['color']) ?> truncate hover:opacity-80 transition">
                <?= e($it['time'] ? ($cell['out'] ? '' : '') . $it['title'] : $it['title']) ?>
              </button>
            <?php endforeach; ?>
            <?php if (count($items) > 3): ?>
              <p class="text-[10px] text-slate-400 pl-1">+<?= count($items) - 3 ?> more</p>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Upcoming -->
  <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft h-fit">
    <h3 class="font-semibold text-slate-900 dark:text-white">Upcoming</h3>
    <div class="mt-4 space-y-3">
      <?php foreach ($upcoming as $u): ?>
        <div class="flex items-start gap-3">
          <span class="mt-1 w-2 h-2 rounded-full <?= e($u['color']) ?> shrink-0"></span>
          <div class="min-w-0">
            <p class="text-sm font-medium text-slate-700 dark:text-slate-300 truncate">
              <?= e($u['title']) ?>
              <?php if (($u['kind'] ?? '') === 'task' && !empty($u['risk']) && $u['risk'] !== 'safe'): ?>
                <span class="ml-1 text-[10px] font-bold uppercase rounded-full px-1.5 py-0.5 <?= $u['risk'] === 'critical' ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400' ?>"><?= e($u['risk']) ?></span>
              <?php endif; ?>
            </p>
            <p class="text-xs text-slate-400"><?= e(date('d M', strtotime($u['date']))) ?><?= $u['time'] ? ' · ' . e($u['time']) : '' ?></p>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$upcoming): ?>
        <p class="text-sm text-slate-400">Nothing scheduled yet. Add an event to fill your calendar.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Event modal -->
<div id="eventModal" class="hidden fixed inset-0 z-[60] p-4 overflow-y-auto">
  <div class="min-h-full flex items-center justify-center">
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" data-close-modal></div>
    <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-soft border border-slate-200 dark:border-slate-800 p-6">
      <!-- Detail view -->
      <div id="eventDetail">
        <div class="flex items-start justify-between gap-4">
          <div>
            <span class="text-[11px] font-semibold uppercase tracking-wide text-indigo-500" id="ed_cat">Event</span>
            <h3 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white" id="ed_title"></h3>
          </div>
          <button type="button" data-close-modal class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 shrink-0"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-400">
          <p class="flex items-center gap-2"><i data-lucide="calendar" class="w-4 h-4"></i><span id="ed_date"></span></p>
          <p class="flex items-center gap-2"><i data-lucide="clock" class="w-4 h-4"></i><span id="ed_time">—</span></p>
          <p class="flex items-start gap-2"><i data-lucide="align-left" class="w-4 h-4 mt-0.5"></i><span id="ed_desc">No description.</span></p>
        </div>
        <div class="mt-6 flex justify-end gap-3" id="ed_actions">
          <button type="button" id="ed_edit" class="hidden rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Edit</button>
          <button type="button" id="ed_delete" class="hidden rounded-xl border border-rose-200 dark:border-rose-500/30 text-rose-600 px-4 py-2.5 text-sm font-medium hover:bg-rose-50 dark:hover:bg-rose-500/10">Delete</button>
          <a href="tasks.php" id="ed_open_tasks" class="hidden rounded-xl bg-indigo-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-indigo-500">Open tasks</a>
        </div>
      </div>

      <!-- Form view -->
      <form id="eventForm" class="hidden">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white" id="eventFormTitle">Add Event</h3>
          <button type="button" data-close-modal class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <input type="hidden" name="id" id="ef_id">
        <div class="mt-5 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Title *</label>
            <input name="title" id="ef_title" required maxlength="200" placeholder="e.g. Group project meeting"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Description</label>
            <textarea name="description" id="ef_desc" rows="2"
                      class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Date *</label>
              <input type="date" name="event_date" id="ef_date" required
                     class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Category</label>
              <select name="category" id="ef_cat"
                      class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="Personal">Personal</option><option value="Assignment">Assignment</option><option value="Exam">Exam</option><option value="Project">Project</option><option value="Study">Study</option><option value="Other">Other</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Start time</label>
              <input type="time" name="start_time" id="ef_start"
                     class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">End time</label>
              <input type="time" name="end_time" id="ef_end"
                     class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
          </div>
          <div id="eventFormError" class="hidden rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 px-4 py-3 text-sm text-rose-600 dark:text-rose-400"></div>
          <div class="flex justify-end gap-3">
            <button type="button" data-close-modal class="rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
            <button type="submit" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5">Save Event</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
