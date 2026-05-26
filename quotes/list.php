<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

// Eliminar cotización (solo admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quote_id'])) {
    verifyCsrf();
    requireAdmin();
    $delId = cleanInt($_POST['delete_quote_id']);
    Database::execute("DELETE FROM quote_status_log WHERE quote_id = ?", array($delId));
    Database::execute("DELETE FROM quote_items WHERE quote_id = ?",      array($delId));
    Database::execute("DELETE FROM quotes WHERE id = ?",                  array($delId));
    flashMessage('success', 'Cotizacion eliminada.');
    redirect('/quotes/list.php');
}

// Cambiar estado (AJAX o POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    verifyCsrf();
    $qid    = cleanInt(isset($_POST['quote_id']) ? $_POST['quote_id'] : 0);
    $st     = clean(isset($_POST['status']) ? $_POST['status'] : '');
    $note   = clean(isset($_POST['note'])   ? $_POST['note']   : '');
    $valid  = array('borrador','enviada','aceptada','rechazada');
    if (!in_array($st, $valid)) { http_response_code(400); exit; }

    $quote = Database::fetch("SELECT status FROM quotes WHERE id = ?", array($qid));
    if (!$quote) { flashMessage('error','Cotizacion no encontrada.'); redirect('/quotes/list.php'); }

    $now = date('Y-m-d H:i:s');
    $upd = "UPDATE quotes SET status=?";
    $p   = array($st);
    if ($st === 'enviada')   { $upd .= ', sent_at=?';     $p[] = $now; }
    if ($st === 'aceptada')  { $upd .= ', accepted_at=?'; $p[] = $now; }
    if ($st === 'rechazada') { $upd .= ', rejected_at=?'; $p[] = $now; }
    if ($note) { $upd .= ', status_note=?'; $p[] = $note; }
    $upd .= ' WHERE id=?'; $p[] = $qid;
    Database::execute($upd, $p);
    Database::insert(
        "INSERT INTO quote_status_log (quote_id,user_id,from_status,to_status,note) VALUES (?,?,?,?,?)",
        array($qid, $_SESSION['user_id'], $quote['status'], $st, $note)
    );
    if (isset($_POST['ajax'])) { echo json_encode(array('ok'=>true)); exit; }
    flashMessage('success', 'Estado actualizado.');
    redirect('/quotes/list.php');
}

// Filtros
$search  = clean(isset($_GET['q'])      ? $_GET['q']      : '');
$status  = clean(isset($_GET['status']) ? $_GET['status'] : '');
$from    = clean(isset($_GET['from'])   ? $_GET['from']   : '');
$to      = clean(isset($_GET['to'])     ? $_GET['to']     : '');
$page    = max(1, cleanInt(isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = 20;

$where  = array('1=1');
$params = array();

if (!isAdmin()) { $where[] = 'q.user_id = ?'; $params[] = $_SESSION['user_id']; }
if ($search) {
    $where[] = '(q.quote_number LIKE ? OR c.name LIKE ? OR q.event_type LIKE ?)';
    $s = '%'.$search.'%';
    $params[] = $s; $params[] = $s; $params[] = $s;
}
if ($status) { $where[] = 'q.status = ?'; $params[] = $status; }
if ($from)   { $where[] = 'q.event_date >= ?'; $params[] = $from; }
if ($to)     { $where[] = 'q.event_date <= ?'; $params[] = $to; }

$whereStr = implode(' AND ', $where);
$total    = (int)Database::fetch(
    "SELECT COUNT(*) as n FROM quotes q JOIN clients c ON c.id=q.client_id WHERE $whereStr",
    $params
)['n'];
$pag = paginate($total, $perPage, $page);

$quotes = Database::fetchAll(
    "SELECT q.*, c.name as client_name, c.type as client_type, u.name as created_by
     FROM quotes q
     JOIN clients c ON c.id=q.client_id
     JOIN users u   ON u.id=q.user_id
     WHERE $whereStr ORDER BY q.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, array($perPage, $pag['offset']))
);

$statusCounts = Database::fetchAll(
    "SELECT status, COUNT(*) as n FROM quotes" .
    (!isAdmin() ? " WHERE user_id=" . (int)$_SESSION['user_id'] : "") .
    " GROUP BY status"
);
$sCounts = array_column($statusCounts, 'n', 'status');
$allStatuses = array('' => 'Todas', 'borrador' => 'Borrador', 'enviada' => 'Enviada', 'aceptada' => 'Aceptada', 'rechazada' => 'Rechazada');

$pageTitle  = 'Cotizaciones';
$activePage = 'quotes';
include __DIR__ . '/../admin/layout-top.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Cotizaciones</h1>
    <p><?php echo $total; ?> cotizaciones encontradas</p>
  </div>
  <a href="<?php echo APP_URL; ?>/quotes/create.php" class="btn btn-primary">+ Nueva</a>
</div>

<!-- Filtros de estado -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach ($allStatuses as $val => $label):
    $cnt    = $val ? (isset($sCounts[$val]) ? $sCounts[$val] : 0) : array_sum($sCounts);
    $active = $status === $val;
  ?>
  <a href="?status=<?php echo $val; ?>&q=<?php echo urlencode($search); ?>"
     style="padding:6px 14px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;border:1.5px solid;white-space:nowrap;
            <?php echo $active ? 'background:var(--red);color:#fff;border-color:var(--red)' : 'background:#fff;color:var(--text-secondary);border-color:var(--border)'; ?>">
    <?php echo $label; ?> <span style="font-size:11px;opacity:.8">(<?php echo $cnt; ?>)</span>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <!-- Toolbar -->
  <div class="toolbar">
    <form method="get" style="display:contents">
      <input type="hidden" name="status" value="<?php echo clean($status); ?>">
      <div class="search-bar">
        <span class="search-icon">&#128269;</span>
        <input type="text" name="q" value="<?php echo clean($search); ?>"
               placeholder="N° cotizacion, cliente, evento…" oninput="this.form.submit()">
      </div>
      <input type="date" name="from" value="<?php echo clean($from); ?>"
             onchange="this.form.submit()" style="width:auto;padding:9px 14px" title="Desde">
      <input type="date" name="to" value="<?php echo clean($to); ?>"
             onchange="this.form.submit()" style="width:auto;padding:9px 14px" title="Hasta">
      <?php if ($search||$from||$to): ?>
        <a href="?status=<?php echo $status; ?>" class="btn btn-ghost btn-sm">&#10005;</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($quotes)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">&#128203;</div>
    <h3>Sin cotizaciones</h3>
    <p>Crea tu primera cotizacion ahora</p>
    <a href="<?php echo APP_URL; ?>/quotes/create.php" class="btn btn-primary">+ Nueva cotizacion</a>
  </div>
  <?php else: ?>

  <!-- ============================================================
       VISTA DESKTOP (tabla)
       ============================================================ -->
  <div class="quotes-desktop">
    <table class="data-table">
      <thead>
        <tr>
          <th>N° Cotizacion</th>
          <th>Cliente</th>
          <th>Evento</th>
          <th>Fecha</th>
          <th>Total</th>
          <th>Estado</th>
          <th>Por</th>
          <th style="width:160px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($quotes as $q): ?>
        <tr>
          <td>
            <a href="<?php echo APP_URL; ?>/quotes/edit.php?id=<?php echo $q['id']; ?>"
               style="font-weight:700;color:var(--red);text-decoration:none">
              <?php echo clean($q['quote_number']); ?>
            </a>
            <div style="font-size:11px;color:var(--text-muted)"><?php echo formatDate($q['created_at']); ?></div>
          </td>
          <td>
            <strong><?php echo clean($q['client_name']); ?></strong>
            <div style="font-size:12px;color:var(--text-muted)">
              <?php echo $q['client_type']==='empresa' ? '&#127970;' : '&#128100;'; ?>
            </div>
          </td>
          <td><?php echo clean($q['event_type'] ?: '—'); ?></td>
          <td><?php echo $q['event_date'] ? formatDate($q['event_date']) : '<span style="color:var(--text-muted)">—</span>'; ?></td>
          <td>
            <strong style="font-size:14px"><?php echo formatMoney((float)$q['total']); ?></strong>
            <?php if ($q['num_people'] > 0): ?>
            <div style="font-size:11px;color:var(--text-muted)"><?php echo formatMoney((float)$q['price_per_person']); ?>/p</div>
            <?php endif; ?>
          </td>
          <td><?php echo quoteStatusBadge($q['status']); ?></td>
          <td style="font-size:12px;color:var(--text-muted)"><?php echo clean($q['created_by']); ?></td>
          <td>
            <div class="td-actions">
              <a href="<?php echo APP_URL; ?>/quotes/edit.php?id=<?php echo $q['id']; ?>" class="btn btn-ghost btn-sm">Editar</a>
              <a href="<?php echo APP_URL; ?>/quotes/pdf.php?id=<?php echo $q['id']; ?>" class="btn btn-secondary btn-sm" target="_blank">PDF</a>
              <div class="smw" style="position:relative">
                <button type="button" class="btn btn-ghost btn-sm smw-trigger">&#9660;</button>
                <div class="smenu" style="display:none">
                  <?php foreach (array('borrador','enviada','aceptada','rechazada') as $st): ?>
                    <?php if ($st !== $q['status']): ?>
                    <button type="button" onclick="changeStatus(<?php echo $q['id']; ?>,'<?php echo $st; ?>')">
                      <?php echo quoteStatusLabel($st); ?>
                    </button>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  <?php if (isAdmin()): ?>
                  <button type="button" class="smenu-danger"
                          onclick="deleteQuote(<?php echo $q['id']; ?>,'<?php echo clean($q['quote_number']); ?>')">
                    &#128465; Eliminar
                  </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ============================================================
       VISTA MOBILE (cards)
       ============================================================ -->
  <div class="quotes-mobile">
    <?php foreach ($quotes as $q):
      $statusColors = array(
        'borrador'  => '#aaa',
        'enviada'   => '#2563eb',
        'aceptada'  => '#16a34a',
        'rechazada' => '#dc2626',
      );
      $dotColor = isset($statusColors[$q['status']]) ? $statusColors[$q['status']] : '#aaa';
    ?>
    <div class="qcard">
      <!-- Fila 1: número + total -->
      <div class="qcard-top">
        <a href="<?php echo APP_URL; ?>/quotes/edit.php?id=<?php echo $q['id']; ?>" class="qcard-num">
          <?php echo clean($q['quote_number']); ?>
        </a>
        <span class="qcard-total"><?php echo formatMoney((float)$q['total']); ?></span>
      </div>
      <!-- Fila 2: cliente -->
      <div class="qcard-client"><?php echo clean($q['client_name']); ?></div>
      <!-- Fila 3: meta -->
      <div class="qcard-meta">
        <span style="display:inline-flex;align-items:center;gap:4px">
          <span style="width:8px;height:8px;border-radius:50%;background:<?php echo $dotColor; ?>;display:inline-block;flex-shrink:0"></span>
          <?php echo quoteStatusLabel($q['status']); ?>
        </span>
        <?php if ($q['event_type']): ?>
          &nbsp;&#183;&nbsp;<?php echo clean($q['event_type']); ?>
        <?php endif; ?>
        <?php if ($q['event_date']): ?>
          &nbsp;&#183;&nbsp;<?php echo formatDate($q['event_date']); ?>
        <?php endif; ?>
        <?php if ($q['num_people'] > 0): ?>
          &nbsp;&#183;&nbsp;<?php echo formatMoney((float)$q['price_per_person']); ?>/p
        <?php endif; ?>
      </div>
      <!-- Fila 4: acciones -->
      <div class="qcard-actions">
        <a href="<?php echo APP_URL; ?>/quotes/edit.php?id=<?php echo $q['id']; ?>" class="qbtn">
          &#9998; Editar
        </a>
        <a href="<?php echo APP_URL; ?>/quotes/pdf.php?id=<?php echo $q['id']; ?>" target="_blank" class="qbtn">
          &#128438; PDF
        </a>
        <div class="smw" style="position:relative">
          <button type="button" class="qbtn smw-trigger">&#9660; Estado</button>
          <div class="smenu" style="display:none">
            <?php foreach (array('borrador','enviada','aceptada','rechazada') as $st): ?>
              <?php if ($st !== $q['status']): ?>
              <button type="button" onclick="changeStatus(<?php echo $q['id']; ?>,'<?php echo $st; ?>')">
                <?php echo quoteStatusLabel($st); ?>
              </button>
              <?php endif; ?>
            <?php endforeach; ?>
            <?php if (isAdmin()): ?>
            <button type="button" class="smenu-danger"
                    onclick="deleteQuote(<?php echo $q['id']; ?>,'<?php echo clean($q['quote_number']); ?>')">
              &#128465; Eliminar
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Paginación -->
  <?php if ($pag['total_pages'] > 1): ?>
  <div class="pagination">
    <?php if ($pag['has_prev']): ?>
      <a href="?q=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&page=<?php echo $page-1; ?>" class="page-btn">&#8249;</a>
    <?php endif; ?>
    <?php for ($i=max(1,$page-2); $i<=min($pag['total_pages'],$page+2); $i++): ?>
      <a href="?q=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&page=<?php echo $i; ?>"
         class="page-btn <?php echo $i===$page?'active':''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
    <?php if ($pag['has_next']): ?>
      <a href="?q=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&page=<?php echo $page+1; ?>" class="page-btn">&#8250;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div><!-- /card -->

<!-- Form oculto para eliminar -->
<form id="deleteQuoteForm" method="post" style="display:none">
  <?php echo csrfField(); ?>
  <input type="hidden" name="delete_quote_id" id="deleteQuoteId">
</form>

<style>
/* ---- DROPDOWN: abre hacia abajo, SUPERPUESTO (z-index alto) ---- */
.smw { display:inline-block; }
.smenu {
  position: fixed;          /* fixed para salir del overflow de la tabla */
  background: #fff;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  box-shadow: 0 8px 32px rgba(0,0,0,.15);
  z-index: 9999;            /* por encima de todo */
  min-width: 160px;
  overflow: hidden;
}
.smenu button {
  display: block; width: 100%;
  padding: 12px 16px;
  background: none; border: none;
  text-align: left; font-size: 14px;
  cursor: pointer; color: var(--text-primary);
  border-bottom: 1px solid var(--border);
  min-height: 44px;
  -webkit-tap-highlight-color: transparent;
}
.smenu button:last-child { border-bottom: none; }
.smenu button:hover, .smenu button:active { background: var(--red-light); color: var(--red); }
.smenu-danger { color: #dc2626 !important; }
.smenu-danger:hover { background: #fee2e2 !important; color: #dc2626 !important; }

/* ---- DESKTOP ---- */
.quotes-desktop { display: block; }
.quotes-mobile  { display: none; }

/* ---- MOBILE CARDS ---- */
@media (max-width: 768px) {
  .quotes-desktop { display: none; }
  .quotes-mobile  { display: block; }
}

.qcard {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
}
.qcard:last-child { border-bottom: none; }

.qcard-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}
.qcard-num {
  font-size: 15px;
  font-weight: 800;
  color: var(--red);
  text-decoration: none;
}
.qcard-total {
  font-size: 17px;
  font-weight: 800;
  color: var(--text-primary);
}
.qcard-client {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 3px;
}
.qcard-meta {
  font-size: 12px;
  color: var(--text-muted);
  margin-bottom: 10px;
  line-height: 1.5;
}
.qcard-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.qbtn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 9px 14px;
  background: #f4f4f5;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-primary);
  text-decoration: none;
  cursor: pointer;
  min-height: 40px;
  -webkit-tap-highlight-color: transparent;
}
.qbtn:active { opacity: .7; }
</style>

<script>
var CSRF = '<?php echo csrfToken(); ?>';

// Dropdown con posición FIXED calculada dinámicamente
document.addEventListener('click', function(e) {
  var trigger = e.target.closest('.smw-trigger');
  // Cerrar todos primero
  document.querySelectorAll('.smenu').forEach(function(m) {
    if (!trigger || m !== trigger.nextElementSibling) {
      m.style.display = 'none';
    }
  });
  if (!trigger) return;
  e.stopPropagation();

  var menu = trigger.nextElementSibling;
  if (menu.style.display === 'block') {
    menu.style.display = 'none';
    return;
  }

  // Calcular posición FIXED relativa al botón
  var rect   = trigger.getBoundingClientRect();
  var mh     = 200; // altura estimada del menu
  var spaceB = window.innerHeight - rect.bottom;
  var spaceT = rect.top;

  menu.style.display = 'block';
  menu.style.left    = (rect.right - 160) + 'px';

  // Si hay espacio abajo → abrir abajo. Si no → abrir arriba.
  if (spaceB >= mh || spaceB >= spaceT) {
    menu.style.top    = (rect.bottom + 4) + 'px';
    menu.style.bottom = 'auto';
  } else {
    menu.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
    menu.style.top    = 'auto';
  }
});

async function changeStatus(quoteId, status) {
  document.querySelectorAll('.smenu').forEach(function(m) { m.style.display='none'; });
  var fd = new FormData();
  fd.append('change_status', '1');
  fd.append('quote_id',      quoteId);
  fd.append('status',        status);
  fd.append('csrf_token',    CSRF);
  fd.append('ajax',          '1');
  var res = await fetch(location.href, { method:'POST', body:fd });
  if (res.ok) location.reload();
  else alert('Error al cambiar estado.');
}

function deleteQuote(id, number) {
  document.querySelectorAll('.smenu').forEach(function(m) { m.style.display='none'; });
  if (!confirm('Eliminar la cotizacion ' + number + '?\n\nEsta accion no se puede deshacer.')) return;
  document.getElementById('deleteQuoteId').value = id;
  document.getElementById('deleteQuoteForm').submit();
}
</script>

<?php include __DIR__ . '/../admin/layout-bottom.php'; ?>
