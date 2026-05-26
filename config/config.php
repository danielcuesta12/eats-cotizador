<?php
// Auto-generado por setup.php el 19/05/2026 21:20 — NO EDITAR
define('DB_HOST',   'localhost');
define('DB_NAME',   'ebakxdhm_cotizador');
define('DB_USER',   'ebakxdhm_cotizador');
define('DB_PASS',   'R1o9m5u3lo');
define('DB_CHARSET','utf8mb4');

define('APP_NAME',    'El Gringo Cotizador');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'https://elgringo.pe/cotizador');
define('APP_PATH',    '/home/ebakxdhm/elgringo/cotizador');
define('APP_SECRET',  '991dfa1d9c2ef70d15d56bc7f815584e7ea9c311ec6af8f600559dc54130fb78');
define('REMEMBER_DAYS', 30);

define('UPLOAD_PATH', APP_PATH . '/assets/img/uploads/');
define('UPLOAD_URL',  APP_URL  . '/assets/img/uploads/');
define('MAX_FILE_SIZE', 2097152);
define('ALLOWED_IMG_TYPES', array('image/jpeg', 'image/png', 'image/webp'));

date_default_timezone_set('America/Lima');
define('DEBUG_MODE', false);
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_path', '/');
    session_start();
}