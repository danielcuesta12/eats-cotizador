<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

if (isLoggedIn()) {
    // Limpiar token "recordarme" en la BD
    Database::execute(
        "UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE id = ?",
        [$_SESSION['user_id']]
    );
}

// Destruir sesión
$_SESSION = [];
session_destroy();

// Eliminar cookie recordarme
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

redirect('/auth/login.php');
