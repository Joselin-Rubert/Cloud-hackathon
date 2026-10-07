<?php
/**
 * StudentFlow - Student Life Score.
 *
 * A single 0-100 "life balance" metric computed dynamically from existing
 * user data (no schema changes). Six weighted pillars:
 *
 *   Task Completion      30%
 *   Study Sessions       20%
 *   Goal Progress        15%
 *   Habit Consistency    15%
 *   Deadline Management  10%
 *   Budget Management    10%
 *
 * A lightweight previous-week estimate is produced so the dashboard can show
 * "how it changed" without introducing any new storage.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/DeadlineRiskService.php';

final class StudentLifeScoreService
{
    private const WEIGHTS = [
        'tasks'    => 30,
        'study'    => 20,
        'goals'    => 15,
        'habits'   => 15,
        'deadline' => 10,
        'budget'   => 10,
    ];

    private const GRADE_LABELS = [
        [90, 'Outstanding'],
        [75, 'Excellent'],
        [60, 'Good'],
        [40, 'Getting Started'],
        [0,  'Needs Improvement'],
    ];

    private const DEFAULT_STUDY_TARGET = 120;
    private const WEEK_DAYS            = 7;

    public static function grade(int $score): string
    {
        foreach (self::GRADE_LABELS as [$min, $label]) {
            if ($score >= $min) return $label;
        }
        return 'Needs Improvement';
    }

    public static function compute(int $uid): array
    {
        $settings = self::settingsRow($uid);
        $studyTarget = max(1, (int) ($settings['daily_study_target'] ?? self::DEFAULT_STUDY_TARGET));
        $budget = max(0, (float) ($settings['monthly_budget'] ?? 0));

        /* ---- Task Completion (30%) -------------------------------- */
        $tq = self::q(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = 'Completed'), 0) AS completed,
                COALESCE(SUM(status = 'Completed' AND updated_at < NOW() - INTERVAL 7 DAY), 0) AS completed_before
             FROM tasks WHERE user_id = ? AND deleted_at IS NULL",
            [$uid]
        );
        $totalTasks  = (int) $tq['total'];
        $completed   = (int) $tq['completed'];
        $rateNow     = $totalTasks > 0 ? $completed / $totalTasks : 0;
        $ratePrev    = $totalTasks > 0 ? (int) $tq['completed_before'] / $totalTasks : 0;
        $tasksEarned = min(1, $rateNow) * self::WEIGHTS['tasks'];
        $tasksPrev   = min(1, $ratePrev) * self::WEIGHTS['tasks'];

        /* ---- Study Sessions (20%) ---------------------------------- */
        $study = self::studyWindow($uid);
        $weeklyTarget = $studyTarget * self::WEEK_DAYS;
        $studyEarned = min(1, $study['now_minutes'] / $weeklyTarget) * self::WEIGHTS['study'];
        $studyPrev   = min(1, $study['prev_minutes'] / $weeklyTarget) * self::WEIGHTS['study'];

        /* ---- Goal Progress (15%) ----------------------------------- */
        $gq = self::q('SELECT COUNT(*) c, COALESCE(AVG(progress),0) a FROM goals WHERE user_id = ?', [$uid]);
        $goalAvg = (int) $gq['c'] > 0 ? (int) round(min(100, (float) $gq['a'])) : 0;
        $goalEarned = $goalAvg / 100 * self::WEIGHTS['goals'];

        /* ---- Habit Consistency (15%) ------------------------------- */
        $habitCount = self::countHabits($uid);
        $expected = $habitCount * self::WEEK_DAYS;
        $habitDoneNow  = 0;
        $habitDonePrev = 0;
        $habitEarned   = 0;
        $habitPrev     = 0;
        if ($expected > 0) {
            $hq = self::q(
                "SELECT
                    COUNT(DISTINCT CASE WHEN log_date >= DATE(NOW() - INTERVAL 6 DAY) THEN CONCAT(habit_id, ':', log_date) END) AS dnow,
                    COUNT(DISTINCT CASE WHEN log_date >= DATE(NOW() - INTERVAL 13 DAY) AND log_date < DATE(NOW() - INTERVAL 6 DAY) THEN CONCAT(habit_id, ':', log_date) END) AS dprev
                 FROM habit_logs WHERE user_id = ? AND completed = 1",
                [$uid]
            );
            $habitDoneNow  = (int) ($hq['dnow'] ?? 0);
            $habitDonePrev = (int) ($hq['dprev'] ?? 0);
            $habitEarned = min(1, $habitDoneNow / $expected) * self::WEIGHTS['habits'];
            $habitPrev   = min(1, $habitDonePrev / $expected) * self::WEIGHTS['habits'];
        }

        /* ---- Deadline Management (10%) ----------------------------- */
        $dl = DeadlineRiskService::summary($uid)['counts'];
        $open = $dl['open'];
        $critical = $dl['critical'];
        $dlScore = $open > 0 ? max(0, 1 - ($critical / $open)) : 1;
        $deadlineEarned = $dlScore * self::WEIGHTS['deadline'];

        /* ---- Budget Management (10%) ------------------------------- */
        $spent = (float) self::q(
            'SELECT COALESCE(SUM(amount),0) AS spent FROM expenses WHERE user_id = ? AND expense_date >= ?',
            [$uid, date('Y-m-01')]
        )['spent'];
        $budgetScore = $budget > 0 ? max(0, 1 - ($spent / $budget)) : 0.5;
        $budgetEarned = $budgetScore * self::WEIGHTS['budget'];

        /* ---- Aggregate --------------------------------------------- */
        $components = [
            'tasks'    => $tasksEarned,
            'study'    => $studyEarned,
            'goals'    => $goalEarned,
            'habits'   => $habitEarned,
            'deadline' => $deadlineEarned,
            'budget'   => $budgetEarned,
        ];
        $prevComponents = [
            'tasks'    => $tasksPrev,
            'study'    => $studyPrev,
            'goals'    => $goalEarned,
            'habits'   => $habitPrev,
            'deadline' => $deadlineEarned,
            'budget'   => $budgetEarned,
        ];

        $overall = round(array_sum($components));
        $change  = (int) round(array_sum($components) - array_sum($prevComponents));

        $names = [
            'tasks'    => 'Task Completion',
            'study'    => 'Study Sessions',
            'goals'    => 'Goal Progress',
            'habits'   => 'Habit Consistency',
            'deadline' => 'Deadline Management',
            'budget'   => 'Budget Management',
        ];
        $categories = [];
        foreach (self::WEIGHTS as $key => $weight) {
            $earned = $components[$key];
            $categories[] = [
                'key'     => $key,
                'name'    => $names[$key],
                'weight'  => $weight,
                'earned'  => round($earned, 2),
                'percent' => (int) round($weight > 0 ? $earned / $weight * 100 : 0),
            ];
        }

        return [
            'overall'    => (int) $overall,
            'label'      => self::grade((int) $overall),
            'change'     => $change,
            'categories' => $categories,
            'detail'     => [
                'tasks'    => ['total' => $totalTasks, 'completed' => $completed],
                'study'    => ['minutes' => $study['now_minutes'], 'target' => $weeklyTarget],
                'goals'    => ['average' => $goalAvg],
                'habits'   => ['done' => $habitDoneNow, 'expected' => $expected, 'count' => $habitCount],
                'deadline' => ['open' => $open, 'critical' => $critical],
                'budget'   => ['spent' => $spent, 'budget' => $budget],
            ],
        ];
    }

    /* ---------------------------------------------------------------
     * Internals
     * ------------------------------------------------------------- */
    private static function q(string $sql, array $params): array
    {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    private static function countHabits(int $uid): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM habits WHERE user_id = ?');
        $stmt->execute([$uid]);
        return (int) $stmt->fetchColumn();
    }

    private static function settingsRow(int $uid): array
    {
        $stmt = db()->prepare('SELECT * FROM user_settings WHERE user_id = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if ($row) return $row;

        db()->prepare('INSERT INTO user_settings (user_id) VALUES (?)')->execute([$uid]);
        $stmt->execute([$uid]);
        return $stmt->fetch();
    }

    private static function studyWindow(int $uid): array
    {
        $row = self::q(
            "SELECT
                COALESCE(SUM(CASE WHEN session_date >= DATE(NOW() - INTERVAL 6 DAY) THEN duration_minutes ELSE 0 END), 0) AS now_minutes,
                COALESCE(SUM(CASE WHEN session_date >= DATE(NOW() - INTERVAL 13 DAY) AND session_date < DATE(NOW() - INTERVAL 6 DAY) THEN duration_minutes ELSE 0 END), 0) AS prev_minutes
             FROM study_sessions WHERE user_id = ?",
            [$uid]
        );
        return [
            'now_minutes' => (int) ($row['now_minutes'] ?? 0),
            'prev_minutes'=> (int) ($row['prev_minutes'] ?? 0),
        ];
    }
}