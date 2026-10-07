<?php
require_once __DIR__ . '/../config/auth.php';
require_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Method not allowed.', [], 405);
verify_csrf();

$uid     = user_id();
$action  = $_POST['action'] ?? '';
$taskId  = (int) ($_POST['id'] ?? 0);

function fetch_task(int $uid, int $id): array
{
    $stmt = db()->prepare('SELECT * FROM tasks WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $uid]);
    $t = $stmt->fetch();
    if (!$t) respond(false, 'Task not found.', [], 404);
    return $t;
}

function valid_date(?string $d): ?string
{
    if ($d === null || $d === '') return null;
    $f = DateTime::createFromFormat('Y-m-d', $d);
    return ($f && $f->format('Y-m-d') === $d) ? $d : false;
}

function collect_task_input(): array
{
    $title = trim($_POST['title'] ?? '');
    if ($title === '') respond(false, 'Task title is required.');
    if (mb_strlen($title) > 200) respond(false, 'Title must be 200 characters or fewer.');

    $category = $_POST['category'] ?? 'Other';
    $priority = $_POST['priority'] ?? 'Medium';
    $status   = $_POST['status'] ?? 'Not Started';
    if (!in_array($category, ['Assignment', 'Exam', 'Project', 'Study', 'Personal', 'Other'], true)) $category = 'Other';
    if (!in_array($priority, ['High', 'Medium', 'Low'], true)) $priority = 'Medium';
    if (!in_array($status, ['Not Started', 'In Progress', 'Completed'], true)) $status = 'Not Started';

    $due = valid_date($_POST['due_date'] ?? null);
    if ($due === false) respond(false, 'Invalid due date.');

    $time = trim($_POST['due_time'] ?? '');
    if ($time !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) respond(false, 'Invalid due time.');
    if ($time === '') $time = null;

    $est = (int) ($_POST['estimated_minutes'] ?? 60);
    if ($est < 5) $est = 5;
    if ($est > 1440) $est = 1440;

    return [
        'title'             => $title,
        'description'       => trim($_POST['description'] ?? ''),
        'category'          => $category,
        'priority'          => $priority,
        'status'            => $status,
        'due_date'          => $due,
        'due_time'          => $time,
        'estimated_minutes' => $est,
    ];
}

switch ($action) {
    case 'create': {
        $d = collect_task_input();
        $stmt = db()->prepare(
            'INSERT INTO tasks (user_id, title, description, category, priority, due_date, due_time, estimated_minutes, status)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $uid, $d['title'], $d['description'], $d['category'], $d['priority'],
            $d['due_date'], $d['due_time'], $d['estimated_minutes'], $d['status'],
        ]);
        $task = fetch_task($uid, (int) db()->lastInsertId());
        sync_notifications($uid);
        respond(true, 'Task created.', ['task' => task_json($task)]);
        break;
    }

    case 'update': {
        $task = fetch_task($uid, $taskId);
        $d = collect_task_input();
        $wasCompleted = $task['status'] === 'Completed';
        $stmt = db()->prepare(
            'UPDATE tasks SET title=?, description=?, category=?, priority=?, due_date=?, due_time=?, estimated_minutes=?, status=?
             WHERE id=? AND user_id=?'
        );
        $stmt->execute([
            $d['title'], $d['description'], $d['category'], $d['priority'],
            $d['due_date'], $d['due_time'], $d['estimated_minutes'], $d['status'],
            $taskId, $uid,
        ]);
        if ($d['status'] === 'Completed') delete_notifications_for_task($uid, $task['title']);
        sync_notifications($uid);
        $task = fetch_task($uid, $taskId);
        respond(true, 'Task updated.', ['task' => task_json($task)]);
        break;
    }

    case 'status': {
        $task = fetch_task($uid, $taskId);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['Not Started', 'In Progress', 'Completed'], true)) respond(false, 'Invalid status.');
        db()->prepare('UPDATE tasks SET status=? WHERE id=? AND user_id=?')->execute([$status, $taskId, $uid]);
        if ($status === 'Completed') delete_notifications_for_task($uid, $task['title']);
        sync_notifications($uid);
        $task = fetch_task($uid, $taskId);
        respond(true, $status === 'Completed' ? 'Nice work! Task completed.' : 'Task status updated.', ['task' => task_json($task)]);
        break;
    }

    case 'delete': {
        $task = fetch_task($uid, $taskId);
        db()->prepare('UPDATE tasks SET deleted_at = NOW() WHERE id = ? AND user_id = ?')->execute([$taskId, $uid]);
        respond(true, 'Task moved to trash.', ['task' => task_json(fetch_task($uid, $taskId))]);
        break;
    }

    case 'restore': {
        $task = fetch_task($uid, $taskId);
        db()->prepare('UPDATE tasks SET deleted_at = NULL WHERE id = ? AND user_id = ?')->execute([$taskId, $uid]);
        sync_notifications($uid);
        respond(true, 'Task restored.', ['task' => task_json(fetch_task($uid, $taskId))]);
        break;
    }

    default:
        respond(false, 'Unknown action.', [], 400);
}
