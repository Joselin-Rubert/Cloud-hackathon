<?php
/**
 * StudentFlow - GitHub Developer Activity & Profile Explorer service.
 *
 * Reads GitHub's public REST API over PHP cURL. No tokens, keys or OAuth —
 * only public endpoints. Primary data is cached in github_profiles /
 * github_repositories per user; the GitHub API is called only on explicit
 * search / sync / compare actions.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

final class GitHubService
{
    private const API_BASE        = 'https://api.github.com';
    private const TIMEOUT         = 18;
    private const CONNECT_TIMEOUT = 8;
    private const MAX_EVENTS      = 12;

    /** Fixed filter buckets shown in the UI. Anything else groups under "Other". */
    public const FILTER_LANGUAGES = ['Java', 'Python', 'JavaScript', 'PHP', 'C', 'C++', 'HTML', 'CSS'];

    private const EVENT_LABELS = [
        'PushEvent'                    => 'Pushed code changes',
        'CreateEvent'                  => 'Created a repository',
        'ForkEvent'                    => 'Forked a repository',
        'IssuesEvent'                  => 'Updated an issue',
        'PullRequestEvent'             => 'Worked on a pull request',
        'WatchEvent'                   => 'Starred a repository',
        'DeleteEvent'                  => 'Deleted a branch or tag',
        'IssueCommentEvent'            => 'Commented on an issue',
        'PullRequestReviewEvent'       => 'Reviewed a pull request',
        'PullRequestReviewCommentEvent' => 'Commented on a pull request review',
        'ReleaseEvent'                 => 'Published a release',
        'PublicEvent'                  => 'Made a repository public',
        'GollumEvent'                  => 'Updated wiki pages',
        'MemberEvent'                  => 'Added a collaborator',
        'TeamAddEvent'                 => 'Added to a team',
        'SponsorshipEvent'             => 'Sponsored a project',
    ];

    private const PALETTE = [
        '#6366f1', '#0ea5e9', '#f59e0b', '#10b981', '#ef4444',
        '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#84cc16',
    ];

    /* ---------------------------------------------------------------
     * Validation
     * ------------------------------------------------------------- */
    public static function isValidUsername($username): bool
    {
        return is_string($username)
            && strlen($username) >= 1
            && strlen($username) <= 39
            && preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?$/', $username) === 1;
    }

    /* ---------------------------------------------------------------
     * Core cURL fetch
     * ------------------------------------------------------------- */
    public static function fetch(string $path): array
    {
        if (!function_exists('curl_init')) {
            return ['error' => 'api', 'message' => 'cURL is not available on this server.'];
        }

        $ch = curl_init(self::API_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github+json', 'User-Agent: StudentFlow'],
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $errno = curl_errno($ch);
            curl_close($ch);
            if (in_array($errno, [CURLE_OPERATION_TIMEDOUT, CURLE_OPERATION_TIMEOUTED], true)) {
                return ['error' => 'timeout'];
            }
            return ['error' => 'network'];
        }

        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $redirect = (int) curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
        curl_close($ch);

        if ($code === 403 || $code === 429) {
            return ['error' => 'rate_limit', 'http' => $code];
        }
        if ($code === 404) {
            return ['error' => 'not_found', 'http' => $code];
        }
        if ($code < 200 || $code >= 300 || $redirect > 0) {
            return ['error' => 'api', 'http' => $code];
        }

        $json = json_decode($body, true);
        return is_array($json) ? ['data' => $json] : ['error' => 'api'];
    }

    public static function errorMessage(string $type, ?string $username = null): string
    {
        switch ($type) {
            case 'not_found':
                return $username !== null
                    ? 'GitHub user "' . $username . '" was not found. Check the spelling and try again.'
                    : 'That GitHub user was not found. Check the spelling and try again.';
            case 'rate_limit':
                return 'GitHub API rate limit reached. Please try again later.';
            case 'network':
                return 'Could not reach the GitHub API. Please check your internet connection and try again.';
            case 'timeout':
                return 'The GitHub API took too long to respond. Please try again in a moment.';
            default:
                return 'GitHub could not process this request right now. Please try again in a moment.';
        }
    }

    /* ---------------------------------------------------------------
     * Mapping data to friendly shapes
     * ------------------------------------------------------------- */
    private static function stringOrNull($value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        return mb_substr($s, 0, $max);
    }

    private static function toDatetime($value): ?string
    {
        if (!$value) {
            return null;
        }
        $ts = strtotime((string) $value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    public static function normalizeEvents(array $raw): array
    {
        $out = [];
        foreach (array_slice($raw, 0, self::MAX_EVENTS) as $e) {
            if (!is_array($e)) {
                continue;
            }
            $type = (string) ($e['type'] ?? '');
            if ($type === '') {
                continue;
            }
            $url = (string) ($e['repo']['url'] ?? '');
            if ($url !== '') {
                $url = str_replace('https://api.github.com/repos/', 'https://github.com/', $url);
            }
            $out[] = [
                'type'       => $type,
                'label'      => self::EVENT_LABELS[$type] ?? 'Made a GitHub activity',
                'repo'       => self::stringOrNull($e['repo']['name'] ?? null, 200),
                'created_at' => self::toDatetime($e['created_at'] ?? null) ?? date('Y-m-d H:i:s'),
                'url'        => $url,
            ];
        }
        return $out;
    }

    public static function computeLanguages(array $repos): array
    {
        $counts = [];
        foreach ($repos as $r) {
            $lang = $r['language'] ?? null;
            if ($lang !== null && $lang !== '') {
                $counts[$lang] = ($counts[$lang] ?? 0) + 1;
            }
        }
        arsort($counts);
        $total  = array_sum($counts);
        $labels = [];
        $data   = [];
        foreach ($counts as $lang => $count) {
            $labels[] = $lang;
            $data[]   = $total > 0 ? round($count / $total * 100, 1) : 0;
        }
        return ['labels' => $labels, 'data' => $data, 'total' => $total];
    }

    private static function topLanguage(array $repos): ?string
    {
        $langs = self::computeLanguages($repos);
        return $langs['labels'][0] ?? null;
    }

    private static function sumStars(array $repos): int
    {
        $sum = 0;
        foreach ($repos as $r) {
            $sum += (int) ($r['stars'] ?? 0);
        }
        return $sum;
    }

    private static function sumForks(array $repos): int
    {
        $sum = 0;
        foreach ($repos as $r) {
            $sum += (int) ($r['forks'] ?? 0);
        }
        return $sum;
    }

    /* ---------------------------------------------------------------
     * Live GitHub fetch (user + repos + public events)
     * ------------------------------------------------------------- */
    public static function fetchUserProfile(string $username): array
    {
        $result = ['ok' => false, 'user' => null, 'repos' => [], 'events' => [], 'issues' => []];

        $res = self::fetch('/users/' . rawurlencode($username));
        if (isset($res['error'])) {
            $result['error'] = $res['error'];
            return $result;
        }
        $user = $res['data'];

        $result['user'] = [
            'username'           => (string) ($user['login'] ?? $username),
            'github_profile_url' => (string) ($user['html_url'] ?? ('https://github.com/' . rawurlencode($username))),
            'avatar_url'         => self::stringOrNull($user['avatar_url'] ?? null, 255),
            'name'               => self::stringOrNull($user['name'] ?? null, 150),
            'bio'                => self::stringOrNull($user['bio'] ?? null, 600),
            'location'           => self::stringOrNull($user['location'] ?? null, 150),
            'company'            => self::stringOrNull($user['company'] ?? null, 150),
            'public_repos'       => (int) ($user['public_repos'] ?? 0),
            'followers'          => (int) ($user['followers'] ?? 0),
            'following'          => (int) ($user['following'] ?? 0),
        ];

        $repos = self::fetch('/users/' . rawurlencode($username) . '/repos?per_page=100&sort=pushed&type=owner');
        if (isset($repos['data']) && is_array($repos['data'])) {
            foreach ($repos['data'] as $r) {
                if (!is_array($r) || !empty($r['fork'])) {
                    continue;
                }
                $result['repos'][] = [
                    'github_repo_id' => (int) ($r['id'] ?? 0),
                    'repo_name'      => self::stringOrNull($r['name'] ?? 'repository', 200) ?? 'repository',
                    'description'    => self::stringOrNull($r['description'] ?? null, 600),
                    'language'       => self::stringOrNull($r['language'] ?? null, 80),
                    'stars'          => (int) ($r['stargazers_count'] ?? 0),
                    'forks'          => (int) ($r['forks_count'] ?? 0),
                    'open_issues'    => (int) ($r['open_issues_count'] ?? 0),
                    'html_url'       => (string) ($r['html_url'] ?? ('https://github.com/' . $username . '/' . rawurlencode((string) ($r['name'] ?? '')))),
                    'repo_updated_at' => self::toDatetime($r['pushed_at'] ?? null),
                ];
            }
        } elseif (isset($repos['error'])) {
            $result['issues'][] = 'Repositories could not be loaded: ' . self::errorMessage($repos['error'], $username);
        } else {
            $result['issues'][] = 'Repositories could not be loaded for this profile.';
        }

        $events = self::fetch('/users/' . rawurlencode($username) . '/events/public?per_page=100');
        if (isset($events['data']) && is_array($events['data'])) {
            $result['events'] = self::normalizeEvents($events['data']);
        } elseif (isset($events['error'])) {
            $result['issues'][] = 'Recent public activity could not be loaded: ' . self::errorMessage($events['error'], $username);
        } else {
            $result['issues'][] = 'Recent public activity could not be loaded for this profile.';
        }

        $result['ok'] = true;
        return $result;
    }

    /* ---------------------------------------------------------------
     * Persistence (per-user; additive tables only)
     * ------------------------------------------------------------- */
    public static function saveSnapshot(int $uid, array $user, array $repos, array $events): void
    {
        $now    = date('Y-m-d H:i:s');
        $langs  = self::computeLanguages($repos);
        $top    = $langs['labels'][0] ?? null;

        $stmt = db()->prepare(
            'INSERT INTO github_profiles
                (user_id, github_username, github_profile_url, avatar_url, name, bio, location, company,
                 public_repos, followers, following, top_language, recent_activity, recent_events,
                 recent_activity_count, last_synced)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 github_username   = VALUES(github_username),
                 github_profile_url= VALUES(github_profile_url),
                 avatar_url        = VALUES(avatar_url),
                 name              = VALUES(name),
                 bio               = VALUES(bio),
                 location          = VALUES(location),
                 company           = VALUES(company),
                 public_repos      = VALUES(public_repos),
                 followers         = VALUES(followers),
                 following         = VALUES(following),
                 top_language      = VALUES(top_language),
                 recent_activity   = VALUES(recent_activity),
                 recent_events     = VALUES(recent_events),
                 recent_activity_count = VALUES(recent_activity_count),
                 last_synced       = VALUES(last_synced)'
        );

        $stmt->execute([
            $uid,
            mb_substr($user['username'], 0, 39),
            mb_substr($user['github_profile_url'], 0, 255),
            $user['avatar_url'],
            $user['name'],
            $user['bio'],
            $user['location'],
            $user['company'],
            $user['public_repos'],
            $user['followers'],
            $user['following'],
            $top,
            $events[0]['label'] ?? null,
            json_encode($events, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            count($events),
            $now,
        ]);

        $del = db()->prepare('DELETE FROM github_repositories WHERE user_id = ?');
        $del->execute([$uid]);

        $ins = db()->prepare(
            'INSERT INTO github_repositories
                (user_id, github_repo_id, repo_name, description, language, stars, forks,
                 open_issues, html_url, repo_updated_at, synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($repos as $r) {
            $ins->execute([
                $uid,
                $r['github_repo_id'],
                mb_substr($r['repo_name'], 0, 200),
                $r['description'],
                $r['language'],
                $r['stars'],
                $r['forks'],
                $r['open_issues'],
                mb_substr($r['html_url'], 0, 255),
                $r['repo_updated_at'],
                $now,
            ]);
        }
    }

    public static function loadPayload(int $uid): ?array
    {
        $stmt = db()->prepare('SELECT * FROM github_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$uid]);
        $p = $stmt->fetch();
        if (!$p) {
            return null;
        }

        $stmt = db()->prepare(
            'SELECT * FROM github_repositories WHERE user_id = ?
             ORDER BY COALESCE(repo_updated_at, synced_at) DESC, id DESC'
        );
        $stmt->execute([$uid]);
        $repos = $stmt->fetchAll();

        $events = [];
        if (!empty($p['recent_events'])) {
            $decoded = json_decode($p['recent_events'], true);
            $events  = is_array($decoded) ? $decoded : [];
        }

        return [
            'profile' => [
                'username'             => $p['github_username'],
                'github_profile_url'   => $p['github_profile_url'],
                'avatar_url'           => $p['avatar_url'],
                'name'                 => $p['name'],
                'bio'                  => $p['bio'],
                'location'             => $p['location'],
                'company'              => $p['company'],
                'public_repos'         => (int) $p['public_repos'],
                'followers'            => (int) $p['followers'],
                'following'            => (int) $p['following'],
                'top_language'         => $p['top_language'],
                'recent_activity'      => $p['recent_activity'],
                'recent_activity_count' => (int) $p['recent_activity_count'],
                'last_synced'          => $p['last_synced'],
            ],
            'repositories' => array_map(static function (array $r): array {
                return [
                    'github_repo_id' => (int) $r['github_repo_id'],
                    'repo_name'      => $r['repo_name'],
                    'description'    => $r['description'],
                    'language'       => $r['language'],
                    'stars'          => (int) $r['stars'],
                    'forks'          => (int) $r['forks'],
                    'open_issues'    => (int) $r['open_issues'],
                    'html_url'       => $r['html_url'],
                    'repo_updated_at' => $r['repo_updated_at'],
                ];
            }, $repos),
            'recent_events' => $events,
            'stats' => [
                'total_stars' => self::sumStars($repos),
                'total_forks' => self::sumForks($repos),
            ],
            'languages' => self::computeLanguages($repos),
        ];
    }

    /* ---------------------------------------------------------------
     * High-level actions used by api/github.php
     * ------------------------------------------------------------- */
    public static function syncUser(int $uid, string $username): array
    {
        if (!self::isValidUsername($username)) {
            return ['ok' => false, 'error' => 'invalid', 'message' => 'That does not look like a valid GitHub username.'];
        }

        $pf = self::fetchUserProfile($username);
        if (empty($pf['ok'])) {
            return ['ok' => false, 'error' => $pf['error'] ?? 'api', 'message' => self::errorMessage($pf['error'] ?? 'api', $username)];
        }

        self::saveSnapshot($uid, $pf['user'], $pf['repos'], $pf['events']);

        $payload             = self::loadPayload($uid);
        $payload['issues']   = $pf['issues'];

        return ['ok' => true, 'message' => 'GitHub profile loaded.', 'data' => $payload];
    }

    public static function compareUsers(string $a, string $b): array
    {
        $build = static function (string $username): array {
            $row = ['username' => $username];
            if (!self::isValidUsername($username)) {
                $row['error'] = 'Invalid GitHub username.';
                return $row;
            }
            $pf = self::fetchUserProfile($username);
            if (empty($pf['ok'])) {
                $row['error'] = self::errorMessage($pf['error'] ?? 'api', $username);
                return $row;
            }
            $success = $pf['user'];
            $langs   = self::computeLanguages($pf['repos']);
            return [
                'username'              => $success['username'],
                'name'                  => $success['name'],
                'public_repos'          => $success['public_repos'],
                'followers'             => $success['followers'],
                'following'             => $success['following'],
                'total_stars'           => self::sumStars($pf['repos']),
                'total_forks'           => self::sumForks($pf['repos']),
                'top_language'          => $langs['labels'][0] ?? null,
                'recent_activity'       => $pf['events'][0]['label'] ?? null,
                'recent_activity_count' => count($pf['events']),
            ];
        };

        return ['users' => [$build($a), $build($b)]];
    }

    /** Compact profile object re-used by the dashboard snapshot. */
    public static function dashboardSnapshot(int $uid): ?array
    {
        return self::loadPayload($uid);
    }
}