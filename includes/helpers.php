<?php
// ============================================================
// helpers.php — Funciones de utilidad global
// ============================================================
require_once __DIR__ . '/../config/database.php';

// ------------------------------------------------------------
// AUTENTICACIÓN Y SESIÓN
// ------------------------------------------------------------

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        // Quitar el prefijo /cotizador de REQUEST_URI para que redirect() lo reconstruya bien
        $appPrefix = parse_url(APP_URL, PHP_URL_PATH); // ej: '/cotizador'
        $requestUri = $_SERVER['REQUEST_URI'];
        // Si REQUEST_URI empieza con el prefijo, quitarlo
        if ($appPrefix && strpos($requestUri, $appPrefix) === 0) {
            $requestUri = substr($requestUri, strlen($appPrefix));
        }
        $_SESSION['redirect_after_login'] = $requestUri ?: '/admin/dashboard';
        redirect('/auth/login');
    }
}

function requireAdmin(): void
{
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        flashMessage('error', 'No tienes permisos para acceder a esa sección.');
        redirect('/admin/dashboard.php');
    }
}

function currentUser(): ?array
{
    if (!isLoggedIn()) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['user_role'],
    ];
}

function isAdmin(): bool
{
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

// ------------------------------------------------------------
// REDIRECCIÓN
// ------------------------------------------------------------

function redirect(string $path): never
{
    $url = str_starts_with($path, 'http') ? $path : APP_URL . $path;
    header('Location: ' . $url);
    exit;
}

// ------------------------------------------------------------
// MENSAJES FLASH
// ------------------------------------------------------------

function flashMessage(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashMessages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ------------------------------------------------------------
// SEGURIDAD: CSRF
// ------------------------------------------------------------

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('Token de seguridad inválido. Por favor recarga la página.');
    }
}

// ------------------------------------------------------------
// SANITIZACIÓN Y VALIDACIÓN
// ------------------------------------------------------------

function clean(mixed $value): string
{
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
}

function cleanInt(mixed $value): int
{
    return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
}

function cleanFloat(mixed $value): float
{
    return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

function cleanEmail(mixed $value): string|false
{
    return filter_var(trim((string)$value), FILTER_VALIDATE_EMAIL);
}

// ------------------------------------------------------------
// FORMATEO
// ------------------------------------------------------------

function formatMoney(float $amount, string $prefix = 'S/ '): string
{
    return $prefix . number_format($amount, 2, '.', ',');
}

function formatDate(string|null $date, string $format = 'd/m/Y'): string
{
    if (empty($date)) return '—';
    return date($format, strtotime($date));
}

function formatDatetime(string|null $dt): string
{
    if (empty($dt)) return '—';
    return date('d/m/Y H:i', strtotime($dt));
}

// ------------------------------------------------------------
// NÚMEROS DE COTIZACIÓN
// ------------------------------------------------------------

function generateQuoteNumber(): string
{
    $prefix = getSetting('quote_prefix', 'EG');
    $year   = date('Y');

    // Buscar el último número del año
    $last = Database::fetch(
        "SELECT quote_number FROM quotes
         WHERE quote_number LIKE ? ORDER BY id DESC LIMIT 1",
        [$prefix . '-' . $year . '-%']
    );

    if ($last) {
        $parts = explode('-', $last['quote_number']);
        $num   = (int) end($parts) + 1;
    } else {
        $num = 1;
    }

    return sprintf('%s-%s-%04d', $prefix, $year, $num);
}

// ------------------------------------------------------------
// CONFIGURACIÓN DE EMPRESA
// ------------------------------------------------------------

function getSetting(string $key, mixed $default = ''): string
{
    static $cache = [];
    if (!isset($cache[$key])) {
        $row = Database::fetch(
            "SELECT `value` FROM company_settings WHERE `key` = ?",
            [$key]
        );
        $cache[$key] = $row ? $row['value'] : $default;
    }
    return (string) $cache[$key];
}

function setSetting(string $key, string $value): void
{
    Database::execute(
        "INSERT INTO company_settings (`key`, `value`)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
        [$key, $value]
    );
}

// ------------------------------------------------------------
// TOKENS PÚBLICOS
// ------------------------------------------------------------

function generateToken(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

// ------------------------------------------------------------
// SUBIDA DE IMÁGENES
// ------------------------------------------------------------

function uploadImage(array $file, string $folder = 'products'): string|false
{
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_FILE_SIZE)    return false;

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ALLOWED_IMG_TYPES, true)) return false;

    $ext      = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => false,
    };
    if (!$ext) return false;

    $dir = UPLOAD_PATH . $folder . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = uniqid('img_', true) . '.' . $ext;
    $dest     = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;

    return $folder . '/' . $filename;
}

// ------------------------------------------------------------
// ESTADOS DE COTIZACIÓN
// ------------------------------------------------------------

function quoteStatusLabel(string $status): string
{
    return match($status) {
        'borrador'  => 'Borrador',
        'enviada'   => 'Enviada',
        'aceptada'  => 'Aceptada',
        'rechazada' => 'Rechazada',
        default     => ucfirst($status),
    };
}

function quoteStatusBadge(string $status): string
{
    $class = match($status) {
        'borrador'  => 'badge-secondary',
        'enviada'   => 'badge-info',
        'aceptada'  => 'badge-success',
        'rechazada' => 'badge-danger',
        default     => 'badge-secondary',
    };
    return '<span class="badge ' . $class . '">' . quoteStatusLabel($status) . '</span>';
}

// ------------------------------------------------------------
// IGV
// ------------------------------------------------------------

function igvLabel(string $type): string
{
    return match($type) {
        '10.5' => 'IGV 10.5%',
        '18'   => 'IGV 18%',
        default => 'Sin IGV',
    };
}

function igvRate(string $type): float
{
    return match($type) {
        '10.5' => 0.105,
        '18'   => 0.18,
        default => 0.0,
    };
}

// ------------------------------------------------------------
// PAGINACIÓN
// ------------------------------------------------------------

function paginate(int $total, int $perPage, int $currentPage): array
{
    $totalPages = (int) ceil($total / $perPage);
    $offset     = ($currentPage - 1) * $perPage;
    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => max(0, $offset),
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
    ];
}
