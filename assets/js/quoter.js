/* ============================================================
   quoter.js — Motor del cotizador
   El Gringo Burger Joint v1.0
   ============================================================ */

'use strict';

let itemIndex = 0;

/* ============================================================
   UTILIDADES
   ============================================================ */

function fmtSoles(n) {
  return 'S/ ' + parseFloat(n || 0).toLocaleString('es-PE', {
    minimumFractionDigits: 2, maximumFractionDigits: 2
  });
}

function escHtml(str) {
  return String(str || '').replace(/[&<>"']/g,
    m => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[m]));
}

/* ============================================================
   CÁLCULOS EN TIEMPO REAL
   ============================================================ */

function parseNum(val, defaultVal) {
  if (defaultVal === undefined) defaultVal = 0;
  var n = parseFloat(String(val || '').replace(',', '.'));
  return isNaN(n) ? defaultVal : n;
}

function recalculate() {
  const rows = document.querySelectorAll('.item-row');
  let subtotal = 0;

  rows.forEach(row => {
    const priceEls = row.querySelectorAll('[data-field="unit_price"]');
    const qtyEls   = row.querySelectorAll('[data-field="quantity"]');
    const discEls  = row.querySelectorAll('[data-field="discount_pct"]');
    const price = parseNum(priceEls[0]?.value, 0);
    const qty   = parseNum(qtyEls[0]?.value,   1); // vacío = 1
    const disc  = parseNum(discEls[0]?.value,   0);
    const base  = price * qty;
    const sub   = base * (1 - disc / 100);
    subtotal   += sub;
    row.querySelectorAll('[data-field="subtotal"]').forEach(el => {
      el.textContent = fmtSoles(sub);
    });
  });

  const discPct   = parseFloat(document.getElementById('discount_pct')?.value  || 0);
  const extrasAmt = parseFloat(document.getElementById('extras_amount')?.value || 0);
  const igvType   = document.getElementById('igv_type')?.value || 'none';
  const numPeople = parseInt(document.getElementById('num_people')?.value      || 0);

  const igvRate   = igvType === '18' ? 0.18 : igvType === '10.5' ? 0.105 : 0;
  const discAmt   = subtotal * (discPct / 100);
  const base      = subtotal - discAmt + extrasAmt;
  const igvAmt    = base * igvRate;
  const total     = base + igvAmt;
  const perPerson = numPeople > 0 ? total / numPeople : 0;

  // Actualizar totales
  setText('total-subtotal',  fmtSoles(subtotal));
  setText('total-discount',  '- ' + fmtSoles(discAmt));
  setText('total-extras',    '+ ' + fmtSoles(extrasAmt));
  setText('total-igv-label', igvType !== 'none' ? 'IGV ' + igvType + '%' : 'IGV');
  setText('total-igv',       fmtSoles(igvAmt));
  setText('total-final',     fmtSoles(total));
  setText('total-per-person',numPeople > 0 ? fmtSoles(perPerson) + ' / persona' : '');

  // Mostrar/ocultar filas opcionales
  toggle('row-discount', discAmt > 0);
  toggle('row-extras',   extrasAmt > 0);
  toggle('row-igv',      igvType !== 'none');
  toggle('row-per-person', numPeople > 0);

  // Guardar en campos ocultos
  setVal('calc_subtotal',     subtotal.toFixed(2));
  setVal('calc_discount_amt', discAmt.toFixed(2));
  setVal('calc_igv_amount',   igvAmt.toFixed(2));
  setVal('calc_total',        total.toFixed(2));
  setVal('calc_per_person',   perPerson.toFixed(2));

  // Contador de ítems
  setText('itemCount', rows.length + (rows.length === 1 ? ' ítem' : ' ítems'));
  document.getElementById('emptyItems').style.display = rows.length === 0 ? '' : 'none';
}

function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
function setVal(id, val)  { const el = document.getElementById(id); if (el) el.value = val; }
function toggle(id, show) { const el = document.getElementById(id); if (el) el.style.display = show ? '' : 'none'; }

/* ============================================================
   MODO DE PRECIO
   ============================================================ */

function onModeChange(select) {
  const row       = select.closest('.item-row');
  const priceInps = row.querySelectorAll('[data-field="unit_price"]');
  const pp        = parseFloat(select.dataset.perPerson || 0);
  const pe        = parseFloat(select.dataset.perEvent  || 0);
  // Sync el otro select (desktop/mobile)
  row.querySelectorAll('[data-field="price_mode"]').forEach(s => { s.value = select.value; });
  if (select.value === 'per_person') {
    priceInps.forEach(p => { p.value = pp.toFixed(2); p.readOnly = true; });
  } else if (select.value === 'per_event') {
    priceInps.forEach(p => { p.value = pe.toFixed(2); p.readOnly = true; });
  } else {
    priceInps.forEach(p => { p.readOnly = false; p.value = ''; });
    priceInps[0]?.focus();
  }
  recalculate();
}

/* ============================================================
   AGREGAR / QUITAR ÍTEMS
   ============================================================ */

function addItem(product) {
  const tpl  = document.getElementById('itemRowTemplate').innerHTML;
  const pp   = parseFloat(product.price_per_person || 0);
  const pe   = parseFloat(product.price_per_event  || 0);
  const idx  = itemIndex++;

  const html = tpl
    .replace(/__IDX__/g,       idx)
    .replace(/__NAME__/g,      escHtml(product.name))
    .replace(/__DESC__/g,      escHtml(product.description || ''))
    .replace(/__PRODUCT_ID__/g,product.id || '')
    .replace(/__PRICE_PP__/g,  pp.toFixed(2))
    .replace(/__PRICE_PE__/g,  pe.toFixed(2))
    .replace(/min="0\.1" step="1"/g, 'min="0.01" step="any"');

  const wrap = document.createElement('div');
  wrap.innerHTML = html;
  const row = wrap.firstElementChild;

  // Inicializar modo
  const modeSelect = row.querySelector('[data-field="price_mode"]');
  if (modeSelect) {
    modeSelect.addEventListener('change', () => onModeChange(modeSelect));
    if (pp > 0) {
      modeSelect.value = 'per_person';
      // precio autocompleto, cantidad vacía para que el usuario ingrese
      row.querySelectorAll('[data-field="unit_price"]').forEach(el => { el.value = pp.toFixed(2); el.readOnly = true; });
    } else if (pe > 0) {
      modeSelect.value = 'per_event';
      row.querySelectorAll('[data-field="unit_price"]').forEach(el => { el.value = pe.toFixed(2); el.readOnly = true; });
    } else {
      modeSelect.value = 'custom';
      row.querySelectorAll('[data-field="unit_price"]').forEach(el => { el.readOnly = false; });
    }
  }

  document.getElementById('quoteItemsContainer').appendChild(row);

  // Animar entrada
  row.style.opacity = '0';
  requestAnimationFrame(() => {
    row.style.transition = 'opacity .2s';
    row.style.opacity = '1';
  });

  recalculate();
}

function addManualItem() {
  addItem({ id: '', name: 'Ítem personalizado', description: '', price_per_person: 0, price_per_event: 0 });
  const rows    = document.querySelectorAll('.item-row');
  const lastRow = rows[rows.length - 1];
  const nameText = lastRow.querySelector('.item-name-text');
  const nameHid  = lastRow.querySelector('[name$="[name]"]');
  const priceInps = lastRow.querySelectorAll('[data-field="unit_price"]');
  const modes     = lastRow.querySelectorAll('[data-field="price_mode"]');

  if (nameText) {
    const inp = document.createElement('input');
    inp.type        = 'text';
    inp.className   = 'input-sm';
    inp.value       = '';
    inp.placeholder = 'Nombre del ítem…';
    inp.style.flex  = '1';
    inp.addEventListener('input', () => { if (nameHid) nameHid.value = inp.value; });
    nameText.replaceWith(inp);
    inp.focus();
  }

  priceInps.forEach(p => { p.readOnly = false; p.value = ''; });
  modes.forEach(m => { m.value = 'custom'; });
  recalculate();
}

function removeItem(btn) {
  const row = btn.closest('.item-row');
  row.style.transition = 'opacity .15s';
  row.style.opacity    = '0';
  setTimeout(() => { row.remove(); recalculate(); }, 150);
}

/* ============================================================
   BÚSQUEDA DE PRODUCTOS
   ============================================================ */

let productTimer = null;

document.getElementById('productSearch').addEventListener('input', function() {
  clearTimeout(productTimer);
  const q   = this.value.trim();
  const cat = document.getElementById('catFilter').value;
  if (!q && !cat) { hideDropdown('productDropdown'); return; }
  productTimer = setTimeout(() => searchProducts(q, cat), 280);
});

document.getElementById('catFilter').addEventListener('change', function() {
  const q = document.getElementById('productSearch').value.trim();
  searchProducts(q, this.value);
});

async function searchProducts(q, cat) {
  const dd = document.getElementById('productDropdown');
  dd.style.display = 'block';
  dd.innerHTML     = '<div class="dropdown-loading">Buscando…</div>';

  try {
    const res  = await fetch(`${API_URL}?action=search_products&q=${encodeURIComponent(q)}&cat=${encodeURIComponent(cat)}`);
    const json = await res.json();
    const prods = json.data || [];

    if (!prods.length) {
      dd.innerHTML = '<div class="dropdown-empty">Sin resultados. <button type="button" onclick="addManualItem()" style="color:var(--red);background:none;border:none;cursor:pointer;font-size:13px">+ Ítem manual</button></div>';
      return;
    }

    dd.innerHTML = prods.map(p => {
      const imgHtml = p.image
        ? `<img src="${escHtml(p.image)}" class="dropdown-item-img">`
        : `<div class="dropdown-item-img">🍔</div>`;
      const pp = parseFloat(p.price_per_person || 0);
      const pe = parseFloat(p.price_per_event  || 0);
      const priceLabel = pp > 0 ? `S/${pp.toFixed(2)}/p` : (pe > 0 ? `S/${pe.toFixed(2)}/ev` : 'Precio libre');
      return `
        <div class="dropdown-item" onclick="selectProduct(${JSON.stringify(p).replace(/"/g,'&quot;')})">
          ${imgHtml}
          <div class="dropdown-item-info">
            <div class="dropdown-item-name">${escHtml(p.name)}</div>
            <div class="dropdown-item-sub">${escHtml(p.category_name || '')}</div>
          </div>
          <div class="dropdown-item-price">${priceLabel}</div>
        </div>`;
    }).join('');

  } catch (e) {
    dd.innerHTML = '<div class="dropdown-empty">Error al buscar</div>';
  }
}

function selectProduct(product) {
  addItem(product);
  hideDropdown('productDropdown');
  document.getElementById('productSearch').value = '';
}

/* ============================================================
   BÚSQUEDA DE CLIENTES
   ============================================================ */

let clientTimer = null;

document.getElementById('clientSearch').addEventListener('input', function() {
  clearTimeout(clientTimer);
  const q = this.value.trim();
  if (!q) { hideDropdown('clientDropdown'); return; }
  clientTimer = setTimeout(() => searchClients(q), 300);
});

async function searchClients(q) {
  const dd = document.getElementById('clientDropdown');
  dd.style.display = 'block';
  dd.innerHTML     = '<div class="dropdown-loading">Buscando…</div>';

  try {
    const res   = await fetch(`${API_URL}?action=search_clients&q=${encodeURIComponent(q)}`);
    const json  = await res.json();
    const items = json.data || [];

    if (!items.length) {
      const baseUrl = API_URL.replace('/api/quotes.php', '');
      dd.innerHTML = '<div class="dropdown-empty">Sin resultados. <a href="' + baseUrl + '/admin/clients/form?back=' + encodeURIComponent(baseUrl + '/quotes/create') + '" style="color:var(--red)">+ Crear cliente</a></div>';
      return;
    }

    dd.innerHTML = items.map(c => `
      <div class="dropdown-item" onclick='selectClient(${JSON.stringify(c).replace(/'/g,"&#39;")})'>
        <div class="dropdown-item-img" style="background:var(--red-light);color:var(--red);font-weight:700;font-size:14px">
          ${escHtml(c.name.charAt(0).toUpperCase())}
        </div>
        <div class="dropdown-item-info">
          <div class="dropdown-item-name">${escHtml(c.name)}</div>
          <div class="dropdown-item-sub">${c.type === 'empresa' ? '🏢 Empresa' : '👤 Persona'} ${c.ruc_dni ? '· ' + escHtml(c.ruc_dni) : ''}</div>
        </div>
      </div>`).join('');

  } catch(e) {
    dd.innerHTML = '<div class="dropdown-empty">Error al buscar</div>';
  }
}

function selectClient(client) {
  document.getElementById('clientId').value = client.id;
  document.getElementById('clientSearch').value = '';
  hideDropdown('clientDropdown');

  const chip = document.getElementById('clientSelected');
  chip.style.display = 'flex';
  chip.innerHTML = `
    <div class="client-chip-avatar">${escHtml(client.name.charAt(0).toUpperCase())}</div>
    <div class="client-chip-info">
      <div class="client-chip-name">${escHtml(client.name)}</div>
      <div class="client-chip-sub">${client.type === 'empresa' ? '🏢 Empresa' : '👤 Persona'} ${client.ruc_dni ? '· RUC: ' + escHtml(client.ruc_dni) : ''} ${client.phone ? '· 📱 ' + escHtml(client.phone) : ''}</div>
    </div>
    <button type="button" class="client-chip-remove" onclick="clearClient()" title="Cambiar cliente">✕</button>`;
}

function clearClient() {
  document.getElementById('clientId').value = '';
  document.getElementById('clientSelected').style.display = 'none';
  document.getElementById('clientSearch').value = '';
  document.getElementById('clientSearch').focus();
}

/* ============================================================
   CIERRE DE DROPDOWNS
   ============================================================ */

function hideDropdown(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'none';
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('#clientSearch') && !e.target.closest('#clientDropdown')) {
    hideDropdown('clientDropdown');
  }
  if (!e.target.closest('#productSearch') && !e.target.closest('#productDropdown') && !e.target.closest('#catFilter')) {
    hideDropdown('productDropdown');
  }
});

/* ============================================================
   LISTENERS GLOBALES DE CÁLCULO
   ============================================================ */

document.addEventListener('input', function(e) {
  const f = e.target.dataset.field;
  if (f === 'unit_price' || f === 'quantity' || f === 'discount_pct') recalculate();
  if (['discount_pct','extras_amount'].includes(e.target.id)) recalculate();
});

document.addEventListener('change', function(e) {
  if (e.target.id === 'igv_type' || e.target.id === 'num_people') recalculate();
});

/* ============================================================
   VALIDACIÓN AL ENVIAR
   ============================================================ */

document.getElementById('quoteForm').addEventListener('submit', function(e) {
  const clientId = document.getElementById('clientId').value;
  const items    = document.querySelectorAll('.item-row');

  if (!clientId) {
    e.preventDefault();
    alert('Selecciona un cliente antes de guardar.');
    document.getElementById('clientSearch').focus();
    return;
  }
  if (!items.length) {
    e.preventDefault();
    alert('Agrega al menos un producto a la cotización.');
    document.getElementById('productSearch').focus();
    return;
  }
});

/* ============================================================
   INIT
   ============================================================ */
recalculate();
