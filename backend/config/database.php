<?php
$env = function (string $key, string $default): string {
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }
    return $_SERVER[$key] ?? $default;
};
$host = $env('DB_HOST', 'db');
$port = $env('DB_PORT', '3306');
$dbname = $env('DB_NAME', 'hygiene_audit');
$charset = $env('DB_CHARSET', 'utf8mb4');
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => $host,
            'database' => $dbname,
            'username' => $env('DB_USER', 'root'),
            'password' => $env('DB_PASSWORD', 'root'),
            'hostport' => $port,
            'charset' => $charset,
            'prefix' => '',
            'dsn' => "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}",
            'params' => [\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$charset}' COLLATE 'utf8mb4_unicode_ci'"],
        ],
    ],
];
