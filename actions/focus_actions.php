<?php
require_once __DIR__ . '/../config/auth.php';
require_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Method not allowed.', [], 405);
verify_csrf();

$uid = user_id();

$minutes = (int) ($_POST['duration_minutes'] ?? 0);
if ($minutes < 1 || $minutes > 240) respond(false, 'Invalid session duration.');

$taskId = (int) ($_POST['task_id'] ?? 0);
$completed = isset($_POST['completed']) ? (int) $_POST['completed'] : 1;

$taskTitle = null;
if ($taskId > 0) {
    $stmt = db()->prepare('SELECT id, title, status FROM tasks WHERE id = ? AND user_id = ?');
    $stmt->execute([$taskId, $uid]);
    $task = $stmt->fetch();
    if (!$task) respond(false, 'Linked task not found.', [], 404);
    $taskTitle = $task['title'];
    if ($task['status'] === 'Not Started') {
        db()->prepare("UPDATE tasks SET status = 'In Progress' WHERE id = ? AND user_id = ?")->execute([$taskId, $uid]);
    }
}

$stmt = db()->prepare(
    'INSERT INTO focus_sessions (user_id, task_id, duration_minutes, session_date, completed) VALUES (?,?,?,?,?)'
);
$stmt->execute([$uid, $taskId > 0 ? $taskId : null, $minutes, date('Y-m-d'), $completed]);

if ($completed) {
    $suffix = $taskTitle ? ' on "' . $taskTitle . '"' : '';
    push_notification(
        $uid,
        'Focus session completed',
        '⏱ Focus session completed' . $suffix . ' — ' . format_minutes($minutes) . ' of deep work.',
        'focus'
    );
}

$summary = focus_summary($uid);
respond(true, 'Focus session saved.', [
    'summary'    => $summary,
    'task_title' => $taskTitle,
    'productivity' => productivity_score($uid),
]);
