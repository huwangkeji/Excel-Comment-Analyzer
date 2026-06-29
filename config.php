<?php
/**
 * Excel Comment Analyzer - Configuration
 * Version 1.0
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Paths
define('ROOT_DIR', __DIR__);
define('UPLOAD_DIR', ROOT_DIR . '/uploads');
define('INCLUDES_DIR', ROOT_DIR . '/includes');
define('SESSION_DIR', ROOT_DIR . '/sessions');

// Upload limits
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_EXTENSIONS', ['xlsx', 'xls', 'csv']);

// Create upload dir if not exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Create sessions dir if not exists
if (!is_dir(SESSION_DIR)) {
    mkdir(SESSION_DIR, 0755, true);
}

// Session — use project-local directory to ensure cross-request persistence
// Cookie params must be set BEFORE session_start()
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_save_path(SESSION_DIR);
session_start();

// Ensure session is committed after each script
register_shutdown_function(function () {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
});

// Autoload includes
spl_autoload_register(function ($class) {
    $file = INCLUDES_DIR . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
