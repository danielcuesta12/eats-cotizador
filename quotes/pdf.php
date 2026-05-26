<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$id    = cleanInt(isset($_GET['id'])    ? $_GET['id']    : 0);
$token = clean(isset($_GET['token'])    ? $_GET['token'] : '');
$quote = null;

if (isLoggedIn() && $id) {
    $quote = Database::fetch(
        "SELECT q.*, c.name as client_name, c.type as client_type,
                c.ruc_dni, c.email as client_email, c.phone as client_phone,
                c.address as client_address, c.contact_name, u.name as created_by_name
         FROM quotes q JOIN clients c ON c.id=q.client_id JOIN users u ON u.id=q.user_id
         WHERE q.id=?", array($id));
} elseif ($token) {
    $quote = Database::fetch(
        "SELECT q.*, c.name as client_name, c.type as client_type,
                c.ruc_dni, c.email as client_email, c.phone as client_phone,
                c.address as client_address, c.contact_name, u.name as created_by_name
         FROM quotes q JOIN clients c ON c.id=q.client_id JOIN users u ON u.id=q.user_id
         WHERE q.public_token=?", array($token));
}

if (!$quote) { http_response_code(404); die('<h2>Cotizacion no encontrada</h2>'); }

$items    = Database::fetchAll("SELECT * FROM quote_items WHERE quote_id=? ORDER BY sort_order", array($quote['id']));
$isPublic = !isLoggedIn();
$co = array(
    'name'    => getSetting('company_name',        'El Gringo Burger Joint'),
    'ruc'     => getSetting('company_ruc',         ''),
    'address' => getSetting('company_address',     'Lima, Peru'),
    'phone'   => getSetting('company_phone',       ''),
    'email'   => getSetting('company_email',       ''),
    'logo'    => getSetting('company_logo',        ''),
    'color1'  => getSetting('pdf_primary_color',   '#C8102E'),
    'color2'  => getSetting('pdf_secondary_color', '#ffffff'), // color de texto en cajas
);
$pubLink      = APP_URL . '/quotes/view.php?token=' . $quote['public_token'];
$showBankAccs = getSetting('show_bank_accounts', '1') === '1';
$bankAccounts = $showBankAccs
    ? Database::fetchAll("SELECT * FROM bank_accounts WHERE active=1 ORDER BY sort_order")
    : array();
$logoUrl  = !empty($co['logo']) ? UPLOAD_URL  . $co['logo'] : '';
$logoPath = !empty($co['logo']) ? UPLOAD_PATH . $co['logo'] : '';
$c1       = $co['color1'];

// WhatsApp al CLIENTE
$clientPhone = preg_replace('/\D/', '', isset($quote['client_phone']) ? $quote['client_phone'] : '');
$waMsg = urlencode(
    "Hola " . $quote['client_name'] . ", te comparto la cotizacion *" . $quote['quote_number'] . "*.\n\n" .
    "Evento: " . ($quote['event_type'] ?: '-') . "\n" .
    ($quote['event_date'] ? "Fecha: " . formatDate($quote['event_date']) . "\n" : '') .
    "Total: " . formatMoney((float)$quote['total']) . "\n\nVer cotizacion:\n" . $pubLink
);
$waLink = $clientPhone ? "https://wa.me/" . $clientPhone . "?text=" . $waMsg : "https://wa.me/?text=" . $waMsg;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="theme-color" content="<?php echo htmlspecialchars($c1); ?>">
<link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/img/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_URL; ?>/assets/img/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo APP_URL; ?>/assets/img/favicon-180.png">
<title>Cotizacion <?php echo clean($quote['quote_number']); ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--red:<?php echo htmlspecialchars($c1); ?>;--text-on-red:<?php echo htmlspecialchars($co['color2']); ?>;--border:#e8e8e8;--muted:#888;--light:#f7f7f7}
html{-webkit-text-size-adjust:100%}
body{font-family:-apple-system,'SF Pro Text','Segoe UI',sans-serif;font-size:14px;color:#1a1a1a;background:#f0f0f0;-webkit-font-smoothing:antialiased}
.doc{background:#fff;max-width:700px;margin:0 auto}
.doc-header{background:var(--red);padding:20px;display:flex;justify-content:space-between;align-items:center;gap:12px}
.header-logo img{max-height:48px;max-width:140px;object-fit:contain}
.header-logo-text{font-size:18px;font-weight:800;color:var(--text-on-red)}
.header-right{text-align:right;flex-shrink:0}
.header-label{font-size:10px;color:var(--text-on-red);opacity:.7;text-transform:uppercase;letter-spacing:1px}
.header-number{font-size:20px;font-weight:800;color:var(--text-on-red);letter-spacing:-.5px}
.parties{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid var(--border)}
.party{padding:14px 16px}
.party:first-child{border-right:1px solid var(--border)}
.party-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:5px}
.party-name{font-size:13px;font-weight:700;margin-bottom:3px}
.party-detail{font-size:11px;color:#666;line-height:1.6}
.event-bar{background:var(--light);padding:10px 16px;display:grid;grid-template-columns:1fr 1fr;gap:8px 12px;border-bottom:1px solid var(--border)}
.ev-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);display:block;margin-bottom:1px}
.ev-val{font-size:12px;font-weight:700;color:#1a1a1a}
.products-wrap{padding:0 16px}
.section-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);padding:12px 0 6px}
.tbl-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
table{width:100%;min-width:480px;border-collapse:collapse}
thead tr{background:var(--red);color:var(--text-on-red)}
th{padding:8px 10px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;white-space:nowrap}
th.r,td.r{text-align:right}th.c,td.c{text-align:center}
td{padding:9px 10px;font-size:12px;border-bottom:1px solid #f0f0f0;vertical-align:top}
tr:nth-child(even) td{background:#fafafa}
.td-name{font-weight:600}
.td-desc{font-size:10px;color:#999;font-style:italic;margin-top:1px}
.td-mode{display:inline-block;background:#f0f0f0;border-radius:3px;padding:1px 5px;font-size:10px;color:#555}
.totals-wrap{display:flex;justify-content:flex-end;padding:12px 16px}
.totals-box{width:220px}
.tot-row{display:flex;justify-content:space-between;font-size:12px;color:#666;padding:4px 0;border-bottom:1px solid #f5f5f5}
.tot-final{display:flex;justify-content:space-between;align-items:center;background:var(--red);color:var(--text-on-red);padding:10px 12px;border-radius:8px;margin-top:8px}
.tot-final-label{font-size:12px;font-weight:700}
.tot-final-amount{font-size:20px;font-weight:800}
.tot-pp{text-align:right;font-size:11px;color:var(--muted);margin-top:4px}
.notes-wrap{padding:0 16px 16px}
.note-block{margin-bottom:12px}
.note-title{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#888;margin-bottom:5px}
.note-body{font-size:11px;color:#555;line-height:1.6;white-space:pre-line}
.doc-footer{background:var(--red);padding:10px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:4px}
.doc-footer-text{font-size:10px;color:var(--text-on-red);opacity:.7}
.doc-footer-badge{background:var(--text-on-red);color:var(--red);padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700}
.action-bar{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid var(--border);padding:10px 16px;padding-bottom:max(10px,env(safe-area-inset-bottom));display:flex;gap:8px;z-index:100;box-shadow:0 -4px 20px rgba(0,0,0,.08)}
.abtn{flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:13px 10px;border-radius:12px;font-size:13px;font-weight:700;text-decoration:none;border:none;cursor:pointer;min-height:48px;-webkit-tap-highlight-color:transparent}
.abtn:active{opacity:.75}
.abtn-back{background:#f0f0f0;color:#333}
.abtn-print{background:#1a1a1a;color:#fff}
.abtn-wa{background:#25D366;color:#fff}
.abtn-link{background:#f0f0f0;color:#333}
.doc-spacer{height:80px}
@media print{
  *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  html{margin:0!important;padding:0!important;background:#fff!important}
  body{margin:0!important;padding:0!important;background:#fff!important;font-size:10px!important}
  .action-bar,.doc-spacer{display:none!important}
  .doc{max-width:100%!important;box-shadow:none!important;margin:0!important;padding:0!important;background:#fff!important}
  .doc-header{padding:10px 14px!important}
  .header-logo img{max-height:32px!important}
  .header-number{font-size:15px!important}
  .party{padding:8px 12px!important}
  .party-name{font-size:11px!important}
  .party-detail{font-size:9px!important}
  .event-bar{padding:6px 12px!important;grid-template-columns:repeat(4,1fr)!important}
  .ev-val{font-size:10px!important}
  .products-wrap{padding:0 12px!important}
  .tbl-wrap{overflow:visible!important}
  table{min-width:0!important}
  th{font-size:8px!important;padding:5px 7px!important}
  td{font-size:10px!important;padding:5px 7px!important}
  .totals-wrap{padding:8px 12px!important}
  .totals-box{width:180px!important}
  .tot-final-amount{font-size:15px!important}
  .notes-wrap{padding:0 12px 8px!important}
  .note-body{font-size:9px!important}
  @page{size:A4 portrait;margin:8mm}
  .doc-header{page-break-after:avoid!important}
  .parties,.event-bar{page-break-inside:avoid!important}
  tr{page-break-inside:avoid!important}
  .totals-wrap{page-break-inside:avoid!important;page-break-before:avoid!important}
  .notes-wrap{page-break-inside:avoid!important}
  .doc-footer{page-break-before:avoid!important}
}
@media(min-width:700px){
  body{padding:0}
  .doc{box-shadow:0 4px 24px rgba(0,0,0,.1);margin:20px auto 80px}
  .doc-header{padding:24px 28px}
  .party{padding:18px 24px}
  .event-bar{grid-template-columns:repeat(4,1fr);padding:12px 24px}
  .products-wrap{padding:0 24px}
  .totals-wrap{padding:14px 24px}
  .notes-wrap{padding:0 24px 20px}
}
</style>
</head>
<body>
<div class="doc">
  <div class="doc-header">
    <div class="header-logo">
      <?php if ($logoUrl && file_exists($logoPath)): ?>
        <img src="<?php echo clean($logoUrl); ?>" alt="<?php echo clean($co['name']); ?>">
      <?php else: ?>
        <div class="header-logo-text"><?php echo clean($co['name']); ?></div>
      <?php endif; ?>
    </div>
    <div class="header-right">
      <div class="header-label">Cotizacion</div>
      <div class="header-number"><?php echo clean($quote['quote_number']); ?></div>
    </div>
  </div>
  <div class="parties">
    <div class="party">
      <div class="party-label">De</div>
      <div class="party-name"><?php echo clean($co['name']); ?></div>
      <div class="party-detail">
        <?php if ($co['ruc']): ?>RUC: <?php echo clean($co['ruc']); ?><br><?php endif; ?>
        <?php if ($co['address']): ?><?php echo clean($co['address']); ?><br><?php endif; ?>
        <?php if ($co['phone']): ?><?php echo clean($co['phone']); ?><br><?php endif; ?>
        <?php if ($co['email']): ?><?php echo clean($co['email']); ?><?php endif; ?>
      </div>
    </div>
    <div class="party">
      <div class="party-label">Para</div>
      <div class="party-name"><?php echo clean($quote['client_name']); ?></div>
      <div class="party-detail">
        <?php if ($quote['ruc_dni']): ?><?php echo $quote['client_type']==='empresa'?'RUC':'DNI'; ?>: <?php echo clean($quote['ruc_dni']); ?><br><?php endif; ?>
        <?php if ($quote['contact_name']): ?>Contacto: <?php echo clean($quote['contact_name']); ?><br><?php endif; ?>
        <?php if ($quote['client_email']): ?><?php echo clean($quote['client_email']); ?><br><?php endif; ?>
        <?php if ($quote['client_phone']): ?><?php echo clean($quote['client_phone']); ?><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="event-bar">
    <div><span class="ev-label">Evento</span><span class="ev-val"><?php echo clean($quote['event_type']?:'—'); ?></span></div>
    <div><span class="ev-label">Fecha</span><span class="ev-val"><?php echo $quote['event_date']?formatDate($quote['event_date']):'—'; ?></span></div>
    <div><span class="ev-label">Personas</span><span class="ev-val"><?php echo $quote['num_people']>0?$quote['num_people'].' pers.':'—'; ?></span></div>
    <div><span class="ev-label">Vigencia</span><span class="ev-val"><?php echo $quote['valid_until']?formatDate($quote['valid_until']):'—'; ?></span></div>
  </div>
  <div class="products-wrap">
    <div class="section-label">Detalle de servicios</div>
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th style="width:38%">Producto / Servicio</th>
          <th style="width:11%" class="c">Modo</th>
          <th style="width:13%" class="r">Precio</th>
          <th style="width:8%" class="c">Cant.</th>
          <th style="width:8%" class="c">Desc.</th>
          <th style="width:14%" class="r">Subtotal</th>
        </tr></thead>
        <tbody>
          <?php foreach ($items as $item):
            $ml='Libre';
            if ($item['price_mode']==='per_person') $ml='&times; pers.';
            if ($item['price_mode']==='per_event')  $ml='&times; evento';
            $ds=$item['discount_pct']>0?number_format((float)$item['discount_pct'],1).'%':'&mdash;';
          ?>
          <tr>
            <td><div class="td-name"><?php echo clean($item['name']); ?></div><?php if ($item['description']): ?><div class="td-desc"><?php echo clean($item['description']); ?></div><?php endif; ?></td>
            <td class="c"><span class="td-mode"><?php echo $ml; ?></span></td>
            <td class="r"><?php echo formatMoney((float)$item['unit_price'],''); ?></td>
            <td class="c"><?php echo number_format((float)$item['quantity'],1); ?></td>
            <td class="c"><?php echo $ds; ?></td>
            <td class="r"><strong><?php echo formatMoney((float)$item['subtotal']); ?></strong></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="totals-wrap">
    <div class="totals-box">
      <div class="tot-row"><span>Subtotal</span><strong><?php echo formatMoney((float)$quote['subtotal']); ?></strong></div>
      <?php if ($quote['discount_pct']>0): ?><div class="tot-row" style="color:#dc2626"><span>Descuento (<?php echo $quote['discount_pct']; ?>%)</span><strong>- <?php echo formatMoney((float)$quote['discount_amount']); ?></strong></div><?php endif; ?>
      <?php if ($quote['extras_amount']>0): ?><div class="tot-row" style="color:#16a34a"><span><?php echo clean($quote['extras_detail']?:'Adicionales'); ?></span><strong>+ <?php echo formatMoney((float)$quote['extras_amount']); ?></strong></div><?php endif; ?>
      <?php if ($quote['igv_type']!=='none'): ?><div class="tot-row"><span>IGV <?php echo $quote['igv_type']; ?>%</span><strong><?php echo formatMoney((float)$quote['igv_amount']); ?></strong></div><?php endif; ?>
      <div class="tot-final"><span class="tot-final-label">TOTAL</span><span class="tot-final-amount"><?php echo formatMoney((float)$quote['total']); ?></span></div>
      <?php if ($quote['num_people']>0&&$quote['price_per_person']>0): ?><div class="tot-pp"><?php echo formatMoney((float)$quote['price_per_person']); ?> por persona</div><?php endif; ?>
    </div>
  </div>
  <?php if ($quote['observations']||$quote['terms']): ?>
  <div class="notes-wrap">
    <?php if ($quote['observations']): ?><div class="note-block"><div class="note-title">Observaciones</div><div class="note-body"><?php echo clean($quote['observations']); ?></div></div><?php endif; ?>
    <?php if ($quote['terms']): ?><div class="note-block"><div class="note-title">Terminos y condiciones</div><div class="note-body"><?php echo clean($quote['terms']); ?></div></div><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($bankAccounts)): ?>
  <div class="notes-wrap" style="padding:0 16px 16px">
    <div class="note-block">
      <div class="note-title" style="color:#888">Numeros de cuenta</div>
      <?php foreach ($bankAccounts as $ba): ?>
      <div style="border:1px solid #e8e8e8;border-radius:8px;overflow:hidden;margin-bottom:8px">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 12px;background:#f7f7f7;border-bottom:1px solid #e8e8e8">
          <span style="font-size:12px;font-weight:700;color:#1a1a1a"><?php echo clean($ba['bank_name']); ?></span>
          <span style="font-size:10px;color:#888"><?php echo ucfirst(clean($ba['account_type'])); ?> &middot; Soles</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;padding:8px 12px;gap:6px">
          <?php if (!empty($ba['account_holder'])): ?>
          <div>
            <div style="font-size:9px;color:#999;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px">Titular</div>
            <div style="font-size:11px;font-weight:600;color:#1a1a1a"><?php echo clean($ba['account_holder']); ?></div>
          </div>
          <?php endif; ?>
          <?php if (!empty($ba['tax_id'])): ?>
          <div>
            <div style="font-size:9px;color:#999;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px">RUC / DNI</div>
            <div style="font-size:11px;font-weight:600;color:#1a1a1a;font-family:monospace"><?php echo clean($ba['tax_id']); ?></div>
          </div>
          <?php endif; ?>
          <div>
            <div style="font-size:9px;color:#999;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px">N&deg; de cuenta</div>
            <div style="font-size:11px;font-weight:600;color:#1a1a1a;font-family:monospace"><?php echo clean($ba['account_number']); ?></div>
          </div>
          <?php if (!empty($ba['cci'])): ?>
          <div>
            <div style="font-size:9px;color:#999;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px">CCI</div>
            <div style="font-size:11px;font-weight:600;color:#1a1a1a;font-family:monospace"><?php echo clean($ba['cci']); ?></div>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="doc-footer">
    <div class="doc-footer-text">Generado el <?php echo date('d/m/Y'); ?> &middot; <?php echo clean($co['name']); ?></div>
    <div class="doc-footer-badge"><?php echo clean($quote['quote_number']); ?></div>
  </div>
  <div class="doc-spacer"></div>
</div>

<div class="action-bar">
  <?php if (isLoggedIn()): ?>
  <a href="<?php echo APP_URL; ?>/quotes/edit.php?id=<?php echo $quote['id']; ?>" class="abtn abtn-back">&#8592;</a>
  <?php endif; ?>
  <button onclick="window.print()" class="abtn abtn-print">&#128438; Imprimir / PDF</button>
  <?php if (isLoggedIn()): ?>
  <a href="<?php echo $waLink; ?>" target="_blank" class="abtn abtn-wa">&#128172; WhatsApp</a>
  <button onclick="copyLink()" class="abtn abtn-link" id="copyBtn">&#128279; Link</button>
  <?php endif; ?>
</div>

<script>
function copyLink(){
  var url='<?php echo APP_URL; ?>/quotes/view.php?token=<?php echo clean($quote['public_token']); ?>';
  if(navigator.clipboard){navigator.clipboard.writeText(url).then(function(){var b=document.getElementById('copyBtn');b.textContent='&#10003; Copiado';b.style.background='#16a34a';b.style.color='#fff';setTimeout(function(){b.innerHTML='&#128279; Link';b.style.background='';b.style.color='';},2000);});}
  else{prompt('Copia este link:',url);}
}
</script>
</body>
</html>
