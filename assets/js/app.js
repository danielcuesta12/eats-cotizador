/* ============================================================
   app.js — JavaScript principal
   El Gringo Cotizador v1.0
   ============================================================ */

'use strict';

// ---- Sidebar mobile toggle ----
(function() {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const toggle   = document.getElementById('menuToggle');
  const closeBtn = document.getElementById('sidebarClose');

  if (!sidebar) return;

  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  toggle  && toggle.addEventListener('click', openSidebar);
  closeBtn && closeBtn.addEventListener('click', closeSidebar);
  overlay  && overlay.addEventListener('click', closeSidebar);
})();

// ---- Auto-dismiss flash messages ----
(function() {
  document.querySelectorAll('.flash-message').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 400);
    }, 4000);
  });
})();

// ---- Confirmación de eliminación ----
document.addEventListener('click', function(e) {
  const btn = e.target.closest('[data-confirm]');
  if (!btn) return;
  const msg = btn.dataset.confirm || '¿Estás seguro de que deseas eliminar este elemento?';
  if (!confirm(msg)) e.preventDefault();
});

// ---- Preview de imagen al subir ----
document.addEventListener('change', function(e) {
  const input = e.target;
  if (input.type !== 'file' || !input.dataset.preview) return;
  const previewId = input.dataset.preview;
  const preview   = document.getElementById(previewId);
  if (!preview) return;

  const file = input.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = ev => {
    preview.src = ev.target.result;
    preview.style.display = 'block';
  };
  reader.readAsDataURL(file);
});

// ---- Formatear número como moneda peruana ----
function formatSoles(amount) {
  return 'S/ ' + parseFloat(amount || 0).toLocaleString('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

// ---- Modales ----
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.add('open');
}
function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove('open');
}

// Cerrar modal al click en overlay
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-bg')) {
    e.target.classList.remove('open');
  }
  if (e.target.classList.contains('modal-close')) {
    e.target.closest('.modal-bg')?.classList.remove('open');
  }
});

// ============================================================
// COTIZADOR — Lógica de items y cálculos
// ============================================================

const QuotaCalc = {

  // Calcular subtotal de un ítem
  itemSubtotal(unitPrice, quantity, discountPct) {
    const base = parseFloat(unitPrice || 0) * parseFloat(quantity || 1);
    const disc = base * (parseFloat(discountPct || 0) / 100);
    return Math.max(0, base - disc);
  },

  // Recalcular totales completos de la cotización
  recalculate() {
    const rows      = document.querySelectorAll('.quote-item-row');
    let subtotal    = 0;

    rows.forEach(row => {
      const price    = parseFloat(row.querySelector('[data-field="unit_price"]')?.value || 0);
      const qty      = parseFloat(row.querySelector('[data-field="quantity"]')?.value   || 1);
      const disc     = parseFloat(row.querySelector('[data-field="discount_pct"]')?.value || 0);
      const sub      = this.itemSubtotal(price, qty, disc);
      subtotal       += sub;
      const subEl    = row.querySelector('[data-field="subtotal"]');
      if (subEl) subEl.textContent = formatSoles(sub);
    });

    // Descuento global
    const discPct    = parseFloat(document.getElementById('discount_pct')?.value  || 0);
    const extrasAmt  = parseFloat(document.getElementById('extras_amount')?.value || 0);
    const discAmount = subtotal * (discPct / 100);
    const igvType    = document.getElementById('igv_type')?.value || 'none';
    const numPeople  = parseInt(document.getElementById('num_people')?.value      || 0);

    const igvRate    = igvType === '18' ? 0.18 : igvType === '10.5' ? 0.105 : 0;
    const baseAfterDisc = subtotal - discAmount + extrasAmt;
    const igvAmount  = baseAfterDisc * igvRate;
    const total      = baseAfterDisc + igvAmount;
    const perPerson  = numPeople > 0 ? total / numPeople : 0;

    // Actualizar UI de totales
    this.updateEl('total-subtotal',   formatSoles(subtotal));
    this.updateEl('total-discount',   discAmount > 0 ? '- ' + formatSoles(discAmount) : formatSoles(0));
    this.updateEl('total-extras',     extrasAmt  > 0 ? '+ ' + formatSoles(extrasAmt)  : formatSoles(0));
    this.updateEl('total-igv-label',  igvType !== 'none' ? 'IGV ' + igvType + '%' : 'IGV');
    this.updateEl('total-igv',        formatSoles(igvAmount));
    this.updateEl('total-final',      formatSoles(total));
    this.updateEl('total-per-person', numPeople > 0 ? formatSoles(perPerson) + ' / persona' : '—');

    // Guardar valores ocultos para el form
    this.setHidden('calc_subtotal',     subtotal.toFixed(2));
    this.setHidden('calc_discount_amt', discAmount.toFixed(2));
    this.setHidden('calc_igv_amount',   igvAmount.toFixed(2));
    this.setHidden('calc_total',        total.toFixed(2));
    this.setHidden('calc_per_person',   perPerson.toFixed(2));
  },

  updateEl(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  },

  setHidden(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value;
  }
};

// Escuchar cambios en inputs del cotizador
document.addEventListener('input', function(e) {
  const field = e.target.dataset.field;
  if (['unit_price','quantity','discount_pct'].includes(field)) {
    QuotaCalc.recalculate();
  }
});
document.addEventListener('change', function(e) {
  const id = e.target.id;
  if (['discount_pct','extras_amount','igv_type','num_people'].includes(id)) {
    QuotaCalc.recalculate();
  }
  if (e.target.dataset.field === 'price_mode') {
    updatePriceFromMode(e.target.closest('.quote-item-row'));
    QuotaCalc.recalculate();
  }
});

// Actualizar precio según modo seleccionado
function updatePriceFromMode(row) {
  if (!row) return;
  const mode       = row.querySelector('[data-field="price_mode"]')?.value;
  const priceInput = row.querySelector('[data-field="unit_price"]');
  if (!priceInput) return;

  const perPerson = parseFloat(row.dataset.pricePerPerson || 0);
  const perEvent  = parseFloat(row.dataset.pricePerEvent  || 0);

  if (mode === 'per_person') {
    priceInput.value = perPerson.toFixed(2);
    priceInput.readOnly = true;
  } else if (mode === 'per_event') {
    priceInput.value = perEvent.toFixed(2);
    priceInput.readOnly = true;
  } else {
    priceInput.readOnly = false;
    priceInput.focus();
  }
}

// Agregar ítem al cotizador
function addQuoteItem(product) {
  const container = document.getElementById('quote-items-container');
  const idx       = document.querySelectorAll('.quote-item-row').length;

  const row = document.createElement('div');
  row.className = 'quote-item-row';
  row.dataset.pricePerPerson = product.price_per_person || 0;
  row.dataset.pricePerEvent  = product.price_per_event  || 0;

  row.innerHTML = `
    <div>
      <strong style="font-size:14px;display:block">${escHtml(product.name)}</strong>
      <input type="hidden" name="items[${idx}][product_id]" value="${product.id || ''}">
      <input type="hidden" name="items[${idx}][name]"       value="${escHtml(product.name)}">
    </div>
    <select name="items[${idx}][price_mode]" data-field="price_mode" class="form-control-sm">
      <option value="per_person">× persona</option>
      <option value="per_event">× evento</option>
      <option value="custom">Libre</option>
    </select>
    <input type="number" name="items[${idx}][unit_price]" data-field="unit_price"
           value="${parseFloat(product.price_per_person || 0).toFixed(2)}"
           min="0" step="0.01" class="form-control-sm" readonly>
    <input type="number" name="items[${idx}][quantity]" data-field="quantity"
           value="1" min="0.1" step="0.1" class="form-control-sm">
    <input type="number" name="items[${idx}][discount_pct]" data-field="discount_pct"
           value="0" min="0" max="100" step="0.5" class="form-control-sm">
    <button type="button" class="btn btn-danger btn-sm" onclick="removeQuoteItem(this)">✕</button>
  `;

  container.appendChild(row);
  QuotaCalc.recalculate();
}

function removeQuoteItem(btn) {
  btn.closest('.quote-item-row').remove();
  // Re-indexar nombres de campos
  document.querySelectorAll('.quote-item-row').forEach((row, i) => {
    row.querySelectorAll('[name]').forEach(el => {
      el.name = el.name.replace(/items\[\d+\]/, `items[${i}]`);
    });
  });
  QuotaCalc.recalculate();
}

// Escape HTML
function escHtml(str) {
  return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}
