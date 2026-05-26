<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();
requireAdmin();

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $fields = [
        'company_name','company_ruc','company_address',
        'company_phone','company_email','company_website',
        'quote_prefix','quote_validity_days','default_igv',
        'default_terms','default_observations','whatsapp_number',
        'pdf_primary_color','pdf_secondary_color',
    ];

    foreach ($fields as $k) {
        $val = clean($_POST[$k] ?? '');
        setSetting($k, $val);
    }

    // Logo activo (a o b)
    $activeLogoVal = in_array($_POST['active_logo'] ?? 'a', ['a','b']) ? $_POST['active_logo'] : 'a';
    setSetting('active_logo', $activeLogoVal);

    // Logo A
    if (!empty($_FILES['company_logo']['name'])) {
        $uploaded = uploadImage($_FILES['company_logo'], 'logos');
        if ($uploaded) {
            $old = getSetting('company_logo');
            if ($old && file_exists(UPLOAD_PATH . $old)) @unlink(UPLOAD_PATH . $old);
            setSetting('company_logo', $uploaded);
        } else {
            $errors[] = 'Error al subir Logo A. Usa JPG, PNG o WebP (máx. 2MB).';
        }
    }
    if (isset($_POST['remove_logo'])) {
        $old = getSetting('company_logo');
        if ($old && file_exists(UPLOAD_PATH . $old)) @unlink(UPLOAD_PATH . $old);
        setSetting('company_logo', '');
    }

    // Logo B
    if (!empty($_FILES['company_logo_b']['name'])) {
        $uploaded = uploadImage($_FILES['company_logo_b'], 'logos');
        if ($uploaded) {
            $old = getSetting('company_logo_b');
            if ($old && file_exists(UPLOAD_PATH . $old)) @unlink(UPLOAD_PATH . $old);
            setSetting('company_logo_b', $uploaded);
        } else {
            $errors[] = 'Error al subir Logo B. Usa JPG, PNG o WebP (máx. 2MB).';
        }
    }
    if (isset($_POST['remove_logo_b'])) {
        $old = getSetting('company_logo_b');
        if ($old && file_exists(UPLOAD_PATH . $old)) @unlink(UPLOAD_PATH . $old);
        setSetting('company_logo_b', '');
    }

    // Guardar cuentas bancarias
    Database::execute("DELETE FROM bank_accounts WHERE 1=1");
    $bankNames = isset($_POST['bank_name']) ? $_POST['bank_name'] : array();
    foreach ($bankNames as $i => $bname) {
        $bname = clean($bname);
        if (!$bname) continue;
        Database::insert(
            "INSERT INTO bank_accounts (bank_name, account_holder, tax_id, account_type, currency, account_number, cci, sort_order)
             VALUES (?,?,?,?,?,?,?,?)",
            array(
                $bname,
                clean(isset($_POST['account_holder'][$i]) ? $_POST['account_holder'][$i] : ''),
                clean(isset($_POST['tax_id'][$i])         ? $_POST['tax_id'][$i]         : ''),
                clean(isset($_POST['account_type'][$i])   ? $_POST['account_type'][$i]   : 'ahorros'),
                clean(isset($_POST['currency'][$i])       ? $_POST['currency'][$i]       : 'soles'),
                clean(isset($_POST['account_number'][$i]) ? $_POST['account_number'][$i] : ''),
                clean(isset($_POST['cci'][$i])            ? $_POST['cci'][$i]            : ''),
                $i
            )
        );
    }

    if (empty($errors)) {
        flashMessage('success', 'Configuración guardada correctamente.');
        redirect('/admin/settings/index.php');
    }
}

// Cargar valores actuales
$cfg = [];
$rows = Database::fetchAll("SELECT `key`, `value` FROM company_settings");
foreach ($rows as $r) $cfg[$r['key']] = $r['value'];

// Cargar cuentas bancarias
$bankAccounts = Database::fetchAll("SELECT * FROM bank_accounts ORDER BY sort_order");

$pageTitle  = 'Configuración';
$activePage = 'settings';
include __DIR__ . '/../layout-top.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>⚙️ Configuración</h1>
    <p>Datos de tu empresa, PDF, WhatsApp y valores por defecto</p>
  </div>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error">✗ <?= $e ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
<?= csrfField() ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

  <!-- COLUMNA IZQUIERDA -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Datos de la empresa -->
    <div class="card">
      <div class="card-header"><span class="card-title">🏢 Datos de la empresa</span></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-required">Nombre de la empresa</label>
          <input type="text" name="company_name"
                 value="<?= clean($cfg['company_name'] ?? '') ?>"
                 placeholder="El Gringo Burger Joint">
        </div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label>RUC</label>
            <input type="text" name="company_ruc"
                   value="<?= clean($cfg['company_ruc'] ?? '') ?>"
                   placeholder="20xxxxxxxxx" maxlength="11">
          </div>
          <div class="form-group">
            <label>Teléfono / WhatsApp</label>
            <input type="tel" name="company_phone"
                   value="<?= clean($cfg['company_phone'] ?? '') ?>"
                   placeholder="9XX XXX XXX">
          </div>
        </div>
        <div class="form-group">
          <label>Dirección</label>
          <input type="text" name="company_address"
                 value="<?= clean($cfg['company_address'] ?? '') ?>"
                 placeholder="Av. Javier Prado 1234, San Isidro, Lima">
        </div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="company_email"
                   value="<?= clean($cfg['company_email'] ?? '') ?>"
                   placeholder="contacto@elgringo.pe">
          </div>
          <div class="form-group">
            <label>Sitio web</label>
            <input type="text" name="company_website"
                   value="<?= clean($cfg['company_website'] ?? '') ?>"
                   placeholder="www.elgringo.pe">
          </div>
        </div>
      </div>
    </div>

    <!-- WhatsApp -->
    <div class="card">
      <div class="card-header"><span class="card-title">💬 WhatsApp</span></div>
      <div class="card-body">
        <div class="form-group">
          <label>Número de WhatsApp (con código de país)</label>
          <input type="text" name="whatsapp_number"
                 value="<?= clean($cfg['whatsapp_number'] ?? '') ?>"
                 placeholder="51987654321">
          <div class="form-hint">Formato: código de país + número sin espacios. Ejemplo: <strong>51987654321</strong> para Perú</div>
        </div>
        <?php
        $waNum = preg_replace('/\D/', '', $cfg['whatsapp_number'] ?? '');
        if ($waNum): ?>
        <a href="https://wa.me/<?= $waNum ?>" target="_blank" class="btn btn-success btn-sm">
          💬 Probar enlace de WhatsApp
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Cuentas bancarias -->
    <div class="card">
      <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
        <span class="card-title">🏦 Números de cuenta bancaria</span>
      </div>
      <div class="card-body">
        <div id="bankAccountsContainer">
          <?php foreach ($bankAccounts as $i => $ba): ?>
          <div class="bank-entry" style="border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:12px;position:relative">
            <button type="button" onclick="removeBankEntry(this)"
                    style="position:absolute;top:10px;right:10px;background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:16px">&#128465;</button>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
              <div class="form-group" style="margin:0">
                <label>Banco</label>
                <input type="text" name="bank_name[]"
                       value="<?= clean($ba['bank_name']) ?>" placeholder="BCP, Interbank...">
              </div>
              <div class="form-group" style="margin:0">
                <label>Tipo de cuenta</label>
                <select name="account_type[]">
                  <option value="ahorros"   <?= $ba['account_type']==='ahorros'   ?'selected':'' ?>>Ahorros</option>
                  <option value="corriente" <?= $ba['account_type']==='corriente' ?'selected':'' ?>>Corriente</option>
                </select>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
              <div class="form-group" style="margin:0">
                <label>Nombre del titular</label>
                <input type="text" name="account_holder[]"
                       value="<?= clean($ba['account_holder'] ?? '') ?>" placeholder="Smash Peru SAC">
              </div>
              <div class="form-group" style="margin:0">
                <label>RUC / DNI</label>
                <input type="text" name="tax_id[]"
                       value="<?= clean($ba['tax_id'] ?? '') ?>" placeholder="20612345678" inputmode="numeric">
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
              <div class="form-group" style="margin:0">
                <label>Número de cuenta</label>
                <input type="text" name="account_number[]"
                       value="<?= clean($ba['account_number']) ?>" placeholder="XXX-XXXXXXXXX-X-XX">
              </div>
              <div class="form-group" style="margin:0">
                <label>CCI</label>
                <input type="text" name="cci[]"
                       value="<?= clean($ba['cci']) ?>" placeholder="00XXXXXXXXXXXXXXXXXXXXX">
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($bankAccounts)): ?>
          <div id="emptyBankMsg" style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px">
            Sin cuentas configuradas. Agrega una abajo.
          </div>
          <?php endif; ?>
        </div>
        <button type="button" onclick="addBankEntry()" class="btn btn-ghost btn-sm" style="margin-top:4px">
          + Agregar cuenta
        </button>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border)">
        <label style="font-size:13px;font-weight:600">Mostrar en el PDF de cotización</label>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="show_bank_accounts" value="1"
                 <?= ($cfg['show_bank_accounts'] ?? '1') === '1' ? 'checked' : '' ?>
                 style="width:18px;height:18px;accent-color:var(--red)">
          <span style="font-size:13px;color:var(--text-muted)">Visible</span>
        </label>
      </div>
    </div>

    <!-- Términos por defecto -->
    <div class="card">
      <div class="card-header"><span class="card-title">📝 Textos por defecto</span></div>
      <div class="card-body">
        <div class="form-group">
          <label>Términos y condiciones por defecto</label>
          <textarea name="default_terms" rows="7"><?= clean($cfg['default_terms'] ?? '') ?></textarea>
          <div class="form-hint">Se precarga en cada nueva cotización (editable antes de guardar)</div>
        </div>
        <div class="form-group">
          <label>Observaciones por defecto</label>
          <textarea name="default_observations" rows="3"><?= clean($cfg['default_observations'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

  </div>

  <!-- COLUMNA DERECHA -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Logos -->
    <div class="card">
      <div class="card-header"><span class="card-title">🖼 Logos</span></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
        <div class="form-hint" style="margin:0">Sube dos versiones del logo. Elige cuál se muestra en el PDF según el color de fondo.</div>

        <!-- Logo A -->
        <div style="border:1.5px solid var(--border);border-radius:10px;overflow:hidden">
          <div style="padding:10px 14px;background:#fafafa;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <span style="font-size:13px;font-weight:600">Logo A</span>
            <?php if ($cfg['company_logo'] ?? ''): ?>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;color:#dc2626">
              <input type="checkbox" name="remove_logo" value="1" style="accent-color:#dc2626">
              Eliminar
            </label>
            <?php endif; ?>
          </div>
          <div style="padding:12px 14px">
            <?php if ($cfg['company_logo'] ?? ''): ?>
            <div style="background:#1a1a1a;border-radius:8px;padding:12px;text-align:center;margin-bottom:10px">
              <img src="<?= UPLOAD_URL . clean($cfg['company_logo']) ?>"
                   style="max-height:60px;max-width:180px;object-fit:contain" id="logoAPreview">
            </div>
            <?php else: ?>
            <div style="background:#1a1a1a;border-radius:8px;padding:12px;text-align:center;margin-bottom:10px;min-height:50px" id="logoABg">
              <img id="logoAPreview" src="" style="max-height:60px;max-width:180px;object-fit:contain;display:none">
              <span id="logoAEmpty" style="font-size:12px;color:#666">Sin logo</span>
            </div>
            <?php endif; ?>
            <label class="img-upload-box" for="logoInputA" style="padding:10px">
              <div style="font-size:13px;font-weight:600;color:var(--text-secondary)">📤 Subir Logo A</div>
              <div style="font-size:11px;color:var(--text-muted)">PNG transparente · Máx. 2MB</div>
              <input type="file" id="logoInputA" name="company_logo" accept="image/*" style="display:none"
                     onchange="previewLogo(this,'logoAPreview','logoAEmpty')">
            </label>
          </div>
        </div>

        <!-- Logo B -->
        <div style="border:1.5px solid var(--border);border-radius:10px;overflow:hidden">
          <div style="padding:10px 14px;background:#fafafa;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <span style="font-size:13px;font-weight:600">Logo B</span>
            <?php if ($cfg['company_logo_b'] ?? ''): ?>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;color:#dc2626">
              <input type="checkbox" name="remove_logo_b" value="1" style="accent-color:#dc2626">
              Eliminar
            </label>
            <?php endif; ?>
          </div>
          <div style="padding:12px 14px">
            <?php if ($cfg['company_logo_b'] ?? ''): ?>
            <div style="background:#f5f5f0;border-radius:8px;padding:12px;text-align:center;margin-bottom:10px">
              <img src="<?= UPLOAD_URL . clean($cfg['company_logo_b']) ?>"
                   style="max-height:60px;max-width:180px;object-fit:contain" id="logoBPreview">
            </div>
            <?php else: ?>
            <div style="background:#f5f5f0;border-radius:8px;padding:12px;text-align:center;margin-bottom:10px;min-height:50px">
              <img id="logoBPreview" src="" style="max-height:60px;max-width:180px;object-fit:contain;display:none">
              <span id="logoBEmpty" style="font-size:12px;color:#aaa">Sin logo</span>
            </div>
            <?php endif; ?>
            <label class="img-upload-box" for="logoInputB" style="padding:10px">
              <div style="font-size:13px;font-weight:600;color:var(--text-secondary)">📤 Subir Logo B</div>
              <div style="font-size:11px;color:var(--text-muted)">PNG transparente · Máx. 2MB</div>
              <input type="file" id="logoInputB" name="company_logo_b" accept="image/*" style="display:none"
                     onchange="previewLogo(this,'logoBPreview','logoBEmpty')">
            </label>
          </div>
        </div>

        <!-- Selector de logo activo -->
        <div class="form-group" style="margin:0">
          <label style="font-weight:600">Logo activo en el PDF</label>
          <div style="display:flex;gap:10px;margin-top:6px">
            <label style="flex:1;cursor:pointer">
              <input type="radio" name="active_logo" value="a"
                     <?= (($cfg['active_logo'] ?? 'a') === 'a') ? 'checked' : '' ?>
                     style="display:none">
              <div class="logo-opt" id="logoOptA"
                   style="border:2px solid;border-radius:10px;padding:12px;text-align:center;transition:.15s">
                <div style="font-size:22px">A</div>
                <div style="font-size:12px;font-weight:600;margin-top:4px">Logo A</div>
              </div>
            </label>
            <label style="flex:1;cursor:pointer">
              <input type="radio" name="active_logo" value="b"
                     <?= (($cfg['active_logo'] ?? 'a') === 'b') ? 'checked' : '' ?>
                     style="display:none">
              <div class="logo-opt" id="logoOptB"
                   style="border:2px solid;border-radius:10px;padding:12px;text-align:center;transition:.15s">
                <div style="font-size:22px">B</div>
                <div style="font-size:12px;font-weight:600;margin-top:4px">Logo B</div>
              </div>
            </label>
          </div>
        </div>

      </div>
    </div>

    <!-- Configuración de cotizaciones -->
    <div class="card">
      <div class="card-header"><span class="card-title">📋 Cotizaciones</span></div>
      <div class="card-body">
        <div class="form-row form-row-2">
          <div class="form-group">
            <label>Prefijo del número</label>
            <input type="text" name="quote_prefix"
                   value="<?= clean($cfg['quote_prefix'] ?? 'EG') ?>"
                   placeholder="EG" maxlength="5">
            <div class="form-hint">Ej: EG → EG-2024-0001</div>
          </div>
          <div class="form-group">
            <label>Vigencia por defecto (días)</label>
            <input type="number" name="quote_validity_days"
                   value="<?= clean($cfg['quote_validity_days'] ?? '15') ?>"
                   min="1" max="365">
          </div>
        </div>
        <div class="form-group">
          <label>IGV por defecto</label>
          <select name="default_igv">
            <option value="none"  <?= ($cfg['default_igv']??'none')==='none'  ?'selected':'' ?>>Sin IGV</option>
            <option value="10.5"  <?= ($cfg['default_igv']??'')==='10.5'      ?'selected':'' ?>>IGV 10.5%</option>
            <option value="18"    <?= ($cfg['default_igv']??'')==='18'        ?'selected':'' ?>>IGV 18%</option>
          </select>
          <div class="form-hint">Se puede cambiar en cada cotización individual</div>
        </div>
      </div>
    </div>

    <!-- Colores del PDF -->
    <div class="card">
      <div class="card-header"><span class="card-title">🎨 Colores del PDF</span></div>
      <div class="card-body">
        <div class="form-row form-row-2">
          <div class="form-group">
            <label>Color de fondo</label>
            <div style="display:flex;gap:8px;align-items:center">
              <input type="color" name="pdf_primary_color"
                     value="<?= clean($cfg['pdf_primary_color'] ?? '#C8102E') ?>"
                     style="width:44px;height:40px;padding:2px;cursor:pointer;border-radius:6px"
                     oninput="syncText('color1Text',this.value);updatePreview()">
              <input type="text" id="color1Text"
                     value="<?= clean($cfg['pdf_primary_color'] ?? '#C8102E') ?>"
                     style="flex:1;font-family:monospace" maxlength="7"
                     oninput="syncPicker('[name=pdf_primary_color]',this.value);updatePreview()">
            </div>
            <div class="form-hint">Header, cabecera de tabla, total final</div>
          </div>
          <div class="form-group">
            <label>Color de texto en cajas</label>
            <div style="display:flex;gap:8px;align-items:center">
              <input type="color" name="pdf_secondary_color"
                     value="<?= clean($cfg['pdf_secondary_color'] ?? '#ffffff') ?>"
                     style="width:44px;height:40px;padding:2px;cursor:pointer;border-radius:6px"
                     oninput="syncText('color2Text',this.value);updatePreview()">
              <input type="text" id="color2Text"
                     value="<?= clean($cfg['pdf_secondary_color'] ?? '#ffffff') ?>"
                     style="flex:1;font-family:monospace" maxlength="7"
                     oninput="syncPicker('[name=pdf_secondary_color]',this.value);updatePreview()">
            </div>
            <div class="form-hint">Texto dentro del header, tabla y total (ej: blanco #ffffff)</div>
          </div>
        </div>

        <!-- Preview mini del PDF -->
        <div style="border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-top:12px">
          <div id="pdfPreviewHeader" style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center">
            <strong id="pdfPreviewName"><?= clean($cfg['company_name'] ?? 'El Gringo Burger Joint') ?></strong>
            <span style="font-size:13px;opacity:.85">EG-2026-0001</span>
          </div>
          <div style="padding:12px 16px;background:#fff;font-size:12px;color:#666">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px">
              <span>Subtotal</span><span>S/ 500.00</span>
            </div>
            <div id="pdfPreviewTotal" style="display:flex;justify-content:space-between;padding:9px 12px;border-radius:7px;font-weight:700">
              <span>TOTAL</span><span>S/ 500.00</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Guardar -->
    <button type="submit" class="btn btn-primary btn-lg btn-block">
      💾 Guardar configuración
    </button>

  </div>

</div>
</form>

<script>
function syncText(id, val)      { var el=document.getElementById(id); if(el) el.value=val; }
function syncPicker(sel, val)   { var el=document.querySelector(sel); if(el && /^#[0-9a-fA-F]{6}$/.test(val)) el.value=val; }

// Cuentas bancarias
function addBankEntry() {
  var empty = document.getElementById('emptyBankMsg');
  if (empty) empty.style.display = 'none';
  var div = document.createElement('div');
  div.className = 'bank-entry';
  div.style.cssText = 'border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:12px;position:relative';
  div.innerHTML = '<button type="button" onclick="removeBankEntry(this)" style="position:absolute;top:10px;right:10px;background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:16px">&#128465;</button>'
    + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">'
    + '<div class="form-group" style="margin:0"><label>Banco</label><input type="text" name="bank_name[]" placeholder="BCP, Interbank..."></div>'
    + '<div class="form-group" style="margin:0"><label>Tipo de cuenta</label><select name="account_type[]"><option value="ahorros">Ahorros</option><option value="corriente">Corriente</option></select></div>'
    + '</div>'
    + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">'
    + '<div class="form-group" style="margin:0"><label>Nombre del titular</label><input type="text" name="account_holder[]" placeholder="Smash Peru SAC"></div>'
    + '<div class="form-group" style="margin:0"><label>RUC / DNI</label><input type="text" name="tax_id[]" placeholder="20612345678" inputmode="numeric"></div>'
    + '</div>'
    + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">'
    + '<div class="form-group" style="margin:0"><label>Número de cuenta</label><input type="text" name="account_number[]" placeholder="XXX-XXXXXXXXX-X-XX"></div>'
    + '<div class="form-group" style="margin:0"><label>CCI</label><input type="text" name="cci[]" placeholder="00XXXXXXXXXXXXXXXXXXXXX"></div>'
    + '</div>';
  document.getElementById('bankAccountsContainer').appendChild(div);
}
function removeBankEntry(btn) {
  btn.closest('.bank-entry').remove();
}

function previewLogo(input, previewId, emptyId) {
  var file = input.files[0];
  if (!file) return;
  var reader = new FileReader();
  reader.onload = function(e) {
    var img = document.getElementById(previewId);
    if (img) { img.src = e.target.result; img.style.display = 'block'; }
    var empty = document.getElementById(emptyId);
    if (empty) empty.style.display = 'none';
  };
  reader.readAsDataURL(file);
}

function updateLogoSelector() {
  var aChecked = document.querySelector('[name="active_logo"][value="a"]').checked;
  var optA = document.getElementById('logoOptA');
  var optB = document.getElementById('logoOptB');
  optA.style.borderColor = aChecked ? 'var(--red)' : 'var(--border)';
  optA.style.background  = aChecked ? 'var(--red-light)' : '#fff';
  optA.style.color       = aChecked ? 'var(--red)' : 'var(--text-secondary)';
  optB.style.borderColor = !aChecked ? 'var(--red)' : 'var(--border)';
  optB.style.background  = !aChecked ? 'var(--red-light)' : '#fff';
  optB.style.color       = !aChecked ? 'var(--red)' : 'var(--text-secondary)';
}

document.querySelectorAll('[name="active_logo"]').forEach(function(r) {
  r.addEventListener('change', updateLogoSelector);
  // Hacer clickeable el div completo
  r.parentElement.querySelector('.logo-opt').addEventListener('click', function() {
    r.checked = true;
    updateLogoSelector();
  });
});
updateLogoSelector();

function updatePreview() {
  var c1 = document.querySelector('[name="pdf_primary_color"]').value;
  var c2 = document.querySelector('[name="pdf_secondary_color"]').value;
  var hdr = document.getElementById('pdfPreviewHeader');
  var tot = document.getElementById('pdfPreviewTotal');
  hdr.style.background = c1;
  hdr.style.color      = c2;
  tot.style.background = c1;
  tot.style.color      = c2;
}
updatePreview();
</script>

<?php include __DIR__ . '/../layout-bottom.php'; ?>
