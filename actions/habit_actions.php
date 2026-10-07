<?php
require_once __DIR__ . '/../config/auth.php';
require_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Method not allowed.', [], 405);
verify_csrf();

$uid    = user_id();
$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);

function fetch_habit(int $uid, int $id): array
{
    $stmt = db()->prepare('SELECT * FROM habits WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $uid]);
    $row = $stmt->fetch();
    if (!$row) respond(false, 'Habit not found.', [], 404);
    return $row;
}

function habit_payload(int $uid, int $habitId): array
{
    $habit = fetch_habit($uid, $habitId);
    $today = date('Y-m-d');
    $stmt = db()->prepare('SELECT COUNT(*) FROM habit_logs WHERE habit_id = ? AND user_id = ? AND log_date = ? AND completed = 1');
    $stmt->execute([$habitId, $uid, $today]);
    return [
        'habit' => [
            'id'          => (int) $habit['id'],
            'name'        => $habit['name'],
            'description' => $habit['description'],
            'frequency'   => $habit['frequency'],
            'target'      => (int) $habit['target'],
            'streak'      => habit_streak($uid, $habitId),
            'longest'     => habit_longest_streak($uid, $habitId),
            'weekly'      => habit_completion_rate($uid, $habitId, $habit['frequency'], (int) $habit['target'], 7),
            'monthly'     => habit_completion_rate($uid, $habitId, $habit['frequency'], (int) $habit['target'], (int) date('j')),
            'done_today'  => ((int) $stmt->fetchColumn()) > 0,
        ],
        'done_today' => habits_done_today($uid),
        'total'      => habits_total($uid),
        'best_streak'=> best_streak($uid),
    ];
}

function habits_done_today(int $uid): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM habit_logs WHERE user_id = ? AND log_date = ? AND completed = 1');
    $stmt->execute([$uid, date('Y-m-d')]);
    return (int) $stmt->fetchColumn();
}

function habits_total(int $uid): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM habits WHERE user_id = ?');
    $stmt->execute([$uid]);
    return (int) $stmt->fetchColumn();
}

function collect_habit_input(): array
{
    $name = trim($_POST['name'] ?? '');
    if ($name === '') respond(false, 'Habit name is required.');
    if (mb_strlen($name) > 120) respond(false, 'Name must be 120 characters or fewer.');
    $frequency = $_POST['frequency'] ?? 'daily';
    if (!in_array($frequency, ['daily', 'weekly'], true)) $frequency = 'daily';
    $target = (int) ($_POST['target'] ?? 1);
    if ($target < 1) $target = 1;
    if ($target > 30) $target = 30;
    $description = trim($_POST['description'] ?? '');
    if (mb_strlen($description) > 255) $description = mb_substr($description, 0, 255);
    return ['name' => $name, 'description' => $description, 'frequency' => $frequency, 'target' => $target];
}

switch ($action) {
    case 'create': {
        $d = collect_habit_input();
        $stmt = db()->prepare('INSERT INTO habits (user_id, name, description, frequency, target) VALUES (?,?,?,?,?)');
        $stmt->execute([$uid, $d['name'], $d['description'], $d['frequency'], $d['target']]);
        respond(true, 'Habit created.', habit_payload($uid, (int) db()->lastInsertId()));
        break;
    }
    case 'update': {
        fetch_habit($uid, $id);
        $d = collect_habit_input();
        $stmt = db()->prepare('UPDATE habits SET name=?, description=?, frequency=?, target=? WHERE id=? AND user_id=?');
        $stmt->execute([$d['name'], $d['description'], $d['frequency'], $d['target'], $id, $uid]);
        respond(true, 'Habit updated.', habit_payload($uid, $id));
        break;
    }
    case 'delete': {
        fetch_habit($uid, $id);
        db()->prepare('DELETE FROM habits WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        respond(true, 'Habit deleted.', [
            'done_today'  => habits_done_today($uid),
            'total'       => habits_total($uid),
            'best_streak' => best_streak($uid),
        ]);
        break;
    }
    case 'toggle': {
        $habit = fetch_habit($uid, $id);
        $logDate = $_POST['log_date'] ?? date('Y-m-d');
        $f = DateTime::createFromFormat('Y-m-d', $logDate);
        if (!$f || $f->format('Y-m-d') !== $logDate) respond(false, 'Invalid date.');
        if ($logDate > date('Y-m-d')) respond(false, 'Cannot log a habit for a future date.');

        $stmt = db()->prepare('SELECT id FROM habit_logs WHERE habit_id = ? AND user_id = ? AND log_date = ?');
        $stmt->execute([$id, $uid, $logDate]);
        $existing = $stmt->fetch();

        if ($existing) {
            db()->prepare('DELETE FROM habit_logs WHERE id = ? AND user_id = ?')->execute([(int) $existing['id'], $uid]);
            $message = 'Habit unchecked.';
        } else {
            db()->prepare('INSERT INTO habit_logs (habit_id, user_id, log_date, completed) VALUES (?,?,?,1)')
                ->execute([$id, $uid, $logDate]);
            $message = 'Habit completed! 🔥';
        }
        respond(true, $message, habit_payload($uid, $id));
        break;
    }
    default:
        respond(false, 'Unknown action.', [], 400);
}
