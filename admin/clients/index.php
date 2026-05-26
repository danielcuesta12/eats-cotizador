<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();

// Eliminar (solo admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && isAdmin()) {
    verifyCsrf();
    $id    = cleanInt($_POST['delete_id']);
    $count = Database::fetch("SELECT COUNT(*) as n FROM quotes WHERE client_id = ?", [$id]);
    if ((int)$count['n'] > 0) {
        flashMessage('error', 'No puedes eliminar un cliente que tiene cotizaciones.');
    } else {
        Database::execute("DELETE FROM clients WHERE id = ?", [$id]);
        flashMessage('success', 'Cliente eliminado.');
    }
    redirect('/admin/clients/index.php');
}

$search  = clean($_GET['q']    ?? '');
$typeF   = clean($_GET['type'] ?? '');
$page    = max(1, cleanInt($_GET['page'] ?? 1));
$perPage = 25;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(name LIKE ? OR ruc_dni LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $s = '%'.$search.'%';
    array_push($params, $s, $s, $s, $s);
}
if ($typeF === 'empresa')  { $where[] = 'type = "empresa"';  }
if ($typeF === 'persona')  { $where[] = 'type = "persona"';  }

$whereStr = implode(' AND ', $where);
$total    = (int)Database::fetch("SELECT COUNT(*) as n FROM clients WHERE $whereStr", $params)['n'];
$pag      = paginate($total, $perPage, $page);

$clients = Database::fetchAll(
    "SELECT c.*,
       (SELECT COUNT(*) FROM quotes q WHERE q.client_id = c.id) as quote_count
     FROM clients c
     WHERE $whereStr
     ORDER BY c.name
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $pag['offset']])
);

$pageTitle  = 'Clientes';
$activePage = 'clients';
include __DIR__ . '/../layout-top.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Clientes</h1>
    <p><?= $total ?> clientes registrados</p>
  </div>
  <a href="<?= APP_URL ?>/admin/clients/form.php" class="btn btn-primary">+ Nuevo cliente</a>
</div>

<div class="card">
  <div class="toolbar">
    <form method="get" style="display:contents">
      <div class="search-bar">
        <span class="search-icon">🔍</span>
        <input type="text" name="q" value="<?= clean($search) ?>"
               placeholder="Nombre, RUC, email…" oninput="this.form.submit()">
      </div>
      <select name="type" onchange="this.form.submit()" style="width:auto;padding:9px 14px">
        <option value="">Todos los tipos</option>
        <option value="empresa" <?= $typeF==='empresa' ? 'selected':'' ?>>Empresas</option>
        <option value="persona" <?= $typeF==='persona' ? 'selected':'' ?>>Personas</option>
      </select>
      <?php if ($search || $typeF): ?>
        <a href="?" class="btn btn-ghost btn-sm">✕ Limpiar</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($clients)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">👥</div>
      <h3>Sin clientes</h3>
      <p>Registra tus clientes para asignarlos a las cotizaciones</p>
      <a href="<?= APP_URL ?>/admin/clients/form.php" class="btn btn-primary">+ Nuevo cliente</a>
    </div>
  <?php else: ?>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table class="data-table">
      <thead>
        <tr>
          <th>Nombre / Razón social</th>
          <th>Tipo</th>
          <th>RUC / DNI</th>
          <th>Contacto</th>
          <th>Teléfono</th>
          <th>Cotizaciones</th>
          <th style="width:130px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clients as $c): ?>
        <tr>
          <td>
            <strong><?= clean($c['name']) ?></strong>
            <?php if ($c['email']): ?>
              <div style="font-size:12px;color:var(--text-muted)"><?= clean($c['email']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($c['type'] === 'empresa'): ?>
              <span class="badge badge-info">🏢 Empresa</span>
            <?php else: ?>
              <span class="badge badge-secondary">👤 Persona</span>
            <?php endif; ?>
          </td>
          <td style="font-family:monospace;font-size:13px"><?= clean($c['ruc_dni'] ?? '—') ?></td>
          <td><?= clean($c['contact_name'] ?? '—') ?></td>
          <td>
            <?php if ($c['phone']): ?>
              <a href="https://wa.me/51<?= preg_replace('/\D/','',$c['phone']) ?>"
                 target="_blank" style="color:var(--green);text-decoration:none">
                📱 <?= clean($c['phone']) ?>
              </a>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <span class="badge badge-<?= $c['quote_count'] > 0 ? 'success' : 'secondary' ?>">
              <?= $c['quote_count'] ?> cot.
            </span>
          </td>
          <td>
            <div class="td-actions">
              <a href="form.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">Editar</a>
              <?php if (isAdmin()): ?>
              <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                  data-confirm="¿Eliminar al cliente «<?= clean($c['name']) ?>»?">✕</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pag['total_pages'] > 1): ?>
  <div class="pagination">
    <?php if ($pag['has_prev']): ?>
      <a href="?q=<?= urlencode($search) ?>&type=<?= $typeF ?>&page=<?= $page-1 ?>" class="page-btn">‹</a>
    <?php endif; ?>
    <?php for ($i=max(1,$page-2); $i<=min($pag['total_pages'],$page+2); $i++): ?>
      <a href="?q=<?= urlencode($search) ?>&type=<?= $typeF ?>&page=<?= $i ?>"
         class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($pag['has_next']): ?>
      <a href="?q=<?= urlencode($search) ?>&type=<?= $typeF ?>&page=<?= $page+1 ?>" class="page-btn">›</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../layout-bottom.php'; ?>
