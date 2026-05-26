<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();
requireAdmin();

$id  = cleanInt($_GET['id'] ?? 0);
$pkg = $id ? Database::fetch("SELECT * FROM packages WHERE id=?",[$id]) : null;
if ($id && !$pkg) { flashMessage('error','Paquete no encontrado.'); redirect('/admin/packages/index.php'); }

$isEdit    = (bool)$pkg;
$errors    = [];
$allProds  = Database::fetchAll("SELECT p.*,c.name as cat FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 ORDER BY c.sort_order,p.name");
$pkgProds  = $isEdit ? Database::fetchAll("SELECT * FROM package_products WHERE package_id=?",[$id]) : [];
$pkgProdMap = array_column($pkgProds,'quantity','product_id');
$data      = $pkg ?? ['name'=>'','description'=>'','price'=>'','active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $data = [
        'name'        => clean($_POST['name']        ?? ''),
        'description' => clean($_POST['description'] ?? ''),
        'price'       => max(0, cleanFloat($_POST['price'] ?? 0)),
        'active'      => isset($_POST['active']) ? 1 : 0,
    ];
    $selectedProds = $_POST['products'] ?? [];

    if (!$data['name']) $errors[] = 'El nombre es obligatorio.';

    if (empty($errors)) {
        if ($isEdit) {
            Database::execute(
                "UPDATE packages SET name=?,description=?,price=?,active=? WHERE id=?",
                [$data['name'],$data['description'],$data['price'],$data['active'],$id]
            );
            Database::execute("DELETE FROM package_products WHERE package_id=?",[$id]);
            $pkgId = $id;
        } else {
            $pkgId = Database::insert(
                "INSERT INTO packages (name,description,price,active) VALUES (?,?,?,?)",
                [$data['name'],$data['description'],$data['price'],$data['active']]
            );
        }
        // Insertar productos del paquete
        foreach ($selectedProds as $pid => $qty) {
            $pid = cleanInt($pid);
            $qty = max(0.1, cleanFloat($qty));
            if ($pid) {
                Database::insert(
                    "INSERT INTO package_products (package_id,product_id,quantity) VALUES (?,?,?)",
                    [$pkgId,$pid,$qty]
                );
            }
        }
        flashMessage('success', $isEdit ? 'Paquete actualizado.' : 'Paquete creado.');
        redirect('/admin/packages/index.php');
    }
}

$pageTitle  = $isEdit ? 'Editar paquete' : 'Nuevo paquete';
$activePage = 'packages';
include __DIR__ . '/../layout-top.php';
?>

<div class="breadcrumb">
  <a href="<?= APP_URL ?>/admin/packages/index.php">Paquetes</a>
  <span class="breadcrumb-sep">›</span>
  <span class="breadcrumb-current"><?= $isEdit ? 'Editar' : 'Nuevo' ?></span>
</div>
<div class="page-header">
  <div class="page-header-left"><h1><?= $pageTitle ?></h1></div>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error">✗ <?= $e ?></div>
<?php endforeach; ?>

<form method="post">
<?= csrfField() ?>
<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

  <!-- Productos a incluir -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🍔 Productos del paquete</span>
      <span style="font-size:13px;color:var(--text-muted)" id="selectedCount">0 seleccionados</span>
    </div>
    <div class="card-body" style="padding:0">
      <!-- Buscador rápido -->
      <div style="padding:14px 18px;border-bottom:1px solid var(--border)">
        <input type="text" id="prodSearch" placeholder="Filtrar productos…"
               oninput="filterProds(this.value)"
               style="width:100%">
      </div>
      <!-- Lista de productos -->
      <div style="max-height:480px;overflow-y:auto" id="prodList">
        <?php
        $lastCat = null;
        foreach ($allProds as $p):
          if ($p['cat'] !== $lastCat):
            $lastCat = $p['cat'];
        ?>
          <div class="prod-cat-label" style="padding:8px 18px;background:#f8f8f8;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:1px solid var(--border)">
            <?= clean($p['cat'] ?? 'Sin categoría') ?>
          </div>
        <?php endif; ?>
        <label class="prod-item" style="display:flex;align-items:center;gap:12px;padding:12px 18px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s"
               onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
          <input type="checkbox" name="products[<?= $p['id'] ?>]" value="1"
                 data-prod-id="<?= $p['id'] ?>"
                 <?= isset($pkgProdMap[$p['id']]) ? 'checked' : '' ?>
                 onchange="updateCount()"
                 style="width:18px;height:18px;accent-color:var(--red);flex-shrink:0">
          <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600"><?= clean($p['name']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)">
              <?= $p['price_per_person'] > 0 ? formatMoney((float)$p['price_per_person']).' /pers' : '' ?>
              <?= $p['price_per_event']  > 0 ? '· '.formatMoney((float)$p['price_per_event']).' /evento' : '' ?>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:6px">
            <span style="font-size:12px;color:var(--text-muted)">Cant:</span>
            <input type="number" name="products_qty[<?= $p['id'] ?>]"
                   value="<?= $pkgProdMap[$p['id']] ?? 1 ?>"
                   min="0.1" step="1" style="width:60px;padding:4px 8px;font-size:13px;border:1.5px solid var(--border);border-radius:6px;text-align:center"
                   onclick="event.stopPropagation()">
          </div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Info del paquete -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card">
      <div class="card-header"><span class="card-title">Información</span></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-required">Nombre del paquete</label>
          <input type="text" name="name" value="<?= clean($data['name']) ?>"
                 placeholder="Ej: Menú Corporativo Completo" required autofocus>
        </div>
        <div class="form-group">
          <label>Descripción</label>
          <textarea name="description" rows="3"
                    placeholder="Qué incluye este paquete…"><?= clean($data['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label>Precio del paquete (S/)</label>
          <input type="number" name="price"
                 value="<?= number_format((float)$data['price'],2,'.','') ?>"
                 min="0" step="0.50" placeholder="0.00">
          <div class="form-hint">Precio especial del combo. Al cotizar se puede sobreescribir.</div>
        </div>
        <label class="toggle-wrap" style="cursor:pointer">
          <input type="checkbox" name="active" value="1"
                 <?= $data['active'] ? 'checked' : '' ?>
                 style="width:18px;height:18px;accent-color:var(--red)">
          <span class="toggle-label">Paquete activo</span>
        </label>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg btn-block">
      <?= $isEdit ? '💾 Guardar cambios' : '+ Crear paquete' ?>
    </button>
    <a href="<?= APP_URL ?>/admin/packages/index.php" class="btn btn-ghost btn-block">Cancelar</a>
  </div>

</div>
</form>

<script>
function updateCount() {
  const n = document.querySelectorAll('[data-prod-id]:checked').length;
  document.getElementById('selectedCount').textContent = n + ' seleccionado' + (n!==1?'s':'');
}

function filterProds(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.prod-item').forEach(item => {
    const name = item.querySelector('[style*="font-weight"]').textContent.toLowerCase();
    item.style.display = name.includes(q) ? '' : 'none';
  });
  document.querySelectorAll('.prod-cat-label').forEach(label => {
    const next = label.nextElementSibling;
    label.style.display = next && next.style.display !== 'none' ? '' : 'none';
  });
}

// Sync checkbox con qty input (qty > 0 check)
document.querySelectorAll('[data-prod-id]').forEach(cb => {
  cb.addEventListener('change', function() {
    const qtyName = 'products_qty[' + this.dataset.prodId + ']';
    const qtyInput = document.querySelector('[name="' + qtyName + '"]');
    if (this.checked && qtyInput && parseFloat(qtyInput.value) < 0.1) {
      qtyInput.value = 1;
    }
  });
});

updateCount();
</script>

<?php include __DIR__ . '/../layout-bottom.php'; ?>
