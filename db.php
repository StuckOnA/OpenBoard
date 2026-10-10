<?php
// db.php - shared setup for every page: config, PDO connection, session, helpers.
// Every page starts with: require 'db.php';

if (!is_file(__DIR__ . '/config.php')) {
    exit('Missing config.php. Copy config.sample.php to config.php and edit it.');
}
$config = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Could not connect to the database. Check that MySQL is running and config.php is correct.');
}

// Sessions remember who is logged in. Skipped for command-line scripts (create_admin.php).
if (PHP_SAPI !== 'cli') {
    session_set_cookie_params([
        'httponly' => true,   // hidden from JavaScript
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// Escape text before printing it into HTML (prevents XSS).
function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// Read a text value from the URL / form safely (arrays or missing values become '').
function get_str(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}
function post_str(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}
function post_raw(string $key): string   // passwords: never trimmed
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? $v : '';
}

function redirect(string $url)
{
    header('Location: ' . $url);
    exit;
}

// The logged-in user is ['id' => 5, 'username' => 'sam'], or null for guests.
// NOTE: the role is deliberately NOT stored in the session; see is_admin().
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['flash'] = ['danger', 'Please log in first.'];
        redirect('login.php');
    }
    return $user;
}

// True only if the logged-in user's role in the DATABASE is 'admin'.
// Looked up on every request (once per request, then cached) so changes apply immediately.
function is_admin(): bool
{
    global $pdo;                 // the connection created at the top of this file
    static $cached = null;
    $user = current_user();
    if (!$user) {
        return false;
    }
    if ($cached === null) {
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id');
        $stmt->execute([':id' => $user['id']]);
        $cached = ($stmt->fetchColumn() === 'admin');
    }
    return $cached;
}

// Call at the top of any admin-only page or action. Guests go to login; non-admins get 403.
function require_admin(): array
{
    $user = require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Admins only.');
    }
    return $user;
}

// CSRF protection: every state-changing form carries a secret token tied to the session.
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Invalid request (CSRF check failed). Go back, refresh the page and try again.');
    }
}

// Base query for a list of posts: author, score, comment count and tags in one row each.
// The score comes from a small joined table (vs) so that index.php can also sort by it
// inside the Hot formula. Callers append WHERE / ORDER BY.
const POST_LIST_SQL = "
    SELECT p.id, p.user_id, p.title, p.body, p.created_at, u.username,
           COALESCE(vs.net_score, 0) AS score,
           (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count,
           (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ',')
              FROM post_tags pt JOIN tags t ON t.id = pt.tag_id
             WHERE pt.post_id = p.id) AS tags
    FROM posts p
    JOIN users u ON u.id = p.user_id
    LEFT JOIN (SELECT post_id, SUM(vote_value) AS net_score FROM votes GROUP BY post_id) vs
           ON vs.post_id = p.id";
