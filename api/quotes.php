<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

$action = clean($_GET['action'] ?? '');

switch ($action) {

    // Buscar productos activos
    case 'search_products':
        $q    = clean($_GET['q'] ?? '');
        $cat  = cleanInt($_GET['cat'] ?? 0);
        $w    = ['p.active = 1'];
        $p    = [];
        if ($q)   { $w[] = '(p.name LIKE ? OR p.description LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
        if ($cat) { $w[] = 'p.category_id = ?'; $p[] = $cat; }
        $where = implode(' AND ', $w);
        $rows  = Database::fetchAll(
            "SELECT p.id, p.name, p.description, p.price_per_person, p.price_per_event,
                    p.image, c.name as category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE $where
             ORDER BY c.sort_order, p.sort_order, p.name
             LIMIT 30",
            $p
        );
        echo json_encode(['ok' => true, 'data' => $rows]);
        break;

    // Búsqueda de clientes (para select2 / autocomplete)
    case 'search_clients':
        $q   = clean($_GET['q'] ?? '');
        $p   = [];
        $w   = ['active = 1'];
        if ($q) { $w[] = '(name LIKE ? OR ruc_dni LIKE ? OR email LIKE ?)'; $s = "%$q%"; $p = [$s,$s,$s]; }
        $where = implode(' AND ', $w);
        $rows  = Database::fetchAll(
            "SELECT id, name, type, ruc_dni, email, phone FROM clients WHERE $where ORDER BY name LIMIT 20",
            $p
        );
        echo json_encode(['ok' => true, 'data' => $rows]);
        break;

    // Obtener categorías activas
    case 'categories':
        $rows = Database::fetchAll(
            "SELECT id, name FROM categories WHERE active = 1 ORDER BY sort_order, name"
        );
        echo json_encode(['ok' => true, 'data' => $rows]);
        break;

    // Obtener un cliente por ID
    case 'get_client':
        $id  = cleanInt($_GET['id'] ?? 0);
        $row = Database::fetch("SELECT * FROM clients WHERE id = ? AND active = 1", [$id]);
        echo json_encode(['ok' => (bool)$row, 'data' => $row]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
}
