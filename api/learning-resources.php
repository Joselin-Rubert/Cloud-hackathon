<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../services/BookService.php';
require_api_auth();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    respond(false, 'Invalid request method.', [], 405);
}

$action = (string) ($_GET['action'] ?? 'search');
if ($action !== 'search') {
    respond(false, 'Invalid action.', [], 400);
}

$result = BookService::search(user_id(), (string) ($_GET['q'] ?? ''));

if (!$result['ok']) {
    respond(false, $result['message'], [], $result['code'] ?? 400);
}

$count = count($result['resources']);
respond(true, $count > 0 ? 'Found ' . $count . ' resource' . ($count === 1 ? '' : 's') . '.'
                            : 'No matching resources found. Try a different topic.', [
    'query'     => $result['query'],
    'source'    => $result['source'],
    'total'     => $count,
    'resources' => $result['resources'],
]);