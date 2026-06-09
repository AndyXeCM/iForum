<?php

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header("Location: {$url}");
    exit;
}

function request_input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function config_value(array $config, string $path, mixed $default = null): mixed
{
    $value = $config;
    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function table_name(string $name): string
{
    $prefix = $GLOBALS['config']['db']['prefix'] ?? '';
    return '`' . str_replace('`', '', $prefix . $name) . '`';
}

function slugify(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
    $value = trim($value, '-');
    if ($value === '') {
        $value = 'thread';
    }
    return mb_substr($value, 0, 80, 'UTF-8');
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $seconds = max(0, time() - strtotime($datetime));
    if ($seconds < 60) {
        return '刚刚';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . ' 分钟前';
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . ' 小时前';
    }
    return date('Y-m-d', strtotime($datetime));
}

function setting(string $key, mixed $default = null): mixed
{
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        try {
            $stmt = db()->query('SELECT `key`, `value` FROM ' . table_name('settings'));
            foreach ($stmt->fetchAll() as $row) {
                $decoded = json_decode((string) $row['value'], true);
                $settings[$row['key']] = json_last_error() === JSON_ERROR_NONE ? $decoded : $row['value'];
            }
        } catch (Throwable) {
            return $default;
        }
    }
    return $settings[$key] ?? $default;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

