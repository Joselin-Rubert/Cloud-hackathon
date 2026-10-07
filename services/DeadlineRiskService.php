<?php
/**
 * StudentFlow - Deadline Risk Indicator.
 *
 * Each open task is classified at read time (no schema changes) into
 *   - critical   → not enough time left (or already overdue)
 *   - approaching→ time is getting tight
 *   - safe       → plenty of time (or no deadline set)
 *   - none       → completed / trashed tasks (no risk applies)
 *
 * The buffer added to the estimated effort grows with priority so that
 * high-priority tasks are flagged earlier than low-priority ones.
 */

require_once __DIR__ . '/../config/database.php';

final class DeadlineRiskService
{
    /** Priority → extra multiplier applied on top of the estimated minutes. */
    private const PRIORITY_BUFFER = ['High' => 3.0, 'Medium' => 2.0, 'Low' => 1.5];

    /** Ordered levels (used for summaries & charts). 'none' is excluded here. */
    public const LEVELS = ['critical', 'approaching', 'safe'];

    /**
     * Classify a single task row (as fetched from the tasks table).
     *
     * @param array $t task row with at least: status, due_date, due_time,
     *                 estimated_minutes, priority, deleted_at
     */
    public static function risk(array $t): array
    {
        if (!empty($t['deleted_at'])) {
            return self::base('none', 'Deleted', 'Trashed task — no deadline risk.', 'slate', null);
        }
        if (($t['status'] ?? '') === 'Completed') {
            return self::base('none', 'Done', 'This task is already completed.', 'slate', null);
        }
        if (empty($t['due_date'])) {
            return self::base('safe', 'Safe', 'No deadline set — plan your own date to stay on track.', 'emerald', null);
        }

        $deadline = self::deadlineTimestamp($t);
        $now = time();

        if ($now > $deadline) {
            return self::base('critical', 'Critical', 'Overdue — start this task right now.', 'rose', 0);
        }

        $remaining = (int) round(($deadline - $now) / 60);
        $estimate  = max(30, (int) ($t['estimated_minutes'] ?? 0));
        $multiplier = self::PRIORITY_BUFFER[$t['priority'] ?? 'Medium'] ?? 2.0;
        $buffer    = (int) round($estimate * $multiplier);

        if ($remaining <= $estimate) {
            return self::base('critical', 'Critical', 'Not enough time to finish this properly — start immediately.', 'rose', $remaining);
        }
        if ($remaining <= $estimate + $buffer) {
            return self::base('approaching', 'Approaching', 'Start soon — time is getting tight for this deadline.', 'amber', $remaining);
        }
        return self::base('safe', 'Safe', 'Plenty of time to complete this task.', 'emerald', $remaining);
    }

    /** True when the level implies real deadline pressure. */
    public static function isRisky(string $level): bool
    {
        return in_array($level, ['critical', 'approaching'], true);
    }

    /**
     * Summary over a user's open (non-completed, non-trashed) tasks.
     * Returns counts per level plus the top critical tasks.
     */
    public static function summary(int $uid): array
    {
        $stmt = db()->prepare(
            "SELECT * FROM tasks
             WHERE user_id = ? AND deleted_at IS NULL AND status <> 'Completed'"
        );
        $stmt->execute([$uid]);
        $open = $stmt->fetchAll();

        $counts = ['open' => count($open), 'critical' => 0, 'approaching' => 0, 'safe' => 0];
        $critical = [];

        foreach ($open as $t) {
            $level = self::risk($t)['level'];
            if ($level === 'critical') {
                $counts['critical']++;
                $critical[] = $t;
            } elseif ($level === 'approaching') {
                $counts['approaching']++;
            } else {
                $counts['safe']++;
            }
        }

        usort($critical, function ($a, $b) {
            $da = $a['due_date'] ?? '9999-12-31';
            $db = $b['due_date'] ?? '9999-12-31';
            return $da <=> $db;
        });

        $top = array_map(function ($t) {
            $r = self::risk($t);
            return [
                'id'            => (int) $t['id'],
                'title'         => $t['title'],
                'category'      => $t['category'],
                'priority'      => $t['priority'],
                'due_date'      => $t['due_date'],
                'due_time'      => $t['due_time'],
                'estimated'     => (int) $t['estimated_minutes'],
                'message'       => $r['message'],
                'remaining_min' => $r['remaining_minutes'],
            ];
        }, array_slice($critical, 0, 3));

        return ['counts' => $counts, 'top' => $top];
    }

    /**
     * Tailwind styling classes for a risk chip by level.
     * Returns [chipClasses, icon].
     */
    public static function chipClasses(string $level): array
    {
        switch ($level) {
            case 'critical':
                return ['bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400', 'triangle-alert'];
            case 'approaching':
                return ['bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400', 'clock'];
            case 'safe':
                return ['bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400', 'shield-check'];
            default:
                return ['bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400', 'circle-check'];
        }
    }

    /* ---------------------------------------------------------------
     * Internals
     * ------------------------------------------------------------- */
    private static function deadlineTimestamp(array $t): int
    {
        $date = $t['due_date'];
        $time = !empty($t['due_time']) ? $t['due_time'] : '23:59:59';
        $ts = strtotime($date . ' ' . $time);
        return $ts === false ? strtotime($date . ' 23:59:59') : $ts;
    }

    private static function base(string $level, string $label, string $message, string $color, ?int $remaining): array
    {
        return [
            'level'            => $level,
            'label'            => $label,
            'message'          => $message,
            'color'            => $color,
            'remaining_minutes'=> $remaining,
        ];
    }
}