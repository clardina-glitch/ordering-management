<?php

$env = static function (string $key, string $default): string {
    $value = getenv($key);

    return $value === false ? $default : $value;
};

$config = [
    'db' => [
        'host' => $env('DB_HOST', 'sqlXXX.infinityfree.com'),
        'port' => $env('DB_PORT', '3306'),
        'name' => $env('DB_NAME', 'if0_XXXXXXXX_ordering'),
        'user' => $env('DB_USER', 'if0_XXXXXXXX'),
        'password' => $env('DB_PASSWORD', ''),
    ],
    'session_name' => 'ordering_sid',
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

return $config;
