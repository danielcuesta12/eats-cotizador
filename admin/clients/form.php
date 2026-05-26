<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();

$id     = cleanInt(isset($_GET['id']) ? $_GET['id'] : 0);
$client = $id ? Database::fetch("SELECT * FROM clients WHERE id=?", array($id)) : null;
if ($id && !$client) { flashMessage('error','Cliente no encontrado.'); redirect('/admin/clients/index.php'); }

$isEdit = (bool)$client;
$errors = array();
$data   = $client ? $client : array('type'=>'empresa','name'=>'','ruc_dni'=>'','contact_name'=>'','email'=>'','phone'=>'','address'=>'','notes'=>'','active'=>1);

// Codigos de pais mas usados en Peru
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

// Funcion para normalizar telefono: extrae codigo y numero por separado para el form
function parsePhone($fullPhone) {
    $digits = preg_replace('/\D/', '', $fullPhone);
    if (empty($digits)) return array('code' => '51', 'number' => '');
    // Si empieza con 51 y tiene 11 digitos -> Peru
    if (strlen($digits) === 11 && substr($digits, 0, 2) === '51') {
        return array('code' => '51', 'number' => substr($digits, 2));
    }
    // Otros codigos de pais conocidos (3 digitos)
    $codes3 = array('593','591','595','598');
    foreach ($codes3 as $c) {
        if (substr($digits, 0, 3) === $c) {
            return array('code' => $c, 'number' => substr($digits, 3));
        }
    }
    // 2 digitos
    $codes2 = array('56','57','54','55','52','34');
    foreach ($codes2 as $c) {
        if (substr($digits, 0, 2) === $c) {
            return array('code' => $c, 'number' => substr($digits, 2));
        }
    }
    // 1 digito (US +1)
    if (substr($digits, 0, 1) === '1' && strlen($digits) >= 11) {
        return array('code' => '1', 'number' => substr($digits, 1));
    }
    // No se reconoce -> asumir Peru
    return array('code' => '51', 'number' => $digits);
}

// Parsear telefono existente para pre-llenar el form
$phoneParsed = parsePhone(isset($data['phone']) ? $data['phone'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Normalizar telefono: codigo + numero -> guardado junto en BD
    $countryCode = preg_replace('/\D/', '', isset($_POST['country_code']) ? $_POST['country_code'] : '51');
    $phoneNum    = preg_replace('/\D/', '', isset($_POST['phone_number'])  ? $_POST['phone_number']  : '');
    $fullPhone   = ($countryCode && $phoneNum) ? $countryCode . $phoneNum : '';

    $data = array(
        'type'         => in_array(isset($_POST['type']) ? $_POST['type'] : '', array('empresa','persona')) ? $_POST['type'] : 'empresa',
        'name'         => clean(isset($_POST['name'])         ? $_POST['name']         : ''),
        'ruc_dni'      => clean(isset($_POST['ruc_dni'])      ? $_POST['ruc_dni']      : ''),
        'contact_name' => clean(isset($_POST['contact_name']) ? $_POST['contact_name'] : ''),
        'email'        => clean(isset($_POST['email'])        ? $_POST['email']        : ''),
        'phone'        => $fullPhone,
        'address'      => clean(isset($_POST['address'])      ? $_POST['address']      : ''),
        'notes'        => clean(isset($_POST['notes'])        ? $_POST['notes']        : ''),
        'active'       => isset($_POST['active']) ? 1 : 0,
    );

    if (!$data['name'])
        $errors[] = 'El nombre / razon social es obligatorio.';
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
        $errors[] = 'El email no es valido.';
    if ($data['type'] === 'empresa' && $data['ruc_dni'] && strlen($data['ruc_dni']) !== 11)
        $errors[] = 'El RUC debe tener exactamente 11 digitos.';
    if ($data['type'] === 'persona' && $data['ruc_dni'] && !in_array(strlen($data['ruc_dni']), array(8,9)))
        $errors[] = 'El DNI debe tener 8 o 9 digitos.';

    if (empty($errors)) {
        if ($isEdit) {
            Database::execute(
                "UPDATE clients SET type=?,name=?,ruc_dni=?,contact_name=?,email=?,phone=?,address=?,notes=?,active=? WHERE id=?",
                array($data['type'],$data['name'],$data['ruc_dni'],$data['contact_name'],
                 $data['email'],$data['phone'],$data['address'],$data['notes'],$data['active'],$id)
            );
            flashMessage('success','Cliente actualizado.');
        } else {
            Database::insert(
                "INSERT INTO clients (type,name,ruc_dni,contact_name,email,phone,address,notes,active)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                array($data['type'],$data['name'],$data['ruc_dni'],$data['contact_name'],
                 $data['email'],$data['phone'],$data['address'],$data['notes'],$data['active'])
            );
            flashMessage('success','Cliente creado.');
        }
        $back = clean(isset($_GET['back']) ? $_GET['back'] : '');
        redirect($back ?: '/admin/clients/index.php');
    }

    // Si hay error, re-parsear para mostrar en form
    $phoneParsed = array(
        'code'   => preg_replace('/\D/', '', isset($_POST['country_code']) ? $_POST['country_code'] : '51'),
        'number' => preg_replace('/\D/', '', isset($_POST['phone_number'])  ? $_POST['phone_number']  : ''),
    );
}

$pageTitle  = $isEdit ? 'Editar cliente' : 'Nuevo cliente';
$activePage = 'clients';
include __DIR__ . '/../layout-top.php';
?>

<div class="breadcrumb">
  <a href="<?php echo APP_URL; ?>/admin/clients/index.php">Clientes</a>
  <span class="breadcrumb-sep">›</span>
  <span class="breadcrumb-current"><?php echo $isEdit ? 'Editar' : 'Nuevo'; ?></span>
</div>
<div class="page-header">
  <div class="page-header-left"><h1><?php echo $pageTitle; ?></h1></div>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error">&#10007; <?php echo htmlspecialchars($e); ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:600px">
  <div class="card-body">
    <form method="post">
      <?php echo csrfField(); ?>

      <!-- Tipo de cliente -->
      <div class="form-group">
        <label class="form-required">Tipo de cliente</label>
        <div style="display:flex;gap:10px;margin-top:4px" id="typeToggle">
          <label style="flex:1;cursor:pointer">
            <input type="radio" name="type" value="empresa"
                   <?php echo $data['type']==='empresa' ? 'checked' : ''; ?>
                   style="display:none" onchange="updateTypeUI()">
            <div class="type-option" id="opt-empresa"
                 style="border:2px solid;border-radius:10px;padding:14px;text-align:center;transition:.15s">
              <div style="font-size:22px">&#127970;</div>
              <div style="font-size:13px;font-weight:600;margin-top:4px">Empresa</div>
              <div style="font-size:11px;color:var(--text-muted)">Con RUC</div>
            </div>
          </label>
          <label style="flex:1;cursor:pointer">
            <input type="radio" name="type" value="persona"
                   <?php echo $data['type']==='persona' ? 'checked' : ''; ?>
                   style="display:none" onchange="updateTypeUI()">
            <div class="type-option" id="opt-persona"
                 style="border:2px solid;border-radius:10px;padding:14px;text-align:center;transition:.15s">
              <div style="font-size:22px">&#128100;</div>
              <div style="font-size:13px;font-weight:600;margin-top:4px">Persona natural</div>
              <div style="font-size:11px;color:var(--text-muted)">Con DNI</div>
            </div>
          </label>
        </div>
      </div>

      <div class="form-group">
        <label class="form-required" id="nameLabel">Razon social</label>
        <input type="text" name="name" value="<?php echo clean($data['name']); ?>"
               id="nameInput" placeholder="Nombre de la empresa o persona" required autofocus>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label id="docLabel">RUC</label>
          <input type="text" name="ruc_dni" value="<?php echo clean(isset($data['ruc_dni']) ? $data['ruc_dni'] : ''); ?>"
                 id="docInput" placeholder="11 digitos"
                 maxlength="11" inputmode="numeric">
        </div>
        <div class="form-group" id="contactGroup">
          <label>Nombre del contacto</label>
          <input type="text" name="contact_name"
                 value="<?php echo clean(isset($data['contact_name']) ? $data['contact_name'] : ''); ?>"
                 placeholder="Contacto en la empresa">
        </div>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email"
               value="<?php echo clean(isset($data['email']) ? $data['email'] : ''); ?>"
               placeholder="correo@empresa.com">
      </div>

      <!-- TELEFONO CON SELECTOR DE PAIS -->
      <div class="form-group">
        <label>Telefono / WhatsApp</label>
        <div style="display:flex;gap:8px;align-items:stretch">
          <select name="country_code" id="countryCode"
                  style="width:auto;padding:12px 10px;font-size:14px;flex-shrink:0;min-width:90px">
            <?php foreach ($countryCodes as $code => $label): ?>
            <option value="<?php echo $code; ?>"
                    <?php echo $phoneParsed['code'] === (string)$code ? 'selected' : ''; ?>>
              <?php echo $label; ?>
            </option>
            <?php endforeach; ?>
          </select>
          <input type="tel" name="phone_number" id="phoneNumber"
                 value="<?php echo clean($phoneParsed['number']); ?>"
                 placeholder="987654321"
                 inputmode="numeric"
                 style="flex:1">
        </div>
        <div class="form-hint" style="margin-top:5px">
          Se guardara como: <strong id="phonePreview"><?php echo $data['phone'] ?: '—'; ?></strong>
          &nbsp;&#8594;&nbsp;
          <?php if ($data['phone']): ?>
          <a href="https://wa.me/<?php echo preg_replace('/\D/','',$data['phone']); ?>"
             target="_blank" style="color:#25D366;font-weight:600;text-decoration:none">
            Probar WhatsApp
          </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-group">
        <label>Direccion</label>
        <input type="text" name="address"
               value="<?php echo clean(isset($data['address']) ? $data['address'] : ''); ?>"
               placeholder="Av. Ejemplo 1234, San Isidro, Lima">
      </div>

      <div class="form-group">
        <label>Notas internas</label>
        <textarea name="notes" rows="2"
                  placeholder="Informacion interna (no aparece en el PDF)"><?php echo clean(isset($data['notes']) ? $data['notes'] : ''); ?></textarea>
      </div>

      <label class="toggle-wrap" style="cursor:pointer;margin-bottom:20px">
        <input type="checkbox" name="active" value="1"
               <?php echo $data['active'] ? 'checked' : ''; ?>
               style="width:18px;height:18px;accent-color:var(--red)">
        <span class="toggle-label">Cliente activo</span>
      </label>

      <div style="display:flex;gap:12px">
        <button type="submit" class="btn btn-primary">
          <?php echo $isEdit ? '&#128190; Guardar cambios' : '+ Crear cliente'; ?>
        </button>
        <a href="<?php echo APP_URL; ?>/admin/clients/index.php" class="btn btn-ghost">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<style>
.type-option { border-color: var(--border) !important; color: var(--text-secondary); }
.type-option.selected { border-color: var(--red) !important; background: var(--red-light); color: var(--red); }
</style>

<script>
function updateTypeUI() {
  var tipo = document.querySelector('input[name="type"]:checked');
  if (!tipo) return;
  tipo = tipo.value;
  document.getElementById('opt-empresa').classList.toggle('selected', tipo === 'empresa');
  document.getElementById('opt-persona').classList.toggle('selected', tipo === 'persona');
  document.getElementById('nameLabel').textContent  = tipo === 'empresa' ? 'Razon social' : 'Nombre completo';
  document.getElementById('nameInput').placeholder  = tipo === 'empresa' ? 'Nombre de la empresa' : 'Nombre y apellidos';
  document.getElementById('docLabel').textContent   = tipo === 'empresa' ? 'RUC' : 'DNI';
  document.getElementById('docInput').placeholder   = tipo === 'empresa' ? '11 digitos' : '8 digitos';
  document.getElementById('docInput').maxLength     = tipo === 'empresa' ? 11 : 9;
  document.getElementById('contactGroup').style.display = tipo === 'empresa' ? '' : 'none';
}

// Preview del numero completo
function updatePhonePreview() {
  var code   = document.getElementById('countryCode').value.replace(/\D/g,'');
  var number = document.getElementById('phoneNumber').value.replace(/\D/g,'');
  var full   = (code && number) ? '+' + code + ' ' + number : '—';
  var stored = (code && number) ? code + number : '—';
  document.getElementById('phonePreview').textContent = stored;
}

document.getElementById('countryCode').addEventListener('change', updatePhonePreview);
document.getElementById('phoneNumber').addEventListener('input',  updatePhonePreview);

updateTypeUI();
updatePhonePreview();
</script>

<?php include __DIR__ . '/../layout-bottom.php'; ?>
