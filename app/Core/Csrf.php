<?php

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(): void
    {
        $sent = $_POST['_csrf'] ?? '';
        $known = $_SESSION['_csrf'] ?? '';
        if (!$sent || !$known || !hash_equals($known, $sent)) {
            http_response_code(419);
            exit('CSRF token mismatch.');
        }
    }
}

