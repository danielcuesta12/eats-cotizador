<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();

$filter  = clean(isset($_GET['status']) ? $_GET['status'] : 'pendiente');
$page    = max(1, cleanInt(isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = '1=1';
$params = array();
if ($filter !== 'all') { $where = 'status = ?'; $params[] = $filter; }

$total    = (int)Database::fetch("SELECT COUNT(*) as n FROM quote_requests WHERE $where", $params)['n'];
$requests = Database::fetchAll(
    "SELECT * FROM quote_requests WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, array($perPage, $offset))
);
$pag = paginate($total, $perPage, $page);

$counts = Database::fetchAll("SELECT status, COUNT(*) as n FROM quote_requests GROUP BY status");
$cmap   = array_column($counts, 'n', 'status');

$pageTitle  = 'Solicitudes de cotizacion';
$activePage = 'requests';
include __DIR__ . '/../layout-top.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Solicitudes de cotizacion</h1>
    <p>Solicitudes enviadas desde el formulario publico</p>
  </div>
  <a href="<?php echo APP_URL; ?>/solicitud" class="btn btn-ghost btn-sm" target="_blank">
    &#128279; Ver formulario publico
  </a>
</div>

<!-- Filtros -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <?php
  $filters = array('pendiente'=>'Pendientes','aceptada'=>'Aceptadas','rechazada'=>'Rechazadas','all'=>'Todas');
  foreach ($filters as $val => $label):
    $cnt    = $val === 'all' ? array_sum($cmap) : (isset($cmap[$val]) ? $cmap[$val] : 0);
    $active = $filter === $val;
  ?>
  <a href="?status=<?php echo $val; ?>"
     style="padding:6px 14px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;border:1.5px solid;white-space:nowrap;
            <?php echo $active ? 'background:var(--red);color:#fff;border-color:var(--red)' : 'background:#fff;color:var(--text-secondary);border-color:var(--border)'; ?>">
    <?php echo $label; ?> <span style="font-size:11px;opacity:.8">(<?php echo $cnt; ?>)</span>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if (empty($requests)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">&#128203;</div>
    <h3>Sin solicitudes</h3>
    <p>Cuando un cliente complete el formulario publico, aparecera aqui</p>
  </div>
  <?php else: ?>

  <!-- DESKTOP -->
  <div class="requests-desktop">
    <table class="data-table">
      <thead><tr>
        <th>Cliente</th><th>Tipo</th><th>Evento</th><th>Personas</th><th>Recibida</th><th>Estado</th><th style="width:120px"></th>
      </tr></thead>
      <tbody>
        <?php foreach ($requests as $r): ?>
        <tr>
          <td>
            <a href="<?php echo APP_URL; ?>/admin/requests/detail.php?id=<?php echo $r['id']; ?>"
               style="font-weight:600;color:var(--red);text-decoration:none"><?php echo clean($r['name']); ?></a>
            <?php if ($r['email']): ?><div style="font-size:11px;color:var(--text-muted)"><?php echo clean($r['email']); ?></div><?php endif; ?>
          </td>
          <td><?php echo $r['type']==='empresa'?'&#127970; Empresa':'&#128100; Persona'; ?></td>
          <td>
            <?php echo $r['event_date'] ? formatDate($r['event_date']) : '—'; ?>
            <?php if ($r['event_location']): ?><div style="font-size:11px;color:var(--text-muted)"><?php echo clean(mb_substr($r['event_location'],0,30)); ?></div><?php endif; ?>
          </td>
          <td><?php echo $r['num_people'] > 0 ? $r['num_people'] . ' pers.' : '—'; ?></td>
          <td style="font-size:12px;color:var(--text-muted)"><?php echo formatDatetime($r['created_at']); ?></td>
          <td>
            <?php
            $bc = array('pendiente'=>'badge-warning','aceptada'=>'badge-success','rechazada'=>'badge-danger');
            $bl = array('pendiente'=>'Pendiente','aceptada'=>'Aceptada','rechazada'=>'Rechazada');
            ?>
            <span class="badge <?php echo isset($bc[$r['status']])?$bc[$r['status']]:'badge-secondary'; ?>">
              <?php echo isset($bl[$r['status']])?$bl[$r['status']]:$r['status']; ?>
            </span>
          </td>
          <td>
            <a href="<?php echo APP_URL; ?>/admin/requests/detail.php?id=<?php echo $r['id']; ?>"
               class="btn btn-ghost btn-sm">Ver detalle</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- MOBILE -->
  <div class="requests-mobile">
    <?php foreach ($requests as $r):
      $colors = array('pendiente'=>'#ca8a04','aceptada'=>'#16a34a','rechazada'=>'#dc2626');
      $dc = isset($colors[$r['status']]) ? $colors[$r['status']] : '#aaa';
    ?>
    <a href="<?php echo APP_URL; ?>/admin/requests/detail.php?id=<?php echo $r['id']; ?>"
       style="display:flex;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border);text-decoration:none;-webkit-tap-highlight-color:transparent">
      <div style="width:40px;height:40px;border-radius:50%;background:var(--red-light);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:var(--red);flex-shrink:0">
        <?php echo strtoupper(substr($r['name'],0,1)); ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:14px;font-weight:600;color:#1a1a1a"><?php echo clean($r['name']); ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
          <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:<?php echo $dc; ?>;margin-right:4px;vertical-align:middle"></span>
          <?php echo isset($bl[$r['status']])?$bl[$r['status']]:''; ?>
          &nbsp;&middot;&nbsp;<?php echo $r['num_people']>0?$r['num_people'].' pers.':''; ?>
          <?php if ($r['event_date']): ?>&nbsp;&middot;&nbsp;<?php echo formatDate($r['event_date']); ?><?php endif; ?>
        </div>
      </div>
      <div style="font-size:18px;color:var(--text-muted)">&#8250;</div>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($pag['total_pages'] > 1): ?>
  <div class="pagination">
    <?php if ($pag['has_prev']): ?><a href="?status=<?php echo $filter; ?>&page=<?php echo $page-1; ?>" class="page-btn">&#8249;</a><?php endif; ?>
    <?php for ($i=max(1,$page-2);$i<=min($pag['total_pages'],$page+2);$i++): ?>
    <a href="?status=<?php echo $filter; ?>&page=<?php echo $i; ?>" class="page-btn <?php echo $i===$page?'active':''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
    <?php if ($pag['has_next']): ?><a href="?status=<?php echo $filter; ?>&page=<?php echo $page+1; ?>" class="page-btn">&#8250;</a><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<style>
.requests-desktop{display:block}.requests-mobile{display:none}
@media(max-width:768px){.requests-desktop{display:none}.requests-mobile{display:block}}
</style>

<?php include __DIR__ . '/../layout-bottom.php'; ?>
