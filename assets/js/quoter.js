/* ============================================================
   quoter.js — Motor del cotizador
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

function parseNum(val, defaultVal) {
  if (defaultVal === undefined) defaultVal = 0;
  var n = parseFloat(String(val || '').replace(',', '.'));
  return isNaN(n) ? defaultVal : n;
}

/* ============================================================
   CÁLCULOS EN TIEMPO REAL
   ============================================================ */

function recalculate() {
  const rows = document.querySelectorAll('.item-row');
  let subtotal = 0;

  rows.forEach(row => {
    const priceEls = row.querySelectorAll('[data-field="unit_price"]');
    const qtyEls   = row.querySelectorAll('[data-field="quantity"]');
    const price = parseNum(priceEls[0]?.value, 0);
    const qty   = parseNum(qtyEls[0]?.value,   1);
    const sub   = price * qty;
    subtotal   += sub;
    row.querySelectorAll('[data-field="subtotal"]').forEach(el => {
      el.textContent = fmtSoles(sub);
    });
  });

  const discPct   = parseFloat(document.getElementById('discount_pct')?.value  || 0);
  const extrasAmt = parseFloat(document.getElementById('extras_amount')?.value || 0);
  const igvType   = document.getElementById('igv_type')?.value || 'none';
  const numPeople = parseInt(document.getElementById('num_people')?.value      || 0);

  const igvRate   = igvType === '18' ? 0.18 : 0;
  const discAmt   = subtotal * (discPct / 100);
  const base      = subtotal - discAmt + extrasAmt;
  const igvAmt    = base * igvRate;
  const total     = base + igvAmt;
  const perPerson = numPeople > 0 ? total / numPeople : 0;

  setText('total-subtotal',  fmtSoles(subtotal));
  setText('total-discount',  '- ' + fmtSoles(discAmt));
  setText('total-extras',    '+ ' + fmtSoles(extrasAmt));
  setText('total-igv-label', igvType !== 'none' ? 'IGV ' + igvType + '%' : 'IGV');
  setText('total-igv',       fmtSoles(igvAmt));
  setText('total-final',     fmtSoles(total));
  setText('total-per-person',numPeople > 0 ? fmtSoles(perPerson) + ' / persona' : '');

  toggle('row-discount', discAmt > 0);
  toggle('row-extras',   extrasAmt > 0);
  toggle('row-igv',      igvType !== 'none');
  toggle('row-per-person', numPeople > 0);

  setVal('calc_subtotal',     subtotal.toFixed(2));
  setVal('calc_discount_amt', discAmt.toFixed(2));
  setVal('calc_igv_amount',   igvAmt.toFixed(2));
  setVal('calc_total',        total.toFixed(2));
  setVal('calc_per_person',   perPerson.toFixed(2));

  setText('itemCount', rows.length + (rows.length === 1 ? ' ítem' : ' ítems'));
}

function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
function setVal(id, val)  { const el = document.getElementById(id); if (el) el.value = val; }
function toggle(id, show) { const el = document.getElementById(id); if (el) el.style.display = show ? '' : 'none'; }

/* ============================================================
   CATEGORÍAS — Acordeón
   ============================================================ */

function toggleCat(hdr) {
  hdr.closest('.cat-block').classList.toggle('open');
}

/* ============================================================
   AGREGAR ÍTEMS
   ============================================================ */

function addProductFromPill(btn) {
  const catBlock = btn.closest('.cat-block');
  addProductItem(catBlock, btn.dataset.productId, btn.dataset.name, parseNum(btn.dataset.price, 0));
}

function addFreeItem(btn) {
  const catBlock = btn.closest('.cat-block');
  const nameInp  = catBlock.querySelector('.cat-free-name');
  const priceInp = catBlock.querySelector('.cat-free-price');
  const name  = nameInp.value.trim();
  const price = parseNum(priceInp.value, 0);
  if (!name)    { nameInp.focus();  return; }
  if (price <= 0) { priceInp.focus(); return; }
  addProductItem(catBlock, 0, name, price);
  nameInp.value  = '';
  priceInp.value = '';
}

function addProductItem(catBlock, productId, name, price) {
  const idx = itemIndex++;
  const row = document.createElement('div');
  row.className  = 'item-row';
  row.dataset.idx = idx;
  row.innerHTML = `
    <span class="item-name">${escHtml(name)}</span>
    <span class="item-price">${fmtSoles(price)}</span>
    <input type="text" inputmode="decimal"
           name="items[${idx}][quantity]" data-field="quantity"
           value="1" placeholder="0" class="item-qty">
    <span class="item-total" data-field="subtotal">S/ 0.00</span>
    <button type="button" class="item-del" onclick="removeItem(this)">&#x2715;</button>
    <input type="hidden" name="items[${idx}][product_id]" value="${parseInt(productId) || 0}">
    <input type="hidden" name="items[${idx}][name]" value="${escHtml(name)}">
    <input type="hidden" name="items[${idx}][unit_price]" data-field="unit_price" value="${parseFloat(price).toFixed(2)}">
    <input type="hidden" name="items[${idx}][price_mode]" value="custom">
    <input type="hidden" name="items[${idx}][discount_pct]" value="0">
    <input type="hidden" name="items[${idx}][description]" value="">
  `;

  catBlock.querySelector('.cat-items').appendChild(row);

  row.style.opacity = '0';
  requestAnimationFrame(() => {
    row.style.transition = 'opacity .2s';
    row.style.opacity = '1';
  });

  updateCatCount(catBlock);
  recalculate();
}

function removeItem(btn) {
  const row      = btn.closest('.item-row');
  const catBlock = row.closest('.cat-block');
  row.style.transition = 'opacity .15s';
  row.style.opacity    = '0';
  setTimeout(() => {
    row.remove();
    if (catBlock) updateCatCount(catBlock);
    recalculate();
  }, 150);
}

function updateCatCount(catBlock) {
  const n       = catBlock.querySelectorAll('.cat-items .item-row').length;
  const countEl = catBlock.querySelector('.cat-count');
  const sep     = catBlock.querySelector('.cat-sep');
  if (countEl) countEl.textContent = n + (n === 1 ? ' ítem' : ' ítems');
  catBlock.classList.toggle('has-items', n > 0);
  if (sep) sep.style.display = n > 0 ? '' : 'none';
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
});

/* ============================================================
   LISTENERS GLOBALES DE CÁLCULO
   ============================================================ */

document.addEventListener('input', function(e) {
  const f = e.target.dataset.field;
  if (f === 'quantity') recalculate();
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
    alert('Agrega al menos un producto a la propuesta.');
    return;
  }
});

/* ============================================================
   INIT
   ============================================================ */
recalculate();
