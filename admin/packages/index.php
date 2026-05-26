<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();
requireAdmin();

// Eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verifyCsrf();
    $id = cleanInt($_POST['delete_id']);
    Database::execute("DELETE FROM package_products WHERE package_id=?",[$id]);
    Database::execute("DELETE FROM packages WHERE id=?",[$id]);
    flashMessage('success','Paquete eliminado.');
    redirect('/admin/packages/index.php');
}

$packages = Database::fetchAll(
    "SELECT p.*,
       COUNT(pp.id) as product_count
     FROM packages p
     LEFT JOIN package_products pp ON pp.package_id = p.id
     GROUP BY p.id
     ORDER BY p.name"
);

$pageTitle  = 'Paquetes';
$activePage = 'packages';
include __DIR__ . '/../layout-top.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>📦 Paquetes</h1>
    <p>Combos de productos con precio especial</p>
  </div>
  <a href="<?= APP_URL ?>/admin/packages/form.php" class="btn btn-primary">+ Nuevo paquete</a>
</div>

<div class="alert alert-info">
  💡 Los paquetes son combos de productos que puedes agregar en una sola línea al cotizar. Útil para menús completos o servicios agrupados.
</div>

<div class="card">
  <?php if (empty($packages)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📦</div>
      <h3>Sin paquetes</h3>
      <p>Crea combos de productos para cotizar más rápido</p>
      <a href="<?= APP_URL ?>/admin/packages/form.php" class="btn btn-primary">+ Nuevo paquete</a>
    </div>
  <?php else: ?>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table class="data-table">
      <thead>
        <tr>
          <th>Nombre del paquete</th>
          <th>Descripción</th>
          <th>Precio</th>
          <th>Productos incluidos</th>
          <th>Estado</th>
          <th style="width:140px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($packages as $pkg): ?>
        <tr>
          <td><strong><?= clean($pkg['name']) ?></strong></td>
          <td style="color:var(--text-muted);font-size:13px"><?= clean(mb_substr($pkg['description']??'',0,60)) ?></td>
          <td><strong><?= formatMoney((float)$pkg['price']) ?></strong></td>
          <td><span class="badge badge-info"><?= $pkg['product_count'] ?> items</span></td>
          <td><span class="badge <?= $pkg['active']?'badge-success':'badge-secondary' ?>"><?= $pkg['active']?'Activo':'Inactivo' ?></span></td>
          <td>
            <div class="td-actions">
              <a href="form.php?id=<?= $pkg['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
              <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="delete_id" value="<?= $pkg['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                        data-confirm="¿Eliminar paquete «<?= clean($pkg['name']) ?>»?">✕</button>
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
