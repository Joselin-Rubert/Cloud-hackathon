<?php
require_once __DIR__ . '/../config/auth.php';
require_api_auth();

$uid = user_id();

$unread = unread_notification_count($uid);
$stmt = db()->prepare(
    'SELECT id, title, message, type, is_read, created_at
     FROM notifications WHERE user_id = ?
     ORDER BY id DESC LIMIT 30'
);
$stmt->execute([$uid]);
$rows = $stmt->fetchAll();

// Time-ago text without needing the full page context
foreach ($rows as &$r) {
    $r['id'] = (int) $r['id'];
    $r['is_read'] = (int) $r['is_read'];
    $r['time_ago'] = time_ago($r['created_at']);
}
unset($r);

respond(true, '', ['unread' => $unread, 'notifications' => $rows]);
