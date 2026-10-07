<?php
require_once __DIR__ . '/../config/auth.php';
require_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Method not allowed.', [], 405);
verify_csrf();

$uid    = user_id();
$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);

function fetch_event(int $uid, int $id): array
{
    $stmt = db()->prepare('SELECT * FROM events WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $uid]);
    $row = $stmt->fetch();
    if (!$row) respond(false, 'Event not found.', [], 404);
    return $row;
}

function collect_event_input(): array
{
    $title = trim($_POST['title'] ?? '');
    if ($title === '') respond(false, 'Event title is required.');
    if (mb_strlen($title) > 200) respond(false, 'Title must be 200 characters or fewer.');

    $date = $_POST['event_date'] ?? '';
    $f = DateTime::createFromFormat('Y-m-d', $date);
    if (!$f || $f->format('Y-m-d') !== $date) respond(false, 'Invalid event date.');

    $start = trim($_POST['start_time'] ?? '');
    $end   = trim($_POST['end_time'] ?? '');
    foreach ([$start, $end] as $t) {
        if ($t !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)) respond(false, 'Invalid event time.');
    }
    if ($start && $end && $end < $start) respond(false, 'End time must be after start time.');

    $category = $_POST['category'] ?? 'Personal';
    if (!in_array($category, ['Assignment', 'Exam', 'Project', 'Personal', 'Study', 'Other'], true)) $category = 'Personal';

    return [
        'title'       => $title,
        'description' => trim($_POST['description'] ?? ''),
        'event_date'  => $date,
        'start_time'  => $start ?: null,
        'end_time'    => $end ?: null,
        'category'    => $category,
    ];
}

switch ($action) {
    case 'create': {
        $d = collect_event_input();
        $stmt = db()->prepare(
            'INSERT INTO events (user_id, title, description, event_date, start_time, end_time, category)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([$uid, $d['title'], $d['description'], $d['event_date'], $d['start_time'], $d['end_time'], $d['category']]);
        respond(true, 'Event created.', ['event' => fetch_event($uid, (int) db()->lastInsertId())]);
        break;
    }
    case 'update': {
        fetch_event($uid, $id);
        $d = collect_event_input();
        $stmt = db()->prepare(
            'UPDATE events SET title=?, description=?, event_date=?, start_time=?, end_time=?, category=? WHERE id=? AND user_id=?'
        );
        $stmt->execute([$d['title'], $d['description'], $d['event_date'], $d['start_time'], $d['end_time'], $d['category'], $id, $uid]);
        respond(true, 'Event updated.', ['event' => fetch_event($uid, $id)]);
        break;
    }
    case 'delete': {
        fetch_event($uid, $id);
        db()->prepare('DELETE FROM events WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        respond(true, 'Event deleted.');
        break;
    }
    default:
        respond(false, 'Unknown action.', [], 400);
}
