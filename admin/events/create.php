<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();

$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $clientId     = cleanInt(isset($_POST['client_id'])       ? $_POST['client_id']       : 0);
    $eventType    = clean(isset($_POST['event_type'])         ? $_POST['event_type']       : '');
    $eventDate    = clean(isset($_POST['event_date'])         ? $_POST['event_date']       : '');
    $eventTime    = clean(isset($_POST['event_time'])         ? $_POST['event_time']       : '');
    $eventDur     = clean(isset($_POST['event_duration'])     ? $_POST['event_duration']   : '');
    $eventLoc     = clean(isset($_POST['event_location'])     ? $_POST['event_location']   : '');
    $numPeople    = cleanInt(isset($_POST['num_people'])      ? $_POST['num_people']       : 0);
    $igvType      = in_array(isset($_POST['igv_type'])?$_POST['igv_type']:'', array('none','10.5','18')) ? $_POST['igv_type'] : 'none';
    $discPct      = min(100, max(0, cleanFloat(isset($_POST['discount_pct'])  ? $_POST['discount_pct']  : 0)));
    $extrasAmt    = max(0, cleanFloat(isset($_POST['extras_amount'])          ? $_POST['extras_amount'] : 0));
    $extrasDet    = clean(isset($_POST['extras_detail'])      ? $_POST['extras_detail']    : '');
    $observations = clean(isset($_POST['observations'])       ? $_POST['observations']     : '');
    $terms        = clean(isset($_POST['terms'])              ? $_POST['terms']            : '');
    $validUntil   = clean(isset($_POST['valid_until'])        ? $_POST['valid_until']      : '');

    if (!$clientId) $errors[] = 'Selecciona un cliente.';
    if (!$eventDate) $errors[] = 'La fecha del evento es obligatoria.';

    $items = array();
    $rawItems = isset($_POST['items']) ? $_POST['items'] : array();
    foreach ($rawItems as $item) {
        $name = clean(isset($item['name']) ? $item['name'] : '');
        if (!$name) continue;
        $unitPrice = max(0, cleanFloat(isset($item['unit_price'])   ? $item['unit_price']   : 0));
        $quantity  = max(0.01, cleanFloat(isset($item['quantity'])  ? $item['quantity']     : 1));
        $discItem  = min(100, max(0, cleanFloat(isset($item['discount_pct']) ? $item['discount_pct'] : 0)));
        $priceMode = in_array(isset($item['price_mode'])?$item['price_mode']:'', array('per_person','per_event','custom')) ? $item['price_mode'] : 'custom';
        $subtotal  = $unitPrice * $quantity * (1 - $discItem / 100);
        $items[] = array(
            'product_id'   => cleanInt(isset($item['product_id']) ? $item['product_id'] : 0),
            'name'         => $name,
            'description'  => clean(isset($item['description']) ? $item['description'] : ''),
            'price_mode'   => $priceMode,
            'unit_price'   => $unitPrice,
            'quantity'     => $quantity,
            'discount_pct' => $discItem,
            'subtotal'     => $subtotal,
        );
    }
    if (empty($items)) $errors[] = 'Agrega al menos un producto o servicio.';

    if (empty($errors)) {
        $subtotal  = array_sum(array_column($items, 'subtotal'));
        $discAmt   = $subtotal * ($discPct / 100);
        $base      = $subtotal - $discAmt + $extrasAmt;
        $igvRate   = $igvType === '18' ? 0.18 : ($igvType === '10.5' ? 0.105 : 0);
        $igvAmt    = $base * $igvRate;
        $total     = $base + $igvAmt;
        $perPerson = $numPeople > 0 ? $total / $numPeople : 0;

        $prefix = 'EV';
        $year   = date('Y');
        $last   = Database::fetch(
            "SELECT quote_number FROM quotes WHERE quote_number LIKE ? ORDER BY id DESC LIMIT 1",
            array($prefix . '-' . $year . '-%')
        );
        $num         = $last ? ((int)end(explode('-', $last['quote_number'])) + 1) : 1;
        $quoteNumber = sprintf('%s-%s-%04d', $prefix, $year, $num);

        $quoteId = Database::insert(
            "INSERT INTO quotes (quote_number, user_id, client_id, status, origin,
             event_type, event_date, event_time, event_duration, event_location,
             num_people, valid_until, igv_type, igv_amount,
             discount_pct, discount_amount, extras_amount, extras_detail,
             subtotal, total, price_per_person, observations, terms,
             public_token, accepted_at, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
            array($quoteNumber, $_SESSION['user_id'], $clientId, 'aceptada', 'event',
             $eventType, $eventDate, $eventTime, $eventDur, $eventLoc,
             $numPeople, $validUntil, $igvType, $igvAmt,
             $discPct, $discAmt, $extrasAmt, $extrasDet,
             $subtotal, $total, $perPerson, $observations, $terms,
             generateToken(24))
        );

        foreach ($items as $i => $item) {
            Database::insert(
                "INSERT INTO quote_items (quote_id,product_id,name,description,price_mode,unit_price,quantity,discount_pct,subtotal,sort_order)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                array($quoteId, $item['product_id'], $item['name'], $item['description'],
                 $item['price_mode'], $item['unit_price'], $item['quantity'],
                 $item['discount_pct'], $item['subtotal'], $i)
            );
        }

        Database::insert(
            "INSERT INTO quote_status_log (quote_id,user_id,from_status,to_status,note) VALUES (?,?,?,?,?)",
            array($quoteId, $_SESSION['user_id'], '', 'aceptada', 'Evento creado directamente')
        );

        flashMessage('success', 'Evento ' . $quoteNumber . ' creado correctamente.');
        redirect('/quotes/edit.php?id=' . $quoteId);
    }
}

$rawTypes   = getSetting('event_types', 'Corporativo,Boda,Cumpleaños,Social / Familiar,Feria gastronómica,Food truck,Otro');
$eventTypes = array_filter(array_map('trim', explode(',', $rawTypes)));
$categories = Database::fetchAll("SELECT id, name FROM categories WHERE active=1 ORDER BY name");
$defTerms   = getSetting('default_terms', '');
$defObs     = getSetting('default_observations', '');

$pageTitle  = 'Nuevo evento';
$activePage = 'event-new';
$extraHead  = '
<link rel="stylesheet" href="' . APP_URL . '/assets/css/quoter.css">
<style>
.event-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(124,58,237,.1);color:#7c3aed;border:1px solid rgba(124,58,237,.25);border-radius:20px;padding:4px 12px;font-size:13px;font-weight:600;margin-bottom:16px}
</style>';

include __DIR__ . '/../layout-top.php';
?>

<div class="breadcrumb">
  <a href="<?php echo APP_URL; ?>/admin/dashboard">Dashboard</a>
  <span class="breadcrumb-sep">›</span>
  <span class="breadcrumb-current">Nuevo evento</span>
</div>

<div class="event-badge">&#128197; Evento directo — se registra como aceptado inmediatamente</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error">&#10007; <?php echo htmlspecialchars($e); ?></div>
<?php endforeach; ?>

<form method="post" id="quoteForm">
<?= csrfField() ?>
<input type="hidden" name="origin" value="event">

<div class="quoter-grid">

  <div class="quoter-left">

    <div class="card">
      <div class="card-header"><span class="card-title">&#128197; Datos del evento</span></div>
      <div class="card-body">

        <div class="form-group">
          <label class="form-required">Cliente</label>
          <div style="display:flex;gap:8px">
            <div style="position:relative;flex:1">
              <span class="search-icon" style="top:50%;transform:translateY(-50%)">&#128269;</span>
              <input type="text" id="clientSearch" placeholder="Buscar cliente por nombre o RUC..."
                     autocomplete="off" style="padding-left:36px">
              <div id="clientDropdown" class="search-dropdown" style="display:none"></div>
            </div>
            <a href="<?= APP_URL ?>/admin/clients/form?back=<?= urlencode(APP_URL . '/admin/events/create') ?>"
               class="btn btn-ghost btn-sm" style="white-space:nowrap">+ Cliente</a>
          </div>
          <input type="hidden" name="client_id" id="clientId" value="0">
          <div id="clientSelected" class="client-chip" style="display:none"></div>
        </div>

        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-required">Tipo de evento</label>
            <select name="event_type" required>
              <option value="">Seleccionar...</option>
              <?php foreach ($eventTypes as $et): ?>
              <option value="<?= clean($et) ?>"><?= clean($et) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Fecha del evento</label>
            <input type="date" name="event_date" min="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label>Hora de inicio</label>
            <input type="time" name="event_time">
          </div>
          <div class="form-group">
            <label>Duracion</label>
            <input type="text" name="event_duration" placeholder="Ej: 3 horas">
          </div>
        </div>

        <div class="form-row form-row-2">
          <div class="form-group">
            <label>Lugar del evento</label>
            <input type="text" name="event_location" placeholder="Local, direccion...">
          </div>
          <div class="form-group">
            <label>N&ordm; de personas</label>
            <input type="number" name="num_people" id="num_people" min="0" placeholder="0">
            <div class="form-hint">Para calcular precio por persona</div>
          </div>
        </div>

      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title">&#127828; Productos</span>
        <span id="itemCount" style="font-size:13px;color:var(--text-muted)">0 items</span>
      </div>
      <div class="card-body">
        <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
          <div style="position:relative;flex:1;min-width:200px">
            <span class="search-icon" style="top:50%;transform:translateY(-50%)">&#128269;</span>
            <input type="text" id="productSearch" placeholder="Buscar producto..."
                   autocomplete="off" style="padding-left:36px">
            <div id="productDropdown" class="search-dropdown" style="display:none"></div>
          </div>
          <select id="catFilter" style="width:auto;padding:9px 12px">
            <option value="">Todas las categorias</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= clean($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" onclick="addManualItem()" class="btn btn-ghost btn-sm">+ Manual</button>
        </div>

        <div class="items-header">
          <span style="flex:3">Producto</span>
          <span style="flex:1.2;text-align:center">Modo</span>
          <span style="flex:1.1;text-align:right">Precio unit.</span>
          <span style="flex:.9;text-align:center">Cant.</span>
          <span style="flex:.8;text-align:center">Desc.%</span>
          <span style="flex:1.1;text-align:right">Subtotal</span>
          <span style="width:32px"></span>
        </div>
        <div id="quoteItemsContainer"></div>
        <div id="emptyItems" style="text-align:center;padding:24px;color:var(--text-muted);font-size:14px">
          Busca o agrega productos arriba
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">&#128203; Notas</span></div>
      <div class="card-body">
        <div class="form-group">
          <label>Observaciones</label>
          <textarea name="observations" rows="3" placeholder="Detalles especiales..."><?= clean($defObs) ?></textarea>
        </div>
        <div class="form-group">
          <label>Terminos y condiciones</label>
          <textarea name="terms" rows="4"><?= clean($defTerms) ?></textarea>
        </div>
      </div>
    </div>

  </div>

  <div class="quoter-right">
    <div class="card totals-card">
      <div class="card-header"><span class="card-title">&#128176; Resumen</span></div>
      <div class="card-body">

        <div class="form-group">
          <label>IGV</label>
          <select name="igv_type" id="igv_type" class="igv-select">
            <option value="none">Sin IGV</option>
            <option value="10.5">IGV 10.5%</option>
            <option value="18">IGV 18%</option>
          </select>
        </div>

        <div class="form-group">
          <label>Descuento global</label>
          <div style="position:relative">
            <input type="number" name="discount_pct" id="discount_pct" value="0" min="0" max="100" step="any">
            <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px">%</span>
          </div>
        </div>

        <div class="form-group">
          <label>Costos adicionales (S/)</label>
          <input type="number" name="extras_amount" id="extras_amount" value="0" min="0" step="any">
          <input type="text" name="extras_detail" id="extras_detail"
                 placeholder="Detalle (movilidad, personal extra...)" style="margin-top:6px">
        </div>

        <div class="totals-table">
          <div class="total-row"><span>Subtotal</span><span id="total-subtotal">S/ 0.00</span></div>
          <div class="total-row" id="row-discount" style="display:none;color:#dc2626"><span>Descuento</span><span id="total-discount"></span></div>
          <div class="total-row" id="row-extras" style="display:none;color:#16a34a"><span>Adicionales</span><span id="total-extras"></span></div>
          <div class="total-row" id="row-igv" style="display:none"><span id="total-igv-label">IGV</span><span id="total-igv"></span></div>
        </div>

        <div class="total-final"><span>TOTAL</span><span id="total-final">S/ 0.00</span></div>
        <div class="total-per-person" id="row-per-person" style="display:none"><span id="total-per-person"></span></div>

        <input type="hidden" name="valid_until" value="<?= date('Y-m-d', strtotime('+' . getSetting('quote_validity_days','15') . ' days')) ?>">
        <input type="hidden" id="calc_subtotal"     name="calc_subtotal">
        <input type="hidden" id="calc_discount_amt" name="calc_discount_amt">
        <input type="hidden" id="calc_igv_amount"   name="calc_igv_amount">
        <input type="hidden" id="calc_total"        name="calc_total">
        <input type="hidden" id="calc_per_person"   name="calc_per_person">

        <button type="submit" class="btn btn-primary btn-lg btn-block"
                style="margin-top:16px;background:#7c3aed;border-color:#7c3aed">
          &#128197; Guardar evento
        </button>
        <a href="<?= APP_URL ?>/admin/dashboard" class="btn btn-ghost btn-block" style="margin-top:8px">Cancelar</a>

      </div>
    </div>
  </div>

</div>
</form>

<template id="itemRowTemplate">
  <div class="item-row" data-idx="__IDX__">
    <div class="item-name-col">
      <strong class="item-name-text">__NAME__</strong>
      <input type="hidden" name="items[__IDX__][product_id]" value="__PRODUCT_ID__">
      <input type="hidden" name="items[__IDX__][name]"       value="__NAME__">
      <input type="hidden" name="items[__IDX__][description]" value="__DESC__">
      <button type="button" class="btn-del" onclick="removeItem(this)" title="Quitar" style="display:none">✕</button>
    </div>
    <div class="item-mode-col item-field">
      <span class="item-field-label">Modo</span>
      <select name="items[__IDX__][price_mode]" data-field="price_mode"
              data-per-person="__PRICE_PP__" data-per-event="__PRICE_PE__" class="input-sm">
        <option value="per_person">x persona</option>
        <option value="per_event">x evento</option>
        <option value="custom">Libre</option>
      </select>
    </div>
    <div class="item-price-col item-field">
      <span class="item-field-label">Precio</span>
      <input type="text" inputmode="decimal" name="items[__IDX__][unit_price]"
             data-field="unit_price" value="__PRICE_PP__" placeholder="0.00"
             class="input-sm text-right" readonly>
    </div>
    <div class="item-qty-col item-field">
      <span class="item-field-label">Cant.</span>
      <input type="text" inputmode="decimal" name="items[__IDX__][quantity]"
             data-field="quantity" value="" placeholder="1" class="input-sm text-center">
    </div>
    <div class="item-disc-col item-field">
      <span class="item-field-label">Desc.%</span>
      <div style="position:relative">
        <input type="text" inputmode="decimal" name="items[__IDX__][discount_pct]"
               data-field="discount_pct" value="" placeholder="0" class="input-sm text-center">
        <span class="pct-symbol">%</span>
      </div>
    </div>
    <div class="item-sub-col item-field">
      <span class="item-field-label">Subtotal</span>
      <span data-field="subtotal" class="subtotal-display">S/ 0.00</span>
    </div>
    <div class="item-del-col">
      <button type="button" class="btn-del" onclick="removeItem(this)" title="Quitar">✕</button>
    </div>
  </div>
</template>

<?php
$extraScripts = '
<script>
const API_URL    = "' . APP_URL . '/api/quotes.php";
const CSRF_TOKEN = "' . csrfToken() . '";
</script>
<script src="' . APP_URL . '/assets/js/quoter.js"></script>
';
include __DIR__ . '/../layout-bottom.php';
?>
