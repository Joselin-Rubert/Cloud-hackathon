<?php
/**
 * StudentFlow - GitHub Explorer API
 * Actions: search (save profile for current user), sync (refresh saved data),
 *          compare (two public usernames, read-only).
 * All GitHub calls happen here in PHP via cURL. No tokens ever used.
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../services/GitHubService.php';

require_api_auth();

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    /* Search & connect a public GitHub username for the logged-in user. */
    case 'search':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(false, 'Method not allowed.', [], 405);
        }
        verify_csrf();
        $username = trim((string) ($_POST['username'] ?? ''));
        $res = GitHubService::syncUser(user_id(), $username);
        $code = $res['ok'] ? 200 : ($res['error'] === 'not_found' ? 404 : ($res['error'] === 'rate_limit' ? 429 : 400));
        respond($res['ok'], $res['message'], $res['data'] ?? [], $code);
        break;

    /* Re-fetch the already-connected username and refresh stored data. */
    case 'sync':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            respond(false, 'Method not allowed.', [], 405);
        }
        verify_csrf();
        $uid     = user_id();
        $payload = GitHubService::loadPayload($uid);
        if (!$payload) {
            respond(false, 'No GitHub username connected yet. Search a public GitHub username first.', [], 400);
        }
        $res = GitHubService::syncUser($uid, $payload['profile']['username']);
        $code = $res['ok'] ? 200 : ($res['error'] === 'rate_limit' ? 429 : 400);
        respond($res['ok'], $res['ok'] ? 'GitHub data synced successfully.' : $res['message'], $res['data'] ?? [], $code);
        break;

    /* Compare two public usernames (no writes). */
    case 'compare':
        $a = trim((string) ($_POST['user1'] ?? ($_GET['user1'] ?? '')));
        $b = trim((string) ($_POST['user2'] ?? ($_GET['user2'] ?? '')));
        if ($a === '' || $b === '') {
            respond(false, 'Enter two GitHub usernames to compare.', [], 400);
        }
        respond(true, 'Comparison ready.', GitHubService::compareUsers($a, $b));
        break;

    default:
        respond(false, 'Unknown GitHub action.', [], 400);
}