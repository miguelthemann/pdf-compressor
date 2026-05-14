<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name($config['session_name']);
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

if (!isset($_SESSION['files']) || !is_array($_SESSION['files'])) {
    $_SESSION['files'] = [];
}

require_once __DIR__ . '/helpers.php';

cleanupExpiredFiles($config);
