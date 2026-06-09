<?php

$root = __DIR__;
$configFile = $root . '/app/config.php';
$lockFile = $root . '/app/installed.lock';
$errors = [];
$success = false;

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post_value(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function clean_prefix(string $prefix): string
{
    $prefix = preg_replace('/[^A-Za-z0-9_]/', '', $prefix) ?? 'if_';
    return $prefix ?: 'if_';
}

function split_sql(string $sql): array
{
    return array_values(array_filter(array_map('trim', explode(';', $sql))));
}

function write_config(string $path, array $config): bool
{
    $export = var_export($config, true);
    $body = "<?php\n\nreturn {$export};\n";
    return file_put_contents($path, $body, LOCK_EX) !== false;
}

function public_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($path === '' ? '' : $path);
}

if (is_file($lockFile) && is_file($configFile)) {
    header('Location: index.php');
    exit;
}

$defaults = [
    'site_name' => 'iForum',
    'base_url' => public_base_url(),
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'iforum',
    'db_user' => 'root',
    'db_password' => '',
    'db_prefix' => 'if_',
    'admin_username' => 'admin',
    'admin_email' => 'admin@example.com',
    'admin_display_name' => 'Forum Admin',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!extension_loaded('pdo_mysql')) {
        $errors[] = '当前 PHP 没有启用 pdo_mysql 扩展。';
    }

    $siteName = post_value('site_name', $defaults['site_name']);
    $baseUrl = rtrim(post_value('base_url', $defaults['base_url']), '/');
    $host = post_value('db_host', $defaults['db_host']);
    $port = (int) post_value('db_port', $defaults['db_port']);
    $database = post_value('db_name', $defaults['db_name']);
    $username = post_value('db_user', $defaults['db_user']);
    $password = (string) ($_POST['db_password'] ?? '');
    $prefix = clean_prefix(post_value('db_prefix', $defaults['db_prefix']));
    $adminUsername = post_value('admin_username', $defaults['admin_username']);
    $adminEmail = post_value('admin_email', $defaults['admin_email']);
    $adminDisplayName = post_value('admin_display_name', $defaults['admin_display_name']);
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $createDatabase = isset($_POST['create_database']);

    if ($siteName === '') {
        $errors[] = '请填写站点名称。';
    }
    if ($database === '') {
        $errors[] = '请填写数据库名。';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $adminUsername)) {
        $errors[] = '管理员用户名只能包含字母、数字和下划线，长度 3-30。';
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '管理员邮箱格式不正确。';
    }
    if (strlen($adminPassword) < 8) {
        $errors[] = '管理员密码至少需要 8 位。';
    }

    if (!$errors) {
        try {
            if ($createDatabase) {
                $serverDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
                $server = new PDO($serverDsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $safeDb = str_replace('`', '``', $database);
                $server->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $schema = file_get_contents($root . '/database/schema.sql');
            if ($schema === false) {
                throw new RuntimeException('无法读取 database/schema.sql。');
            }
            $schema = str_replace('{prefix}', $prefix, $schema);
            foreach (split_sql($schema) as $statement) {
                $pdo->exec($statement);
            }

            $settings = [
                'site_name' => $siteName,
                'tagline' => '现代、轻量、可直接部署的 PHP 论坛。',
                'welcome_title' => '让社区论坛像博客程序一样好安装。',
                'welcome_body' => 'iForum 面向普通 PHP 空间：上传 zip、填写数据库、创建管理员，然后直接开始运营。',
            ];
            $settingsStmt = $pdo->prepare("INSERT INTO `{$prefix}settings` (`key`, `value`, `created_at`, `updated_at`) VALUES (?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()");
            foreach ($settings as $key => $value) {
                $settingsStmt->execute([$key, json_encode($value, JSON_UNESCAPED_UNICODE)]);
            }

            $categories = [
                ['announcements', '公告与更新', '发布产品、社区和运营相关的重要消息。', '#2563eb', 1],
                ['questions', '问答互助', '提出问题，沉淀可复用的经验和答案。', '#0f8a4b', 2],
                ['showcase', '作品展示', '分享项目、文章、资源和社区成员成果。', '#d97706', 3],
                ['feedback', '反馈建议', '收集功能建议、使用体验和治理讨论。', '#7c3aed', 4],
            ];
            $categoryStmt = $pdo->prepare("INSERT INTO `{$prefix}categories` (`slug`, `name`, `description`, `color`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `color` = VALUES(`color`), `sort_order` = VALUES(`sort_order`), `is_active` = 1");
            foreach ($categories as $category) {
                $categoryStmt->execute($category);
            }

            $adminStmt = $pdo->prepare("INSERT INTO `{$prefix}users` (`username`, `email`, `display_name`, `password_hash`, `role`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, 'ADMIN', NOW(), NOW()) ON DUPLICATE KEY UPDATE `email` = VALUES(`email`), `display_name` = VALUES(`display_name`), `password_hash` = VALUES(`password_hash`), `role` = 'ADMIN', `updated_at` = NOW()");
            $adminStmt->execute([$adminUsername, $adminEmail, $adminDisplayName ?: $adminUsername, password_hash($adminPassword, PASSWORD_DEFAULT)]);

            $config = [
                'installed' => true,
                'site' => [
                    'name' => $siteName,
                    'base_url' => $baseUrl,
                ],
                'db' => [
                    'driver' => 'mysql',
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $username,
                    'password' => $password,
                    'charset' => 'utf8mb4',
                    'prefix' => $prefix,
                ],
                'security' => [
                    'session_name' => 'iforum_session',
                ],
            ];

            if (!write_config($configFile, $config)) {
                throw new RuntimeException('无法写入 app/config.php，请检查 app 目录权限。');
            }
            if (file_put_contents($lockFile, 'installed at ' . date(DATE_ATOM), LOCK_EX) === false) {
                throw new RuntimeException('无法写入 app/installed.lock，请检查 app 目录权限。');
            }
            $success = true;
        } catch (Throwable $error) {
            $errors[] = $error->getMessage();
        }
    }
}

?><!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>安装 iForum</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="install-page">
  <main class="install-shell">
    <section class="install-hero">
      <div>
        <p class="eyebrow">iForum 1.0 Installer</p>
        <h1>像安装 Typecho 一样安装现代论坛。</h1>
        <p>上传 zip、填写数据库、创建管理员。iForum 会自动建表、写入配置、初始化分类，并锁定安装入口。</p>
      </div>
      <div class="install-checklist">
        <span>PHP 8.1+</span>
        <span>PDO MySQL</span>
        <span>无 Composer</span>
        <span>JSON API ready</span>
      </div>
    </section>

    <?php if ($success): ?>
      <section class="panel">
        <h2>安装完成</h2>
        <p>配置文件和数据库已创建。为了安全，安装入口已经锁定。</p>
        <a class="button primary" href="index.php">进入论坛</a>
      </section>
    <?php else: ?>
      <?php if ($errors): ?>
        <div class="notice danger">
          <?php foreach ($errors as $error): ?>
            <p><?= h($error) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="panel install-form">
        <div class="install-step">
          <strong>1</strong>
          <span>站点信息</span>
        </div>
        <div class="form-grid">
          <label>
            <span>站点名称</span>
            <input name="site_name" required value="<?= h(post_value('site_name', $defaults['site_name'])) ?>">
          </label>
          <label>
            <span>站点地址</span>
            <input name="base_url" required value="<?= h(post_value('base_url', $defaults['base_url'])) ?>">
          </label>
        </div>

        <div class="install-step">
          <strong>2</strong>
          <span>数据库连接</span>
        </div>
        <div class="form-grid">
          <label>
            <span>数据库主机</span>
            <input name="db_host" required value="<?= h(post_value('db_host', $defaults['db_host'])) ?>">
          </label>
          <label>
            <span>端口</span>
            <input name="db_port" required value="<?= h(post_value('db_port', $defaults['db_port'])) ?>">
          </label>
          <label>
            <span>数据库名</span>
            <input name="db_name" required value="<?= h(post_value('db_name', $defaults['db_name'])) ?>">
          </label>
          <label>
            <span>表前缀</span>
            <input name="db_prefix" required value="<?= h(post_value('db_prefix', $defaults['db_prefix'])) ?>">
          </label>
          <label>
            <span>数据库用户</span>
            <input name="db_user" required value="<?= h(post_value('db_user', $defaults['db_user'])) ?>">
          </label>
          <label>
            <span>数据库密码</span>
            <input type="password" name="db_password" value="<?= h((string) ($_POST['db_password'] ?? $defaults['db_password'])) ?>">
          </label>
        </div>
        <label class="check-row">
          <input type="checkbox" name="create_database" value="1" <?= isset($_POST['create_database']) ? 'checked' : '' ?>>
          <span>如果数据库不存在，尝试自动创建</span>
        </label>

        <div class="install-step">
          <strong>3</strong>
          <span>管理员账号</span>
        </div>
        <div class="form-grid">
          <label>
            <span>用户名</span>
            <input name="admin_username" required value="<?= h(post_value('admin_username', $defaults['admin_username'])) ?>">
          </label>
          <label>
            <span>显示名</span>
            <input name="admin_display_name" required value="<?= h(post_value('admin_display_name', $defaults['admin_display_name'])) ?>">
          </label>
          <label>
            <span>邮箱</span>
            <input type="email" name="admin_email" required value="<?= h(post_value('admin_email', $defaults['admin_email'])) ?>">
          </label>
          <label>
            <span>密码</span>
            <input type="password" name="admin_password" required minlength="8">
          </label>
        </div>

        <button class="button primary" type="submit">开始安装</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
