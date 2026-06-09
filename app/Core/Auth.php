<?php

final class Auth
{
    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $stmt = db()->prepare('SELECT id, username, email, display_name, role, bio, created_at FROM ' . table_name('users') . ' WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function attempt(string $login, string $password): bool
    {
        $stmt = db()->prepare('SELECT * FROM ' . table_name('users') . ' WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        return true;
    }

    public static function register(string $username, string $email, string $displayName, string $password): array
    {
        $username = trim($username);
        $email = trim($email);
        $displayName = trim($displayName) ?: $username;

        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            throw new InvalidArgumentException('用户名只能包含字母、数字和下划线，长度 3-30。');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('邮箱格式不正确。');
        }
        if (mb_strlen($password) < 8) {
            throw new InvalidArgumentException('密码至少需要 8 位。');
        }

        $stmt = db()->prepare('INSERT INTO ' . table_name('users') . ' (username, email, display_name, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, "MEMBER", NOW(), NOW())');
        $stmt->execute([$username, $email, $displayName, password_hash($password, PASSWORD_DEFAULT)]);
        $_SESSION['user_id'] = (int) db()->lastInsertId();
        return self::user() ?? [];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            redirect('index.php?page=login');
        }
        return $user;
    }

    public static function isAdmin(?array $user = null): bool
    {
        $user ??= self::user();
        return (($user['role'] ?? '') === 'ADMIN');
    }
}

