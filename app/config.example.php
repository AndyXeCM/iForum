<?php

return [
    'installed' => false,
    'site' => [
        'name' => 'iForum',
        'base_url' => '',
    ],
    'db' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'iforum',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'prefix' => 'if_',
    ],
    'security' => [
        'session_name' => 'iforum_session',
    ],
];

