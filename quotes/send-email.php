<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

$id    = cleanInt(isset($_GET['id']) ? $_GET['id'] : 0);
$quote = Database::fetch(
    "SELECT q.*, c.name as client_name, c.email as client_email, c.phone as client_phone
     FROM quotes q JOIN clients c ON c.id = q.client_id WHERE q.id = ?",
    array($id)
);
if (!$quote) {
    flashMessage('error', 'Cotizacion no encontrada.');
    redirect('/quotes/list.php');
}

$errors = array();
$sent   = false;

$company   = getSetting('company_name',  'El Gringo Burger Joint');
$fromEmail = 'cotizaciones@elgringo.pe';
$replyTo   = 'daniel@elgringo.pe, eventos@elgringo.pe';
$pubLink   = APP_URL . '/quotes/view.php?token=' . $quote['public_token'];

// Asunto y cuerpo del email adaptados al estado de la cotización
$_eventDate = $quote['event_date'] ? formatDate($quote['event_date']) : '';
$_daysSent  = $quote['sent_at'] ? (int)((time() - strtotime($quote['sent_at'])) / 86400) : null;

switch ($quote['status']) {
    case 'borrador':
        $defSubject = 'Cotización ' . $quote['quote_number'] . ' — ' . $company;
        $defBody = 'Estimado/a ' . $quote['client_name'] . ',' . "\n\n" .
            'Te enviamos la cotización ' . $quote['quote_number'] .
            ($_eventDate ? ' para tu evento del ' . $_eventDate : ' para tu evento') . '.' . "\n\n" .
            'Puedes revisar todos los detalles en el siguiente enlace:' . "\n" .
            $pubLink . "\n\n" .
            'Total: ' . formatMoney((float)$quote['total']) . "\n\n" .
            'Quedamos atentos a cualquier consulta. Puedes responder este correo y llegará directamente a nuestro equipo.' . "\n\n" .
            'Saludos,' . "\n" . $company;
        break;
    case 'enviada':
        $_diasStr = $_daysSent !== null ? ' hace ' . $_daysSent . ' día' . ($_daysSent !== 1 ? 's' : '') : '';
        $defSubject = 'Seguimiento — Cotización ' . $quote['quote_number'];
        $defBody = 'Hola ' . $quote['client_name'] . ',' . "\n\n" .
            'Te escribimos para hacer seguimiento de la cotización ' . $quote['quote_number'] .
            ' que te enviamos' . $_diasStr . '.' . "\n\n" .
            '¿Tuviste oportunidad de revisarla? Puedes consultarla en cualquier momento aquí:' . "\n" .
            $pubLink . "\n\n" .
            'Total: ' . formatMoney((float)$quote['total']) . "\n\n" .
            'Quedamos atentos a cualquier duda o ajuste que necesites.' . "\n\n" .
            'Saludos,' . "\n" . $company;
        break;
    case 'aceptada':
        $defSubject = 'Coordinación del evento' . ($_eventDate ? ' — ' . $_eventDate : '') . ' — ' . $company;
        $defBody = 'Hola ' . $quote['client_name'] . ',' . "\n\n" .
            '¡Gracias por confiar en nosotros! Con el evento' .
            ($_eventDate ? ' del ' . $_eventDate : '') . ' acercándose, queremos coordinar los últimos detalles contigo.' . "\n\n" .
            'Puedes revisar el resumen confirmado aquí:' . "\n" .
            $pubLink . "\n\n" .
            '¿Tienes disponibilidad para conversar esta semana? Responde este correo o contáctanos directamente.' . "\n\n" .
            'Saludos,' . "\n" . $company;
        break;
    case 'rechazada':
    default:
        $defSubject = 'Seguimos disponibles — ' . $company;
        $defBody = 'Hola ' . $quote['client_name'] . ',' . "\n\n" .
            'Entendemos que por el momento no pudimos concretar. Queremos que sepas que quedamos a tu disposición para cuando lo necesites.' . "\n\n" .
            'Si en algún momento retoman la búsqueda de un servicio de catering o food truck, con gusto les preparamos una nueva propuesta a medida.' . "\n\n" .
            '¡Muchos éxitos!' . "\n\n" .
            'Saludos,' . "\n" . $company;
        break;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $to      = clean(isset($_POST['to'])      ? $_POST['to']      : '');
    $subject = clean(isset($_POST['subject']) ? $_POST['subject'] : '');
    $body    = clean(isset($_POST['body'])    ? $_POST['body']    : '');

    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL))
        $errors[] = 'El email del destinatario no es valido.';
    if (!$subject)
        $errors[] = 'El asunto es obligatorio.';

    if (empty($errors)) {
        // HTML del email
        $bodyHtml = '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f0f0;font-family:-apple-system,Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f0;padding:20px 0">
<tr><td align="center">
<table width="100%" style="max-width:560px;background:#fff;border-radius:12px;overflow:hidden">

  <!-- Header rojo -->
  <tr>
    <td style="background:#C8102E;padding:24px 28px">
      <p style="margin:0;font-size:20px;font-weight:800;color:#fff">' . htmlspecialchars($company) . '</p>
      <p style="margin:4px 0 0;font-size:12px;color:rgba(255,255,255,.7)">Cotizacion de eventos</p>
    </td>
  </tr>

  <!-- Cuerpo -->
  <tr>
    <td style="padding:28px">
      <p style="margin:0 0 20px;font-size:15px;color:#1a1a1a;line-height:1.6">' . nl2br(htmlspecialchars($body)) . '</p>

      <!-- Card de la cotización -->
      <table width="100%" style="background:#f7f7f7;border-radius:10px;padding:16px;margin-bottom:24px">
        <tr><td style="padding:4px 0">
          <p style="margin:0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#999">N° Cotizacion</p>
          <p style="margin:2px 0 0;font-size:16px;font-weight:800;color:#1a1a1a">' . htmlspecialchars($quote['quote_number']) . '</p>
        </td></tr>
        <tr><td style="padding:4px 0">
          <p style="margin:0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#999">Tipo de evento</p>
          <p style="margin:2px 0 0;font-size:14px;color:#1a1a1a">' . htmlspecialchars($quote['event_type'] ?: '—') . '</p>
        </td></tr>
        ' . ($quote['event_date'] ? '<tr><td style="padding:4px 0">
          <p style="margin:0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#999">Fecha del evento</p>
          <p style="margin:2px 0 0;font-size:14px;color:#1a1a1a">' . formatDate($quote['event_date']) . '</p>
        </td></tr>' : '') . '
        <tr><td style="padding:8px 0 4px">
          <p style="margin:0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#999">Total</p>
          <p style="margin:2px 0 0;font-size:24px;font-weight:800;color:#C8102E">' . formatMoney((float)$quote['total']) . '</p>
        </td></tr>
      </table>

      <!-- Boton -->
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td align="center">
            <a href="' . $pubLink . '"
               style="display:inline-block;background:#C8102E;color:#fff;padding:14px 28px;border-radius:10px;text-decoration:none;font-size:15px;font-weight:700">
              Ver cotizacion completa &rarr;
            </a>
          </td>
        </tr>
      </table>

      <p style="margin:20px 0 0;font-size:12px;color:#aaa;text-align:center">
        Para responder a este correo, escribe directamente al equipo de ' . htmlspecialchars($company) . '.
      </p>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#1a1a1a;padding:14px 28px">
      <p style="margin:0;font-size:11px;color:#555">
        ' . htmlspecialchars($company) . ' &middot; Lima, Peru
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';

        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $headers .= 'From: ' . $company . ' <' . $fromEmail . '>' . "\r\n";
        $headers .= 'Reply-To: ' . $replyTo . "\r\n";
        $headers .= 'X-Mailer: ElGringoCotizador/1.0' . "\r\n";
        $headers .= 'X-Priority: 1' . "\r\n";

        if (@mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $bodyHtml, $headers)) {
            // Marcar como enviada si estaba en borrador
            if ($quote['status'] === 'borrador') {
                Database::execute(
                    "UPDATE quotes SET status='enviada', sent_at=NOW() WHERE id=?",
                    array($quote['id'])
                );
                Database::insert(
                    "INSERT INTO quote_status_log (quote_id,user_id,from_status,to_status,note) VALUES (?,?,?,?,?)",
                    array($quote['id'], $_SESSION['user_id'], 'borrador', 'enviada', 'Enviada por email a ' . $to)
                );
            }
            flashMessage('success', 'Email enviado correctamente a ' . $to);
            redirect('/quotes/edit.php?id=' . $quote['id']);
        } else {
            $errors[] = 'No se pudo enviar el email. Verifica que el dominio elgringo.pe tenga configurado el servidor de correo saliente en cPanel.';
        }
    }
}

$pageTitle  = 'Enviar por email';
$activePage = 'quotes';
include __DIR__ . '/../admin/layout-top.php';
?>

<div class="breadcrumb">
  <a href="<?php echo APP_URL; ?>/quotes/list.php">Cotizaciones</a>
  <span class="breadcrumb-sep">›</span>
  <a href="<?php echo APP_URL; ?>/quotes/edit.php?id=<?php echo $quote['id']; ?>"><?php echo clean($quote['quote_number']); ?></a>
  <span class="breadcrumb-sep">›</span>
  <span class="breadcrumb-current">Enviar email</span>
</div>

<div class="page-header">
  <div class="page-header-left">
    <h1>&#9993; Enviar cotizacion por email</h1>
    <p><?php echo clean($quote['quote_number']); ?> — <?php echo clean($quote['client_name']); ?></p>
  </div>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error">&#10007; <?php echo htmlspecialchars($e); ?></div>
<?php endforeach; ?>

<div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start">

  <div class="card">
    <div class="card-body">
      <form method="post">
        <?php echo csrfField(); ?>

        <div class="form-group">
          <label class="form-required">Email del destinatario</label>
          <input type="email" name="to"
                 value="<?php echo clean(isset($_POST['to']) ? $_POST['to'] : ($quote['client_email'] ?: '')); ?>"
                 placeholder="email@cliente.com" required>
        </div>

        <div class="form-group">
          <label class="form-required">Asunto</label>
          <input type="text" name="subject"
                 value="<?php echo clean(isset($_POST['subject']) ? $_POST['subject'] : $defSubject); ?>" required>
        </div>

        <div class="form-group">
          <label>Mensaje</label>
          <textarea name="body" rows="10"><?php echo clean(isset($_POST['body']) ? $_POST['body'] : $defBody); ?></textarea>
        </div>

        <div class="alert alert-info" style="margin-bottom:16px;font-size:13px">
          &#128274; Remitente: <strong><?php echo $fromEmail; ?></strong><br>
          &#8617; Respuestas a: <strong><?php echo $replyTo; ?></strong>
        </div>

        <div style="display:flex;gap:12px">
          <button type="submit" class="btn btn-primary">&#9993; Enviar email</button>
          <a href="<?php echo APP_URL; ?>/quotes/edit.php?id=<?php echo $quote['id']; ?>" class="btn btn-ghost">Cancelar</a>
        </div>
      </form>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card">
      <div class="card-body">
        <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:10px">Resumen</div>
        <div style="font-size:14px;font-weight:700"><?php echo clean($quote['quote_number']); ?></div>
        <div style="font-size:13px;color:var(--text-muted);margin:2px 0 8px"><?php echo clean($quote['client_name']); ?></div>
        <div style="font-size:22px;font-weight:800;color:#C8102E"><?php echo formatMoney((float)$quote['total']); ?></div>

        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
          <div style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:6px">LINK PUBLICO</div>
          <div style="font-size:11px;background:#f8f8f8;border-radius:6px;padding:8px;word-break:break-all;color:#555;line-height:1.4">
            <?php echo APP_URL; ?>/quotes/view.php?token=<?php echo clean($quote['public_token']); ?>
          </div>
          <button type="button" onclick="
            var url='<?php echo APP_URL; ?>/quotes/view.php?token=<?php echo clean($quote['public_token']); ?>';
            navigator.clipboard.writeText(url).then(function(){
              this.textContent='&#10003; Copiado';
            }.bind(this));" class="btn btn-ghost btn-sm" style="margin-top:8px;width:100%">
            Copiar link
          </button>
        </div>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/../admin/layout-bottom.php'; ?>
