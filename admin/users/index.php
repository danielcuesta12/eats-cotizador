<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();
requireAdmin();

// Desactivar/activar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    verifyCsrf();
    $uid = cleanInt($_POST['toggle_id']);
    if ($uid === (int)$_SESSION['user_id']) {
        flashMessage('error', 'No puedes desactivarte a ti mismo.');
    } else {
        Database::execute("UPDATE users SET active = NOT active WHERE id = ?", [$uid]);
        flashMessage('success', 'Estado del usuario actualizado.');
    }
    redirect('/admin/users/index.php');
}

// Eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verifyCsrf();
    $uid = cleanInt($_POST['delete_id']);
    if ($uid === (int)$_SESSION['user_id']) {
        flashMessage('error', 'No puedes eliminarte a ti mismo.');
    } else {
        Database::execute("UPDATE users SET active=0 WHERE id=?", [$uid]);
        flashMessage('success', 'Usuario desactivado.');
    }
    redirect('/admin/users/index.php');
}

$users = Database::fetchAll(
    "SELECT u.*,
       (SELECT COUNT(*) FROM quotes q WHERE q.user_id = u.id) as quote_count
     FROM users u ORDER BY u.role, u.name"
);

$pageTitle  = 'Usuarios';
$activePage = 'users';
include __DIR__ . '/../layout-top.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>👤 Usuarios</h1>
    <p><?= count($users) ?> usuarios registrados</p>
  </div>
  <a href="<?= APP_URL ?>/admin/users/form.php" class="btn btn-primary">+ Nuevo usuario</a>
</div>

<div class="card">
  <?php if (empty($users)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">👤</div>
      <h3>Sin usuarios</h3>
      <a href="<?= APP_URL ?>/admin/users/form.php" class="btn btn-primary">+ Nuevo usuario</a>
    </div>
  <?php else: ?>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table class="data-table">
      <thead>
        <tr>
          <th>Usuario</th>
          <th>Email</th>
          <th>Rol</th>
          <th>Cotizaciones</th>
          <th>Último acceso</th>
          <th>Estado</th>
          <th style="width:160px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:36px;height:36px;border-radius:50%;background:<?= $u['role']==='admin'?'var(--red)':'#555' ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0">
                <?= strtoupper(substr($u['name'],0,1)) ?>
              </div>
              <div>
                <strong><?= clean($u['name']) ?></strong>
                <?php if ($u['id'] == $_SESSION['user_id']): ?>
                  <span style="font-size:11px;background:#f0f0f0;padding:1px 6px;border-radius:4px;margin-left:4px">Tú</span>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td><?= clean($u['email']) ?></td>
          <td>
            <?php if ($u['role']==='admin'): ?>
              <span class="badge badge-danger">Admin</span>
            <?php else: ?>
              <span class="badge badge-secondary">Asistente</span>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-info"><?= $u['quote_count'] ?></span></td>
          <td style="font-size:12px;color:var(--text-muted)"><?= $u['last_login'] ? formatDatetime($u['last_login']) : 'Nunca' ?></td>
          <td>
            <span class="badge <?= $u['active'] ? 'badge-success' : 'badge-secondary' ?>">
              <?= $u['active'] ? 'Activo' : 'Inactivo' ?>
            </span>
          </td>
          <td>
            <div class="td-actions">
              <a href="form.php?id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
              <?php if ($u['id'] != $_SESSION['user_id']): ?>
              <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="toggle_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm"
                        data-confirm="¿Cambiar estado de <?= clean($u['name']) ?>?">
                  <?= $u['active'] ? 'Desactivar' : 'Activar' ?>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Tabla de permisos por rol -->
<div class="card" style="margin-top:24px">
  <div class="card-header"><span class="card-title">📋 Permisos por rol</span></div>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table class="data-table">
      <thead>
        <tr>
          <th>Acción</th>
          <th style="text-align:center">Admin</th>
          <th style="text-align:center">Asistente</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $perms = [
          ['Crear cotizaciones',            true,  true],
          ['Ver todas las cotizaciones',    true,  false],
          ['Ver solo sus cotizaciones',     true,  true],
          ['Cambiar estado cotizaciones',   true,  true],
          ['CRUD Productos',                true,  false],
          ['CRUD Categorías',               true,  false],
          ['CRUD Paquetes',                 true,  false],
          ['Crear y editar clientes',       true,  true],
          ['Eliminar clientes',             true,  false],
          ['Gestión de usuarios',           true,  false],
          ['Configuración del sistema',     true,  false],
        ];
        foreach ($perms as [$label,$admin,$asistente]):
        ?>
        <tr>
          <td><?= $label ?></td>
          <td style="text-align:center"><?= $admin     ? '<span style="color:var(--green);font-size:18px">✓</span>' : '<span style="color:#ddd;font-size:18px">✗</span>' ?></td>
          <td style="text-align:center"><?= $asistente ? '<span style="color:var(--green);font-size:18px">✓</span>' : '<span style="color:#ddd;font-size:18px">✗</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../layout-bottom.php'; ?>
