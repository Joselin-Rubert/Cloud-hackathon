<?php
require_once __DIR__ . '/../config/auth.php';
require_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Method not allowed.', [], 405);
verify_csrf();

$uid    = user_id();
$action = $_POST['action'] ?? 'profile';

switch ($action) {
    case 'profile': {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') respond(false, 'Name is required.');
        if (mb_strlen($name) > 100) respond(false, 'Name must be 100 characters or fewer.');

        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(false, 'Please enter a valid email address.');
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $stmt->execute([$email, $uid]);
        if ($stmt->fetch()) respond(false, 'That email is already in use.');

        $college    = mb_substr(trim($_POST['college'] ?? ''), 0, 150);
        $department = mb_substr(trim($_POST['department'] ?? ''), 0, 100);
        $year       = mb_substr(trim($_POST['year'] ?? ''), 0, 30);

        $profileImage = null;
        if (!empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!isset($allowed[$mime])) respond(false, 'Image must be JPG, PNG or WebP.');
            if ($file['size'] > 2 * 1024 * 1024) respond(false, 'Image must be smaller than 2 MB.');

            $dir = __DIR__ . '/../assets/images/uploads';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $filename = 'user_' . $uid . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
                respond(false, 'Could not save the uploaded image.');
            }
            // remove old image
            $old = db()->prepare('SELECT profile_image FROM users WHERE id = ?');
            $old->execute([$uid]);
            $prev = (string) $old->fetchColumn();
            if ($prev && is_file($dir . '/' . $prev)) @unlink($dir . '/' . $prev);
            $profileImage = $filename;
        }

        if ($profileImage) {
            $stmt = db()->prepare(
                'UPDATE users SET name=?, email=?, college=?, department=?, year=?, profile_image=? WHERE id=?'
            );
            $stmt->execute([$name, $email, $college, $department, $year, $profileImage, $uid]);
        } else {
            $stmt = db()->prepare(
                'UPDATE users SET name=?, email=?, college=?, department=?, year=? WHERE id=?'
            );
            $stmt->execute([$name, $email, $college, $department, $year, $uid]);
        }
        respond(true, 'Profile updated.', ['user' => [
            'name'          => $name,
            'email'         => $email,
            'college'       => $college,
            'department'    => $department,
            'year'          => $year,
            'profile_image' => $profileImage,
        ]]);
        break;
    }

    case 'password': {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = db()->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $hash = (string) $stmt->fetchColumn();
        if (!password_verify($current, $hash)) respond(false, 'Current password is incorrect.');
        if (strlen($new) < 8) respond(false, 'New password must be at least 8 characters.');
        if ($new !== $confirm) respond(false, 'Passwords do not match.');

        db()->prepare('UPDATE users SET password = ?, remember_token = NULL WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
        respond(true, 'Password changed successfully.');
        break;
    }

    default:
        respond(false, 'Unknown action.', [], 400);
}
