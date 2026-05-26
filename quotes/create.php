<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // --- Datos cabecera ---
    $clientId        = cleanInt($_POST['client_id']    ?? 0);
    $eventType       = clean($_POST['event_type']       ?? '');
    $eventDate       = clean($_POST['event_date']       ?? '');
    $eventLoc        = clean($_POST['event_location']   ?? '');
    $numPeople       = cleanInt($_POST['num_people']    ?? 0);
    $showInCalendar  = isset($_POST['show_in_calendar']) ? 1 : 0;
    $igvType         = in_array($_POST['igv_type']??'', ['none','18']) ? $_POST['igv_type'] : 'none';
    $discPct      = min(100, max(0, cleanFloat($_POST['discount_pct']   ?? 0)));
    $extrasAmt    = max(0, cleanFloat($_POST['extras_amount']  ?? 0));
    $extrasDetail = clean($_POST['extras_detail']    ?? '');
    $observations = clean($_POST['observations']     ?? '');
    $terms        = clean($_POST['terms']            ?? '');
    $validUntil   = clean($_POST['valid_until']      ?? '');

    // --- Validaciones cabecera ---
    if (!$clientId)  $errors[] = 'Selecciona un cliente.';
    if (!$eventType) $errors[] = 'Indica el tipo de servicio.';

    // --- Ítems ---
    $items     = $_POST['items'] ?? [];
    $itemsClean = [];

    foreach ($items as $i => $item) {
        $name       = clean($item['name']         ?? '');
        $productId  = cleanInt($item['product_id'] ?? 0) ?: null;
        $priceMode  = in_array($item['price_mode']??'',['per_person','per_event','custom'])
                        ? $item['price_mode'] : 'custom';
        $unitPrice  = max(0, cleanFloat($item['unit_price']    ?? 0));
        $qty        = max(0.1, cleanFloat($item['quantity']    ?? 1));
        $discItem   = min(100, max(0, cleanFloat($item['discount_pct'] ?? 0)));
        $desc       = clean($item['description']  ?? '');

        if (!$name) continue; // saltar filas vacías

        $sub = ($unitPrice * $qty) * (1 - $discItem / 100);
        $itemsClean[] = compact('name','productId','priceMode','unitPrice','qty','discItem','sub','desc');
    }

    if (empty($itemsClean)) $errors[] = 'Agrega al menos un producto a la propuesta.';

    if (empty($errors)) {
        // --- Calcular totales ---
        $subtotal    = array_sum(array_column($itemsClean, 'sub'));
        $discAmount  = $subtotal * ($discPct / 100);
        $base        = $subtotal - $discAmount + $extrasAmt;
        $igvRate     = igvRate($igvType);
        $igvAmount   = $base * $igvRate;
        $total       = $base + $igvAmount;
        $perPerson   = $numPeople > 0 ? $total / $numPeople : 0;

        // --- Generar número y token ---
        $year   = date('Y');
        $last   = Database::fetch(
            "SELECT quote_number FROM quotes WHERE quote_number LIKE ? ORDER BY id DESC LIMIT 1",
            ['EA-' . $year . '-%']
        );
        $num         = $last ? ((int) end(explode('-', $last['quote_number'])) + 1) : 1;
        $quoteNumber = sprintf('EA-%s-%04d', $year, $num);
        $token       = generateToken();
        $validDate   = $validUntil ?: date('Y-m-d', strtotime('+' . getSetting('quote_validity_days', 15) . ' days'));

        // --- Insertar propuesta ---
        $quoteId = Database::insert(
            "INSERT INTO quotes
             (quote_number, client_id, user_id, event_type, event_date, event_location,
              num_people, subtotal, discount_pct, discount_amount, extras_amount, extras_detail,
              igv_type, igv_amount, total, price_per_person,
              observations, terms, status, public_token, valid_until, show_in_calendar)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $quoteNumber, $clientId, $_SESSION['user_id'],
                $eventType,
                $eventDate ?: null,
                $eventLoc,
                $numPeople,
                $subtotal, $discPct, $discAmount,
                $extrasAmt, $extrasDetail,
                $igvType, $igvAmount, $total, $perPerson,
                $observations, $terms,
                'borrador',
                $token,
                $validDate,
                $showInCalendar,
            ]
        );

        // --- Insertar ítems ---
        foreach ($itemsClean as $idx => $it) {
            Database::insert(
                "INSERT INTO quote_items
                 (quote_id, product_id, name, description, price_mode, unit_price, quantity, discount_pct, subtotal, sort_order)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$quoteId, $it['productId'], $it['name'], $it['desc'],
                 $it['priceMode'], $it['unitPrice'], $it['qty'], $it['discItem'], $it['sub'], $idx]
            );
        }

        // --- Log de estado ---
        Database::insert(
            "INSERT INTO quote_status_log (quote_id, user_id, from_status, to_status, note)
             VALUES (?,?,?,?,?)",
            [$quoteId, $_SESSION['user_id'], null, 'borrador', 'Propuesta creada']
        );

        flashMessage('success', "Propuesta {$quoteNumber} creada correctamente.");
        redirect('/quotes/edit.php?id=' . $quoteId);
    }
}

// --- Datos para el formulario ---
$categoriesWithProducts = Database::fetchAll(
    "SELECT c.id as cat_id, c.name as cat_name,
            p.id as prod_id, p.name as prod_name,
            COALESCE(p.price_per_person, p.price_per_event, 0) as price
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.active = 1
     WHERE c.active = 1
     ORDER BY c.name, p.name"
);
$catMap = [];
foreach ($categoriesWithProducts as $row) {
    $cid = $row['cat_id'];
    if (!isset($catMap[$cid])) {
        $catMap[$cid] = ['id' => $cid, 'name' => $row['cat_name'], 'products' => []];
    }
    if ($row['prod_id']) {
        $catMap[$cid]['products'][] = ['id' => $row['prod_id'], 'name' => $row['prod_name'], 'price' => (float)$row['price']];
    }
}
// Productos sin categoría asignada (category_id IS NULL) — bloque fallback
$uncategorized = Database::fetchAll(
    "SELECT id as prod_id, name as prod_name,
            COALESCE(price_per_person, price_per_event, 0) as price
     FROM products
     WHERE active = 1 AND (category_id IS NULL OR category_id = 0)
     ORDER BY name"
);
if (!empty($uncategorized)) {
    $catMap[0] = ['id' => 0, 'name' => 'Sin categoría', 'products' => []];
    foreach ($uncategorized as $p) {
        $catMap[0]['products'][] = ['id' => $p['prod_id'], 'name' => $p['prod_name'], 'price' => (float)$p['price']];
    }
}
$defaultTerms = getSetting('default_terms');
// Tipos de evento configurables desde Configuracion → Datos del negocio
$rawTypes   = getSetting('event_types', 'Corporativo,Boda,Cumpleaños,Social / Familiar,Feria gastronómica,Food truck,Otro');
$eventTypes = array_filter(array_map('trim', explode(',', $rawTypes)));

$pageTitle  = 'Nueva propuesta';
$activePage = 'quote-new';

$extraHead = '<link rel="stylesheet" href="' . APP_URL . '/assets/css/quoter.css?v=2">';
include __DIR__ . '/../admin/layout-top.php';
?>

<!-- Errores -->
<?php foreach ($errors as $e): ?>
  <div class="alert alert-error">✗ <?= $e ?></div>
<?php endforeach; ?>

<form method="post" id="quoteForm">
<?= csrfField() ?>

<div class="quoter-grid">

  <!-- ================================================================
       COLUMNA IZQUIERDA: Datos del evento + Productos
       ================================================================ -->
  <div class="quoter-left">

    <!-- SECCIÓN 1: Datos del evento -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">📅 Datos del servicio</span>
      </div>
      <div class="card-body">

        <!-- Cliente -->
        <div class="form-group">
          <label class="form-required">Cliente</label>
          <div style="display:flex;gap:8px">
            <div style="position:relative;flex:1">
              <span class="search-icon" style="top:50%;transform:translateY(-50%)">🔍</span>
              <input type="text" id="clientSearch" placeholder="Buscar cliente por nombre o RUC…"
                     autocomplete="off" style="padding-left:36px">
              <div id="clientDropdown" class="search-dropdown" style="display:none"></div>
            </div>
            <a href="<?= APP_URL ?>/admin/clients/form?back=<?= urlencode(APP_URL . '/quotes/create') ?>"
               class="btn btn-ghost btn-sm" title="Nuevo cliente" style="white-space:nowrap">+ Cliente</a>
          </div>
          <input type="hidden" name="client_id" id="clientId"
                 value="<?= cleanInt($_POST['client_id'] ?? 0) ?>">
          <div id="clientSelected" class="client-chip" style="display:none"></div>
        </div>

        <div class="form-row form-row-2">
          <!-- Tipo de servicio -->
          <div class="form-group">
            <label class="form-required">Tipo de servicio</label>
            <select name="event_type" id="eventType" required>
              <option value="">Seleccionar…</option>
              <?php foreach ($eventTypes as $et): ?>
              <option value="<?= $et ?>" <?= ($_POST['event_type']??'')===$et?'selected':'' ?>>
                <?= $et ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Fecha de inicio -->
          <div class="form-group">
            <label>Fecha de inicio</label>
            <input type="date" name="event_date"
                   value="<?= clean($_POST['event_date'] ?? '') ?>"
                   min="<?= date('Y-m-d') ?>">
          </div>
        </div>

        <div class="form-row form-row-2">
          <!-- Agregar al calendario -->
          <div class="form-group" style="grid-column:1/-1">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600">
              <input type="checkbox" name="show_in_calendar" value="1" id="calendarToggle"
                     style="width:16px;height:16px;cursor:pointer;accent-color:var(--red)"
                     <?= isset($_POST['show_in_calendar']) ? 'checked' : '' ?>>
              Agregar al calendario
            </label>
            <div class="form-hint">Mostrar esta propuesta en el calendario de servicios</div>
          </div>
          <!-- Lugar -->
          <div class="form-group">
            <label>Lugar del servicio</label>
            <input type="text" name="event_location"
                   value="<?= clean($_POST['event_location'] ?? '') ?>"
                   placeholder="Oficina, planta, dirección…">
          </div>
          <!-- N° personas -->
          <div class="form-group">
            <label>N° de personas</label>
            <input type="number" name="num_people" id="num_people"
                   value="<?= cleanInt($_POST['num_people'] ?? 0) ?>"
                   min="0" step="1" placeholder="0">
            <div class="form-hint">Para calcular precio por persona</div>
          </div>
        </div>

      </div>
    </div>

    <!-- SECCIÓN 2: Productos por categoría -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">🍔 Productos</span>
        <span style="font-size:13px;color:var(--text-muted)" id="itemCount">0 ítems</span>
      </div>
      <div class="card-body">

        <?php foreach ($catMap as $cat): ?>
        <div class="cat-block" data-cat-id="<?= (int)$cat['id'] ?>">
          <div class="cat-hdr" onclick="toggleCat(this)">
            <span class="cat-name"><?= clean($cat['name']) ?></span>
            <span class="cat-count">0 ítems</span>
            <span class="cat-chev">&#9662;</span>
          </div>
          <div class="cat-body" style="display:none">
            <div class="cat-items"></div>
            <div class="cat-sep" style="display:none"></div>
            <div class="cat-pills">
              <?php foreach ($cat['products'] as $p): ?>
              <button type="button" class="cat-pill"
                      data-product-id="<?= (int)$p['id'] ?>"
                      data-price="<?= number_format($p['price'], 2, '.', '') ?>"
                      data-name="<?= clean($p['name']) ?>"
                      onclick="addProductFromPill(this)">
                + <?= clean($p['name']) ?>
              </button>
              <?php endforeach; ?>
              <?php if (empty($cat['products'])): ?>
              <span style="font-size:12px;color:var(--text-muted)">Sin productos en el catálogo</span>
              <?php endif; ?>
            </div>
            <div class="cat-free">
              <div class="cat-free-lbl">Ítem personalizado</div>
              <div class="cat-free-row">
                <input type="text" class="cat-free-name" placeholder="Nombre del ítem...">
                <input type="text" class="cat-free-price" inputmode="decimal" placeholder="S/ precio">
                <button type="button" class="cat-free-btn" onclick="addFreeItem(this)">Agregar</button>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($catMap)): ?>
        <div style="text-align:center;padding:32px;color:var(--text-muted)">
          <div style="font-size:14px">No hay categorías activas.
            <a href="<?= APP_URL ?>/admin/categories" style="color:var(--red)">Crear categoría →</a>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- SECCIÓN 3: Observaciones y términos -->
    <div class="card">
      <div class="card-header"><span class="card-title">📝 Notas y condiciones</span></div>
      <div class="card-body">
        <div class="form-group">
          <label>Observaciones</label>
          <textarea name="observations" rows="3"
                    placeholder="Detalles especiales, acuerdos, notas para el cliente…"><?= clean($_POST['observations'] ?? getSetting('default_observations')) ?></textarea>
        </div>
        <div class="form-group">
          <label>Términos y condiciones</label>
          <textarea name="terms" rows="5"
                    id="termsField"><?= clean($_POST['terms'] ?? $defaultTerms) ?></textarea>
          <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap" id="templateBtns"></div>
        </div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label>Vigencia de la cotización</label>
            <input type="date" name="valid_until"
                   value="<?= clean($_POST['valid_until'] ?? date('Y-m-d', strtotime('+' . getSetting('quote_validity_days',15) . ' days'))) ?>">
          </div>
        </div>
      </div>
    </div>

  </div><!-- /quoter-left -->

  <!-- ================================================================
       COLUMNA DERECHA: Totales y configuración
       ================================================================ -->
  <div class="quoter-right">

    <!-- Card de totales (sticky) -->
    <div class="card totals-card">
      <div class="card-header"><span class="card-title">💰 Resumen</span></div>
      <div class="card-body">

        <!-- IGV -->
        <div class="form-group">
          <label>IGV</label>
          <select name="igv_type" id="igv_type" class="igv-select">
            <option value="none" <?= ($_POST['igv_type']??'none')==='none' ?'selected':'' ?>>Sin IGV</option>
            <option value="18"   <?= ($_POST['igv_type']??'')==='18'   ?'selected':'' ?>>IGV 18%</option>
          </select>
        </div>

        <!-- Descuento global -->
        <div class="form-group">
          <label>Descuento global</label>
          <div style="position:relative">
            <input type="number" name="discount_pct" id="discount_pct"
                   value="<?= cleanFloat($_POST['discount_pct'] ?? 0) ?>"
                   min="0" max="100" step="0.5" placeholder="0">
            <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-weight:600">%</span>
          </div>
        </div>

        <!-- Costos extras -->
        <div class="form-group">
          <label>Costos adicionales (S/)</label>
          <input type="number" name="extras_amount" id="extras_amount"
                 value="<?= cleanFloat($_POST['extras_amount'] ?? 0) ?>"
                 min="0" step="1" placeholder="0.00">
          <input type="text" name="extras_detail" id="extras_detail"
                 value="<?= clean($_POST['extras_detail'] ?? '') ?>"
                 placeholder="Detalle (movilidad, personal extra…)"
                 style="margin-top:6px">
        </div>

        <hr style="border:none;border-top:1px solid var(--border);margin:16px 0">

        <!-- Tabla de totales -->
        <div class="totals-table">
          <div class="total-row">
            <span>Subtotal</span>
            <span id="total-subtotal">S/ 0.00</span>
          </div>
          <div class="total-row" id="row-discount" style="display:none">
            <span>Descuento</span>
            <span id="total-discount" style="color:#dc2626">- S/ 0.00</span>
          </div>
          <div class="total-row" id="row-extras" style="display:none">
            <span>Adicionales</span>
            <span id="total-extras" style="color:var(--green)">+ S/ 0.00</span>
          </div>
          <div class="total-row" id="row-igv" style="display:none">
            <span id="total-igv-label">IGV</span>
            <span id="total-igv">S/ 0.00</span>
          </div>
          <div class="total-final">
            <span>TOTAL</span>
            <span id="total-final">S/ 0.00</span>
          </div>
          <div class="total-per-person" id="row-per-person" style="display:none">
            <span id="total-per-person">— / persona</span>
          </div>
        </div>

        <!-- Campos ocultos con totales calculados -->
        <input type="hidden" id="calc_subtotal"     name="calc_subtotal"     value="0">
        <input type="hidden" id="calc_discount_amt" name="calc_discount_amt" value="0">
        <input type="hidden" id="calc_igv_amount"   name="calc_igv_amount"   value="0">
        <input type="hidden" id="calc_total"        name="calc_total"        value="0">
        <input type="hidden" id="calc_per_person"   name="calc_per_person"   value="0">

        <button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-top:20px">
          💾 Guardar propuesta
        </button>
        <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-ghost btn-block" style="margin-top:8px">
          Cancelar
        </a>

      </div>
    </div>

  </div><!-- /quoter-right -->

</div><!-- /quoter-grid -->

</form>

<!-- Template de ítem (referencia para el submit del form) -->
<template id="itemRowTemplate">
  <div class="item-row" data-idx="__IDX__">
    <span class="item-name">__NAME__</span>
    <span class="item-price">__PRICE_DISPLAY__</span>
    <input type="text" inputmode="decimal"
           name="items[__IDX__][quantity]" data-field="quantity"
           value="1" placeholder="0" class="item-qty">
    <span class="item-total" data-field="subtotal">S/ 0.00</span>
    <button type="button" class="item-del" onclick="removeItem(this)">&#x2715;</button>
    <input type="hidden" name="items[__IDX__][product_id]" value="__PRODUCT_ID__">
    <input type="hidden" name="items[__IDX__][name]" value="__NAME__">
    <input type="hidden" name="items[__IDX__][unit_price]" data-field="unit_price" value="__UNIT_PRICE__">
    <input type="hidden" name="items[__IDX__][price_mode]" value="custom">
    <input type="hidden" name="items[__IDX__][discount_pct]" value="0">
    <input type="hidden" name="items[__IDX__][description]" value="">
  </div>
</template>

<?php
$extraScripts = '
<script>
const API_URL   = "' . APP_URL . '/api/quotes.php";
const CSRF_TOKEN = "' . csrfToken() . '";
</script>
<script src="' . APP_URL . '/assets/js/quoter.js?v=2"></script>
';
include __DIR__ . '/../admin/layout-bottom.php';
?>
