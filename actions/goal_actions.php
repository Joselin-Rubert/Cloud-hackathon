<?php
require_once __DIR__ . '/../config/auth.php';
require_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Method not allowed.', [], 405);
verify_csrf();

$uid    = user_id();
$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);

function fetch_goal(int $uid, int $id): array
{
    $stmt = db()->prepare('SELECT * FROM goals WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $uid]);
    $row = $stmt->fetch();
    if (!$row) respond(false, 'Goal not found.', [], 404);
    return $row;
}

function goal_json(array $g): array
{
    $status = goal_status($g);
    $days = $g['target_date'] ? date_diff_days($g['target_date']) : null;
    return [
        'id'          => (int) $g['id'],
        'title'       => $g['title'],
        'description' => $g['description'] ?? '',
        'github_repo_url' => $g['github_repo_url'] ?? null,
        'category'    => $g['category'],
        'target_date' => $g['target_date'],
        'progress'    => (int) $g['progress'],
        'status'      => $status,
        'days_left'   => $days,
        'updated_at'  => $g['updated_at'] ?? null,
    ];
}

function store_status(string $status): string
{
    return in_array($status, ['On Track', 'At Risk', 'Completed'], true) ? $status : 'On Track';
}

switch ($action) {
    case 'create':
    case 'update': {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') respond(false, 'Goal title is required.');
        if (mb_strlen($title) > 200) respond(false, 'Title must be 200 characters or fewer.');

        $category = trim($_POST['category'] ?? 'Personal');
        if ($category === '') $category = 'Personal';
        if (mb_strlen($category) > 50) $category = mb_substr($category, 0, 50);

        $target = $_POST['target_date'] ?? '';
        if ($target !== '') {
            $f = DateTime::createFromFormat('Y-m-d', $target);
            if (!$f || $f->format('Y-m-d') !== $target) respond(false, 'Invalid target date.');
        } else {
            $target = null;
        }

        $progress = (int) ($_POST['progress'] ?? 0);
        if ($progress < 0) $progress = 0;
        if ($progress > 100) $progress = 100;

        $description = trim($_POST['description'] ?? '');

        $repoUrl = trim($_POST['github_repo_url'] ?? '');
        if ($repoUrl !== '' && mb_strlen($repoUrl) > 255) {
            respond(false, 'GitHub repository link must be 255 characters or fewer.');
        }
        if ($repoUrl !== '' && preg_match('/\s/', $repoUrl)) {
            respond(false, 'GitHub repository link cannot contain spaces.');
        }
        $repoUrl = $repoUrl !== '' ? $repoUrl : null;

        $candidate = ['progress' => $progress, 'target_date' => $target];
        $status = store_status(goal_status($candidate));

        if ($action === 'create') {
            $stmt = db()->prepare(
                'INSERT INTO goals (user_id, title, description, github_repo_url, category, target_date, progress, status) VALUES (?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([$uid, $title, $description, $repoUrl, $category, $target, $progress, $status]);
            $newId = (int) db()->lastInsertId();
            sync_notifications($uid);
            respond(true, 'Goal created.', ['goal' => goal_json(fetch_goal($uid, $newId))]);
        }

        fetch_goal($uid, $id);
        $stmt = db()->prepare(
            'UPDATE goals SET title=?, description=?, github_repo_url=?, category=?, target_date=?, progress=?, status=? WHERE id=? AND user_id=?'
        );
        $stmt->execute([$title, $description, $repoUrl, $category, $target, $progress, $status, $id, $uid]);
        sync_notifications($uid);
        respond(true, 'Goal updated.', ['goal' => goal_json(fetch_goal($uid, $id))]);
        break;
    }

    case 'progress': {
        $goal = fetch_goal($uid, $id);
        $progress = (int) ($_POST['progress'] ?? 0);
        if ($progress < 0) $progress = 0;
        if ($progress > 100) $progress = 100;
        $status = store_status(goal_status(['progress' => $progress, 'target_date' => $goal['target_date']]));
        db()->prepare('UPDATE goals SET progress=?, status=? WHERE id=? AND user_id=?')
            ->execute([$progress, $status, $id, $uid]);
        respond(true, $progress >= 100 ? 'Goal completed! 🎉' : 'Progress updated.', ['goal' => goal_json(fetch_goal($uid, $id))]);
        break;
    }

    case 'delete': {
        fetch_goal($uid, $id);
        db()->prepare('DELETE FROM goals WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        respond(true, 'Goal deleted.');
        break;
    }

    default:
        respond(false, 'Unknown action.', [], 400);
}
