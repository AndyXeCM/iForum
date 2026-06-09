<?php

final class Database
{
    public static function connect(array $config): PDO
    {
        $db = $config['db'];
        $charset = $db['charset'] ?? 'utf8mb4';
        $host = $db['host'] ?? '127.0.0.1';
        $port = (int) ($db['port'] ?? 3306);
        $database = $db['database'] ?? '';
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        return new PDO($dsn, $db['username'] ?? '', $db['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}

