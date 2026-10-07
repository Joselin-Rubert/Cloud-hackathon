<?php
require_once __DIR__ . '/../config/auth.php';
require_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Method not allowed.', [], 405);
verify_csrf();

$uid    = user_id();
$action = $_POST['action'] ?? '';

function valid_date_or_null(?string $d): ?string
{
    if ($d === null || $d === '') return null;
    $f = DateTime::createFromFormat('Y-m-d', $d);
    return ($f && $f->format('Y-m-d') === $d) ? $d : false;
}

function planner_sessions(int $uid): array
{
    $stmt = db()->prepare(
        'SELECT ss.*, s.name AS subject_name, s.difficulty, s.exam_date
         FROM study_sessions ss
         LEFT JOIN subjects s ON s.id = ss.subject_id AND s.user_id = ss.user_id
         WHERE ss.user_id = ?
         ORDER BY ss.session_date ASC, ss.id ASC'
    );
    $stmt->execute([$uid]);
    return $stmt->fetchAll();
}

switch ($action) {
    case 'generate': {
        $subject = trim($_POST['subject'] ?? '');
        $topic   = trim($_POST['topic'] ?? '');
        if ($subject === '') respond(false, 'Subject is required.');
        if ($topic === '') respond(false, 'Topic is required.');

        $difficulty = $_POST['difficulty'] ?? 'Medium';
        if (!in_array($difficulty, ['Easy', 'Medium', 'Hard'], true)) $difficulty = 'Medium';

        $exam = valid_date_or_null($_POST['exam_date'] ?? null);
        if ($exam === false) respond(false, 'Invalid exam date.');

        $total = (int) ($_POST['total_minutes'] ?? 120);
        if ($total < 30) $total = 30;
        if ($total > 1440) $total = 1440;

        $studyTime = $_POST['study_time'] ?? '18:00';
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $studyTime)) $studyTime = '18:00';

        $plan = generate_study_plan([
            'topic'         => $topic,
            'difficulty'    => $difficulty,
            'total_minutes' => $total,
            'exam_date'     => $exam,
            'study_time'    => $studyTime,
        ]);

        respond(true, 'Study plan generated.', [
            'plan' => $plan,
            'subject' => $subject,
            'topic' => $topic,
            'difficulty' => $difficulty,
            'exam_date' => $exam,
            'total_minutes' => $total,
        ]);
        break;
    }

    case 'save': {
        $subject = trim($_POST['subject'] ?? '');
        $topic   = trim($_POST['topic'] ?? '');
        if ($subject === '' || $topic === '') respond(false, 'Subject and topic are required.');

        $difficulty = $_POST['difficulty'] ?? 'Medium';
        if (!in_array($difficulty, ['Easy', 'Medium', 'Hard'], true)) $difficulty = 'Medium';
        $exam = valid_date_or_null($_POST['exam_date'] ?? null);
        if ($exam === false) respond(false, 'Invalid exam date.');

        $sessions = json_decode($_POST['sessions'] ?? '[]', true);
        if (!is_array($sessions) || !$sessions) respond(false, 'No plan sessions to save.');

        // Find or create the subject
        $stmt = db()->prepare('SELECT id FROM subjects WHERE user_id = ? AND name = ?');
        $stmt->execute([$uid, $subject]);
        $subjectRow = $stmt->fetch();
        if ($subjectRow) {
            $subjectId = (int) $subjectRow['id'];
            db()->prepare('UPDATE subjects SET difficulty = ?, exam_date = COALESCE(?, exam_date) WHERE id = ? AND user_id = ?')
                ->execute([$difficulty, $exam, $subjectId, $uid]);
        } else {
            db()->prepare('INSERT INTO subjects (user_id, name, difficulty, exam_date) VALUES (?,?,?,?)')
                ->execute([$uid, $subject, $difficulty, $exam]);
            $subjectId = (int) db()->lastInsertId();
        }

        $ins = db()->prepare('INSERT INTO study_sessions (user_id, subject_id, topic, duration_minutes, session_date, notes) VALUES (?,?,?,?,?,?)');
        $saved = 0;
        foreach ($sessions as $s) {
            $date = valid_date_or_null($s['date'] ?? null);
            $time = $s['time'] ?? '18:00';
            if ($date === false || $date === null) continue;
            $dur = max(10, min(480, (int) ($s['duration'] ?? 30)));
            $sessTopic = mb_substr(trim($s['topic'] ?? $topic), 0, 200);
            $notes = isset($s['time']) ? 'Preferred time: ' . $time : null;
            $ins->execute([$uid, $subjectId, $sessTopic, $dur, $date, $notes]);
            $saved++;
        }
        if ($saved === 0) respond(false, 'Nothing was saved. Please regenerate the plan.');

        respond(true, 'Study plan saved (' . $saved . ' sessions).', ['sessions' => planner_sessions($uid)]);
        break;
    }

    case 'session_update': {
        $sid = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM study_sessions WHERE id = ? AND user_id = ?');
        $stmt->execute([$sid, $uid]);
        if (!$stmt->fetch()) respond(false, 'Session not found.', [], 404);

        $topic = trim($_POST['topic'] ?? '');
        if ($topic === '') respond(false, 'Topic is required.');
        $dur = (int) ($_POST['duration_minutes'] ?? 30);
        if ($dur < 10) $dur = 10;
        if ($dur > 480) $dur = 480;
        $date = valid_date_or_null($_POST['session_date'] ?? null);
        if ($date === false || $date === null) respond(false, 'Invalid session date.');
        $notes = trim($_POST['notes'] ?? '');

        db()->prepare('UPDATE study_sessions SET topic=?, duration_minutes=?, session_date=?, notes=? WHERE id=? AND user_id=?')
            ->execute([$topic, $dur, $date, $notes, $sid, $uid]);
        respond(true, 'Session updated.', ['sessions' => planner_sessions($uid)]);
        break;
    }

    case 'session_delete': {
        $sid = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT id FROM study_sessions WHERE id = ? AND user_id = ?');
        $stmt->execute([$sid, $uid]);
        if (!$stmt->fetch()) respond(false, 'Session not found.', [], 404);
        db()->prepare('DELETE FROM study_sessions WHERE id = ? AND user_id = ?')->execute([$sid, $uid]);
        respond(true, 'Session deleted.', ['sessions' => planner_sessions($uid)]);
        break;
    }

    case 'subject_delete': {
        $sid = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT id FROM subjects WHERE id = ? AND user_id = ?');
        $stmt->execute([$sid, $uid]);
        if (!$stmt->fetch()) respond(false, 'Subject not found.', [], 404);
        db()->prepare('DELETE FROM subjects WHERE id = ? AND user_id = ?')->execute([$sid, $uid]);
        respond(true, 'Subject deleted.', ['sessions' => planner_sessions($uid)]);
        break;
    }

    default:
        respond(false, 'Unknown action.', [], 400);
}
