<?php
/**
 * Application shell (top).
 * Every authenticated page does:
 *   $page_title = '...';
 *   require_once __DIR__ . '/includes/auth_check.php';   (optional, header does it too)
 *   require __DIR__ . '/includes/header.php';
 *   ... page content ...
 *   require __DIR__ . '/includes/footer.php';
 *
 * Optional variables: $page_scripts = ['dashboard.js'];  $page_data = [...];
 */

require_once __DIR__ . '/../config/auth.php';
require_login();

$user = current_user();
$settings = get_settings($user['id']);
$unread = unread_notification_count($user['id']);
$dark = (bool) ($settings['dark_mode'] ?? false);
$page_title = $page_title ?? 'Dashboard';

require __DIR__ . '/head.php';
?>
<div id="app" class="flex min-h-screen">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="flex-1 min-w-0 flex flex-col lg:pl-64">
<?php require __DIR__ . '/navbar.php'; ?>
<main class="flex-1 w-full max-w-[1500px] mx-auto px-4 sm:px-6 lg:px-8 py-6">
