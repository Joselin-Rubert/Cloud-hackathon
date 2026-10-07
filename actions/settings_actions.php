<?php
require_once __DIR__ . '/../config/auth.php';
require_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Method not allowed.', [], 405);
verify_csrf();

$uid = user_id();
$current = get_settings($uid); // ensure row exists

// Partial updates: only keys present in the request are changed,
// so theme toggling from the navbar cannot wipe other preferences.
$cols = [];
$vals = [];

if (array_key_exists('dark_mode', $_POST)) {
    $cols[] = 'dark_mode';
    $vals[] = ((int) $_POST['dark_mode'] === 1) ? 1 : 0;
}

if (array_key_exists('daily_study_target', $_POST)) {
    $target = (int) $_POST['daily_study_target'];
    if ($target < 15) $target = 15;
    if ($target > 960) $target = 960;
    $cols[] = 'daily_study_target';
    $vals[] = $target;
}

if (array_key_exists('monthly_budget', $_POST)) {
    $budget = $_POST['monthly_budget'];
    if (!is_numeric($budget)) respond(false, 'Monthly budget must be a number.');
    $budget = round((float) $budget, 2);
    if ($budget < 0) respond(false, 'Monthly budget cannot be negative.');
    if ($budget > 10000000) respond(false, 'Monthly budget is too large.');
    $cols[] = 'monthly_budget';
    $vals[] = $budget;
}

foreach (['email_notifications', 'task_reminders', 'habit_reminders'] as $flag) {
    if (array_key_exists($flag, $_POST)) {
        $cols[] = $flag;
        $vals[] = ((int) $_POST[$flag] === 1) ? 1 : 0;
    }
}

if ($cols) {
    $sql = 'UPDATE user_settings SET ' . implode(', ', array_map(static function (string $c): string {
        return $c . '=?';
    }, $cols)) . ' WHERE user_id=?';
    db()->prepare($sql)->execute(array_merge($vals, [$uid]));
    sync_notifications($uid);
}

respond(true, 'Settings saved.', ['settings' => get_settings($uid)]);
