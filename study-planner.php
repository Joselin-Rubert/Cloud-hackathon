<?php
$page_title = 'Study Planner';
require_once __DIR__ . '/includes/auth_check.php';

$uid = user_id();

$stmt = db()->prepare(
    'SELECT ss.*, s.name AS subject_name, s.difficulty, s.exam_date
     FROM study_sessions ss
     LEFT JOIN subjects s ON s.id = ss.subject_id AND s.user_id = ss.user_id
     WHERE ss.user_id = ?
     ORDER BY ss.session_date ASC, ss.id ASC'
);
$stmt->execute([$uid]);
$sessions = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM subjects WHERE user_id = ? ORDER BY exam_date IS NULL, exam_date ASC');
$stmt->execute([$uid]);
$subjects = $stmt->fetchAll();

$grouped = [];
$totalMinutes = 0;
foreach ($sessions as $s) {
    $grouped[$s['session_date']][] = $s;
    $totalMinutes += (int) $s['duration_minutes'];
}

$weekMinutes = 0;
$stmt = db()->prepare(
    'SELECT COALESCE(SUM(duration_minutes),0) FROM study_sessions WHERE user_id = ? AND session_date >= ?'
);
$stmt->execute([$uid, date('Y-m-d', strtotime('-6 days'))]);
$weekMinutes = (int) $stmt->fetchColumn();

$diffColors = ['Easy' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
               'Medium' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
               'Hard' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400'];

$page_scripts = ['planner.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="grid lg:grid-cols-5 gap-6">
  <!-- Generator -->
  <div class="lg:col-span-2">
    <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
      <div class="flex items-center gap-3">
        <span class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center"><i data-lucide="wand-sparkles" class="w-5 h-5"></i></span>
        <div>
          <h3 class="font-semibold text-slate-900 dark:text-white">Generate a study plan</h3>
          <p class="text-xs text-slate-400">Rule-based plan built around your exam date.</p>
        </div>
      </div>

      <form id="planForm" class="mt-5 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2 sm:col-span-1">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Subject *</label>
            <input name="subject" id="pf_subject" required placeholder="Operating Systems"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div class="col-span-2 sm:col-span-1">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Topic *</label>
            <input name="topic" id="pf_topic" required placeholder="Deadlocks"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Exam date</label>
            <input type="date" name="exam_date" id="pf_exam"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Difficulty</label>
            <select name="difficulty" id="pf_difficulty"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="Easy">Easy</option><option value="Medium" selected>Medium</option><option value="Hard">Hard</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Available time</label>
            <select name="total_minutes" id="pf_total"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="60">1 hour</option>
              <option value="120" selected>2 hours</option>
              <option value="180">3 hours</option>
              <option value="240">4 hours</option>
              <option value="300">5 hours</option>
              <option value="480">8 hours</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Preferred study time</label>
            <input type="time" name="study_time" id="pf_time" value="18:00"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
        </div>

        <button type="submit" class="w-full rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-3 transition inline-flex items-center justify-center gap-2">
          <i data-lucide="sparkles" class="w-4 h-4"></i> Generate plan
        </button>
      </form>
    </div>

    <!-- Subjects -->
    <div class="mt-6 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
      <h3 class="font-semibold text-slate-900 dark:text-white">Subjects & exams</h3>
      <div class="mt-4 space-y-2.5" id="subjectList">
        <?php foreach ($subjects as $s): ?>
          <?php $daysLeft = $s['exam_date'] ? date_diff_days($s['exam_date']) : null; ?>
          <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-100 dark:border-slate-800 p-3.5" data-subject-id="<?= (int) $s['id'] ?>">
            <div class="min-w-0">
              <p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate"><?= e($s['name']) ?></p>
              <p class="text-xs text-slate-400">
                <?php if ($s['exam_date']): ?>
                  Exam <?= e(date('d M Y', strtotime($s['exam_date']))) ?>
                  <?php if ($daysLeft !== null): ?>
                    · <span class="<?= $daysLeft <= 3 ? 'text-rose-500 font-medium' : '' ?>"><?= $daysLeft < 0 ? 'passed' : ($daysLeft === 0 ? 'today' : $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left') ?></span>
                  <?php endif; ?>
                <?php else: ?>No exam date<?php endif; ?>
              </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <span class="rounded-full px-2 py-0.5 text-[11px] font-medium <?= $diffColors[$s['difficulty']] ?? '' ?>"><?= e($s['difficulty']) ?></span>
              <button type="button" data-delete-subject class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/15" title="Delete subject"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$subjects): ?>
          <p class="text-sm text-slate-400">Subjects appear here after you save your first plan.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Plan output -->
  <div class="lg:col-span-3 space-y-6">
    <!-- Preview (hidden until generated) -->
    <div id="planPreview" class="hidden rounded-3xl border-2 border-indigo-200 dark:border-indigo-500/30 bg-white dark:bg-slate-900 p-6 shadow-soft">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
          <p class="text-[11px] font-bold tracking-[0.18em] text-indigo-500">GENERATED PLAN</p>
          <h3 class="mt-1 font-semibold text-slate-900 dark:text-white" id="pv_title">—</h3>
        </div>
        <div class="flex gap-2">
          <button type="button" id="pv_discard" class="rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Discard</button>
          <button type="button" id="pv_save" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5 inline-flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Save plan</button>
        </div>
      </div>
      <ol class="mt-5 space-y-3" id="pv_list"></ol>
      <p class="mt-4 text-xs text-slate-400" id="pv_note"></p>
    </div>

    <!-- Saved sessions -->
    <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h3 class="font-semibold text-slate-900 dark:text-white">Your study plan</h3>
          <p class="text-xs text-slate-400 mt-0.5">
            <?= count($sessions) ?> session<?= count($sessions) === 1 ? '' : 's' ?> · <?= format_minutes($totalMinutes) ?> total ·
            <?= format_minutes($weekMinutes) ?> this week
          </p>
        </div>
        <div class="flex items-center gap-2 text-xs text-slate-400">
          <span class="rounded-full bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 px-2.5 py-1 font-medium">Concept</span>
          <span class="rounded-full bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 px-2.5 py-1 font-medium">Examples</span>
          <span class="rounded-full bg-violet-50 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400 px-2.5 py-1 font-medium">Problems</span>
          <span class="rounded-full bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 px-2.5 py-1 font-medium">Revision</span>
        </div>
      </div>

      <div class="mt-5 space-y-5" id="sessionGroups">
        <?php if (!$grouped): ?>
          <div class="text-center py-10">
            <span class="inline-flex w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 items-center justify-center"><i data-lucide="book-open" class="w-6 h-6 text-slate-400"></i></span>
            <p class="mt-3 font-medium text-slate-700 dark:text-slate-300">No study plan yet</p>
            <p class="text-sm text-slate-400 mt-1">Generate a plan on the left — it breaks your time into concept, examples, problem solving and revision.</p>
          </div>
        <?php endif; ?>
        <?php foreach ($grouped as $date => $daySessions): ?>
          <div>
            <div class="flex items-center gap-2 mb-2.5">
              <span class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= e(date('D, d M Y', strtotime($date))) ?></span>
              <?php if ($date === date('Y-m-d')): ?><span class="rounded-full bg-indigo-600 text-white text-[10px] px-2 py-0.5 font-semibold">Today</span><?php endif; ?>
              <span class="flex-1 h-px bg-slate-100 dark:bg-slate-800"></span>
              <span class="text-xs text-slate-400"><?= format_minutes(array_sum(array_map(fn($s) => (int) $s['duration_minutes'], $daySessions))) ?></span>
            </div>
            <div class="space-y-2.5">
              <?php foreach ($daySessions as $s): ?>
                <?php
                $phase = 'other';
                $topic = $s['topic'];
                foreach (['Concept learning' => 'concept', 'Examples' => 'examples', 'Problem solving' => 'problems', 'Revision' => 'revision'] as $needle => $key) {
                    if (stripos($topic, $needle) !== false) { $phase = $key; break; }
                }
                $phaseClass = ['concept' => 'border-l-indigo-500', 'examples' => 'border-l-sky-500', 'problems' => 'border-l-violet-500', 'revision' => 'border-l-emerald-500'][$phase];
                ?>
                <div class="flex items-start gap-3 rounded-2xl border border-slate-100 dark:border-slate-800 border-l-4 <?= $phaseClass ?> p-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition"
                     data-session-id="<?= (int) $s['id'] ?>"
                     data-topic="<?= e($s['topic']) ?>"
                     data-duration="<?= (int) $s['duration_minutes'] ?>"
                     data-date="<?= e($s['session_date']) ?>"
                     data-notes="<?= e($s['notes'] ?? '') ?>">
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($s['topic']) ?></p>
                    <p class="text-xs text-slate-400 mt-0.5">
                      <?= format_minutes((int) $s['duration_minutes']) ?>
                      <?php if ($s['notes']): ?> · <?= e($s['notes']) ?><?php endif; ?>
                      <?php if ($s['subject_name']): ?> · <?= e($s['subject_name']) ?><?php endif; ?>
                    </p>
                  </div>
                  <div class="flex gap-1 shrink-0">
                    <button type="button" data-edit-session class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15" title="Edit"><i data-lucide="pencil" class="w-4 h-4"></i></button>
                    <button type="button" data-delete-session class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/15" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Session edit modal -->
<div id="sessionModal" class="hidden fixed inset-0 z-[60] p-4">
  <div class="min-h-full flex items-center justify-center">
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" data-close-modal></div>
    <div class="relative w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-soft border border-slate-200 dark:border-slate-800 p-6">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Edit session</h3>
        <button type="button" data-close-modal class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <form id="sessionForm" class="mt-5 space-y-4">
        <input type="hidden" name="id" id="sf_id">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Topic</label>
          <input name="topic" id="sf_topic" required class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Duration (min)</label>
            <input type="number" name="duration_minutes" id="sf_dur" min="10" max="480" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Date</label>
            <input type="date" name="session_date" id="sf_date" required class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Notes</label>
          <input name="notes" id="sf_notes" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" data-close-modal class="rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
          <button type="submit" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
