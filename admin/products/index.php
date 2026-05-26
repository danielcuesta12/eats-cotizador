<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();
if (!isAdmin()) { flashMessage('error','Sin permisos.'); redirect('/admin/dashboard.php'); }

// Eliminar producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verifyCsrf();
    $id = cleanInt($_POST['delete_id']);
    $prod = Database::fetch("SELECT image FROM products WHERE id = ?", [$id]);
    if ($prod) {
        // Eliminar imagen si existe
        if ($prod['image'] && file_exists(UPLOAD_PATH . $prod['image'])) {
            @unlink(UPLOAD_PATH . $prod['image']);
        }
        Database::execute("DELETE FROM products WHERE id = ?", [$id]);
        flashMessage('success', 'Producto eliminado.');
    }
    redirect('/admin/products/index.php');
}

// Toggle activo/inactivo vía AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    verifyCsrf();
    $id = cleanInt($_POST['toggle_id']);
    Database::execute("UPDATE products SET active = NOT active WHERE id = ?", [$id]);
    echo json_encode(['ok' => true]);
    exit;
}

// Filtros
$search   = clean($_GET['q']   ?? '');
$catFilter = cleanInt($_GET['cat'] ?? 0);
$statusF  = clean($_GET['status'] ?? '');

$page    = max(1, cleanInt($_GET['page'] ?? 1));
$perPage = 20;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = '%'.$search.'%';
    $params[] = '%'.$search.'%';
}
if ($catFilter) {
    $where[]  = 'p.category_id = ?';
    $params[] = $catFilter;
}
if ($statusF === 'active')   { $where[] = 'p.active = 1'; }
if ($statusF === 'inactive') { $where[] = 'p.active = 0'; }

$whereStr = implode(' AND ', $where);

$total = (int)Database::fetch(
    "SELECT COUNT(*) as n FROM products p WHERE $whereStr", $params
)['n'];

$pag      = paginate($total, $perPage, $page);
$products = Database::fetchAll(
    "SELECT p.*, c.name as category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE $whereStr
     ORDER BY c.sort_order, p.sort_order, p.name
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $pag['offset']])
);

$categories = Database::fetchAll("SELECT id, name FROM categories WHERE active=1 ORDER BY sort_order, name");

$pageTitle  = 'Productos';
$activePage = 'products';
include __DIR__ . '/../layout-top.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Productos</h1>
    <p><?= $total ?> productos en el catálogo</p>
  </div>
  <a href="<?= APP_URL ?>/admin/products/form.php" class="btn btn-primary">+ Nuevo producto</a>
</div>

<div class="card">
  <!-- Toolbar con filtros -->
  <div class="toolbar">
    <form method="get" style="display:contents">
      <div class="search-bar">
        <span class="search-icon">🔍</span>
        <input type="text" name="q" value="<?= clean($search) ?>"
               placeholder="Buscar producto…" oninput="this.form.submit()">
      </div>

      <select name="cat" onchange="this.form.submit()" style="width:auto;padding:9px 14px">
        <option value="">Todas las categorías</option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $catFilter == $c['id'] ? 'selected' : '' ?>>
          <?= clean($c['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>

      <select name="status" onchange="this.form.submit()" style="width:auto;padding:9px 14px">
        <option value="">Todos los estados</option>
        <option value="active"   <?= $statusF==='active'   ? 'selected':'' ?>>Solo activos</option>
        <option value="inactive" <?= $statusF==='inactive' ? 'selected':'' ?>>Solo inactivos</option>
      </select>

      <?php if ($search || $catFilter || $statusF): ?>
        <a href="?" class="btn btn-ghost btn-sm">✕ Limpiar</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($products)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">🍔</div>
      <h3>Sin productos</h3>
      <p>Agrega tus productos para poder cotizarlos</p>
      <a href="<?= APP_URL ?>/admin/products/form.php" class="btn btn-primary">+ Nuevo producto</a>
    </div>
  <?php else: ?>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:56px">Img</th>
          <th>Nombre</th>
          <th>Categoría</th>
          <th>Precio / persona</th>
          <th>Precio / evento</th>
          <th>Estado</th>
          <th style="width:150px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr id="prod-row-<?= $p['id'] ?>">
          <td>
            <?php if ($p['image']): ?>
              <img src="<?= UPLOAD_URL . clean($p['image']) ?>"
                   style="width:42px;height:42px;object-fit:cover;border-radius:8px;display:block">
            <?php else: ?>
              <div style="width:42px;height:42px;background:#f0f0f0;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px">🍔</div>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= clean($p['name']) ?></strong>
            <?php if ($p['description']): ?>
              <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
                <?= clean(mb_substr($p['description'],0,60)) ?>…
              </div>
            <?php endif; ?>
          </td>
          <td>
            <?= $p['category_name']
                ? '<span class="badge badge-info">'.clean($p['category_name']).'</span>'
                : '<span style="color:var(--text-muted)">—</span>' ?>
          </td>
          <td><strong><?= formatMoney((float)$p['price_per_person']) ?></strong></td>
          <td><?= formatMoney((float)$p['price_per_event']) ?></td>
          <td>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0">
              <input type="checkbox"
                     <?= $p['active'] ? 'checked' : '' ?>
                     onchange="toggleProduct(<?= $p['id'] ?>, this)"
                     style="width:18px;height:18px;accent-color:var(--red)">
              <span style="font-size:13px;color:var(--text-secondary)">
                <?= $p['active'] ? 'Activo' : 'Inactivo' ?>
              </span>
            </label>
          </td>
          <td>
            <div class="td-actions">
              <a href="form.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
              <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                  data-confirm="¿Eliminar el producto «<?= clean($p['name']) ?>»?">✕</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <?php if ($pag['total_pages'] > 1): ?>
  <div class="pagination">
    <?php if ($pag['has_prev']): ?>
      <a href="?q=<?= urlencode($search) ?>&cat=<?= $catFilter ?>&status=<?= $statusF ?>&page=<?= $page-1 ?>" class="page-btn">‹</a>
    <?php endif; ?>
    <?php for ($i = max(1,$page-2); $i <= min($pag['total_pages'],$page+2); $i++): ?>
      <a href="?q=<?= urlencode($search) ?>&cat=<?= $catFilter ?>&status=<?= $statusF ?>&page=<?= $i ?>"
         class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($pag['has_next']): ?>
      <a href="?q=<?= urlencode($search) ?>&cat=<?= $catFilter ?>&status=<?= $statusF ?>&page=<?= $page+1 ?>" class="page-btn">›</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<script>
const csrf = '<?= csrfToken() ?>';

async function toggleProduct(id, checkbox) {
  const label = checkbox.nextElementSibling;
  const fd    = new FormData();
  fd.append('toggle_id',   id);
  fd.append('csrf_token',  csrf);
  const res = await fetch(location.href, { method:'POST', body: fd });
  if (res.ok) {
    label.textContent = checkbox.checked ? 'Activo' : 'Inactivo';
  } else {
    checkbox.checked = !checkbox.checked;
    alert('Error al cambiar estado.');
  }
}
</script>

<?php include __DIR__ . '/../layout-bottom.php'; ?>
