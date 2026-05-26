<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();
if (!isAdmin()) { flashMessage('error','Sin permisos.'); redirect('/admin/dashboard.php'); }

// Eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verifyCsrf();
    $id = cleanInt($_POST['delete_id']);
    // Verificar si tiene productos
    $count = Database::fetch("SELECT COUNT(*) as n FROM products WHERE category_id = ?", [$id]);
    if ((int)$count['n'] > 0) {
        flashMessage('error', 'No puedes eliminar una categoría que tiene productos asignados.');
    } else {
        Database::execute("DELETE FROM categories WHERE id = ?", [$id]);
        flashMessage('success', 'Categoría eliminada.');
    }
    redirect('/admin/categories/index.php');
}

// Cambiar orden (drag simple via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder'])) {
    verifyCsrf();
    $ids = array_map('intval', $_POST['ids'] ?? []);
    foreach ($ids as $i => $id) {
        Database::execute("UPDATE categories SET sort_order = ? WHERE id = ?", [$i, $id]);
    }
    http_response_code(200);
    exit;
}

$search = clean($_GET['q'] ?? '');
$params = [];
$where  = '';
if ($search) {
    $where    = 'WHERE name LIKE ?';
    $params[] = '%' . $search . '%';
}

$categories = Database::fetchAll(
    "SELECT c.*, COUNT(p.id) as product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.active = 1
     $where
     GROUP BY c.id
     ORDER BY c.sort_order, c.name",
    $params
);

$pageTitle  = 'Categorías';
$activePage = 'categories';
include __DIR__ . '/../layout-top.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Categorías</h1>
    <p>Organiza tus productos en categorías</p>
  </div>
  <?php if (isAdmin()): ?>
  <a href="<?= APP_URL ?>/admin/categories/form.php" class="btn btn-primary">+ Nueva categoría</a>
  <?php endif; ?>
</div>

<div class="card">
  <div class="toolbar">
    <div class="search-bar">
      <span class="search-icon">🔍</span>
      <form method="get">
        <input type="text" name="q" value="<?= clean($search) ?>" placeholder="Buscar categoría…" oninput="this.form.submit()">
      </form>
    </div>
    <span style="color:var(--text-muted);font-size:13px"><?= count($categories) ?> categorías</span>
  </div>

  <?php if (empty($categories)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">🏷️</div>
      <h3>Sin categorías</h3>
      <p>Crea tu primera categoría para organizar los productos</p>
      <a href="<?= APP_URL ?>/admin/categories/form.php" class="btn btn-primary">+ Nueva categoría</a>
    </div>
  <?php else: ?>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>Nombre</th>
          <th>Descripción</th>
          <th>Productos activos</th>
          <th>Estado</th>
          <th style="width:140px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $i => $cat): ?>
        <tr>
          <td style="color:var(--text-muted)"><?= $i+1 ?></td>
          <td><strong><?= clean($cat['name']) ?></strong></td>
          <td style="color:var(--text-muted)"><?= clean($cat['description'] ?? '—') ?></td>
          <td>
            <span class="badge badge-info"><?= (int)$cat['product_count'] ?> productos</span>
          </td>
          <td>
            <?php if ($cat['active']): ?>
              <span class="badge badge-success">Activa</span>
            <?php else: ?>
              <span class="badge badge-secondary">Inactiva</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="td-actions">
              <a href="form.php?id=<?= $cat['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
              <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="delete_id" value="<?= $cat['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                  data-confirm="¿Eliminar la categoría «<?= clean($cat['name']) ?>»?">
                  Eliminar
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../layout-bottom.php'; ?>
