<?php
// ============================================================
// config.php — Carga variables desde .env
// ============================================================

$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    die('Error: archivo .env no encontrado en ' . $envFile);
}

$env = parse_ini_file($envFile);
if ($env === false) {
    die('Error: no se pudo leer el archivo .env');
}

define('DB_HOST',    $env['DB_HOST']    ?? 'localhost');
define('DB_NAME',    $env['DB_NAME']    ?? '');
define('DB_USER',    $env['DB_USER']    ?? '');
define('DB_PASS',    $env['DB_PASS']    ?? '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'Cotizador');
define('APP_VERSION', '1.0.0');
define('APP_URL',     rtrim($env['APP_URL']  ?? '', '/'));
define('APP_PATH',    rtrim($env['APP_PATH'] ?? '', '/'));
define('APP_SECRET',  $env['APP_SECRET'] ?? bin2hex(random_bytes(32)));
define('REMEMBER_DAYS', 30);

define('UPLOAD_PATH', rtrim($env['UPLOAD_PATH'] ?? (APP_PATH . '/assets/img/uploads/'), '/') . '/');
define('UPLOAD_URL',  rtrim($env['UPLOAD_URL']  ?? (APP_URL  . '/assets/img/uploads/'), '/') . '/');
define('MAX_FILE_SIZE', (int)($env['MAX_FILE_SIZE'] ?? 2097152));
define('ALLOWED_IMG_TYPES', array('image/jpeg', 'image/png', 'image/webp'));

define('MAIL_FROM',      $env['MAIL_FROM']      ?? '');
define('MAIL_FROM_NAME', $env['MAIL_FROM_NAME'] ?? APP_NAME);
define('MAIL_REPLY_TO',  $env['MAIL_REPLY_TO']  ?? '');

date_default_timezone_set('America/Lima');
define('DEBUG_MODE', false);
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    $sessionName = $env['SESSION_NAME'] ?? 'cotizador_session';
    session_name($sessionName);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_path', '/');
    session_start();
}
