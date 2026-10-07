<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../services/StudentLifeScoreService.php';
require_api_auth();

$uid = user_id();
sync_notifications($uid);

$stats = dashboard_stats($uid);
$settings = get_settings($uid);
$rec = recommendation($uid);

// Top open tasks by smart score (drives "Today's Priorities")
$stmt = db()->prepare(
    "SELECT * FROM tasks
     WHERE user_id = ? AND deleted_at IS NULL AND status <> 'Completed'
     ORDER BY due_date IS NULL, due_date ASC, created_at ASC"
);
$stmt->execute([$uid]);
$open = $stmt->fetchAll();
usort($open, function ($a, $b) {
    [$sa] = task_score($a);
    [$sb] = task_score($b);
    return $sb <=> $sa;
});
$priorities = array_map('task_json', array_slice($open, 0, 5));

$display = [
    'today_tasks'   => (string) $stats['today_tasks'],
    'overdue'       => (string) $stats['overdue'],
    'study_today'   => format_minutes($stats['study_today']),
    'month_spent'   => format_money($stats['month_spent']),
    'habit_streak'  => $stats['habit_streak'] . ' day' . ($stats['habit_streak'] === 1 ? '' : 's'),
    'goals_progress'=> $stats['goals_progress'] . '%',
];
$footers = [
    'today_tasks'   => $stats['completed_today'] . ' completed today',
    'overdue'       => $stats['overdue'] > 0 ? 'needs attention' : 'all caught up',
    'study_today'   => 'target ' . format_minutes((int) $settings['daily_study_target']) . '/day',
    'month_spent'   => $settings['monthly_budget'] > 0
        ? 'budget ' . format_money((float) $settings['monthly_budget'])
        : 'no budget set',
    'habit_streak'  => $stats['habits_done'] . '/' . $stats['habits_total'] . ' habits today',
    'goals_progress'=> 'average of active goals',
];

$payload = [
    'stats'          => $stats,
    'display'        => $display,
    'footers'        => $footers,
    'recommendation' => $rec ? (task_json($rec) + ['why' => $rec['why']]) : null,
    'priorities'     => $priorities,
    'brief'          => daily_brief($uid),
    'productivity'   => productivity_score($uid),
    'life_score'     => StudentLifeScoreService::compute($uid),
    'risk_summary'   => DeadlineRiskService::summary($uid),
    'unread'         => unread_notification_count($uid),
    'daily_target'   => (int) $settings['daily_study_target'],
];

respond(true, '', $payload);
