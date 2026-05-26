<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

// Cotizaciones y eventos para el calendario
$calQuotes = Database::fetchAll(
    "SELECT q.id, q.quote_number, q.status, q.origin,
            q.event_date, q.event_type,
            q.event_time, q.event_duration, q.event_location,
            q.num_people, q.total, q.price_per_person,
            c.name as client_name
     FROM quotes q JOIN clients c ON c.id=q.client_id
     WHERE q.status IN ('enviada','aceptada')
       AND q.event_date IS NOT NULL AND q.event_date != ''
     ORDER BY q.event_date ASC"
);

// Ítems por cotización para el tooltip
$quoteIds = array_column($calQuotes, 'id');
$itemsMap = array();
if (!empty($quoteIds)) {
    $ph   = implode(',', array_fill(0, count($quoteIds), '?'));
    $rows = Database::fetchAll(
        "SELECT quote_id, name, quantity FROM quote_items WHERE quote_id IN ($ph) ORDER BY sort_order",
        $quoteIds
    );
    foreach ($rows as $r) {
        $itemsMap[$r['quote_id']][] = $r['name'] . ' × ' . number_format((float)$r['quantity'], 0);
    }
}

// Agregar items a cada quote
foreach ($calQuotes as &$q) {
    $q['items_summary'] = isset($itemsMap[$q['id']]) ? implode(' · ', array_slice($itemsMap[$q['id']], 0, 4)) : '';
}
unset($q);

$pageTitle  = 'Calendario';
$activePage = 'calendar';
include __DIR__ . '/layout-top.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1>Calendario de eventos</h1>
    <p>Cotizaciones enviadas, aceptadas y eventos directos</p>
  </div>
  <a href="<?php echo APP_URL; ?>/admin/events/create" class="btn btn-secondary" style="color:#7c3aed;border-color:#7c3aed">
    &#128197; Nuevo evento
  </a>
</div>

<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px">
    <span style="font-size:16px;font-weight:700;color:var(--text-primary)" id="calTitle">Cargando...</span>
    <div style="display:flex;gap:6px;align-items:center">
      <button onclick="calNav(-1)" class="btn btn-ghost btn-sm">&#8249;</button>
      <button onclick="calToday()" class="btn btn-ghost btn-sm">Hoy</button>
      <button onclick="calNav(1)"  class="btn btn-ghost btn-sm">&#8250;</button>
      <div style="width:1px;height:20px;background:var(--border);margin:0 4px"></div>
      <button onclick="setView('month')" id="btnMonth" class="btn btn-ghost btn-sm">Mes</button>
      <button onclick="setView('list')"  id="btnList"  class="btn btn-ghost btn-sm">Lista</button>
    </div>
  </div>

  <!-- Vista Mes -->
  <div id="viewMonth">
    <div style="display:grid;grid-template-columns:repeat(7,1fr);border-bottom:1px solid var(--border)">
      <?php foreach (array('Lun','Mar','Mié','Jue','Vie','Sáb','Dom') as $d): ?>
      <div style="text-align:center;font-size:11px;font-weight:600;color:var(--text-muted);padding:8px 4px;background:#fafafa"><?php echo $d; ?></div>
      <?php endforeach; ?>
    </div>
    <div id="calGrid" style="display:grid;grid-template-columns:repeat(7,1fr)"></div>
  </div>

  <!-- Vista Lista -->
  <div id="viewList" style="display:none">
    <div id="listContent"></div>
  </div>

  <!-- Leyenda -->
  <div style="display:flex;gap:14px;padding:10px 16px;border-top:1px solid var(--border);flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted)">
      <span style="width:10px;height:10px;border-radius:2px;background:#dbeafe;display:inline-block"></span>Enviada
    </div>
    <div style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted)">
      <span style="width:10px;height:10px;border-radius:2px;background:#dcfce7;display:inline-block"></span>Aceptada
    </div>
    <div style="display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted)">
      <span style="width:10px;height:10px;border-radius:2px;background:#ede9fe;display:inline-block"></span>Evento directo
    </div>
  </div>
</div>

<!-- Tooltip global -->
<div id="globalTooltip" style="display:none;position:fixed;z-index:9999;width:260px;
     background:var(--bg-card);border:1.5px solid var(--border);border-radius:12px;
     box-shadow:0 8px 24px rgba(0,0,0,.14);overflow:hidden">
  <div id="ttHeader" style="padding:10px 14px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
    <span id="ttName" style="font-size:13px;font-weight:700;color:var(--text-primary)"></span>
    <span id="ttBadge" style="font-size:10px;padding:2px 8px;border-radius:10px;font-weight:600"></span>
  </div>
  <div style="padding:10px 14px;display:flex;flex-direction:column;gap:6px" id="ttBody"></div>
  <div id="ttProds" style="font-size:11px;color:var(--text-muted);padding:6px 14px;border-top:1px solid var(--border);background:var(--bg-input)"></div>
  <div style="padding:8px 14px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
    <span id="ttTotal" style="font-size:13px;font-weight:700;color:var(--text-primary)"></span>
    <a id="ttLink" href="#" style="font-size:12px;font-weight:600;color:#2563eb;text-decoration:none">
      Ver detalle →
    </a>
  </div>
</div>
<div id="tooltipOverlay" onclick="closeTooltip()" style="display:none;position:fixed;inset:0;z-index:9998"></div>

<style>
.cal-day-cell { min-height:70px;padding:4px 3px;border-right:1px solid var(--border);border-bottom:1px solid var(--border);vertical-align:top;position:relative }
.cal-day-cell:nth-child(7n){ border-right:none }
.cal-ev { border-radius:3px;padding:2px 5px;font-size:10px;font-weight:600;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer;display:block;border:none;width:100%;text-align:left;transition:opacity .1s }
.cal-ev:hover{ opacity:.8 }
.cal-ev-e { background:#dbeafe;color:#1e40af }
.cal-ev-a { background:#dcfce7;color:#166534 }
.cal-ev-v { background:#ede9fe;color:#5b21b6 }
.list-item { display:flex;align-items:stretch;border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s }
.list-item:last-child{ border-bottom:none }
.list-item:hover { background:var(--bg-input) }
.list-main { display:flex;align-items:center;gap:12px;padding:13px 18px;flex:1 }
.list-detail { background:var(--bg-input);padding:10px 18px 12px 76px;border-top:1px solid var(--border);display:none }
.list-detail.open { display:block }
.detail-grid2 { display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px }
.detail-lbl2 { font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px }
.detail-val2 { font-size:12px;font-weight:600;color:var(--text-primary) }
.list-month-hdr { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);padding:10px 18px 6px;background:#fafafa;border-bottom:1px solid var(--border) }
.tt-info-row { display:flex;align-items:flex-start;gap:8px;font-size:12px;color:var(--text-muted) }
.tt-info-row span { color:var(--text-primary) }
</style>

<script>
var QUOTES = <?php echo json_encode($calQuotes); ?>;
var APP    = '<?php echo APP_URL; ?>';
var today  = new Date();
var cur    = new Date(today.getFullYear(), today.getMonth(), 1);
var view   = 'month';
var MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
var DAYS   = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];

function fmtMoney(n) { return 'S/ ' + parseFloat(n||0).toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:2}); }

function setView(v) {
  view = v;
  document.getElementById('viewMonth').style.display = v==='month' ? '' : 'none';
  document.getElementById('viewList').style.display  = v==='list'  ? '' : 'none';
  document.getElementById('btnMonth').style.cssText  = v==='month' ? 'color:var(--red);border-color:var(--red);background:rgba(200,16,46,.06)' : '';
  document.getElementById('btnList').style.cssText   = v==='list'  ? 'color:var(--red);border-color:var(--red);background:rgba(200,16,46,.06)' : '';
  if (v==='month') renderMonth(); else renderList();
}

function calNav(dir) { cur.setMonth(cur.getMonth()+dir); setView(view); }
function calToday()  { cur = new Date(today.getFullYear(),today.getMonth(),1); setView(view); }

function quotesByDate(d) { return QUOTES.filter(function(q){ return q.event_date===d; }); }

function evClass(q) {
  if (q.origin==='event') return 'cal-ev-v';
  return q.status==='aceptada' ? 'cal-ev-a' : 'cal-ev-e';
}
function badgeStyle(q) {
  if (q.origin==='event') return 'background:#ede9fe;color:#5b21b6';
  return q.status==='aceptada' ? 'background:#dcfce7;color:#166534' : 'background:#dbeafe;color:#1e40af';
}
function badgeLabel(q) {
  if (q.origin==='event') return 'Evento';
  return q.status==='aceptada' ? 'Aceptada' : 'Enviada';
}
function barColor(q) {
  if (q.origin==='event') return '#7c3aed';
  return q.status==='aceptada' ? '#16a34a' : '#2563eb';
}
function numColor(q) { return barColor(q); }

function renderMonth() {
  document.getElementById('calTitle').textContent = MONTHS[cur.getMonth()] + ' ' + cur.getFullYear();
  var grid = document.getElementById('calGrid');
  var year = cur.getFullYear(), month = cur.getMonth();
  var firstDow = (new Date(year,month,1).getDay()+6)%7;
  var daysInM  = new Date(year,month+1,0).getDate();
  var daysInP  = new Date(year,month,0).getDate();
  var todayStr = today.toISOString().split('T')[0];
  var html = '';

  for (var i=firstDow-1;i>=0;i--) {
    html += '<div class="cal-day-cell" style="opacity:.3"><div style="font-size:11px;color:#999;padding-left:2px">'+(daysInP-i)+'</div></div>';
  }
  for (var d=1;d<=daysInM;d++) {
    var mm = String(month+1).padStart(2,'0');
    var dd = String(d).padStart(2,'0');
    var dateS = year+'-'+mm+'-'+dd;
    var isT   = dateS===todayStr;
    var qs    = quotesByDate(dateS);
    var bg    = isT ? 'background:#eff6ff' : '';
    var nc    = isT ? 'color:#1d4ed8;font-weight:600' : 'color:#999';
    html += '<div class="cal-day-cell" style="'+bg+'">';
    html += '<div style="font-size:11px;'+nc+';padding-left:2px;margin-bottom:2px">'+d+'</div>';
    qs.forEach(function(q) {
      var ec = evClass(q);
      html += '<button class="cal-ev '+ec+'" onclick="showTooltip(event,'+q.id+')">'+q.quote_number+'</button>';
    });
    html += '</div>';
  }
  var total = firstDow+daysInM;
  var rem   = total%7;
  if (rem>0) for (var x=1;x<=(7-rem);x++) html += '<div class="cal-day-cell" style="opacity:.3"><div style="font-size:11px;color:#999;padding-left:2px">'+x+'</div></div>';
  grid.innerHTML = html;
}

function showTooltip(e, qid) {
  e.stopPropagation();
  var q = QUOTES.find(function(x){ return x.id===qid; });
  if (!q) return;

  document.getElementById('ttName').textContent   = q.client_name;
  document.getElementById('ttBadge').setAttribute('style', badgeStyle(q));
  document.getElementById('ttBadge').textContent  = badgeLabel(q);

  var body = '';
  if (q.event_time) body += '<div class="tt-info-row"><span>&#128336;</span><span>'+q.event_time+(q.event_duration?' · '+q.event_duration:'')+'</span></div>';
  if (q.event_location) body += '<div class="tt-info-row"><span>&#128205;</span><span>'+q.event_location+'</span></div>';
  if (q.num_people>0) body += '<div class="tt-info-row"><span>&#128101;</span><span>'+q.num_people+' personas</span></div>';
  document.getElementById('ttBody').innerHTML = body;
  document.getElementById('ttProds').textContent = q.items_summary || 'Sin productos';
  document.getElementById('ttTotal').textContent = fmtMoney(q.total);
  document.getElementById('ttLink').href = APP+'/quotes/edit.php?id='+q.id;
  document.getElementById('ttLink').textContent = (q.origin==='event'?'Ver evento':'Ver cotización')+' →';

  var tip = document.getElementById('globalTooltip');
  var rect = e.target.getBoundingClientRect();
  var top  = rect.bottom+window.scrollY+6;
  var left = Math.min(rect.left+window.scrollX, window.innerWidth-280);

  tip.style.display = 'block';
  tip.style.top     = top+'px';
  tip.style.left    = Math.max(8,left)+'px';
  tip.style.position = 'absolute';
  document.getElementById('tooltipOverlay').style.display = 'block';
}

function closeTooltip() {
  document.getElementById('globalTooltip').style.display = 'none';
  document.getElementById('tooltipOverlay').style.display = 'none';
}

function renderList() {
  var year=cur.getFullYear(), month=cur.getMonth();
  document.getElementById('calTitle').textContent = MONTHS[month]+' '+year+' — Lista';
  var startS = year+'-'+String(month+1).padStart(2,'0')+'-01';
  var endDate = new Date(year,month+3,0);
  var endS   = endDate.getFullYear()+'-'+String(endDate.getMonth()+1).padStart(2,'0')+'-'+String(endDate.getDate()).padStart(2,'0');
  var filtered = QUOTES.filter(function(q){ return q.event_date>=startS && q.event_date<=endS; });

  if (!filtered.length) {
    document.getElementById('listContent').innerHTML = '<div style="padding:32px;text-align:center;color:var(--text-muted);font-size:14px">Sin eventos en este periodo</div>';
    return;
  }

  var html=''; var lastM='';
  filtered.forEach(function(q) {
    var parts=q.event_date.split('-');
    var mKey=parts[0]+'-'+parts[1];
    var mIdx=parseInt(parts[1])-1;
    if (mKey!==lastM) { html+='<div class="list-month-hdr">'+MONTHS[mIdx]+' '+parts[0]+'</div>'; lastM=mKey; }
    var d   = parseInt(parts[2]);
    var dow = DAYS[new Date(q.event_date).getDay()];
    var bc  = barColor(q);
    var bl  = badgeLabel(q);
    var bs  = badgeStyle(q);
    var nc  = numColor(q);
    var nBadge = '<span style="font-size:10px;padding:2px 7px;border-radius:8px;font-weight:600;'+bs+'">'+bl+'</span>';
    var detail = '';
    if (q.event_time||q.event_location||q.num_people||q.items_summary) {
      detail  = '<div class="list-detail" id="ld'+q.id+'">';
      detail += '<div class="detail-grid2">';
      if (q.event_time) detail += '<div><div class="detail-lbl2">Hora</div><div class="detail-val2">'+q.event_time+(q.event_duration?' · '+q.event_duration:'')+'</div></div>';
      if (q.num_people>0) detail += '<div><div class="detail-lbl2">Personas</div><div class="detail-val2">'+q.num_people+' pers.</div></div>';
      if (q.event_location) detail += '<div style="grid-column:1/-1"><div class="detail-lbl2">Lugar</div><div class="detail-val2">'+q.event_location+'</div></div>';
      detail += '</div>';
      if (q.items_summary) detail += '<div style="font-size:11px;color:var(--text-muted);margin-bottom:8px">'+q.items_summary+'</div>';
      detail += '<a href="'+APP+'/quotes/edit.php?id='+q.id+'" style="font-size:12px;font-weight:600;color:#2563eb;text-decoration:none">'+(q.origin==='event'?'Ver evento':'Ver cotización')+' →</a>';
      detail += '</div>';
    }
    html += '<div class="list-item" id="li'+q.id+'">';
    html += '<div class="list-main" onclick="toggleListDetail('+q.id+')">';
    html += '<div style="text-align:center;min-width:38px;flex-shrink:0"><div style="font-size:20px;font-weight:700;line-height:1;color:var(--text-primary)">'+d+'</div><div style="font-size:10px;color:var(--text-muted);text-transform:uppercase">'+dow+'</div></div>';
    html += '<div style="width:3px;border-radius:2px;align-self:stretch;min-height:36px;flex-shrink:0;background:'+bc+'"></div>';
    html += '<div style="flex:1;min-width:0"><div style="font-size:11px;font-weight:600;color:'+nc+'">'+q.quote_number+'</div><div style="font-size:13px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+q.client_name+'</div>';
    if (q.event_type||q.event_time) html += '<div style="font-size:11px;color:var(--text-muted)">'+(q.event_type||'')+(q.event_time?' · '+q.event_time:'')+'</div>';
    html += '</div>';
    html += '<div style="text-align:right;flex-shrink:0"><div>'+nBadge+'</div><div style="font-size:13px;font-weight:700;color:var(--text-primary);margin-top:3px">'+fmtMoney(q.total)+'</div></div>';
    html += '<span style="font-size:16px;color:var(--text-muted);margin-left:8px;transition:transform .2s" id="ch'+q.id+'">&#8250;</span>';
    html += '</div>';
    html += detail;
    html += '</div>';
  });
  document.getElementById('listContent').innerHTML = html;
}

function toggleListDetail(id) {
  var detail = document.getElementById('ld'+id);
  var ch     = document.getElementById('ch'+id);
  if (!detail) return;
  var open = detail.classList.contains('open');
  document.querySelectorAll('.list-detail').forEach(function(d){ d.classList.remove('open'); });
  document.querySelectorAll('[id^="ch"]').forEach(function(c){ c.style.transform=''; });
  if (!open) { detail.classList.add('open'); if(ch) ch.style.transform='rotate(90deg)'; }
}

setView('month');
</script>

<?php include __DIR__ . '/layout-bottom.php'; ?>
