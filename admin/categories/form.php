<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();
if (!isAdmin()) { flashMessage('error','Sin permisos.'); redirect('/admin/dashboard.php'); }

$id  = cleanInt($_GET['id'] ?? 0);
$cat = $id ? Database::fetch("SELECT * FROM categories WHERE id = ?", [$id]) : null;
if ($id && !$cat) { flashMessage('error','Categoría no encontrada.'); redirect('/admin/categories/index.php'); }

$isEdit = (bool)$cat;
$errors = [];
$data   = $cat ?? ['name'=>'','description'=>'','sort_order'=>0,'active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $data = [
        'name'        => clean($_POST['name']        ?? ''),
        'description' => clean($_POST['description'] ?? ''),
        'sort_order'  => cleanInt($_POST['sort_order'] ?? 0),
        'active'      => isset($_POST['active']) ? 1 : 0,
    ];
    if (!$data['name']) $errors[] = 'El nombre es obligatorio.';

    if (empty($errors)) {
        if ($isEdit) {
            Database::execute(
                "UPDATE categories SET name=?,description=?,sort_order=?,active=? WHERE id=?",
                [$data['name'],$data['description'],$data['sort_order'],$data['active'],$id]
            );
            flashMessage('success','Categoría actualizada.');
        } else {
            Database::insert(
                "INSERT INTO categories (name,description,sort_order,active) VALUES (?,?,?,?)",
                [$data['name'],$data['description'],$data['sort_order'],$data['active']]
            );
            flashMessage('success','Categoría creada.');
        }
        redirect('/admin/categories/index.php');
    }
}

$pageTitle  = $isEdit ? 'Editar categoría' : 'Nueva categoría';
$activePage = 'categories';
include __DIR__ . '/../layout-top.php';
?>

<div class="breadcrumb">
  <a href="<?= APP_URL ?>/admin/categories/index.php">Categorías</a>
  <span class="breadcrumb-sep">›</span>
  <span class="breadcrumb-current"><?= $isEdit ? 'Editar' : 'Nueva' ?></span>
</div>

<div class="page-header">
  <div class="page-header-left">
    <h1><?= $pageTitle ?></h1>
  </div>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error">✗ <?= $e ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:560px">
  <div class="card-body">
    <form method="post">
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-required">Nombre de la categoría</label>
        <input type="text" name="name" value="<?= clean($data['name']) ?>"
               placeholder="Ej: Smash Burgers" required autofocus>
      </div>

      <div class="form-group">
        <label>Descripción <small style="font-weight:400;color:var(--text-muted)">(opcional)</small></label>
        <textarea name="description" rows="3" placeholder="Descripción breve de esta categoría"><?= clean($data['description'] ?? '') ?></textarea>
      </div>

      <div class="form-row form-row-2">
        <div class="form-group">
          <label>Orden de visualización</label>
          <input type="number" name="sort_order" value="<?= (int)$data['sort_order'] ?>" min="0" step="1">
          <div class="form-hint">Número menor = aparece primero</div>
        </div>
        <div class="form-group">
          <label>Estado</label>
          <div style="padding-top:8px">
            <label class="toggle-wrap" style="cursor:pointer">
              <input type="checkbox" name="active" value="1"
                     <?= $data['active'] ? 'checked' : '' ?>
                     style="width:18px;height:18px;accent-color:var(--red)">
              <span class="toggle-label">Categoría activa</span>
            </label>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:12px;margin-top:8px">
        <button type="submit" class="btn btn-primary">
          <?= $isEdit ? '💾 Guardar cambios' : '+ Crear categoría' ?>
        </button>
        <a href="<?= APP_URL ?>/admin/categories/index.php" class="btn btn-ghost">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../layout-bottom.php'; ?>
