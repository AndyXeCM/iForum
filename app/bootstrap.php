<?php

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: install.php');
        exit;
    }
    return;
}

$config = require $configPath;
$GLOBALS['config'] = $config;

require_once __DIR__ . '/Core/helpers.php';
require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/Csrf.php';
require_once __DIR__ . '/Core/Auth.php';

session_name(config_value($config, 'security.session_name', 'iforum_session'));
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = Database::connect($GLOBALS['config']);
    }
    return $pdo;
}

