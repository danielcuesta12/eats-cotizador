<form method="post" id="quoteForm">
      <?php if (isset($isEvent) && $isEvent): ?><input type="hidden" name="origin" value="event"><?php endif; ?>
<?= csrfField() ?>

<div class="quoter-grid">

  <!-- ================================================================
       COLUMNA IZQUIERDA: Datos del evento + Productos
       ================================================================ -->
  <div class="quoter-left">

    <!-- SECCIÓN 1: Datos del evento -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">📅 Datos del evento</span>
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
          <!-- Tipo de evento -->
          <div class="form-group">
            <label class="form-required">Tipo de evento</label>
            <select name="event_type" id="eventType" required>
              <option value="">Seleccionar…</option>
              <?php foreach ($eventTypes as $et): ?>
              <option value="<?= $et ?>" <?= ($_POST['event_type']??'')===$et?'selected':'' ?>>
                <?= $et ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Fecha del evento -->
          <div class="form-group">
            <label>Fecha del evento</label>
            <input type="date" name="event_date"
                   value="<?= clean($_POST['event_date'] ?? '') ?>"
                   min="<?= date('Y-m-d') ?>">
          </div>
        </div>

        <div class="form-row form-row-2">
          <!-- Lugar -->
          <div class="form-group">
            <label>Lugar del evento</label>
            <input type="text" name="event_location"
                   value="<?= clean($_POST['event_location'] ?? '') ?>"
                   placeholder="Local, dirección…">
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

    <!-- SECCIÓN 2: Productos -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">🍔 Productos</span>
        <span style="font-size:13px;color:var(--text-muted)" id="itemCount">0 ítems</span>
      </div>
      <div class="card-body" style="padding-bottom:0">

        <!-- Buscador de productos -->
        <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
          <div style="position:relative;flex:1;min-width:200px">
            <span class="search-icon" style="top:50%;transform:translateY(-50%)">🔍</span>
            <input type="text" id="productSearch" placeholder="Buscar producto…"
                   autocomplete="off" style="padding-left:36px">
            <div id="productDropdown" class="search-dropdown" style="display:none"></div>
          </div>
          <select id="catFilter" style="width:auto;padding:9px 14px">
            <option value="">Todas las categorías</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addManualItem()">
            + Manual
          </button>
        </div>

        <!-- Cabecera de la tabla de ítems (solo desktop) -->
        <div class="items-header">
          <span style="flex:3">Producto</span>
          <span style="flex:1.2;text-align:center">Modo</span>
          <span style="flex:1.1;text-align:right">Precio unit.</span>
          <span style="flex:.9;text-align:center">Cant.</span>
          <span style="flex:.8;text-align:center">Desc.%</span>
          <span style="flex:1.1;text-align:right">Subtotal</span>
          <span style="width:32px"></span>
        </div>

        <!-- Contenedor de ítems (se llena con JS) -->
        <div id="quoteItemsContainer">
          <!-- Ítems dinámicos aquí -->
        </div>

        <!-- Estado vacío -->
        <div id="emptyItems" style="text-align:center;padding:40px 20px;color:var(--text-muted)">
          <div style="font-size:36px;margin-bottom:8px">🍔</div>
          <div style="font-size:14px">Busca y agrega productos arriba</div>
        </div>

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
            <option value="none"  <?= ($_POST['igv_type']??'none')==='none' ?'selected':'' ?>>Sin IGV</option>
            <option value="10.5"  <?= ($_POST['igv_type']??'')==='10.5'  ?'selected':'' ?>>IGV 10.5%</option>
            <option value="18"    <?= ($_POST['igv_type']??'')==='18'    ?'selected':'' ?>>IGV 18%</option>
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
          <?php echo isset($isEvent) && $isEvent ? '&#128197; Guardar evento' : '💾 Guardar cotización'; ?>
        </button>
        <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-ghost btn-block" style="margin-top:8px">
          Cancelar
        </a>

      </div>
    </div>

  </div><!-- /quoter-right -->

</div><!-- /quoter-grid -->

</form>