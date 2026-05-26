<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>Solicitar cotización</title>
<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

$co = array(
    'name'   => getSetting('company_name',      'El Gringo Burger Joint'),
    'color1' => getSetting('pdf_primary_color',   '#C8102E'),
    'color2' => getSetting('pdf_secondary_color', '#ffffff'),
    'logo'   => getSetting('company_logo',        ''),
    'wa'     => getSetting('whatsapp_number',     ''),
);
$logoUrl  = !empty($co['logo']) ? UPLOAD_URL . $co['logo'] : '';
$logoPath = !empty($co['logo']) ? UPLOAD_PATH . $co['logo'] : '';
$c1       = $co['color1'];

$countryCodes = array(
    '51'  => 'PE +51',
    '56'  => 'CL +56',
    '57'  => 'CO +57',
    '54'  => 'AR +54',
    '55'  => 'BR +55',
    '1'   => 'US +1',
    '34'  => 'ES +34',
    '52'  => 'MX +52',
    '593' => 'EC +593',
    '591' => 'BO +591',
    '595' => 'PY +595',
    '598' => 'UY +598',
);

$errors  = array();
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type     = in_array(isset($_POST['type'])?$_POST['type']:'', array('empresa','persona')) ? $_POST['type'] : 'empresa';
    $name     = clean(isset($_POST['name'])         ? $_POST['name']         : '');
    $rucDni   = clean(isset($_POST['ruc_dni'])      ? $_POST['ruc_dni']      : '');
    $contact  = clean(isset($_POST['contact_name']) ? $_POST['contact_name'] : '');
    $email    = clean(isset($_POST['email'])        ? $_POST['email']        : '');
    $code     = preg_replace('/\D/', '', isset($_POST['country_code'])  ? $_POST['country_code']  : '51');
    $phoneNum = preg_replace('/\D/', '', isset($_POST['phone_number'])  ? $_POST['phone_number']  : '');
    $phone    = ($code && $phoneNum) ? $code . $phoneNum : '';
    $evDate   = clean(isset($_POST['event_date'])     ? $_POST['event_date']     : '');
    $evTime   = clean(isset($_POST['event_time'])     ? $_POST['event_time']     : '');
    $evDur    = clean(isset($_POST['event_duration']) ? $_POST['event_duration'] : '');
    $evLoc    = clean(isset($_POST['event_location']) ? $_POST['event_location'] : '');
    $numP     = cleanInt(isset($_POST['num_people'])  ? $_POST['num_people']     : 0);
    $comments = clean(isset($_POST['comments'])       ? $_POST['comments']       : '');

    if (!$name)  $errors[] = 'El nombre es obligatorio.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no es valido.';
    if (!$evDate) $errors[] = 'La fecha del evento es obligatoria.';
    if (!$numP)   $errors[] = 'Indica el numero de personas.';

    // Anti-spam simple
    if (isset($_POST['website']) && !empty($_POST['website'])) {
        $success = true; // honeypot
    } elseif (empty($errors)) {
        $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
        Database::insert(
            "INSERT INTO quote_requests (type,name,ruc_dni,contact_name,email,phone,event_date,event_time,event_duration,event_location,num_people,comments,ip_address)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            array($type,$name,$rucDni,$contact,$email,$phone,$evDate,$evTime,$evDur,$evLoc,$numP,$comments,$ip)
        );
        $success = true;
    }
}
?>
<meta name="theme-color" content="<?php echo htmlspecialchars($c1); ?>">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--red:<?php echo htmlspecialchars($c1); ?>;--text-on-red:<?php echo htmlspecialchars($co["color2"]); ?>;--text-btn:<?php echo htmlspecialchars($co["color2"]); ?>}
html{-webkit-text-size-adjust:100%}
body{font-family:-apple-system,'SF Pro Text','Segoe UI',sans-serif;background:#f0f0f0;min-height:100vh;-webkit-font-smoothing:antialiased}
.page{max-width:560px;margin:0 auto;background:#fff;min-height:100vh}
.page-header{background:var(--red);padding:20px 18px;padding-top:max(20px,env(safe-area-inset-top))}
.page-header-logo img{max-height:44px;max-width:140px;object-fit:contain;display:block;margin-bottom:10px}
.page-header-name{font-size:18px;font-weight:800;color:var(--text-on-red);margin-bottom:4px}
.page-header-sub{font-size:13px;color:var(--text-on-red);opacity:.7}
.form-body{padding:20px 18px;padding-bottom:max(24px,env(safe-area-inset-bottom))}

/* Tipo toggle */
.seg{display:flex;background:#f0f0f0;border-radius:10px;padding:3px;margin-bottom:20px}
.seg-opt{flex:1;text-align:center;padding:10px;font-size:14px;font-weight:600;border-radius:8px;color:#666;cursor:pointer;-webkit-tap-highlight-color:transparent;transition:.15s}
.seg-opt.on{background:#fff;color:#1a1a1a;box-shadow:0 1px 3px rgba(0,0,0,.1)}

/* Secciones */
.section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#888;margin-bottom:12px;margin-top:20px}
.section-title:first-child{margin-top:0}
.divider{height:1px;background:#f0f0f0;margin:20px 0}

/* Campos */
.fgroup{margin-bottom:14px}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.frow.full{grid-template-columns:1fr}
label{display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:5px}
label span{color:var(--red)}
input,select,textarea{width:100%;padding:13px 14px;background:#f8f8f8;border:1.5px solid #e5e5e5;border-radius:10px;font-size:16px;color:#1a1a1a;font-family:inherit;outline:none;-webkit-appearance:none;transition:border-color .2s}
input:focus,select:focus,textarea:focus{border-color:var(--red);background:#fff}
textarea{min-height:90px;resize:vertical}

/* Teléfono con código de país */
.phone-wrap{display:flex;gap:8px}
.phone-code{width:110px;flex-shrink:0}
.phone-num{flex:1}

/* Honeypot oculto */
.hp{display:none}

/* Botón */
.btn-submit{width:100%;padding:16px;background:var(--red);color:var(--text-btn);border:none;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;margin-top:8px;min-height:54px;-webkit-tap-highlight-color:transparent;transition:opacity .15s}
.btn-submit:active{opacity:.8}
.btn-submit:disabled{opacity:.6}

/* Alertas */
.alert{padding:14px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;line-height:1.5}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.alert-success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}

/* Success screen */
.success-screen{text-align:center;padding:48px 24px}
.success-icon{font-size:56px;margin-bottom:16px}
.success-title{font-size:22px;font-weight:800;color:#1a1a1a;margin-bottom:8px}
.success-sub{font-size:15px;color:#666;line-height:1.6}

@media(min-width:560px){
  .page{min-height:auto;margin:24px auto;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1)}
  body{padding:24px 16px}
}
</style>
</head>
<body>
<div class="page">
  <div class="page-header">
    <?php if ($logoUrl && file_exists($logoPath)): ?>
      <div class="page-header-logo"><img src="<?php echo clean($logoUrl); ?>" alt="<?php echo clean($co['name']); ?>"></div>
    <?php else: ?>
      <div class="page-header-name"><?php echo clean($co['name']); ?></div>
    <?php endif; ?>
    <div class="page-header-sub">Solicitar cotización de evento</div>
  </div>

  <?php if ($success): ?>
  <div class="success-screen">
    <div class="success-icon">🎉</div>
    <div class="success-title">Solicitud enviada</div>
    <div class="success-sub">
      Recibimos tu solicitud correctamente.<br>
      Nos pondremos en contacto contigo a la brevedad para confirmar los detalles.
    </div>
  </div>
  <?php else: ?>

  <div class="form-body">
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-error">&#10007; <?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <form method="post" id="reqForm">
      <!-- Honeypot anti-spam -->
      <div class="hp"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

      <!-- Tipo -->
      <div class="seg" id="typeToggle">
        <div class="seg-opt on" id="opt-empresa" onclick="setType('empresa')">Empresa</div>
        <div class="seg-opt" id="opt-persona" onclick="setType('persona')">Persona natural</div>
      </div>
      <input type="hidden" name="type" id="typeInput" value="empresa">

      <!-- Datos del cliente -->
      <div class="section-title">Tus datos</div>

      <div class="frow">
        <div class="fgroup">
          <label id="docLabel">RUC <span>*</span></label>
          <input type="text" name="ruc_dni" id="docInput"
                 value="<?php echo clean(isset($_POST['ruc_dni'])?$_POST['ruc_dni']:''); ?>"
                 placeholder="11 digitos" inputmode="numeric" maxlength="11">
        </div>
        <div class="fgroup" id="contactGroup">
          <label>Nombre del contacto</label>
          <input type="text" name="contact_name"
                 value="<?php echo clean(isset($_POST['contact_name'])?$_POST['contact_name']:''); ?>"
                 placeholder="Persona de contacto">
        </div>
      </div>

      <div class="fgroup">
        <label id="nameLabel">Razon social <span>*</span></label>
        <input type="text" name="name"
               value="<?php echo clean(isset($_POST['name'])?$_POST['name']:''); ?>"
               id="nameInput" placeholder="Nombre de la empresa" required>
      </div>

      <div class="fgroup">
        <label>Email</label>
        <input type="email" name="email"
               value="<?php echo clean(isset($_POST['email'])?$_POST['email']:''); ?>"
               placeholder="correo@empresa.com" inputmode="email">
      </div>

      <!-- Teléfono con código de país -->
      <div class="fgroup">
        <label>Celular / WhatsApp</label>
        <div class="phone-wrap">
          <div class="phone-code">
            <select name="country_code" id="countryCode">
              <?php foreach ($countryCodes as $code => $label): ?>
              <option value="<?php echo $code; ?>"
                      <?php echo (isset($_POST['country_code'])?$_POST['country_code']:'51') === (string)$code ? 'selected' : ''; ?>>
                <?php echo $label; ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="phone-num">
            <input type="tel" name="phone_number" id="phoneNumber"
                   value="<?php echo clean(isset($_POST['phone_number'])?$_POST['phone_number']:''); ?>"
                   placeholder="987654321" inputmode="numeric">
          </div>
        </div>
      </div>

      <div class="divider"></div>

      <!-- Datos del evento -->
      <div class="section-title">Datos del evento</div>

      <div class="frow">
        <div class="fgroup">
          <label>Fecha <span>*</span></label>
          <input type="date" name="event_date"
                 value="<?php echo clean(isset($_POST['event_date'])?$_POST['event_date']:''); ?>"
                 min="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="fgroup">
          <label>Hora de inicio</label>
          <input type="time" name="event_time"
                 value="<?php echo clean(isset($_POST['event_time'])?$_POST['event_time']:''); ?>">
        </div>
      </div>

      <div class="frow">
        <div class="fgroup">
          <label>Duracion estimada</label>
          <input type="text" name="event_duration"
                 value="<?php echo clean(isset($_POST['event_duration'])?$_POST['event_duration']:''); ?>"
                 placeholder="Ej: 3 horas">
        </div>
        <div class="fgroup">
          <label>N° de personas <span>*</span></label>
          <input type="number" name="num_people"
                 value="<?php echo cleanInt(isset($_POST['num_people'])?$_POST['num_people']:0)?:'' ; ?>"
                 placeholder="0" min="1" inputmode="numeric">
        </div>
      </div>

      <div class="fgroup">
        <label>Lugar del evento</label>
        <input type="text" name="event_location"
               value="<?php echo clean(isset($_POST['event_location'])?$_POST['event_location']:''); ?>"
               placeholder="Direccion o nombre del local">
      </div>

      <div class="fgroup">
        <label>Comentarios adicionales</label>
        <textarea name="comments" placeholder="Cuéntanos mas sobre tu evento, requerimientos especiales, etc."><?php echo clean(isset($_POST['comments'])?$_POST['comments']:''); ?></textarea>
      </div>

      <button type="submit" class="btn-submit" id="submitBtn">
        Enviar solicitud
      </button>
    </form>
  </div>
  <?php endif; ?>

</div>

<script>
function setType(t) {
  document.getElementById('typeInput').value = t;
  document.getElementById('opt-empresa').classList.toggle('on', t==='empresa');
  document.getElementById('opt-persona').classList.toggle('on', t==='persona');
  document.getElementById('nameLabel').textContent  = (t==='empresa' ? 'Razon social' : 'Nombre completo') + ' *';
  document.getElementById('nameInput').placeholder  = t==='empresa' ? 'Nombre de la empresa' : 'Nombre y apellidos';
  document.getElementById('docLabel').textContent   = (t==='empresa' ? 'RUC' : 'DNI');
  document.getElementById('docInput').placeholder   = t==='empresa' ? '11 digitos' : '8 digitos';
  document.getElementById('docInput').maxLength     = t==='empresa' ? 11 : 9;
  document.getElementById('contactGroup').style.display = t==='empresa' ? '' : 'none';
}

document.getElementById('reqForm').addEventListener('submit', function() {
  var btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.textContent = 'Enviando...';
});
</script>
</body>
</html>
