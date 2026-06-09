<?php

require __DIR__ . '/app/bootstrap.php';

function flash(?string $message = null, string $type = 'info'): ?array
{
    if ($message !== null) {
        $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $flash;
}

function categories(): array
{
    $stmt = db()->query('SELECT * FROM ' . table_name('categories') . ' WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
    return $stmt->fetchAll();
}

function thread_count(?int $categoryId = null): int
{
    if ($categoryId) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM ' . table_name('threads') . ' WHERE status = "PUBLISHED" AND category_id = ?');
        $stmt->execute([$categoryId]);
    } else {
        $stmt = db()->query('SELECT COUNT(*) FROM ' . table_name('threads') . ' WHERE status = "PUBLISHED"');
    }
    return (int) $stmt->fetchColumn();
}

function post_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM ' . table_name('posts') . ' WHERE status = "VISIBLE"')->fetchColumn();
}

function all_threads(?int $categoryId = null): array
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
    $sql .= ' ORDER BY t.pinned DESC, t.last_activity_at DESC LIMIT 60';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function find_thread(int $id): ?array
{
    $stmt = db()->prepare('SELECT t.*, c.name AS category_name, c.color AS category_color, u.username, u.display_name, u.bio
        FROM ' . table_name('threads') . ' t
        JOIN ' . table_name('categories') . ' c ON c.id = t.category_id
        JOIN ' . table_name('users') . ' u ON u.id = t.user_id
        WHERE t.id = ? AND t.status = "PUBLISHED" LIMIT 1');
    $stmt->execute([$id]);
    $thread = $stmt->fetch();
    return $thread ?: null;
}

function thread_posts(int $threadId): array
{
    $stmt = db()->prepare('SELECT p.*, u.username, u.display_name
        FROM ' . table_name('posts') . ' p
        JOIN ' . table_name('users') . ' u ON u.id = p.user_id
        WHERE p.thread_id = ? AND p.status = "VISIBLE"
        ORDER BY p.created_at ASC');
    $stmt->execute([$threadId]);
    return $stmt->fetchAll();
}

function make_unique_slug(string $title): string
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

function render_content(string $content): string
{
    $escaped = e($content);
    $escaped = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" target="_blank" rel="noreferrer">$1</a>', $escaped) ?? $escaped;
    return nl2br($escaped);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'login') {
            if (!Auth::attempt((string) $_POST['login'], (string) $_POST['password'])) {
                throw new RuntimeException('账号或密码不正确。');
            }
            flash('欢迎回来。', 'success');
            redirect('index.php');
        }

        if ($action === 'register') {
            Auth::register((string) $_POST['username'], (string) $_POST['email'], (string) $_POST['display_name'], (string) $_POST['password']);
            flash('注册成功，欢迎加入。', 'success');
            redirect('index.php');
        }

        if ($action === 'logout') {
            Auth::logout();
            redirect('index.php');
        }

        if ($action === 'create_thread') {
            $user = Auth::requireUser();
            $title = trim((string) $_POST['title']);
            $content = trim((string) $_POST['content']);
            $categoryId = (int) $_POST['category_id'];
            if (mb_strlen($title) < 4 || mb_strlen($title) > 180) {
                throw new RuntimeException('标题长度需要在 4-180 字之间。');
            }
            if (mb_strlen($content) < 2) {
                throw new RuntimeException('内容不能为空。');
            }
            $categoryStmt = db()->prepare('SELECT id FROM ' . table_name('categories') . ' WHERE id = ? AND is_active = 1');
            $categoryStmt->execute([$categoryId]);
            if (!$categoryStmt->fetch()) {
                throw new RuntimeException('请选择有效分类。');
            }
            $stmt = db()->prepare('INSERT INTO ' . table_name('threads') . ' (category_id, user_id, title, slug, content, created_at, updated_at, last_activity_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())');
            $stmt->execute([$categoryId, (int) $user['id'], $title, make_unique_slug($title), $content]);
            $id = (int) db()->lastInsertId();
            flash('主题已发布。', 'success');
            redirect('index.php?page=thread&id=' . $id);
        }

        if ($action === 'reply') {
            $user = Auth::requireUser();
            $threadId = (int) $_POST['thread_id'];
            $content = trim((string) $_POST['content']);
            if (mb_strlen($content) < 2) {
                throw new RuntimeException('回复内容不能为空。');
            }
            if (!find_thread($threadId)) {
                throw new RuntimeException('主题不存在。');
            }
            $stmt = db()->prepare('INSERT INTO ' . table_name('posts') . ' (thread_id, user_id, content, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
            $stmt->execute([$threadId, (int) $user['id'], $content]);
            $update = db()->prepare('UPDATE ' . table_name('threads') . ' SET last_activity_at = NOW() WHERE id = ?');
            $update->execute([$threadId]);
            flash('回复已发布。', 'success');
            redirect('index.php?page=thread&id=' . $threadId . '#replies');
        }

        if ($action === 'save_category') {
            $user = Auth::requireUser();
            if (!Auth::isAdmin($user)) {
                throw new RuntimeException('需要管理员权限。');
            }
            $name = trim((string) $_POST['name']);
            $description = trim((string) $_POST['description']);
            $color = trim((string) ($_POST['color'] ?? '#2563eb'));
            if ($name === '') {
                throw new RuntimeException('分类名称不能为空。');
            }
            $stmt = db()->prepare('INSERT INTO ' . table_name('categories') . ' (slug, name, description, color, sort_order, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), color = VALUES(color), sort_order = VALUES(sort_order), is_active = 1');
            $stmt->execute([slugify($name), $name, $description, $color, (int) ($_POST['sort_order'] ?? 10)]);
            flash('分类已保存。', 'success');
            redirect('index.php?page=admin');
        }

        if ($action === 'save_settings') {
            $user = Auth::requireUser();
            if (!Auth::isAdmin($user)) {
                throw new RuntimeException('需要管理员权限。');
            }
            $stmt = db()->prepare('INSERT INTO ' . table_name('settings') . ' (`key`, `value`, `created_at`, `updated_at`) VALUES (?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()');
            foreach (['site_name', 'tagline', 'welcome_title', 'welcome_body'] as $key) {
                $stmt->execute([$key, json_encode(trim((string) ($_POST[$key] ?? '')), JSON_UNESCAPED_UNICODE)]);
            }
            flash('站点设置已保存。', 'success');
            redirect('index.php?page=admin');
        }
    } catch (Throwable $error) {
        flash($error->getMessage(), 'danger');
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}

$user = Auth::user();
$page = (string) ($_GET['page'] ?? 'home');
$selectedCategory = isset($_GET['category']) ? (int) $_GET['category'] : null;
$siteName = setting('site_name', config_value($config, 'site.name', 'iForum'));
$tagline = setting('tagline', 'A clean community forum template.');
$flash = flash();

?><!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($siteName) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <header class="topbar">
    <a class="brand" href="index.php">
      <span class="brand-mark">iF</span>
      <span>
        <strong><?= e($siteName) ?></strong>
        <small><?= e($tagline) ?></small>
      </span>
    </a>
    <nav class="nav">
      <a href="index.php">主题</a>
      <?php if ($user): ?>
        <a href="index.php?page=compose">发布</a>
        <?php if (Auth::isAdmin($user)): ?><a href="index.php?page=admin">后台</a><?php endif; ?>
        <form method="post" class="inline-form">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="logout">
          <button type="submit">退出</button>
        </form>
      <?php else: ?>
        <a href="index.php?page=login">登录</a>
      <?php endif; ?>
    </nav>
  </header>

  <?php if ($flash): ?>
    <div class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <?php if ($page === 'thread' && isset($_GET['id'])): ?>
    <?php
      $thread = find_thread((int) $_GET['id']);
      if ($thread) {
          db()->prepare('UPDATE ' . table_name('threads') . ' SET views = views + 1 WHERE id = ?')->execute([(int) $thread['id']]);
      }
    ?>
    <main class="layout single">
      <?php if (!$thread): ?>
        <section class="panel"><h1>主题不存在</h1><p>它可能已经被删除或隐藏。</p></section>
      <?php else: ?>
        <article class="thread-detail">
          <a class="chip" style="--chip: <?= e($thread['category_color']) ?>" href="index.php?category=<?= (int) $thread['category_id'] ?>"><?= e($thread['category_name']) ?></a>
          <h1><?= e($thread['title']) ?></h1>
          <p class="meta">由 <?= e($thread['display_name']) ?> 发布 · <?= e(time_ago($thread['created_at'])) ?> · <?= (int) $thread['views'] + 1 ?> 次浏览</p>
          <div class="content-body"><?= render_content($thread['content']) ?></div>
        </article>

        <section class="panel replies" id="replies">
          <div class="section-head">
            <h2>回复</h2>
            <span><?= count(thread_posts((int) $thread['id'])) ?> 条</span>
          </div>
          <?php foreach (thread_posts((int) $thread['id']) as $post): ?>
            <article class="reply">
              <strong><?= e($post['display_name']) ?></strong>
              <span><?= e(time_ago($post['created_at'])) ?></span>
              <p><?= render_content($post['content']) ?></p>
            </article>
          <?php endforeach; ?>
          <?php if ($user): ?>
            <form method="post" class="stack-form">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="reply">
              <input type="hidden" name="thread_id" value="<?= (int) $thread['id'] ?>">
              <label>
                <span>写回复</span>
                <textarea name="content" required rows="5" placeholder="分享你的想法"></textarea>
              </label>
              <button class="button primary" type="submit">发布回复</button>
            </form>
          <?php else: ?>
            <p class="muted"><a href="index.php?page=login">登录</a> 后参与回复。</p>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </main>

  <?php elseif ($page === 'compose'): ?>
    <?php Auth::requireUser(); ?>
    <main class="layout single">
      <section class="panel">
        <h1>发布主题</h1>
        <form method="post" class="stack-form">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="create_thread">
          <label>
            <span>分类</span>
            <select name="category_id" required>
              <?php foreach (categories() as $category): ?>
                <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            <span>标题</span>
            <input name="title" required minlength="4" maxlength="180" placeholder="写一个清楚的问题或话题">
          </label>
          <label>
            <span>内容</span>
            <textarea name="content" required rows="12" placeholder="支持纯文本和链接。"></textarea>
          </label>
          <button class="button primary" type="submit">发布</button>
        </form>
      </section>
    </main>

  <?php elseif ($page === 'login'): ?>
    <main class="auth-layout">
      <section class="panel">
        <h1>登录</h1>
        <form method="post" class="stack-form">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="login">
          <label><span>用户名或邮箱</span><input name="login" required></label>
          <label><span>密码</span><input name="password" type="password" required></label>
          <button class="button primary" type="submit">登录</button>
        </form>
      </section>
      <section class="panel">
        <h1>注册</h1>
        <form method="post" class="stack-form">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="register">
          <label><span>用户名</span><input name="username" required pattern="[A-Za-z0-9_]{3,30}"></label>
          <label><span>显示名</span><input name="display_name" required></label>
          <label><span>邮箱</span><input name="email" type="email" required></label>
          <label><span>密码</span><input name="password" type="password" required minlength="8"></label>
          <button class="button secondary" type="submit">创建账号</button>
        </form>
      </section>
    </main>

  <?php elseif ($page === 'admin'): ?>
    <?php $admin = Auth::requireUser(); if (!Auth::isAdmin($admin)) { redirect('index.php'); } ?>
    <main class="layout single">
      <section class="panel">
        <h1>站点设置</h1>
        <form method="post" class="stack-form">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="save_settings">
          <label><span>站点名称</span><input name="site_name" value="<?= e($siteName) ?>" required></label>
          <label><span>副标题</span><input name="tagline" value="<?= e($tagline) ?>"></label>
          <label><span>首页标题</span><input name="welcome_title" value="<?= e(setting('welcome_title', '欢迎来到 iForum')) ?>"></label>
          <label><span>首页介绍</span><textarea name="welcome_body" rows="4"><?= e(setting('welcome_body', '这是一个通用论坛模板。')) ?></textarea></label>
          <button class="button primary" type="submit">保存设置</button>
        </form>
      </section>
      <section class="panel">
        <h1>分类</h1>
        <div class="category-list">
          <?php foreach (categories() as $category): ?>
            <div class="category-row">
              <span class="dot" style="background: <?= e($category['color']) ?>"></span>
              <div><strong><?= e($category['name']) ?></strong><small><?= e($category['description']) ?></small></div>
            </div>
          <?php endforeach; ?>
        </div>
        <form method="post" class="stack-form compact-form">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="save_category">
          <label><span>分类名称</span><input name="name" required></label>
          <label><span>描述</span><input name="description"></label>
          <label><span>颜色</span><input name="color" type="color" value="#2563eb"></label>
          <label><span>排序</span><input name="sort_order" type="number" value="10"></label>
          <button class="button secondary" type="submit">新增/更新分类</button>
        </form>
      </section>
    </main>

  <?php else: ?>
    <main class="layout">
      <aside class="sidebar">
        <section class="intro">
          <p class="eyebrow">Community Template</p>
          <h1><?= e(setting('welcome_title', '欢迎来到 iForum')) ?></h1>
          <p><?= e(setting('welcome_body', '一个干净的通用论坛模板，安装后即可开始运营。')) ?></p>
          <a class="button primary" href="<?= $user ? 'index.php?page=compose' : 'index.php?page=login' ?>"><?= $user ? '发布主题' : '登录 / 注册' ?></a>
        </section>
        <section class="panel stats">
          <div><strong><?= thread_count() ?></strong><span>主题</span></div>
          <div><strong><?= post_count() ?></strong><span>回复</span></div>
        </section>
        <section class="panel">
          <h2>分类</h2>
          <a class="category-link <?= $selectedCategory ? '' : 'active' ?>" href="index.php">全部主题</a>
          <?php foreach (categories() as $category): ?>
            <a class="category-link <?= $selectedCategory === (int) $category['id'] ? 'active' : '' ?>" href="index.php?category=<?= (int) $category['id'] ?>">
              <span class="dot" style="background: <?= e($category['color']) ?>"></span><?= e($category['name']) ?>
            </a>
          <?php endforeach; ?>
        </section>
      </aside>

      <section class="feed">
        <div class="section-head">
          <h2>最新主题</h2>
          <span><?= thread_count($selectedCategory) ?> 条</span>
        </div>
        <?php $threads = all_threads($selectedCategory); ?>
        <?php if (!$threads): ?>
          <article class="empty-state">
            <h3>还没有主题</h3>
            <p>发布第一条讨论，让这个社区开始运转。</p>
            <a class="button secondary" href="<?= $user ? 'index.php?page=compose' : 'index.php?page=login' ?>">开始发布</a>
          </article>
        <?php endif; ?>
        <?php foreach ($threads as $thread): ?>
          <article class="thread-card">
            <a class="chip" style="--chip: <?= e($thread['category_color']) ?>" href="index.php?category=<?= (int) $thread['category_id'] ?>"><?= e($thread['category_name']) ?></a>
            <h3><a href="index.php?page=thread&id=<?= (int) $thread['id'] ?>"><?= e($thread['title']) ?></a></h3>
            <p><?= e(mb_substr(strip_tags($thread['content']), 0, 140)) ?><?= mb_strlen($thread['content']) > 140 ? '...' : '' ?></p>
            <footer>
              <span><?= e($thread['display_name']) ?></span>
              <span><?= e(time_ago($thread['last_activity_at'])) ?></span>
              <span><?= (int) $thread['reply_count'] ?> 回复</span>
            </footer>
          </article>
        <?php endforeach; ?>
      </section>
    </main>
  <?php endif; ?>

  <footer class="site-footer">
    <span>Powered by iForum</span>
    <a href="api.php?action=site">API</a>
  </footer>
</body>
</html>

