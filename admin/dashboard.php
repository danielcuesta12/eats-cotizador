<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

$pageTitle = 'Dashboard';

// Stats
$statsCot = Database::fetch(
    "SELECT COUNT(*) as n FROM quotes
     WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"
);
// Facturado = solo cotizaciones y eventos ACEPTADOS este mes
$statsFacturado = Database::fetch(
    "SELECT COALESCE(SUM(total),0) as sum FROM quotes
     WHERE status='aceptada'
     AND MONTH(accepted_at)=MONTH(NOW()) AND YEAR(accepted_at)=YEAR(NOW())"
);
$byStatus = Database::fetchAll("SELECT status, COUNT(*) as n FROM quotes GROUP BY status");
$smap     = array_column($byStatus, 'n', 'status');

// Solicitudes pendientes
$pendingReq = (int)Database::fetch("SELECT COUNT(*) as n FROM quote_requests WHERE status='pendiente'")['n'];

// Cotizaciones para calendario (enviadas y aceptadas con fecha de evento)
$calQuotes = Database::fetchAll(
    "SELECT q.id, q.quote_number, q.status, q.origin,
            q.event_date, q.event_type, q.event_time, q.event_duration,
            q.event_location, q.num_people, q.total,
            c.name as client_name
     FROM quotes q JOIN clients c ON c.id=q.client_id
     WHERE q.status IN ('enviada','aceptada') AND q.event_date IS NOT NULL AND q.event_date != ''
     ORDER BY q.event_date ASC"
);

// Items por cotización para tooltips
$calIds = array_column($calQuotes, 'id');
$calItemsMap = array();
if (!empty($calIds)) {
    $ph = implode(',', array_fill(0, count($calIds), '?'));
    $rows = Database::fetchAll(
        "SELECT quote_id, name, quantity FROM quote_items WHERE quote_id IN ($ph) ORDER BY sort_order",
        $calIds
    );
    foreach ($rows as $r) {
        $calItemsMap[$r['quote_id']][] = $r['name'] . ' × ' . number_format((float)$r['quantity'], 0);
    }
}
foreach ($calQuotes as &$q) {
    $q['items_summary'] = isset($calItemsMap[$q['id']]) ? implode(' · ', array_slice($calItemsMap[$q['id']], 0, 3)) : '';
}
unset($q);

// Agrupar por fecha
$calMap = array();
foreach ($calQuotes as $cq) {
    $calMap[$cq['event_date']][] = $cq;
}

// Últimas cotizaciones
$recentQuotes = Database::fetchAll(
    "SELECT q.*, c.name as client_name FROM quotes q
     JOIN clients c ON c.id=q.client_id
     ORDER BY q.created_at DESC LIMIT 6"
);

$activePage = 'dashboard';
include __DIR__ . '/layout-top.php';
?>

<!-- Stats compactos -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px">
  <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:12px 14px;box-shadow:var(--shadow)">
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:4px">Cot. este mes</div>
    <div style="font-size:22px;font-weight:800;line-height:1"><?php echo (int)($statsCot['n']??0); ?></div>
    <div style="font-size:11px;color:var(--text-muted);margin-top:2px">cotizaciones</div>
  </div>
  <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:12px 14px;box-shadow:var(--shadow)">
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:4px">Facturado mes</div>
    <div style="font-size:15px;font-weight:800;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo formatMoney((float)($statsFacturado['sum']??0)); ?></div>
    <div style="font-size:11px;color:var(--text-muted);margin-top:2px">aceptadas</div>
  </div>
  <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:12px 14px;box-shadow:var(--shadow)">
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:4px">Aceptadas</div>
    <div style="font-size:22px;font-weight:800;line-height:1;color:var(--green)"><?php echo (int)(isset($smap['aceptada'])?$smap['aceptada']:0); ?></div>
    <div style="font-size:11px;color:var(--text-muted);margin-top:2px">historico</div>
  </div>
  <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:12px 14px;box-shadow:var(--shadow);position:relative">
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:4px">Solicitudes</div>
    <div style="font-size:22px;font-weight:800;line-height:1;color:<?php echo $pendingReq>0?'#ca8a04':'var(--text-primary)'; ?>"><?php echo $pendingReq; ?></div>
    <div style="font-size:11px;color:var(--text-muted);margin-top:2px">pendientes</div>
    <?php if ($pendingReq > 0): ?>
    <a href="<?php echo APP_URL; ?>/admin/requests/index.php" style="position:absolute;top:8px;right:8px;background:#fef9c3;color:#854d0e;font-size:10px;padding:2px 7px;border-radius:6px;text-decoration:none;font-weight:600">Ver</a>
    <?php endif; ?>
  </div>
</div>

<!-- CALENDARIO -->
<div class="card" style="margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px">
    <span style="font-size:16px;font-weight:700" id="calTitle">Cargando...</span>
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
      <?php foreach (array('L','M','X','J','V','S','D') as $d): ?>
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
  <div style="display:flex;gap:14px;padding:8px 16px;border-top:1px solid var(--border);flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--text-muted)">
      <span style="width:10px;height:10px;border-radius:2px;background:#dbeafe;display:inline-block"></span>Enviada
    </div>
    <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--text-muted)">
      <span style="width:10px;height:10px;border-radius:2px;background:#dcfce7;display:inline-block"></span>Aceptada
    </div>
  </div>
</div>

<!-- Últimas cotizaciones -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Ultimas cotizaciones</span>
    <a href="<?php echo APP_URL; ?>/quotes/list.php" class="btn btn-ghost btn-sm">Ver todas &rarr;</a>
  </div>
  <?php if (empty($recentQuotes)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">&#128203;</div>
    <h3>Sin cotizaciones</h3>
    <a href="<?php echo APP_URL; ?>/quotes/create.php" class="btn btn-primary">+ Nueva cotizacion</a>
  </div>
  <?php else: ?>
  <!-- Desktop -->
  <div class="dash-desktop">
    <div class="table-wrap" style="border:none;border-radius:0">
      <table class="data-table">
        <thead><tr><th>N°</th><th>Cliente</th><th>Evento</th><th>Total</th><th>Estado</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($recentQuotes as $q): ?>
          <tr>
            <td><a href="<?php echo APP_URL; ?>/quotes/edit.php?id=<?php echo $q['id']; ?>" style="font-weight:700;color:var(--red);text-decoration:none"><?php echo clean($q['quote_number']); ?></a><div style="font-size:11px;color:var(--text-muted)"><?php echo formatDate($q['created_at']); ?></div></td>
            <td><?php echo clean($q['client_name']); ?></td>
            <td><?php echo clean($q['event_type']?:'—'); ?></td>
            <td><strong><?php echo formatMoney((float)$q['total']); ?></strong></td>
            <td><?php echo quoteStatusBadge($q['status']); ?></td>
            <td><div class="td-actions"><a href="<?php echo APP_URL; ?>/quotes/edit.php?id=<?php echo $q['id']; ?>" class="btn btn-ghost btn-sm">Editar</a><a href="<?php echo APP_URL; ?>/quotes/pdf.php?id=<?php echo $q['id']; ?>" class="btn btn-secondary btn-sm" target="_blank">PDF</a></div></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <!-- Mobile -->
  <div class="dash-mobile">
    <?php foreach ($recentQuotes as $q):
      $sc = array('borrador'=>'#aaa','enviada'=>'#2563eb','aceptada'=>'#16a34a','rechazada'=>'#dc2626');
      $dc = isset($sc[$q['status']])?$sc[$q['status']]:'#aaa';
    ?>
    <a href="<?php echo APP_URL; ?>/quotes/edit.php?id=<?php echo $q['id']; ?>"
       style="display:flex;justify-content:space-between;align-items:center;padding:13px 16px;border-bottom:1px solid var(--border);text-decoration:none;gap:10px;-webkit-tap-highlight-color:transparent">
      <div style="min-width:0">
        <div style="font-size:14px;font-weight:700;color:var(--red)"><?php echo clean($q['quote_number']); ?></div>
        <div style="font-size:13px;font-weight:600;color:#1a1a1a;margin-top:1px"><?php echo clean($q['client_name']); ?></div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:2px">
          <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:<?php echo $dc; ?>;margin-right:3px;vertical-align:middle"></span>
          <?php echo quoteStatusLabel($q['status']); ?><?php echo $q['event_type']?' &middot; '.clean($q['event_type']):''; ?>
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div style="font-size:15px;font-weight:800;color:#1a1a1a"><?php echo formatMoney((float)$q['total']); ?></div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:2px"><?php echo formatDate($q['created_at']); ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<style>
.dash-desktop{display:block}.dash-mobile{display:none}
@media(max-width:768px){.dash-desktop{display:none}.dash-mobile{display:block}}
.cal-day-cell{min-height:60px;padding:4px 3px;border-right:1px solid var(--border);border-bottom:1px solid var(--border);vertical-align:top}
.cal-day-cell:nth-child(7n){border-right:none}
.cal-ev{border-radius:3px;padding:1px 4px;font-size:9px;font-weight:600;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer;display:block;text-decoration:none}
.cal-ev-e{background:#dbeafe;color:#1e40af}
.cal-ev-a{background:#dcfce7;color:#166534}
.list-item{display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid var(--border);text-decoration:none;-webkit-tap-highlight-color:transparent}
.list-item:last-child{border-bottom:none}
.list-item:hover{background:#fafafa}
.list-month-hdr{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);padding:10px 18px 6px;background:#fafafa;border-bottom:1px solid var(--border)}
</style>

<script>
var QUOTES = <?php echo json_encode($calQuotes); ?>;
var APP    = '<?php echo APP_URL; ?>';
var today  = new Date();
var cur    = new Date(today.getFullYear(), today.getMonth(), 1);
var view   = 'month';

var MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function setView(v) {
  view = v;
  document.getElementById('viewMonth').style.display = v==='month' ? '' : 'none';
  document.getElementById('viewList').style.display  = v==='list'  ? '' : 'none';
  document.getElementById('btnMonth').style.cssText  = v==='month' ? 'color:var(--red);border-color:var(--red);background:rgba(200,16,46,.06)' : '';
  document.getElementById('btnList').style.cssText   = v==='list'  ? 'color:var(--red);border-color:var(--red);background:rgba(200,16,46,.06)' : '';
  if (v==='month') renderMonth();
  else renderList();
}

function calNav(dir) {
  cur.setMonth(cur.getMonth() + dir);
  if (view==='month') renderMonth(); else renderList();
}
function calToday() {
  cur = new Date(today.getFullYear(), today.getMonth(), 1);
  if (view==='month') renderMonth(); else renderList();
}

function quotesByDate(dateStr) {
  return QUOTES.filter(function(q){ return q.event_date === dateStr; });
}

function evClass(q) {
  if (q.origin==='event') return 'cal-ev-v';
  return q.status==='aceptada' ? 'cal-ev-a' : 'cal-ev-e';
}
function barColor(q) {
  if (q.origin==='event') return '#7c3aed';
  return q.status==='aceptada' ? '#16a34a' : '#2563eb';
}
function badgeLabel(q) {
  if (q.origin==='event') return 'Evento';
  return q.status==='aceptada' ? 'Aceptada' : 'Enviada';
}
function badgeStyle(q) {
  if (q.origin==='event') return 'background:#ede9fe;color:#5b21b6';
  return q.status==='aceptada' ? 'background:#dcfce7;color:#166534' : 'background:#dbeafe;color:#1e40af';
}

function showDashTooltip(e, qid) {
  e.stopPropagation();
  var q = QUOTES.find(function(x){ return x.id==qid; });
  if (!q) return;
  var tip = document.getElementById('dashTip');
  if (!tip) {
    tip = document.createElement('div');
    tip.id = 'dashTip';
    tip.style.cssText = 'position:fixed;z-index:9999;width:240px;background:var(--bg-card,#fff);border:1.5px solid #e5e5e5;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.14);overflow:hidden;font-family:inherit';
    document.body.appendChild(tip);
    document.addEventListener('click', function(ev){ if(!tip.contains(ev.target)) tip.style.display='none'; });
  }
  var body = '';
  if (q.event_time) body += '<div style="font-size:12px;color:#555;display:flex;gap:6px;margin-bottom:4px"><span>&#128336;</span><span>'+q.event_time+(q.event_duration?' · '+q.event_duration:'')+'</span></div>';
  if (q.event_location) body += '<div style="font-size:12px;color:#555;display:flex;gap:6px;margin-bottom:4px"><span>&#128205;</span><span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+q.event_location+'</span></div>';
  if (q.num_people>0) body += '<div style="font-size:12px;color:#555;display:flex;gap:6px"><span>&#128101;</span><span>'+q.num_people+' personas</span></div>';
  tip.innerHTML = '<div style="padding:9px 12px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center"><span style="font-size:12px;font-weight:700;color:#1a1a1a">'+q.client_name+'</span><span style="font-size:10px;padding:2px 7px;border-radius:10px;font-weight:600;'+badgeStyle(q)+'">'+badgeLabel(q)+'</span></div>'
    +'<div style="padding:9px 12px">'+body+'</div>'
    +(q.items_summary?'<div style="font-size:11px;color:#888;padding:6px 12px;border-top:1px solid #eee;background:#fafafa">'+q.items_summary+'</div>':'')
    +'<div style="padding:8px 12px;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center"><span style="font-size:13px;font-weight:700;color:#1a1a1a">S/ '+parseFloat(q.total).toLocaleString("es-PE",{minimumFractionDigits:2})+'</span><a href="'+APP+'/quotes/edit.php?id='+q.id+'" style="font-size:12px;font-weight:600;color:#2563eb;text-decoration:none">'+(q.origin==='event'?'Ver evento':'Ver cot.')+'→</a></div>';
  var rect = e.target.getBoundingClientRect();
  var left = Math.min(rect.left, window.innerWidth-256);
  tip.style.display='block';
  tip.style.top=(rect.bottom+6)+'px';
  tip.style.left=Math.max(8,left)+'px';
}

function renderMonth() {
  document.getElementById('calTitle').textContent = MONTHS[cur.getMonth()] + ' ' + cur.getFullYear();
  var grid    = document.getElementById('calGrid');
  var year    = cur.getFullYear();
  var month   = cur.getMonth();
  var firstDow= (new Date(year, month, 1).getDay() + 6) % 7; // Lunes=0
  var daysInM = new Date(year, month+1, 0).getDate();
  var daysInP = new Date(year, month, 0).getDate();
  var html    = '';
  var todayStr= today.toISOString().split('T')[0];

  // Días del mes anterior
  for (var i=firstDow-1; i>=0; i--) {
    html += '<div class="cal-day-cell" style="opacity:.3"><div style="font-size:11px;color:#999;padding-left:2px">'+(daysInP-i)+'</div></div>';
  }
  // Días del mes
  for (var d=1; d<=daysInM; d++) {
    var mm    = String(month+1).padStart(2,'0');
    var dd    = String(d).padStart(2,'0');
    var dateS = year+'-'+mm+'-'+dd;
    var isToday = dateS === todayStr;
    var qs    = quotesByDate(dateS);
    var bg    = isToday ? 'background:var(--blue-bg)' : '';
    var numStyle = isToday ? 'color:var(--blue);font-weight:700' : 'color:#999';
    html += '<div class="cal-day-cell" style="'+bg+'">';
    html += '<div style="font-size:11px;'+numStyle+';padding-left:2px;margin-bottom:2px">'+d+'</div>';
    qs.forEach(function(q) {
      var cls = q.status==='aceptada' ? 'cal-ev-a' : 'cal-ev-e';
      var cls2=evClass(q); html += '<button onclick="showDashTooltip(event,'+q.id+')" class="cal-ev '+cls2+'" style="border:none;cursor:pointer">'+q.quote_number+'</button>';
    });
    html += '</div>';
  }
  // Completar última fila
  var total = firstDow + daysInM;
  var rem   = total % 7;
  if (rem > 0) {
    for (var x=1; x<=(7-rem); x++) {
      html += '<div class="cal-day-cell" style="opacity:.3"><div style="font-size:11px;color:#999;padding-left:2px">'+x+'</div></div>';
    }
  }
  grid.innerHTML = html;
}

function renderList() {
  var year  = cur.getFullYear();
  var month = cur.getMonth();
  document.getElementById('calTitle').textContent = MONTHS[month] + ' ' + year + ' — Lista';

  var start  = new Date(year, month, 1);
  var end    = new Date(year, month+3, 0);
  var startS = start.toISOString().split('T')[0];
  var endS   = end.toISOString().split('T')[0];
  var filtered = QUOTES.filter(function(q){ return q.event_date >= startS && q.event_date <= endS; });

  if (!filtered.length) {
    document.getElementById('listContent').innerHTML = '<div style="padding:32px;text-align:center;color:#999;font-size:14px">Sin eventos en este periodo</div>';
    return;
  }

  var html = '';
  var lastM = '';
  filtered.forEach(function(q) {
    var parts = q.event_date.split('-');
    var mKey  = parts[0]+'-'+parts[1];
    var mIdx  = parseInt(parts[1])-1;
    if (mKey !== lastM) {
      html += '<div class="list-month-hdr">'+MONTHS[mIdx]+' '+parts[0]+'</div>';
      lastM = mKey;
    }
    var d   = parseInt(parts[2]);
    var dow = ['Dom','Lun','Mar','Mie','Jue','Vie','Sab'][new Date(q.event_date).getDay()];
    var bc  = barColor(q);
    var bl  = badgeLabel(q);
    var bs  = badgeStyle(q);
    var nc  = (q.origin==='event') ? '#7c3aed' : (q.status==='aceptada' ? '#16a34a' : '#2563eb');

    // Detalle expandible
    var detail = '';
    if (q.event_time || q.event_location || q.num_people || q.items_summary) {
      detail += '<div id="ld'+q.id+'" style="display:none;background:#fafafa;padding:10px 18px 12px 72px;border-top:1px solid var(--border)">';
      detail += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">';
      if (q.event_time) detail += '<div><div style="font-size:10px;color:#999;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px">Hora</div><div style="font-size:12px;font-weight:600;color:#1a1a1a">'+q.event_time+(q.event_duration?' · '+q.event_duration:'')+'</div></div>';
      if (q.num_people>0) detail += '<div><div style="font-size:10px;color:#999;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px">Personas</div><div style="font-size:12px;font-weight:600;color:#1a1a1a">'+q.num_people+' pers.</div></div>';
      if (q.event_location) detail += '<div style="grid-column:1/-1"><div style="font-size:10px;color:#999;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px">Lugar</div><div style="font-size:12px;font-weight:600;color:#1a1a1a">'+q.event_location+'</div></div>';
      detail += '</div>';
      if (q.items_summary) detail += '<div style="font-size:11px;color:#999;margin-bottom:8px">'+q.items_summary+'</div>';
      detail += '<a href="'+APP+'/quotes/edit.php?id='+q.id+'" style="font-size:12px;font-weight:600;color:#2563eb;text-decoration:none">'+(q.origin==='event'?'Ver evento':'Ver cotización')+' →</a>';
      detail += '</div>';
    }

    html += '<div style="border-bottom:1px solid var(--border)">';
    html += '<div onclick="toggleDashDetail('+q.id+')" style="display:flex;align-items:center;gap:12px;padding:13px 18px;cursor:pointer;-webkit-tap-highlight-color:transparent" id="li'+q.id+'">';
    html += '<div style="text-align:center;min-width:38px;flex-shrink:0"><div style="font-size:20px;font-weight:700;line-height:1;color:#1a1a1a">'+d+'</div><div style="font-size:10px;color:#999;text-transform:uppercase">'+dow+'</div></div>';
    html += '<div style="width:3px;border-radius:2px;align-self:stretch;min-height:36px;flex-shrink:0;background:'+bc+'"></div>';
    html += '<div style="flex:1;min-width:0">';
    html += '<div style="font-size:11px;font-weight:600;color:'+nc+'">'+q.quote_number+'</div>';
    html += '<div style="font-size:13px;font-weight:600;color:#1a1a1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+q.client_name+'</div>';
    if (q.event_type || q.event_time) html += '<div style="font-size:11px;color:#999">'+(q.event_type||'')+(q.event_time?' · '+q.event_time:'')+'</div>';
    html += '</div>';
    html += '<span style="'+bs+';padding:3px 8px;border-radius:8px;font-size:11px;font-weight:600;flex-shrink:0">'+bl+'</span>';
    html += '<span id="ch'+q.id+'" style="font-size:16px;color:#999;margin-left:6px;transition:transform .2s;display:inline-block">›</span>';
    html += '</div>';
    html += detail;
    html += '</div>';
  });
  document.getElementById('listContent').innerHTML = html;
}

function toggleDashDetail(id) {
  var detail = document.getElementById('ld'+id);
  var ch     = document.getElementById('ch'+id);
  if (!detail) {
    window.location.href = APP+'/quotes/edit.php?id='+id;
    return;
  }
  var isOpen = detail.style.display !== 'none';
  // Cerrar todos
  document.querySelectorAll('[id^="ld"]').forEach(function(d){ d.style.display='none'; });
  document.querySelectorAll('[id^="ch"]').forEach(function(c){ c.style.transform=''; });
  if (!isOpen) {
    detail.style.display = 'block';
    if (ch) ch.style.transform = 'rotate(90deg)';
  }
}

// Agregar "Solicitudes" al nav si hay pendientes
<?php if ($pendingReq > 0): ?>
var navLinks = document.querySelectorAll('.nav-link');
<?php endif; ?>

setView('month');
</script>

<?php include __DIR__ . '/layout-bottom.php'; ?>
