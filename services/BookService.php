<?php
/**
 * StudentFlow - Learning Resource Finder (Google Books API).
 *
 * Searches the public Google Books API over PHP cURL. No API key is required
 * for the public endpoint; if a server-side SF_GOOGLE_BOOKS_KEY env var is
 * present it is appended for higher quotas. Results are cached per user in
 * learning_resource_cache for up to 24 hours (the key never leaves the server).
 */

require_once __DIR__ . '/../config/database.php';

final class BookService
{
    private const API_BASE    = 'https://www.googleapis.com/books/v1/volumes';
    private const CACHE_TTL_H = 24;
    private const CACHE_MAX   = 60;
    private const TIMEOUT     = 15;
    private const CONNECT_TMO = 8;
    private const MAX_RESULTS = 18;

    /**
     * Search for learning resources matching a query.
     *
     * @return array{ok:bool, message:string, code?:int, query?:string, source?:string, resources?:array}
     */
    public static function search(int $uid, string $query): array
    {
        $query = trim($query);
        $len = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);

        if ($query === '' || $len < 2) {
            return ['ok' => false, 'code' => 400, 'message' => 'Enter at least 2 characters to search.'];
        }
        if ($len > 120) {
            return ['ok' => false, 'code' => 400, 'message' => 'Search query is too long (max 120 characters).'];
        }

        // Fresh cache hit → no external call.
        $cached = self::readCache($uid, $query);
        if ($cached !== null) {
            return [
                'ok'        => true,
                'query'     => $query,
                'source'    => 'cache',
                'resources' => $cached,
            ];
        }

        $payload = self::httpGetJson(self::buildUrl($query));
        $provider = 'Google Books';

        if ($payload['error'] === null) {
            $resources = self::mapItems($payload['body']['items'] ?? [], 'Google Books');
        } else {
            // Rate-limited on a keyless shared IP? Fall back to the keyless
            // Open Library search so the feature still returns results.
            $fb = self::httpGetJson(self::openLibraryUrl($query));
            if ($fb['error'] !== null) {
                return ['ok' => false, 'code' => $fb['status'], 'message' => $fb['error']];
            }
            $provider  = 'Open Library';
            $payload   = $fb;
            $resources = self::mapOpenLibrary($payload['body']['docs'] ?? [], $provider);
        }

        self::storeCache($uid, $query, $resources);

        return [
            'ok'        => true,
            'query'     => $query,
            'source'    => $provider,
            'resources' => $resources,
        ];
    }

    /* ---------------------------------------------------------------
     * API plumbing
     * ------------------------------------------------------------- */
    private static function buildUrl(string $query): string
    {
        $url = self::API_BASE . '?q=' . rawurlencode($query)
            . '&maxResults=' . self::MAX_RESULTS
            . '&printType=books';

        $key = getenv('SF_GOOGLE_BOOKS_KEY');
        if (!$key) $key = getenv('GOOGLE_BOOKS_API_KEY');
        if (is_string($key) && $key !== '') {
            $url .= '&key=' . rawurlencode($key);
        }
        return $url;
    }

    /** Keyless fallback provider (generous quota). */
    private static function openLibraryUrl(string $query): string
    {
        return 'https://openlibrary.org/search.json?q=' . rawurlencode($query)
            . '&limit=' . self::MAX_RESULTS
            . '&fields=title,author_name,first_publish_year,publisher,cover_i,key,number_of_pages_median';
    }

    /**
     * @return array{error:?string, status:int, body:?array}
     */
    private static function httpGetJson(string $url): array
    {
        if (!function_exists('curl_init')) {
            return ['error' => 'The learning resource service is unavailable on this server (cURL missing).', 'status' => 500, 'body' => null];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TMO,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'User-Agent: StudentFlow/1.0',
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false) {
            $errno = curl_errno($ch);
            curl_close($ch);
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                return ['error' => 'The search took too long to respond. Please try again.', 'status' => 504, 'body' => null];
            }
            return ['error' => 'Could not reach the resource service. Check your internet connection.', 'status' => 502, 'body' => null];
        }
        curl_close($ch);

        if ($status === 429 || $status === 403) {
            return ['error' => 'The resource service is rate limiting requests. Wait a minute and try again.', 'status' => 429, 'body' => null];
        }
        if ($status >= 500) {
            return ['error' => 'The resource service is temporarily unavailable. Please try again later.', 'status' => 502, 'body' => null];
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return ['error' => 'The resource service returned an unexpected response.', 'status' => 502, 'body' => null];
        }
        return ['error' => null, 'status' => $status, 'body' => $json];
    }

    /**
     * Normalize Google Books volume items into compact resource cards.
     */
    private static function mapItems(array $items, string $provider): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $vi = $item['volumeInfo'] ?? [];
            if (!is_array($vi)) $vi = [];

            $title = trim((string) ($vi['title'] ?? ''));
            if ($title === '') continue;

            $authors = array_filter((array) ($vi['authors'] ?? []));
            $author = $authors
                ? implode(', ', array_slice(array_values($authors), 0, 3))
                : 'Unknown author';

            $year = '';
            if (!empty($vi['publishedDate'])) {
                if (preg_match('/^\d{4}/', (string) $vi['publishedDate'], $m)) {
                    $year = $m[0];
                }
            }

            $desc = trim((string) ($vi['description'] ?? ''));
            if (function_exists('mb_strlen') && mb_strlen($desc) > 220) {
                $desc = mb_substr($desc, 0, 219) . '…';
            } elseif (strlen($desc) > 220) {
                $desc = substr($desc, 0, 219) . '…';
            }

            $images = $vi['imageLinks'] ?? [];
            $cover = is_array($images)
                ? (string) ($images['thumbnail'] ?? $images['smallThumbnail'] ?? '')
                : '';
            if ($cover !== '' && strpos($cover, 'http') === 0) {
                $cover = preg_replace('/^http:/i', 'https:', $cover) ?? $cover;
            }

            $preview = (string) ($vi['previewLink'] ?? '');
            $info    = (string) ($vi['infoLink'] ?? '');
            $link    = $info !== '' ? $info : $preview;

            $out[] = [
                'id'         => (string) ($item['id'] ?? ''),
                'title'      => $title,
                'authors'    => array_values($authors),
                'author'     => $author,
                'year'       => $year,
                'publisher'  => trim((string) ($vi['publisher'] ?? '')),
                'description'=> $desc,
                'pages'      => isset($vi['pageCount']) ? (int) $vi['pageCount'] : null,
                'language'   => (string) ($vi['language'] ?? ''),
                'categories' => array_slice(array_values((array) ($vi['categories'] ?? [])), 0, 3),
                'cover'      => $cover,
                'preview'    => $preview,
                'link'       => $link,
                'type'       => 'Book',
                'provider'   => $provider,
            ];
        }
        return $out;
    }

    /**
     * Normalize Open Library search docs into the same resource-card shape.
     */
    private static function mapOpenLibrary(array $docs, string $provider): array
    {
        $out = [];
        foreach ($docs as $d) {
            if (!is_array($d)) continue;

            $title = trim((string) ($d['title'] ?? ''));
            if ($title === '') continue;

            $authors = array_filter((array) ($d['author_name'] ?? []));
            $author = $authors
                ? implode(', ', array_slice(array_values($authors), 0, 3))
                : 'Unknown author';

            $publishers = array_values(array_filter((array) ($d['publisher'] ?? [])));
            $key = (string) ($d['key'] ?? '');
            $page = 'https://openlibrary.org' . $key;

            $out[] = [
                'id'         => 'ol-' . ($key !== '' ? $key : md5($title)),
                'title'      => $title,
                'authors'    => array_slice(array_values($authors), 0, 3),
                'author'     => $author,
                'year'       => (string) ($d['first_publish_year'] ?? ''),
                'publisher'  => $publishers ? (string) $publishers[0] : '',
                'description'=> '',
                'pages'      => isset($d['number_of_pages_median']) ? (int) $d['number_of_pages_median'] : null,
                'language'   => '',
                'categories' => [],
                'cover'      => !empty($d['cover_i'])
                    ? 'https://covers.openlibrary.org/b/id/' . (int) $d['cover_i'] . '-M.jpg'
                    : '',
                'preview'    => $page,
                'link'       => $page,
                'type'       => 'Book',
                'provider'   => $provider,
            ];
        }
        return $out;
    }

    /* ---------------------------------------------------------------
     * Per-user cache (24h)
     * ------------------------------------------------------------- */
    private static function readCache(int $uid, string $query): ?array
    {
        $stmt = db()->prepare(
            'SELECT payload FROM learning_resource_cache
             WHERE user_id = ? AND LOWER(query) = LOWER(?)
               AND created_at > (NOW() - INTERVAL ' . self::CACHE_TTL_H . ' HOUR)
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$uid, $query]);
        $payload = $stmt->fetchColumn();
        if ($payload === false || $payload === null || $payload === '') {
            self::cleanup($uid);
            return null;
        }
        $data = json_decode((string) $payload, true);
        return is_array($data) ? $data : null;
    }

    private static function storeCache(int $uid, string $query, array $resources): void
    {
        if (!$resources) return;
        try {
            $stmt = db()->prepare(
                'INSERT INTO learning_resource_cache (user_id, query, payload) VALUES (?, ?, ?)'
            );
            $stmt->execute([$uid, $query, json_encode($resources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            self::cleanup($uid);
        } catch (Throwable $ex) {
            // Cache is best-effort; a failed write must not break the search.
            error_log('StudentFlow resource cache write failed: ' . $ex->getMessage());
        }
    }

    /** Keep each user's cache bounded. */
    private static function cleanup(int $uid): void
    {
        try {
            $stmt = db()->prepare(
                'DELETE FROM learning_resource_cache
                 WHERE user_id = ?
                   AND id NOT IN (SELECT id FROM (
                        SELECT id FROM learning_resource_cache WHERE user_id = ?
                        ORDER BY id DESC LIMIT ' . self::CACHE_MAX . '
                   ) recent)'
            );
            $stmt->execute([$uid, $uid]);
        } catch (Throwable $ex) {
            error_log('StudentFlow resource cache cleanup failed: ' . $ex->getMessage());
        }
    }
}