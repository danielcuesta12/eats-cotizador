<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();
if (!isAdmin()) { flashMessage('error','Sin permisos.'); redirect('/admin/dashboard.php'); }

$id     = cleanInt($_GET['id'] ?? 0);
$prod   = $id ? Database::fetch("SELECT * FROM products WHERE id = ?", [$id]) : null;
if ($id && !$prod) { flashMessage('error','Producto no encontrado.'); redirect('/admin/products/index.php'); }

$isEdit     = (bool)$prod;
$errors     = [];
$categories = Database::fetchAll("SELECT id,name FROM categories WHERE active=1 ORDER BY sort_order,name");
$data       = $prod ?? ['name'=>'','description'=>'','price_per_person'=>'','price_per_event'=>'','category_id'=>'','image'=>'','active'=>1,'sort_order'=>0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'name'             => clean($_POST['name']             ?? ''),
        'description'      => clean($_POST['description']      ?? ''),
        'price_per_person' => cleanFloat($_POST['price_per_person'] ?? 0),
        'price_per_event'  => cleanFloat($_POST['price_per_event']  ?? 0),
        'category_id'      => cleanInt($_POST['category_id']   ?? 0) ?: null,
        'sort_order'       => cleanInt($_POST['sort_order']    ?? 0),
        'active'           => isset($_POST['active']) ? 1 : 0,
        'image'            => $prod['image'] ?? '',
    ];

    if (!$data['name'])                              $errors[] = 'El nombre es obligatorio.';
    if ($data['price_per_person'] < 0)               $errors[] = 'El precio por persona no puede ser negativo.';
    if ($data['price_per_event'] < 0)                $errors[] = 'El precio por evento no puede ser negativo.';

    // Manejar subida de imagen
    if (!empty($_FILES['image']['name'])) {
        $uploaded = uploadImage($_FILES['image'], 'products');
        if ($uploaded === false) {
            $errors[] = 'Error al subir la imagen. Usa JPG, PNG o WebP (máx. 2MB).';
        } else {
            // Eliminar imagen anterior
            if (!empty($prod['image']) && file_exists(UPLOAD_PATH . $prod['image'])) {
                @unlink(UPLOAD_PATH . $prod['image']);
            }
            $data['image'] = $uploaded;
        }
    }

    // Eliminar imagen si se marcó
    if (isset($_POST['remove_image']) && $prod['image']) {
        @unlink(UPLOAD_PATH . $prod['image']);
        $data['image'] = '';
    }

    if (empty($errors)) {
        if ($isEdit) {
            Database::execute(
                "UPDATE products SET name=?,description=?,price_per_person=?,price_per_event=?,
                 category_id=?,image=?,active=?,sort_order=? WHERE id=?",
                [$data['name'],$data['description'],$data['price_per_person'],$data['price_per_event'],
                 $data['category_id'],$data['image'],$data['active'],$data['sort_order'],$id]
            );
            flashMessage('success', 'Producto actualizado.');
        } else {
            Database::insert(
                "INSERT INTO products (name,description,price_per_person,price_per_event,category_id,image,active,sort_order)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$data['name'],$data['description'],$data['price_per_person'],$data['price_per_event'],
                 $data['category_id'],$data['image'],$data['active'],$data['sort_order']]
            );
            flashMessage('success', 'Producto creado.');
        }
        redirect('/admin/products/index.php');
    }
}

$pageTitle  = $isEdit ? 'Editar producto' : 'Nuevo producto';
$activePage = 'products';
include __DIR__ . '/../layout-top.php';
?>

<div class="breadcrumb">
  <a href="<?= APP_URL ?>/admin/products/index.php">Productos</a>
  <span class="breadcrumb-sep">›</span>
  <span class="breadcrumb-current"><?= $isEdit ? 'Editar' : 'Nuevo' ?></span>
</div>

<div class="page-header">
  <div class="page-header-left"><h1><?= $pageTitle ?></h1></div>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error">✗ <?= $e ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
<?= csrfField() ?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">

  <!-- Columna principal -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <div class="card">
      <div class="card-header"><span class="card-title">Información del producto</span></div>
      <div class="card-body">

        <div class="form-group">
          <label class="form-required">Nombre del producto</label>
          <input type="text" name="name" value="<?= clean($data['name']) ?>"
                 placeholder="Ej: The Classic Smash" required autofocus>
        </div>

        <div class="form-group">
          <label>Descripción</label>
          <textarea name="description" rows="4"
                    placeholder="Ingredientes, preparación, detalles relevantes para la cotización…"><?= clean($data['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label>Categoría</label>
          <select name="category_id">
            <option value="">Sin categoría</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $data['category_id'] == $c['id'] ? 'selected' : '' ?>>
              <?= clean($c['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>
    </div>

    <!-- Precios -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">💰 Precios</span>
      </div>
      <div class="card-body">

        <div class="alert alert-info" style="margin-bottom:20px">
          ℹ️ Al cotizar podrás elegir entre estos precios o ingresar un precio libre.
          Deja en <strong>0.00</strong> los que no apliquen.
        </div>

        <div class="form-row form-row-2">
          <div class="form-group">
            <label>Precio por persona (S/)</label>
            <input type="number" name="price_per_person"
                   value="<?= number_format((float)$data['price_per_person'], 2, '.', '') ?>"
                   min="0" step="0.50" placeholder="0.00">
            <div class="form-hint">Se multiplica por N° de personas</div>
          </div>
          <div class="form-group">
            <label>Precio por evento (S/)</label>
            <input type="number" name="price_per_event"
                   value="<?= number_format((float)$data['price_per_event'], 2, '.', '') ?>"
                   min="0" step="0.50" placeholder="0.00">
            <div class="form-hint">Precio fijo independiente de personas</div>
          </div>
        </div>

      </div>
    </div>

  </div>

  <!-- Columna lateral -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Imagen -->
    <div class="card">
      <div class="card-header"><span class="card-title">Imagen</span></div>
      <div class="card-body">
        <?php if (!empty($data['image'])): ?>
          <img src="<?= UPLOAD_URL . clean($data['image']) ?>"
               id="imgPreview"
               style="width:100%;max-height:200px;object-fit:cover;border-radius:8px;margin-bottom:12px">
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;cursor:pointer">
            <input type="checkbox" name="remove_image" value="1"
                   style="width:16px;height:16px;accent-color:var(--red)">
            <span style="font-size:13px;color:#dc2626">Eliminar imagen actual</span>
          </label>
        <?php else: ?>
          <img src="" id="imgPreview"
               style="width:100%;max-height:200px;object-fit:cover;border-radius:8px;margin-bottom:12px;display:none">
        <?php endif; ?>

        <label class="img-upload-box" for="imageInput" id="uploadBox">
          <div style="font-size:28px;margin-bottom:8px">📷</div>
          <div style="font-size:14px;font-weight:600;color:var(--text-secondary)">
            Subir imagen
          </div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:4px">
            JPG, PNG, WebP · Máx. 2MB
          </div>
          <input type="file" id="imageInput" name="image" accept="image/*"
                 style="display:none" data-preview="imgPreview">
        </label>
      </div>
    </div>

    <!-- Config adicional -->
    <div class="card">
      <div class="card-header"><span class="card-title">Configuración</span></div>
      <div class="card-body">
        <div class="form-group">
          <label>Orden de visualización</label>
          <input type="number" name="sort_order"
                 value="<?= (int)$data['sort_order'] ?>" min="0" step="1">
          <div class="form-hint">Menor número = primero en lista</div>
        </div>

        <label class="toggle-wrap" style="cursor:pointer">
          <input type="checkbox" name="active" value="1"
                 <?= $data['active'] ? 'checked' : '' ?>
                 style="width:18px;height:18px;accent-color:var(--red)">
          <span class="toggle-label">Producto activo</span>
        </label>
        <div class="form-hint" style="margin-top:6px">
          Los productos inactivos no aparecen en el cotizador
        </div>
      </div>
    </div>

    <!-- Acciones -->
    <div style="display:flex;flex-direction:column;gap:10px">
      <button type="submit" class="btn btn-primary btn-lg btn-block">
        <?= $isEdit ? '💾 Guardar cambios' : '+ Crear producto' ?>
      </button>
      <a href="<?= APP_URL ?>/admin/products/index.php" class="btn btn-ghost btn-block">
        Cancelar
      </a>
    </div>

  </div>
</div>

</form>

<script>
// Actualizar label del upload box cuando se selecciona archivo
document.getElementById('imageInput').addEventListener('change', function() {
  if (this.files[0]) {
    document.getElementById('uploadBox').querySelector('div:nth-child(2)').textContent =
      this.files[0].name;
    document.getElementById('imgPreview').style.display = 'block';
  }
});
</script>

<?php include __DIR__ . '/../layout-bottom.php'; ?>
