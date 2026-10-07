<?php
/**
 * StudentFlow - Shared helper functions
 * All user-scoped queries require an explicit $uid.
 */

require_once __DIR__ . '/../services/DeadlineRiskService.php';

/* ---------------------------------------------------------------
 * Output escaping & formatting
 * ------------------------------------------------------------- */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_money($amount): string
{
    return '₹' . number_format((float) $amount, $amount == (int) $amount ? 0 : 2);
}

function format_minutes(int $minutes): string
{
    if ($minutes <= 0) return '0m';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h && $m) return $h . 'h ' . $m . 'm';
    if ($h) return $h . 'h';
    return $m . 'm';
}

function date_diff_days(string $date, string $from = 'today'): int
{
    $base = strtotime($from === 'today' ? 'today' : $from);
    $target = strtotime($date);
    return (int) round(($target - $base) / 86400);
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return intdiv($diff, 60) . 'm ago';
    if ($diff < 86400) return intdiv($diff, 3600) . 'h ago';
    if ($diff < 604800) return intdiv($diff, 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

function options_html(array $options, $selected = null): string
{
    $html = '';
    foreach ($options as $value => $label) {
        $sel = ((string) $value === (string) $selected) ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    return $html;
}

/* ---------------------------------------------------------------
 * Flash messages
 * ------------------------------------------------------------- */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------------------------------------------------------------
 * Unified action response (JSON for AJAX, redirect for forms)
 * ------------------------------------------------------------- */
function wants_json(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
}

function respond(bool $ok, string $message = '', array $data = [], ?int $code = null): void
{
    if (wants_json()) {
        http_response_code($ok ? 200 : ($code ?: 400));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message, 'data' => $data]);
        exit;
    }
    flash($ok ? 'success' : 'error', $message !== '' ? $message : ($ok ? 'Saved.' : 'Something went wrong.'));
    $back = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
    if (parse_url($back, PHP_URL_HOST)) $back = '/';
    header('Location: ' . $back);
    exit;
}

/* ---------------------------------------------------------------
 * Settings
 * ------------------------------------------------------------- */
function get_settings(int $uid): array
{
    $stmt = db()->prepare('SELECT * FROM user_settings WHERE user_id = ?');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if (!$row) {
        db()->prepare('INSERT INTO user_settings (user_id) VALUES (?)')->execute([$uid]);
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
    }
    return $row;
}

function unread_notification_count(int $uid): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$uid]);
    return (int) $stmt->fetchColumn();
}

/* ---------------------------------------------------------------
 * SMART PRIORITY SYSTEM
 * Rule-based scoring: deadline urgency + importance + effort + status
 * ------------------------------------------------------------- */
function task_score(array $t): array
{
    if (($t['status'] ?? '') === 'Completed' || !empty($t['deleted_at'])) {
        return [0, []];
    }

    $score   = 0;
    $reasons = [];
    $due     = $t['due_date'] ?? null;

    if ($due) {
        $days = date_diff_days($due);
        if ($days < 0) {
            $score += 40 + min(20, abs($days) * 2);
            $reasons[] = 'Overdue by ' . abs($days) . ' day' . (abs($days) === 1 ? '' : 's');
        } elseif ($days === 0) {
            $score += 35;
            $reasons[] = 'Deadline is today';
        } elseif ($days === 1) {
            $score += 30;
            $reasons[] = 'Deadline is tomorrow';
        } elseif ($days <= 3) {
            $score += 25;
            $reasons[] = 'Deadline is within 3 days';
        } elseif ($days <= 7) {
            $score += 15;
            $reasons[] = 'Deadline is within a week';
        } else {
            $score += 5;
        }
    }

    $priorityPoints = ['High' => 30, 'Medium' => 20, 'Low' => 10];
    $p = $priorityPoints[$t['priority'] ?? 'Medium'] ?? 10;
    $score += $p;
    if ($p === 30) $reasons[] = 'Marked as high priority';
    elseif ($p === 20) $reasons[] = 'Marked as medium priority';

    $est = (int) ($t['estimated_minutes'] ?? 60);
    if ($est >= 120) {
        $score += 10;
        $reasons[] = 'Needs an estimated ' . format_minutes($est);
    } elseif ($est >= 60) {
        $score += 7;
    } else {
        $score += 4;
    }

    if (($t['status'] ?? '') === 'In Progress') {
        $score += 5;
        $reasons[] = 'Work has already started';
    } elseif (($t['status'] ?? '') === 'Not Started') {
        $score += 3;
        $reasons[] = 'Not started yet';
    }

    return [$score, $reasons];
}

/** Best next action: highest scoring open task for the user. */
function recommendation(int $uid): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM tasks
         WHERE user_id = ? AND status <> 'Completed' AND deleted_at IS NULL
         ORDER BY due_date IS NULL, due_date ASC, created_at ASC"
    );
    $stmt->execute([$uid]);
    $tasks = $stmt->fetchAll();
    if (!$tasks) return null;

    $best = null;
    foreach ($tasks as $t) {
        [$score, $reasons] = task_score($t);
        $t['score']   = $score;
        $t['reasons'] = $reasons;
        if ($best === null || $score > $best['score']) {
            $best = $t;
        }
    }
    if ($best && $best['reasons']) {
        $r = $best['reasons'];
        $first = strtolower($r[0]);
        $second = isset($r[1]) ? ' and ' . strtolower($r[1]) : '';
        $best['why'] = 'Recommended because ' . $first . $second . '.';
    } elseif ($best) {
        $best['why'] = 'Recommended as the next open task in your list.';
    }
    return $best;
}

/* ---------------------------------------------------------------
 * STUDY PLAN GENERATOR (rule based)
 * ------------------------------------------------------------- */
function generate_study_plan(array $input): array
{
    $topic      = trim($input['topic'] ?? 'Topic');
    $difficulty = $input['difficulty'] ?? 'Medium';
    $total      = max(30, min(1440, (int) ($input['total_minutes'] ?? 120)));
    $examDate   = $input['exam_date'] ?? null;
    $studyTime  = $input['study_time'] ?? '18:00';
    $startDate  = date('Y-m-d');
    $endDate    = $examDate ?: date('Y-m-d', strtotime('+7 days'));
    if (strtotime($endDate) < strtotime($startDate)) $endDate = $startDate;

    $weights = [
        'Easy'   => ['Concept learning' => 25, 'Examples & practice' => 25, 'Problem solving' => 25, 'Revision' => 25],
        'Medium' => ['Concept learning' => 30, 'Examples & practice' => 25, 'Problem solving' => 25, 'Revision' => 20],
        'Hard'   => ['Concept learning' => 35, 'Examples & practice' => 30, 'Problem solving' => 25, 'Revision' => 10],
    ];
    $plan = $weights[$difficulty] ?? $weights['Medium'];

    $sessions = [];
    $assigned = 0;
    $count = count($plan);
    $i = 0;
    foreach ($plan as $phase => $weight) {
        $duration = (int) round($total * $weight / 100 / 5) * 5;
        if ($i === $count - 1) {
            $duration = max(10, $total - $assigned); // last session absorbs rounding
        }
        $assigned += $duration;
        $sessions[] = ['phase' => $phase, 'duration' => $duration];
        $i++;
    }

    $days = max(1, min(count($sessions), date_diff_days($endDate, $startDate) + 1));
    $startTs = strtotime($startDate);
    $result = [];
    foreach ($sessions as $idx => $s) {
        $offset = ($days === 1) ? 0 : (int) floor($idx * $days / count($sessions));
        $date = date('Y-m-d', strtotime('+' . $offset . ' days', $startTs));
        $result[] = [
            'topic'    => $topic . ' — ' . $s['phase'],
            'phase'    => $s['phase'],
            'duration' => $s['duration'],
            'date'     => $date,
            'time'     => $studyTime,
        ];
    }
    return $result;
}

/* ---------------------------------------------------------------
 * HABIT STREAKS
 * ------------------------------------------------------------- */
function habit_streak(int $uid, int $habitId): int
{
    $stmt = db()->prepare(
        'SELECT log_date FROM habit_logs
         WHERE user_id = ? AND habit_id = ? AND completed = 1
         ORDER BY log_date DESC LIMIT 400'
    );
    $stmt->execute([$uid, $habitId]);
    $dates = array_column($stmt->fetchAll(), 'log_date');
    if (!$dates) return 0;

    $set = array_flip($dates);
    $cursor = date('Y-m-d');
    if (!isset($set[$cursor])) {
        $cursor = date('Y-m-d', strtotime('-1 day'));
        if (!isset($set[$cursor])) return 0;
    }
    $streak = 0;
    while (isset($set[$cursor])) {
        $streak++;
        $cursor = date('Y-m-d', strtotime('-1 day', strtotime($cursor)));
    }
    return $streak;
}

function habit_longest_streak(int $uid, int $habitId): int
{
    $stmt = db()->prepare(
        'SELECT log_date FROM habit_logs
         WHERE user_id = ? AND habit_id = ? AND completed = 1
         ORDER BY log_date ASC LIMIT 1000'
    );
    $stmt->execute([$uid, $habitId]);
    $dates = array_column($stmt->fetchAll(), 'log_date');
    if (!$dates) return 0;

    $best = 1;
    $cur  = 1;
    for ($i = 1; $i < count($dates); $i++) {
        $prev = strtotime($dates[$i - 1]);
        $now  = strtotime($dates[$i]);
        if (($now - $prev) === 86400) {
            $cur++;
            $best = max($best, $cur);
        } else {
            $cur = 1;
        }
    }
    return $best;
}

function habit_completion_rate(int $uid, int $habitId, string $freq, int $target, int $days): float
{
    $since = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $stmt  = db()->prepare(
        'SELECT COUNT(DISTINCT log_date) FROM habit_logs
         WHERE user_id = ? AND habit_id = ? AND completed = 1 AND log_date >= ?'
    );
    $stmt->execute([$uid, $habitId, $since]);
    $logged = (int) $stmt->fetchColumn();
    $expected = $freq === 'weekly' ? max(1, $target) : $days;
    if ($freq === 'weekly') {
        $weeks = max(1, ceil($days / 7));
        $expected = max(1, $target * $weeks);
    }
    return min(100, round($logged / $expected * 100));
}

function best_streak(int $uid): int
{
    $stmt = db()->prepare('SELECT id FROM habits WHERE user_id = ?');
    $stmt->execute([$uid]);
    $best = 0;
    foreach ($stmt->fetchAll() as $h) {
        $best = max($best, habit_streak($uid, (int) $h['id']));
    }
    return $best;
}

/* ---------------------------------------------------------------
 * GOAL STATUS (auto derived)
 * ------------------------------------------------------------- */
function goal_status(array $g): string
{
    $progress = (int) $g['progress'];
    if ($progress >= 100) return 'Completed';
    if (empty($g['target_date'])) return 'On Track';
    $days = date_diff_days($g['target_date']);
    if ($days < 0) return 'At Risk';
    if ($days <= 7 && $progress < 60) return 'At Risk';
    if ($days <= 14 && $progress < 35) return 'At Risk';
    return 'On Track';
}

/* ---------------------------------------------------------------
 * PRODUCTIVITY SCORE 0-100
 * Tasks 30% | Study 25% | Habits 20% | Focus 15% | Goals 10%
 * ------------------------------------------------------------- */
function productivity_score(int $uid): array
{
    $settings = get_settings($uid);
    $target = max(30, (int) $settings['daily_study_target']);

    // Tasks (30)
    $stmt = db()->prepare(
        "SELECT COUNT(*) total,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) done
         FROM tasks WHERE user_id = ? AND deleted_at IS NULL"
    );
    $stmt->execute([$uid]);
    $t = $stmt->fetch();
    $totalTasks = (int) $t['total'];
    $doneTasks  = (int) $t['done'];
    $tasks = $totalTasks > 0 ? round($doneTasks / $totalTasks * 100) : 0;

    // Study time (25) - this week vs weekly target
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(duration_minutes),0) FROM study_sessions
         WHERE user_id = ? AND session_date >= ?'
    );
    $stmt->execute([$uid, date('Y-m-d', strtotime('-6 days'))]);
    $studyWeek = (int) $stmt->fetchColumn();
    $study = min(100, (int) round($studyWeek / (7 * $target) * 100));

    // Habit consistency (20) - this month
    $monthStart = date('Y-m-01');
    $stmt = db()->prepare('SELECT COUNT(*) FROM habits WHERE user_id = ?');
    $stmt->execute([$uid]);
    $habitCount = (int) $stmt->fetchColumn();
    $habits = 0;
    if ($habitCount > 0) {
        $stmt = db()->prepare(
            'SELECT COUNT(DISTINCT habit_id, log_date) FROM habit_logs
             WHERE user_id = ? AND completed = 1 AND log_date >= ?'
        );
        $stmt->execute([$uid, $monthStart]);
        $logs = (int) $stmt->fetchColumn();
        $expected = $habitCount * (int) date('j');
        $habits = $expected > 0 ? min(100, (int) round($logs / $expected * 100)) : 0;
    }

    // Focus sessions (15) - sessions completed in the last 7 days
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM focus_sessions
         WHERE user_id = ? AND completed = 1 AND session_date >= ?'
    );
    $stmt->execute([$uid, date('Y-m-d', strtotime('-6 days'))]);
    $focusSessions = (int) $stmt->fetchColumn();
    $focus = min(100, (int) round($focusSessions / 7 * 100));

    // Goal progress (10)
    $stmt = db()->prepare(
        'SELECT COALESCE(AVG(progress),0) FROM goals
         WHERE user_id = ? AND progress < 100'
    );
    $stmt->execute([$uid]);
    $goals = (int) round((float) $stmt->fetchColumn());
    $stmt = db()->prepare('SELECT COUNT(*) FROM goals WHERE user_id = ?');
    $stmt->execute([$uid]);
    if ((int) $stmt->fetchColumn() === 0) {
        $goals = 0; // no goals yet
    } elseif ($goals === 0) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM goals WHERE user_id = ? AND progress >= 100');
        $stmt->execute([$uid]);
        $goals = ((int) $stmt->fetchColumn() > 0) ? 100 : 0;
    }

    $overall = (int) round($tasks * 0.30 + $study * 0.25 + $habits * 0.20 + $focus * 0.15 + $goals * 0.10);

    return [
        'overall'    => $overall,
        'tasks'      => $tasks,
        'study'      => $study,
        'habits'     => $habits,
        'focus'      => $focus,
        'goals'      => $goals,
        'done_tasks' => $doneTasks,
        'total_tasks'=> $totalTasks,
    ];
}

/* ---------------------------------------------------------------
 * NOTIFICATIONS (generated from real data)
 * ------------------------------------------------------------- */
function push_notification(int $uid, string $title, string $message, string $type = 'system'): void
{
    $stmt = db()->prepare('SELECT id FROM notifications WHERE user_id = ? AND type = ? AND title = ? LIMIT 1');
    $stmt->execute([$uid, $type, $title]);
    if ($stmt->fetch()) return;

    $ins = db()->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)');
    $ins->execute([$uid, $title, mb_substr($message, 0, 500), $type]);

    // keep the 50 most recent
    db()->prepare(
        'DELETE FROM notifications WHERE user_id = ? AND id NOT IN (
            SELECT id FROM (SELECT id FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50) t
         )'
    )->execute([$uid, $uid]);
}

function sync_notifications(int $uid): void
{
    $settings = get_settings($uid);
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    // Regenerate daily reminders so messages always reflect current data
    db()->prepare(
        "DELETE FROM notifications
         WHERE user_id = ? AND created_at < CURDATE()
           AND type IN ('deadline','overdue','exam','goal','habit','budget')"
    )->execute([$uid]);

    if ($settings['task_reminders']) {
        // Overdue tasks
        $stmt = db()->prepare(
            "SELECT title, due_date FROM tasks
             WHERE user_id = ? AND deleted_at IS NULL AND status <> 'Completed'
               AND due_date IS NOT NULL AND due_date < ?"
        );
        $stmt->execute([$uid, $today]);
        foreach ($stmt->fetchAll() as $t) {
            $days = date_diff_days($t['due_date']);
            push_notification($uid, $t['title'],
                '⚠ ' . $t['title'] . ' is overdue by ' . abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . '.',
                'overdue');
        }
        // Due tomorrow / today
        $stmt = db()->prepare(
            "SELECT title, due_date FROM tasks
             WHERE user_id = ? AND deleted_at IS NULL AND status <> 'Completed'
               AND due_date IN (?, ?)"
        );
        $stmt->execute([$uid, $today, $tomorrow]);
        foreach ($stmt->fetchAll() as $t) {
            $when = $t['due_date'] === $today ? 'today' : 'tomorrow';
            push_notification($uid, $t['title'],
                '⚠ ' . $t['title'] . ' is due ' . $when . '.', 'deadline');
        }
    }

    // Exam reminders within 3 days
    $stmt = db()->prepare(
        'SELECT name, exam_date FROM subjects
         WHERE user_id = ? AND exam_date IS NOT NULL AND exam_date >= ?
           AND exam_date <= ?'
    );
    $stmt->execute([$uid, $today, date('Y-m-d', strtotime('+3 days'))]);
    foreach ($stmt->fetchAll() as $s) {
        $days = date_diff_days($s['exam_date']);
        $when = $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : 'in ' . $days . ' days');
        push_notification($uid, 'Exam: ' . $s['name'],
            '📚 ' . $s['name'] . ' exam is ' . $when . '.', 'exam');
    }

    // Habit streak
    if ($settings['habit_reminders']) {
        $streak = best_streak($uid);
        if ($streak >= 3) {
            push_notification($uid, 'Keep your streak alive',
                '🔥 Your current study streak is ' . $streak . ' day' . ($streak === 1 ? '' : 's') . '.', 'habit');
        }
        // Habits not done today
        $stmt = db()->prepare('SELECT COUNT(*) FROM habits WHERE user_id = ?');
        $stmt->execute([$uid]);
        $all = (int) $stmt->fetchColumn();
        $doneToday = db()->prepare('SELECT COUNT(*) FROM habit_logs WHERE user_id = ? AND log_date = ? AND completed = 1');
        $doneToday->execute([$uid, $today]);
        $done = (int) $doneToday->fetchColumn();
        if ($all > 0 && $done === 0) {
            push_notification($uid, 'Habit check-in',
                '🎯 You have not checked in any habit today yet.', 'habit');
        }
    }

    // Budget warning
    $budget = (float) $settings['monthly_budget'];
    if ($budget > 0) {
        $stmt = db()->prepare(
            'SELECT COALESCE(SUM(amount),0) FROM expenses
             WHERE user_id = ? AND expense_date >= ?'
        );
        $stmt->execute([$uid, date('Y-m-01')]);
        $spent = (float) $stmt->fetchColumn();
        if ($spent >= $budget) {
            push_notification($uid, 'Monthly budget exceeded',
                '⚠ You have exceeded your monthly budget (' . format_money($spent) . ' of ' . format_money($budget) . ').',
                'budget');
        } elseif ($spent >= $budget * 0.8) {
            push_notification($uid, 'Approaching monthly budget',
                '💰 You have ' . format_money($budget - $spent) . ' remaining from your ' . format_money($budget) . ' monthly budget.',
                'budget');
        }
    }

    // Goals nearing deadline
    $stmt = db()->prepare(
        'SELECT title, target_date, progress FROM goals
         WHERE user_id = ? AND progress < 100 AND target_date IS NOT NULL
           AND target_date >= ? AND target_date <= ?'
    );
    $stmt->execute([$uid, $today, date('Y-m-d', strtotime('+5 days'))]);
    foreach ($stmt->fetchAll() as $g) {
        $days = date_diff_days($g['target_date']);
        $when = $days <= 0 ? 'today' : ($days === 1 ? 'tomorrow' : 'in ' . $days . ' days');
        push_notification($uid, $g['title'],
            '🎯 Goal "' . $g['title'] . '" is due ' . $when . ' (' . (int) $g['progress'] . '% complete).', 'goal');
    }
}

function delete_notifications_for_task(int $uid, string $taskTitle): void
{
    db()->prepare(
        "DELETE FROM notifications WHERE user_id = ? AND type IN ('deadline','overdue') AND title = ?"
    )->execute([$uid, $taskTitle]);
}

/* ---------------------------------------------------------------
 * Dashboard statistics (all from MySQL)
 * ------------------------------------------------------------- */
function dashboard_stats(int $uid): array
{
    $today = date('Y-m-d');
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM tasks
         WHERE user_id = ? AND deleted_at IS NULL AND status <> 'Completed' AND due_date = ?"
    );
    $stmt->execute([$uid, $today]);
    $todayTasks = (int) $stmt->fetchColumn();

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM tasks
         WHERE user_id = ? AND deleted_at IS NULL AND status <> 'Completed'
           AND due_date IS NOT NULL AND due_date < ?"
    );
    $stmt->execute([$uid, $today]);
    $overdue = (int) $stmt->fetchColumn();

    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(duration_minutes),0) FROM study_sessions WHERE user_id = ? AND session_date = ?'
    );
    $stmt->execute([$uid, $today]);
    $studyToday = (int) $stmt->fetchColumn();

    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(duration_minutes),0) FROM focus_sessions WHERE user_id = ? AND session_date = ?'
    );
    $stmt->execute([$uid, $today]);
    $focusToday = (int) $stmt->fetchColumn();

    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id = ? AND expense_date >= ?'
    );
    $stmt->execute([$uid, date('Y-m-01')]);
    $monthSpent = (float) $stmt->fetchColumn();

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM habits WHERE user_id = ?'
    );
    $stmt->execute([$uid]);
    $habitCount = (int) $stmt->fetchColumn();

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM habit_logs WHERE user_id = ? AND log_date = ? AND completed = 1'
    );
    $stmt->execute([$uid, $today]);
    $habitsDoneToday = (int) $stmt->fetchColumn();

    $stmt = db()->prepare(
        'SELECT COALESCE(AVG(progress),0) FROM goals WHERE user_id = ? AND progress < 100'
    );
    $stmt->execute([$uid]);
    $goalsProgress = (int) round((float) $stmt->fetchColumn());

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM tasks WHERE user_id = ? AND deleted_at IS NULL AND status = 'Completed'"
    );
    $stmt->execute([$uid]);
    $tasksCompleted = (int) $stmt->fetchColumn();

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM tasks WHERE user_id = ? AND deleted_at IS NULL AND status = 'Completed' AND DATE(updated_at) = ?"
    );
    $stmt->execute([$uid, $today]);
    $completedToday = (int) $stmt->fetchColumn();

    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(duration_minutes),0) FROM study_sessions WHERE user_id = ? AND session_date >= ?'
    );
    $stmt->execute([$uid, date('Y-m-d', strtotime('-6 days'))]);
    $studyWeek = (int) $stmt->fetchColumn();

    return [
        'today_tasks'     => $todayTasks,
        'overdue'         => $overdue,
        'study_today'     => $studyToday,
        'focus_today'     => $focusToday,
        'month_spent'     => $monthSpent,
        'habit_streak'    => best_streak($uid),
        'goals_progress'  => $goalsProgress,
        'habits_total'    => $habitCount,
        'habits_done'     => $habitsDoneToday,
        'tasks_completed' => $tasksCompleted,
        'completed_today' => $completedToday,
        'study_week'      => $studyWeek,
    ];
}

/** Daily brief lines generated from real data. */
function daily_brief(int $uid): array
{
    $today = date('Y-m-d');
    $lines = [];

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM tasks WHERE user_id = ? AND deleted_at IS NULL AND status <> 'Completed' AND due_date = ?"
    );
    $stmt->execute([$uid, $today]);
    $todayCount = (int) $stmt->fetchColumn();
    $lines[] = $todayCount > 0
        ? 'You have ' . $todayCount . ' task' . ($todayCount === 1 ? '' : 's') . ' due today.'
        : 'No tasks are due today — a great day to get ahead.';

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM tasks WHERE user_id = ? AND deleted_at IS NULL AND status <> 'Completed' AND priority = 'High' AND due_date <= ?"
    );
    $stmt->execute([$uid, date('Y-m-d', strtotime('+7 days'))]);
    $high = (int) $stmt->fetchColumn();
    if ($high > 0) $lines[] = $high . ' high priority task' . ($high === 1 ? ' is' : 's are') . ' waiting within the next week.';

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM tasks WHERE user_id = ? AND deleted_at IS NULL AND status <> 'Completed' AND due_date = ?"
    );
    $stmt->execute([$uid, date('Y-m-d', strtotime('+1 day'))]);
    $tomorrow = (int) $stmt->fetchColumn();
    if ($tomorrow > 0) $lines[] = $tomorrow . ' task' . ($tomorrow === 1 ? ' is' : 's are') . ' due tomorrow.';

    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(duration_minutes),0) FROM study_sessions WHERE user_id = ? AND session_date = ?'
    );
    $stmt->execute([$uid, date('Y-m-d', strtotime('-1 day'))]);
    $yesterdayStudy = (int) $stmt->fetchColumn();
    if ($yesterdayStudy > 0) $lines[] = 'You studied ' . format_minutes($yesterdayStudy) . ' yesterday.';

    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id = ? AND expense_date >= ?'
    );
    $stmt->execute([$uid, date('Y-m-d', strtotime('-6 days'))]);
    $weekSpend = (float) $stmt->fetchColumn();
    if ($weekSpend > 0) $lines[] = 'You spent ' . format_money($weekSpend) . ' this week.';

    $streak = best_streak($uid);
    if ($streak > 0) $lines[] = 'Your current habit streak is ' . $streak . ' day' . ($streak === 1 ? '' : 's') . '.';

    return $lines;
}


/* ---------------------------------------------------------------
 * Expense summaries & chart datasets
 * ------------------------------------------------------------- */
function expense_summary(int $uid): array
{
    $settings = get_settings($uid);
    $budget = (float) $settings['monthly_budget'];

    $single = function (string $sql, array $params) use ($uid) {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    };

    $today = $single('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id = ? AND expense_date = ?', [$uid, date('Y-m-d')]);
    $week  = $single('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id = ? AND expense_date >= ?', [$uid, date('Y-m-d', strtotime('-6 days'))]);
    $prevWeek = $single('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id = ? AND expense_date >= ? AND expense_date < ?', [$uid, date('Y-m-d', strtotime('-13 days')), date('Y-m-d', strtotime('-6 days'))]);
    $month = $single('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id = ? AND expense_date >= ?', [$uid, date('Y-m-01')]);

    return [
        'today'       => $today,
        'week'        => $week,
        'prev_week'   => $prevWeek,
        'month'       => $month,
        'budget'      => $budget,
        'remaining'   => $budget > 0 ? $budget - $month : null,
        'over_budget' => $budget > 0 && $month > $budget,
        'week_change' => $prevWeek > 0 ? round(($week - $prevWeek) / $prevWeek * 100) : null,
    ];
}

function expense_insights(int $uid): array
{
    $summary = expense_summary($uid);
    $insights = [];
    $month = $summary['month'];

    if ($month <= 0) {
        return ['No expenses recorded this month yet. Add one to start seeing insights.'];
    }

    $stmt = db()->prepare(
        'SELECT category, COALESCE(SUM(amount),0) total FROM expenses
         WHERE user_id = ? AND expense_date >= ? GROUP BY category ORDER BY total DESC'
    );
    $stmt->execute([$uid, date('Y-m-01')]);
    $byCat = $stmt->fetchAll();

    $top = $byCat[0] ?? null;
    if ($top) {
        $pct = (int) round($top['total'] / $month * 100);
        $insights[] = 'You spent ' . format_money($top['total']) . ' on ' . strtolower($top['category']) . ' this month.';
        $insights[] = $top['category'] . ' represents ' . $pct . '% of your total spending.';
    }

    if ($summary['week_change'] !== null) {
        $c = $summary['week_change'];
        if ($c > 0) $insights[] = 'You spent ' . $c . '% more this week compared to last week.';
        elseif ($c < 0) $insights[] = 'You spent ' . abs($c) . '% less this week compared to last week.';
        else $insights[] = 'Your weekly spending is the same as last week.';
    }

    if ($summary['budget'] > 0) {
        if ($summary['over_budget']) {
            $insights[] = '⚠ You have exceeded your monthly budget by ' . format_money(abs($summary['remaining'])) . '.';
        } else {
            $insights[] = 'You have ' . format_money($summary['remaining']) . ' remaining from your ' . format_money($summary['budget']) . ' monthly budget.';
        }
    }

    return $insights;
}

function expense_chart_data(int $uid): array
{
    $cats = ['Food', 'Travel', 'Education', 'Shopping', 'Entertainment', 'Bills', 'Other'];
    $stmt = db()->prepare(
        'SELECT category, COALESCE(SUM(amount),0) total FROM expenses
         WHERE user_id = ? AND expense_date >= ? GROUP BY category'
    );
    $stmt->execute([$uid, date('Y-m-01', strtotime('-90 days'))]);
    $rows = $stmt->fetchAll();
    $byCat = array_fill_keys($cats, 0);
    foreach ($rows as $r) $byCat[$r['category']] = (float) $r['total'];

    // last 6 weeks
    $weeks = [];
    for ($i = 5; $i >= 0; $i--) {
        $start = strtotime('-' . ($i * 7 + 6) . ' days');
        $end   = strtotime('-' . ($i * 7) . ' days');
        $stmt = db()->prepare(
            'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id = ? AND expense_date >= ? AND expense_date <= ?'
        );
        $stmt->execute([$uid, date('Y-m-d', $start), date('Y-m-d', $end)]);
        $weeks[] = [
            'label' => date('d M', $start),
            'value' => (float) $stmt->fetchColumn(),
        ];
    }

    // last 6 months
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $first = date('Y-m-01', strtotime('-' . $i . ' months'));
        $last  = date('Y-m-t', strtotime('-' . $i . ' months'));
        $stmt = db()->prepare(
            'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id = ? AND expense_date >= ? AND expense_date <= ?'
        );
        $stmt->execute([$uid, $first, $last]);
        $months[] = [
            'label' => date('M', strtotime($first)),
            'value' => (float) $stmt->fetchColumn(),
        ];
    }

    return ['categories' => $byCat, 'weeks' => $weeks, 'months' => $months];
}

/* ---------------------------------------------------------------
 * Focus summaries
 * ------------------------------------------------------------- */
function focus_summary(int $uid): array
{
    $get = function (string $from) use ($uid) {
        $stmt = db()->prepare(
            'SELECT COALESCE(SUM(duration_minutes),0) FROM focus_sessions
             WHERE user_id = ? AND completed = 1 AND session_date >= ?'
        );
        $stmt->execute([$uid, $from]);
        return (int) $stmt->fetchColumn();
    };
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM focus_sessions WHERE user_id = ? AND completed = 1 AND session_date >= ?'
    );
    $stmt->execute([$uid, date('Y-m-d', strtotime('-6 days'))]);
    return [
        'today' => $get(date('Y-m-d')),
        'week'  => $get(date('Y-m-d', strtotime('-6 days'))),
        'sessions_week' => (int) $stmt->fetchColumn(),
    ];
}

/* ---------------------------------------------------------------
 * Full analytics dataset (drives Chart.js via api/analytics.php)
 * ------------------------------------------------------------- */
function analytics_data(int $uid): array
{
    $productivity = productivity_score($uid);

    // Task completion last 7 days (completed on that day + due that day)
    $taskDays = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime('-' . $i . ' days'));
        $s1 = db()->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'Completed' AND DATE(updated_at) = ?");
        $s1->execute([$uid, $d]);
        $done = (int) $s1->fetchColumn();
        $s2 = db()->prepare('SELECT COUNT(*) FROM tasks WHERE user_id = ? AND deleted_at IS NULL AND due_date = ?');
        $s2->execute([$uid, $d]);
        $taskDays[] = ['label' => date('D', strtotime($d)), 'completed' => $done, 'due' => (int) $s2->fetchColumn()];
    }

    // Study hours last 7 days
    $studyDays = [];
    $focusDays = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime('-' . $i . ' days'));
        $s = db()->prepare('SELECT COALESCE(SUM(duration_minutes),0) FROM study_sessions WHERE user_id = ? AND session_date = ?');
        $s->execute([$uid, $d]);
        $studyDays[] = ['label' => date('D', strtotime($d)), 'value' => round(((int) $s->fetchColumn()) / 60, 1)];
        $s = db()->prepare('SELECT COALESCE(SUM(duration_minutes),0) FROM focus_sessions WHERE user_id = ? AND session_date = ? AND completed = 1');
        $s->execute([$uid, $d]);
        $focusDays[] = ['label' => date('D', strtotime($d)), 'value' => round(((int) $s->fetchColumn()) / 60, 1)];
    }

    // Habit consistency last 7 days (% of habits logged per day)
    $habitDays = [];
    $stmt = db()->prepare('SELECT COUNT(*) FROM habits WHERE user_id = ?');
    $stmt->execute([$uid]);
    $habitTotal = (int) $stmt->fetchColumn();
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime('-' . $i . ' days'));
        $logged = 0;
        if ($habitTotal > 0) {
            $s = db()->prepare('SELECT COUNT(DISTINCT habit_id) FROM habit_logs WHERE user_id = ? AND log_date = ? AND completed = 1');
            $s->execute([$uid, $d]);
            $logged = (int) $s->fetchColumn();
        }
        $habitDays[] = ['label' => date('D', strtotime($d)), 'value' => $habitTotal > 0 ? round($logged / $habitTotal * 100) : 0];
    }

    // Goals
    $stmt = db()->prepare('SELECT title, progress, target_date FROM goals WHERE user_id = ? ORDER BY progress ASC LIMIT 8');
    $stmt->execute([$uid]);
    $goals = array_map(function ($g) {
        return [
            'label' => mb_substr($g['title'], 0, 24),
            'value' => (int) $g['progress'],
            'status' => goal_status($g),
        ];
    }, $stmt->fetchAll());

    // Task completion totals
    $stmt = db()->prepare("SELECT status, COUNT(*) c FROM tasks WHERE user_id = ? AND deleted_at IS NULL GROUP BY status");
    $stmt->execute([$uid]);
    $statusCounts = array_fill_keys(['Not Started', 'In Progress', 'Completed'], 0);
    foreach ($stmt->fetchAll() as $r) $statusCounts[$r['status']] = (int) $r['c'];

    $stmt = db()->prepare('SELECT COALESCE(SUM(duration_minutes),0) FROM study_sessions WHERE user_id = ?');
    $stmt->execute([$uid]);
    $studyTotal = round(((int) $stmt->fetchColumn()) / 60, 1);

    $stmt = db()->prepare('SELECT COALESCE(SUM(duration_minutes),0) FROM focus_sessions WHERE user_id = ? AND completed = 1');
    $stmt->execute([$uid]);
    $focusTotal = round(((int) $stmt->fetchColumn()) / 60, 1);

    $stmt = db()->prepare('SELECT COUNT(*) FROM habits WHERE user_id = ?');
    $stmt->execute([$uid]);
    $habitCount = (int) $stmt->fetchColumn();
    $monthStart = date('Y-m-01');
    $habitConsistency = 0;
    if ($habitCount > 0) {
        $stmt = db()->prepare('SELECT COUNT(DISTINCT habit_id, log_date) FROM habit_logs WHERE user_id = ? AND completed = 1 AND log_date >= ?');
        $stmt->execute([$uid, $monthStart]);
        $expected = $habitCount * (int) date('j');
        $habitConsistency = $expected > 0 ? min(100, (int) round((int) $stmt->fetchColumn() / $expected * 100)) : 0;
    }

    $totalTasks = array_sum($statusCounts);
    $completionRate = $totalTasks > 0 ? (int) round($statusCounts['Completed'] / $totalTasks * 100) : 0;

    $goalAvg = 0;
    $stmt = db()->prepare('SELECT COUNT(*) FROM goals WHERE user_id = ?');
    $stmt->execute([$uid]);
    if ((int) $stmt->fetchColumn() > 0) {
        $stmt = db()->prepare('SELECT COALESCE(AVG(progress),0) FROM goals WHERE user_id = ?');
        $stmt->execute([$uid]);
        $goalAvg = (int) round((float) $stmt->fetchColumn());
    }

return [
        'productivity' => $productivity,
        'tasks'        => ['days' => $taskDays, 'status' => $statusCounts, 'completion_rate' => $completionRate],
        'study'        => ['days' => $studyDays, 'total_hours' => $studyTotal],
        'focus'        => ['days' => $focusDays, 'total_hours' => $focusTotal],
        'habits'       => ['days' => $habitDays, 'consistency' => $habitConsistency, 'count' => $habitCount],
        'goals'        => ['items' => $goals, 'average' => $goalAvg],
        'expenses'     => expense_chart_data($uid),
        'deadline_risk'=> DeadlineRiskService::summary($uid),
    ];
}


function task_section(array $t): string
{
    if (!empty($t['deleted_at'])) return 'trash';
    if (($t['status'] ?? '') === 'Completed') return 'completed';
    if (empty($t['due_date'])) return 'upcoming';
    $d = date_diff_days($t['due_date']);
    if ($d < 0) return 'overdue';
    if ($d === 0) return 'today';
    return 'upcoming';
}

function task_json(array $t): array
{
    [$score, $reasons] = task_score($t);
    $risk = DeadlineRiskService::risk($t);
    return [
        'id'                => (int) $t['id'],
        'title'             => $t['title'],
        'description'       => $t['description'] ?? '',
        'category'          => $t['category'],
        'priority'          => $t['priority'],
        'due_date'          => $t['due_date'],
        'due_time'          => $t['due_time'],
        'estimated_minutes' => (int) $t['estimated_minutes'],
        'status'            => $t['status'],
        'deleted'           => !empty($t['deleted_at']),
        'created_at'        => $t['created_at'],
        'score'             => $score,
        'reasons'           => $reasons,
        'section'           => task_section($t),
        'risk'              => $risk['level'],
        'risk_message'      => $risk['message'],
        'risk_remaining'    => $risk['remaining_minutes'],
    ];
}
