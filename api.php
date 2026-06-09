<?php

require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

function body_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST;
}

function bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function api_user(): ?array
{
    $token = bearer_token();
    if (!$token) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, username, email, display_name, role, bio, created_at FROM ' . table_name('users') . ' WHERE api_token_hash = ? LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_api_user(): array
{
    $user = api_user();
    if (!$user) {
        json_response(['error' => '请先登录。'], 401);
    }
    return $user;
}

function issue_api_token(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $stmt = db()->prepare('UPDATE ' . table_name('users') . ' SET api_token_hash = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([hash('sha256', $token), $userId]);
    return $token;
}

function public_user(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'email' => $user['email'] ?? null,
        'displayName' => $user['display_name'],
        'role' => $user['role'],
        'bio' => $user['bio'],
        'createdAt' => $user['created_at'],
    ];
}

function category_payload(array $category): array
{
    return [
        'id' => (int) $category['id'],
        'slug' => $category['slug'],
        'name' => $category['name'],
        'description' => $category['description'],
        'color' => $category['color'],
        'sortOrder' => (int) $category['sort_order'],
    ];
}

function list_categories(): array
{
    $stmt = db()->query('SELECT * FROM ' . table_name('categories') . ' WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
    return array_map('category_payload', $stmt->fetchAll());
}

function list_threads(?int $categoryId = null): array
{
    $sql = 'SELECT t.*, c.name AS category_name, c.color AS category_color, u.username, u.display_name,
            (SELECT COUNT(*) FROM ' . table_name('posts') . ' p WHERE p.thread_id = t.id AND p.status = "VISIBLE") AS reply_count
            FROM ' . table_name('threads') . ' t
            JOIN ' . table_name('categories') . ' c ON c.id = t.category_id
            JOIN ' . table_name('users') . ' u ON u.id = t.user_id
            WHERE t.status = "PUBLISHED"';
    $params = [];
    if ($categoryId) {
        $sql .= ' AND t.category_id = ?';
        $params[] = $categoryId;
    }
    $sql .= ' ORDER BY t.pinned DESC, t.last_activity_at DESC LIMIT 80';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return array_map('thread_summary_payload', $stmt->fetchAll());
}

function thread_summary_payload(array $thread): array
{
    return [
        'id' => (int) $thread['id'],
        'categoryId' => (int) $thread['category_id'],
        'categoryName' => $thread['category_name'],
        'categoryColor' => $thread['category_color'],
        'title' => $thread['title'],
        'slug' => $thread['slug'],
        'excerpt' => mb_substr(trim(strip_tags($thread['content'])), 0, 180),
        'author' => [
            'username' => $thread['username'],
            'displayName' => $thread['display_name'],
        ],
        'replyCount' => (int) $thread['reply_count'],
        'views' => (int) $thread['views'],
        'pinned' => (bool) $thread['pinned'],
        'createdAt' => $thread['created_at'],
        'lastActivityAt' => $thread['last_activity_at'],
    ];
}

function get_thread_payload(int $id): ?array
{
    $stmt = db()->prepare('SELECT t.*, c.name AS category_name, c.color AS category_color, u.username, u.display_name,
        (SELECT COUNT(*) FROM ' . table_name('posts') . ' p WHERE p.thread_id = t.id AND p.status = "VISIBLE") AS reply_count
        FROM ' . table_name('threads') . ' t
        JOIN ' . table_name('categories') . ' c ON c.id = t.category_id
        JOIN ' . table_name('users') . ' u ON u.id = t.user_id
        WHERE t.id = ? AND t.status = "PUBLISHED" LIMIT 1');
    $stmt->execute([$id]);
    $thread = $stmt->fetch();
    if (!$thread) {
        return null;
    }
    $posts = db()->prepare('SELECT p.*, u.username, u.display_name FROM ' . table_name('posts') . ' p JOIN ' . table_name('users') . ' u ON u.id = p.user_id WHERE p.thread_id = ? AND p.status = "VISIBLE" ORDER BY p.created_at ASC');
    $posts->execute([$id]);

    return array_merge(thread_summary_payload($thread), [
        'content' => $thread['content'],
        'posts' => array_map(fn (array $post) => [
            'id' => (int) $post['id'],
            'content' => $post['content'],
            'author' => [
                'username' => $post['username'],
                'displayName' => $post['display_name'],
            ],
            'createdAt' => $post['created_at'],
        ], $posts->fetchAll()),
    ]);
}

function api_unique_slug(string $title): string
{
    $base = slugify($title);
    $slug = $base;
    $i = 2;
    while (true) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM ' . table_name('threads') . ' WHERE slug = ?');
        $stmt->execute([$slug]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

$action = (string) ($_GET['action'] ?? '');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($action === 'site') {
            json_response([
                'site' => [
                    'name' => setting('site_name', config_value($config, 'site.name', 'iForum')),
                    'tagline' => setting('tagline', 'A clean community forum template.'),
                    'welcomeTitle' => setting('welcome_title', '欢迎来到 iForum'),
                    'welcomeBody' => setting('welcome_body', '一个干净的通用论坛模板。'),
                ],
                'user' => ($user = api_user()) ? public_user($user) : null,
            ]);
        }

        if ($action === 'categories') {
            json_response(['categories' => list_categories()]);
        }

        if ($action === 'threads') {
            $categoryId = isset($_GET['categoryId']) ? (int) $_GET['categoryId'] : null;
            json_response(['threads' => list_threads($categoryId)]);
        }

        if ($action === 'thread') {
            $thread = get_thread_payload((int) ($_GET['id'] ?? 0));
            if (!$thread) {
                json_response(['error' => '主题不存在。'], 404);
            }
            db()->prepare('UPDATE ' . table_name('threads') . ' SET views = views + 1 WHERE id = ?')->execute([(int) $thread['id']]);
            json_response(['thread' => $thread]);
        }

        json_response(['error' => '未知接口。'], 404);
    }

    $input = body_input();

    if ($action === 'login') {
        $login = trim((string) ($input['login'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $stmt = db()->prepare('SELECT * FROM ' . table_name('users') . ' WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            json_response(['error' => '账号或密码不正确。'], 401);
        }
        $token = issue_api_token((int) $user['id']);
        json_response(['token' => $token, 'user' => public_user($user)]);
    }

    if ($action === 'register') {
        Auth::register((string) ($input['username'] ?? ''), (string) ($input['email'] ?? ''), (string) ($input['displayName'] ?? ''), (string) ($input['password'] ?? ''));
        $user = Auth::user();
        $token = issue_api_token((int) $user['id']);
        json_response(['token' => $token, 'user' => public_user($user)], 201);
    }

    if ($action === 'logout') {
        $user = require_api_user();
        db()->prepare('UPDATE ' . table_name('users') . ' SET api_token_hash = NULL WHERE id = ?')->execute([(int) $user['id']]);
        json_response(['ok' => true]);
    }

    if ($action === 'thread') {
        $user = require_api_user();
        $title = trim((string) ($input['title'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));
        $categoryId = (int) ($input['categoryId'] ?? 0);
        if (mb_strlen($title) < 4 || mb_strlen($content) < 2) {
            json_response(['error' => '标题或内容太短。'], 422);
        }
        $category = db()->prepare('SELECT id FROM ' . table_name('categories') . ' WHERE id = ? AND is_active = 1');
        $category->execute([$categoryId]);
        if (!$category->fetch()) {
            json_response(['error' => '分类不存在。'], 422);
        }
        $stmt = db()->prepare('INSERT INTO ' . table_name('threads') . ' (category_id, user_id, title, slug, content, created_at, updated_at, last_activity_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())');
        $stmt->execute([$categoryId, (int) $user['id'], $title, api_unique_slug($title), $content]);
        $thread = get_thread_payload((int) db()->lastInsertId());
        json_response(['thread' => $thread], 201);
    }

    if ($action === 'reply') {
        $user = require_api_user();
        $threadId = (int) ($input['threadId'] ?? 0);
        $content = trim((string) ($input['content'] ?? ''));
        if (mb_strlen($content) < 2) {
            json_response(['error' => '回复内容不能为空。'], 422);
        }
        if (!get_thread_payload($threadId)) {
            json_response(['error' => '主题不存在。'], 404);
        }
        $stmt = db()->prepare('INSERT INTO ' . table_name('posts') . ' (thread_id, user_id, content, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $stmt->execute([$threadId, (int) $user['id'], $content]);
        db()->prepare('UPDATE ' . table_name('threads') . ' SET last_activity_at = NOW() WHERE id = ?')->execute([$threadId]);
        json_response(['thread' => get_thread_payload($threadId)], 201);
    }

    json_response(['error' => '未知接口。'], 404);
} catch (Throwable $error) {
    json_response(['error' => $error->getMessage()], 500);
}
