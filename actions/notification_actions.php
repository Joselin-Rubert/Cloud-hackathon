<?php
require_once __DIR__ . '/../config/auth.php';
require_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Method not allowed.', [], 405);
verify_csrf();

$uid    = user_id();
$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);

switch ($action) {
    case 'read': {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $uid]);
        if ($stmt->rowCount() === 0) {
            // already read or not yours - verify ownership without changing data
            $chk = db()->prepare('SELECT id FROM notifications WHERE id = ? AND user_id = ?');
            $chk->execute([$id, $uid]);
            if (!$chk->fetch()) respond(false, 'Notification not found.', [], 404);
        }
        respond(true, 'Marked as read.', ['unread' => unread_notification_count($uid)]);
        break;
    }
    case 'read_all': {
        db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
        respond(true, 'All notifications marked as read.', ['unread' => 0]);
        break;
    }
    case 'delete': {
        $stmt = db()->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $uid]);
        if ($stmt->rowCount() === 0) {
            $chk = db()->prepare('SELECT id FROM notifications WHERE id = ? AND user_id = ?');
            $chk->execute([$id, $uid]);
            if (!$chk->fetch()) respond(false, 'Notification not found.', [], 404);
        }
        respond(true, 'Notification deleted.', ['unread' => unread_notification_count($uid)]);
        break;
    }
    case 'delete_all': {
        db()->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1')->execute([$uid]);
        respond(true, 'Cleared read notifications.', ['unread' => unread_notification_count($uid)]);
        break;
    }
    default:
        respond(false, 'Unknown action.', [], 400);
}
