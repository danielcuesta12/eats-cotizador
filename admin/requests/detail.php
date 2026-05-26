<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();

$id  = cleanInt(isset($_GET['id']) ? $_GET['id'] : 0);
$req = Database::fetch("SELECT * FROM quote_requests WHERE id=?", array($id));
if (!$req) { flashMessage('error','Solicitud no encontrada.'); redirect('/admin/requests/index.php'); }

// Aceptar solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();
    $action = clean($_POST['action']);

    if ($action === 'reject') {
        Database::execute(
            "UPDATE quote_requests SET status='rechazada', reviewed_by=?, reviewed_at=NOW() WHERE id=?",
            array($_SESSION['user_id'], $id)
        );
        flashMessage('success', 'Solicitud rechazada.');
        redirect('/admin/requests/index.php');
    }

    if ($action === 'accept') {
        // 1. Crear cliente en la BD
        $clientId = Database::insert(
            "INSERT INTO clients (type,name,ruc_dni,contact_name,email,phone,active)
             VALUES (?,?,?,?,?,?,1)",
            array($req['type'],$req['name'],$req['ruc_dni'],$req['contact_name'],$req['email'],$req['phone'])
        );
        // 2. Marcar solicitud como aceptada
        Database::execute(
            "UPDATE quote_requests SET status='aceptada', reviewed_by=?, reviewed_at=NOW(), client_id=? WHERE id=?",
            array($_SESSION['user_id'], $clientId, $id)
        );
        // 3. Redirigir al cotizador con datos precargados
        flashMessage('success', 'Cliente creado. Completa la cotizacion.');
        redirect('/quotes/create.php?client_id=' . $clientId .
            '&event_date=' . urlencode($req['event_date'] ?: '') .
            '&event_location=' . urlencode($req['event_location'] ?: '') .
            '&num_people=' . (int)$req['num_people']);
    }
}

// Construir links de contacto
$phone = preg_replace('/\D/', '', $req['phone'] ?: '');
$waMsg = rawurlencode("Hola " . $req['name'] . ", hemos recibido tu solicitud de cotizacion para el " . ($req['event_date'] ? formatDate($req['event_date']) : 'evento') . ". Nos comunicamos para coordinar los detalles.");
$waLink   = $phone ? "https://wa.me/" . $phone . "?text=" . $waMsg : '';
$callLink = $phone ? "tel:+" . $phone : '';
$mailLink = $req['email'] ? APP_URL . '/quotes/send-request-email.php?req_id=' . $id : '';

$pageTitle  = 'Solicitud — ' . $req['name'];
$activePage = 'requests';

$extraHead = '
<style>
.det-wrap{display:flex;flex-direction:column;gap:16px}
.contact-box{background:var(--bg-input);border:1.5px solid var(--border);border-radius:12px;padding:16px}
.contact-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:12px}
.contact-person{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.contact-av{width:42px;height:42px;border-radius:50%;background:var(--red-light);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:var(--red);flex-shrink:0}
.contact-name{font-size:15px;font-weight:700;color:var(--text-primary)}
.contact-sub{font-size:12px;color:var(--text-muted);margin-top:2px}
.contact-btns{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}
.cbtn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;padding:12px 8px;border-radius:10px;border:1.5px solid var(--border);background:var(--bg-card);cursor:pointer;font-size:12px;font-weight:600;color:var(--text-primary);text-decoration:none;min-height:70px;-webkit-tap-highlight-color:transparent;transition:all .15s}
.cbtn:active{opacity:.7;transform:scale(.97)}
.cbtn-icon{font-size:22px;line-height:1}
.cbtn-wa{border-color:#bbf7d0;background:#f0fdf4;color:#166534}
.cbtn-call{border-color:#bfdbfe;background:#eff6ff;color:#1e40af}
.cbtn.disabled{opacity:.35;pointer-events:none}

.det-section{border:1px solid var(--border);border-radius:12px;overflow:hidden}
.det-sec-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);padding:8px 14px;background:var(--bg-input);border-bottom:1px solid var(--border)}
.det-grid{display:grid;grid-template-columns:1fr 1fr}
.det-cell{padding:10px 14px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)}
.det-cell:nth-child(2n){border-right:none}
.det-cell.last-row,.det-cell.last-row~.det-cell{border-bottom:none}
.det-cell.full{grid-column:1/-1;border-right:none}
.det-lbl{font-size:10px;color:var(--text-muted);margin-bottom:3px}
.det-val{font-size:13px;font-weight:600;color:var(--text-primary)}

/* Barra fija bottom en mobile */
.action-footer-fixed{position:fixed;bottom:0;left:0;right:0;background:var(--bg-card);border-top:1px solid var(--border);padding:10px 16px;padding-bottom:max(10px,env(safe-area-inset-bottom));display:flex;gap:8px;z-index:100;box-shadow:0 -4px 16px rgba(0,0,0,.08)}
.action-footer-fixed .btn{flex:1;min-height:48px;font-size:13px;-webkit-tap-highlight-color:transparent}
.det-spacer{height:76px}
@media(min-width:768px){
  .action-footer-fixed{position:static;padding:16px 0 0;box-shadow:none;border:none}
  .det-spacer{display:none}
}
</style>';

include __DIR__ . '/../layout-top.php';
?>

<div class="breadcrumb">
  <a href="<?php echo APP_URL; ?>/admin/requests/index.php">Solicitudes</a>
  <span class="breadcrumb-sep">›</span>
  <span class="breadcrumb-current"><?php echo clean($req['name']); ?></span>
</div>

<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px">
  <h1 style="font-size:20px;font-weight:800"><?php echo clean($req['name']); ?></h1>
  <?php
  $bc = array('pendiente'=>'badge-warning','aceptada'=>'badge-success','rechazada'=>'badge-danger');
  $bl = array('pendiente'=>'Pendiente','aceptada'=>'Aceptada','rechazada'=>'Rechazada');
  ?>
  <span class="badge <?php echo isset($bc[$req['status']])?$bc[$req['status']]:'badge-secondary'; ?>">
    <?php echo isset($bl[$req['status']])?$bl[$req['status']]:$req['status']; ?>
  </span>
  <span style="font-size:12px;color:var(--text-muted)">Recibida <?php echo formatDatetime($req['created_at']); ?></span>
</div>

<div class="det-wrap">

  <!-- CONTACTO RAPIDO -->
  <div class="contact-box">
    <div class="contact-title">Contactar al cliente antes de cotizar</div>
    <div class="contact-person">
      <div class="contact-av"><?php echo strtoupper(substr($req['name'],0,1)); ?></div>
      <div>
        <div class="contact-name"><?php echo clean($req['contact_name'] ?: $req['name']); ?></div>
        <div class="contact-sub">
          <?php echo clean($req['name']); ?>
          <?php if ($req['phone']): ?> &middot; +<?php echo clean($req['phone']); ?><?php endif; ?>
          <?php if ($req['email']): ?> &middot; <?php echo clean($req['email']); ?><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="contact-btns">
      <?php if ($waLink): ?>
      <a href="<?php echo $waLink; ?>" target="_blank" class="cbtn cbtn-wa">
        <span class="cbtn-icon">&#128172;</span>WhatsApp
      </a>
      <?php else: ?>
      <div class="cbtn disabled"><span class="cbtn-icon">&#128172;</span>WhatsApp</div>
      <?php endif; ?>

      <?php if ($callLink): ?>
      <a href="<?php echo $callLink; ?>" class="cbtn cbtn-call">
        <span class="cbtn-icon">&#128222;</span>Llamar
      </a>
      <?php else: ?>
      <div class="cbtn disabled"><span class="cbtn-icon">&#128222;</span>Llamar</div>
      <?php endif; ?>

      <?php if ($req['email']): ?>
      <a href="mailto:<?php echo clean($req['email']); ?>?subject=<?php echo rawurlencode('Cotizacion ' . $req['name']); ?>&body=<?php echo rawurlencode('Hola ' . ($req['contact_name']?:$req['name']) . ','); ?>" class="cbtn">
        <span class="cbtn-icon">&#9993;</span>Email
      </a>
      <?php else: ?>
      <div class="cbtn disabled"><span class="cbtn-icon">&#9993;</span>Email</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- DATOS DEL CLIENTE -->
  <div class="det-section">
    <div class="det-sec-title">Datos del cliente</div>
    <div class="det-grid">
      <div class="det-cell"><div class="det-lbl">Tipo</div><div class="det-val"><?php echo $req['type']==='empresa'?'Empresa':'Persona natural'; ?></div></div>
      <div class="det-cell"><div class="det-lbl"><?php echo $req['type']==='empresa'?'RUC':'DNI'; ?></div><div class="det-val"><?php echo clean($req['ruc_dni']?:'—'); ?></div></div>
      <div class="det-cell"><div class="det-lbl"><?php echo $req['type']==='empresa'?'Razon social':'Nombre'; ?></div><div class="det-val"><?php echo clean($req['name']); ?></div></div>
      <?php if ($req['type']==='empresa'): ?>
      <div class="det-cell"><div class="det-lbl">Contacto</div><div class="det-val"><?php echo clean($req['contact_name']?:'—'); ?></div></div>
      <?php endif; ?>
      <div class="det-cell"><div class="det-lbl">Email</div><div class="det-val"><?php echo clean($req['email']?:'—'); ?></div></div>
      <div class="det-cell last-row"><div class="det-lbl">Celular</div><div class="det-val"><?php echo $req['phone']?'+'.clean($req['phone']):'—'; ?></div></div>
    </div>
  </div>

  <!-- DATOS DEL EVENTO -->
  <div class="det-section">
    <div class="det-sec-title">Datos del evento solicitado</div>
    <div class="det-grid">
      <div class="det-cell"><div class="det-lbl">Fecha</div><div class="det-val"><?php echo $req['event_date']?formatDate($req['event_date']):'—'; ?></div></div>
      <div class="det-cell"><div class="det-lbl">Hora</div><div class="det-val"><?php echo clean($req['event_time']?:'—'); ?></div></div>
      <div class="det-cell"><div class="det-lbl">Duracion</div><div class="det-val"><?php echo clean($req['event_duration']?:'—'); ?></div></div>
      <div class="det-cell"><div class="det-lbl">N° personas</div><div class="det-val"><?php echo $req['num_people']>0?$req['num_people'].' personas':'—'; ?></div></div>
      <?php if ($req['event_location']): ?>
      <div class="det-cell full"><div class="det-lbl">Lugar</div><div class="det-val"><?php echo clean($req['event_location']); ?></div></div>
      <?php endif; ?>
      <?php if ($req['comments']): ?>
      <div class="det-cell full last-row"><div class="det-lbl">Comentarios</div><div class="det-val" style="font-weight:400;line-height:1.5"><?php echo clean($req['comments']); ?></div></div>
      <?php endif; ?>
    </div>
  </div>

</div>

<div class="det-spacer"></div>

<!-- ACCIONES -->
<?php if ($req['status'] === 'pendiente'): ?>
<form method="post" id="actionForm">
  <?php echo csrfField(); ?>
  <input type="hidden" name="action" id="actionInput">
  <div class="action-footer-fixed">
    <button type="button" class="btn btn-ghost"
            onclick="doAction('reject')"
            data-confirm="Rechazar esta solicitud de <?php echo clean($req['name']); ?>?">
      Rechazar
    </button>
    <button type="button" class="btn btn-primary"
            onclick="doAction('accept')">
      &#10003; Aceptar y cotizar &rarr;
    </button>
  </div>
</form>
<script>
function doAction(action) {
  if (action === 'reject') {
    if (!confirm('Rechazar esta solicitud?')) return;
  }
  if (action === 'accept') {
    if (!confirm('Crear el cliente y abrir el cotizador con sus datos?')) return;
  }
  document.getElementById('actionInput').value = action;
  document.getElementById('actionForm').submit();
}
</script>
<?php else: ?>
<div style="margin-top:16px">
  <a href="<?php echo APP_URL; ?>/admin/requests/index.php" class="btn btn-ghost">
    &#8592; Volver a solicitudes
  </a>
  <?php if ($req['client_id']): ?>
  <a href="<?php echo APP_URL; ?>/quotes/create.php?client_id=<?php echo $req['client_id']; ?>"
     class="btn btn-primary" style="margin-left:10px">
    + Nueva cotizacion para este cliente
  </a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../layout-bottom.php'; ?>
