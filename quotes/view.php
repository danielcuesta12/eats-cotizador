<?php
// ============================================================
// view.php — Vista pública de cotización (sin login)
// Acceso via token único: /quotes/view.php?token=xxxx
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$token = clean($_GET['token'] ?? '');

if (!$token) {
    http_response_code(404);
    die('<h2>Link no válido</h2>');
}

$quote = Database::fetch(
    "SELECT q.*, c.name as client_name FROM quotes q JOIN clients c ON c.id=q.client_id WHERE q.public_token = ?",
    [$token]
);

if (!$quote) {
    http_response_code(404);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>No encontrado</title></head><body style="font-family:sans-serif;text-align:center;padding:60px"><h2>Cotización no encontrada</h2><p>El link puede haber expirado o no ser válido.</p></body></html>');
}

// Redirigir al PDF en modo HTML con el token
header('Location: ' . APP_URL . '/quotes/pdf.php?token=' . urlencode($token) . '&mode=public');
exit;
