<?php
/* invoice_template.php — tīrraksts ar perioda piezīmi zem kopsummas */

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n, 2, '.', ''); }
function nobreak_hyphen($s){ return str_replace('-', "\u{2011}", (string)$s); } // U+2011
function fmt_date_nb($s){
  if (!$s) return '';
  $d = date('Y-m-d', strtotime((string)$s));
  return nobreak_hyphen($d);
}
function clean_text($s){
  $s = (string)$s;
  $s = str_replace(["\r\n","\r"], "\n", $s);
  $s = str_ireplace(['<br>','<br/>','<br />'], "\n", $s);
  $s = strip_tags($s);
  $s = preg_replace('/[ \t]+/', ' ', $s);
  $s = preg_replace('/\n+/', ', ', $s);
  return trim($s, " \t\n\r\0\x0B,");
}
function clean_lines($s){
  $s = (string)$s;
  $s = str_replace(["\r\n","\r"], "\n", $s);
  $s = str_ireplace(['<br>','<br/>','<br />'], "\n", $s);
  $s = strip_tags($s);
  $rows = array_values(array_filter(array_map('trim', explode("\n", $s))));
  return $rows;
}
function iban_groups_nbsp($iban){
  $s = preg_replace('/\s+/', '', strtoupper((string)$iban));
  if ($s==='') return '';
  $s = trim(chunk_split($s, 4, ' '));
  return str_replace(' ', "\u{00A0}", $s);
}

/* ===== FIKSĒTS KONTA NR. galvenei (Elīna) ===== */
$BANK_IBAN    = 'LV97HABA0000123456789';
$BANK_IBAN_NB = iban_groups_nbsp($BANK_IBAN);

/* ---- Rindas no $inv ---- */
$items = [];
if (!empty($inv['items']) && is_array($inv['items'])) {
  foreach ($inv['items'] as $r) {
    $name=trim((string)($r['name']??'')); $qty=(float)($r['qty']??0); $price=(float)($r['price']??0);
    if ($name!=='' && $qty>0 && $price>=0) $items[]=['name'=>$name,'qty'=>$qty,'price'=>$price,'total'=>isset($r['total'])?(float)$r['total']:round($qty*$price,2)];
  }
} elseif (!empty($inv['services']) && is_array($inv['services'])) {
  foreach ($inv['services'] as $r) {
    $name=trim((string)($r['name']??'')); $qty=(float)($r['qty']??1); $price=(float)($r['price']??0);
    if ($name!=='' && $qty>0 && $price>=0) $items[]=['name'=>$name,'qty'=>$qty,'price'=>$price,'total'=>round($qty*$price,2)];
  }
}

/* ---- Summas ---- */
$subtotal = isset($inv['subtotal']) ? (float)$inv['subtotal'] : array_reduce($items, fn($s,$r)=>$s + (float)$r['total'], 0.0);
$vatRate  = isset($inv['vat_rate']) ? (float)$inv['vat_rate'] : 0.00;
$vat      = isset($inv['vat'])      ? (float)$inv['vat']      : round($subtotal * $vatRate, 2);
$total    = isset($inv['total'])    ? (float)$inv['total']    : round($subtotal + $vat, 2);

/* ---- Maksātāja rindas (bez tel/e-pasta; bez PVN; + banka/konts) ---- */
function payer_rows(array $c): array {
  $rows = [];
  $kind = strtolower((string)($c['kind'] ?? ''));
  $isCompany = $kind === 'legal' || $kind === 'company' || !empty($c['company_name']);

  if ($isCompany) {
    if (!empty($c['company_name']))                              $rows[] = ['Uzņēmums', clean_text($c['company_name'])];
    if (!empty($c['reg_no'] ?? $c['registration_no'] ?? ''))     $rows[] = ['Reģ. nr.', clean_text($c['reg_no'] ?? $c['registration_no'])];
  } else {
    $full = trim(($c['first_name'] ?? '').' '.($c['last_name'] ?? ''));
    if ($full !== '')                                           $rows[] = ['Vārds, uzvārds', clean_text($full)];
    if (!empty($c['personal_code'] ?? $c['pk'] ?? ''))          $rows[] = ['Personas kods',  clean_text($c['personal_code'] ?? $c['pk'])];
  }

  $addr=[];
  foreach (['address','street','addr1','addr','city','state','postal_code','zip','country'] as $k) {
    if (!empty($c[$k])) $addr[] = clean_text($c[$k]);
  }
  if ($addr) $rows[] = ['Adrese', implode(', ', $addr)];

  if (!empty($c['bank'] ?? ''))       $rows[] = ['Banka', clean_text($c['bank'])];
  if (!empty($c['account_no'] ?? '')) $rows[] = ['Konta Nr.', iban_groups_nbsp($c['account_no'])];

  if (!$rows && !empty($c['display'])) {
    foreach (clean_lines($c['display']) as $ln) $rows[] = ['', $ln];
  }
  if (!$rows) $rows[] = ['', '—'];
  return $rows;
}
$payerRows = payer_rows($client);

/* ---- Datums / Rēķina Nr. ---- */
$dateRaw = $inv['invoice_time'] ?? $inv['date'] ?? null;
$dateNB  = fmt_date_nb($dateRaw);
$invNo   = $inv['number'] ?? $inv['invoice_no'] ?? null;
$invNoNB = $invNo ? nobreak_hyphen($invNo) : '';
$invNoDisplay = $invNoNB !== '' ? $invNoNB : 'Rēķ. nav arhivēts';
$dateDisplay  = $dateNB !== '' ? $dateNB : '—';

/* ---- Perioda teksta sagatavošana zem kopsummas ---- */
$periodText = '';
if (!empty($inv['inv_period']) && is_array($inv['inv_period'])) {
  $p = $inv['inv_period'];
  if (($p['mode'] ?? '') === 'date' && !empty($p['date'])) {
    $periodText = 'laikā ' . fmt_date_nb($p['date']);
  } elseif (($p['mode'] ?? '') === 'range' && !empty($p['from']) && !empty($p['to'])) {
    $periodText = 'laika periodā no ' . fmt_date_nb($p['from']) . ' līdz ' . fmt_date_nb($p['to']);
  }
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
<meta charset="UTF-8">
<title>Rēķins <?= h($invNoDisplay) ?></title>
<style>
  @page { margin: 18mm 15mm 25mm 15mm; }
  body{font-family:DejaVu Sans,Arial,Helvetica,sans-serif;font-size:10pt;color:#111; line-height:1.35;}
  h1,h2,h3{margin:0;}
  table{border-collapse:collapse;}
  .header {width:100%; margin-bottom:14pt;}
  .header td{vertical-align:top;}
  hr{border:none;border-top:1px solid #ccc;margin:12pt 0;}
  .right{text-align:right;}
  .nowrap{white-space:nowrap;}

  .kv{width:100%;}
  .kv td{padding:2pt 0; vertical-align:top;}
  .kv .lbl{width:110pt; color:#555; white-space:nowrap; padding-right:6pt;}
  .kv .val{ }

  .tbl{width:100%; margin-top:12pt; border:1px solid #ccc;}
  .tbl th,.tbl td{border:1px solid #ccc; padding:6pt;}
  .tbl th{background:#f6f7f9; text-align:left;}

  .totals{width:45%; margin-left:auto; margin-top:10pt;}
  .totals td{padding:3pt 0; vertical-align:bottom;}
  .totals .lbl{white-space:nowrap; color:#333;}
  .totals .line{border-bottom:0.7pt dotted #999; width:100%;}
  .totals .amt{text-align:right; white-space:nowrap;}

  .titlebar td{ vertical-align:bottom; }

  .period-note{
    margin-top:25px;
    font-size:10pt;
    color:#111;
  }

  .footer{
    position: fixed;
    left: 0; right: 0; bottom: 6mm;
    text-align: center;
    font-size: 8.5pt;
    color: #555;
  }
</style>
</head>
<body>

<!-- Galvene -->
<table class="header">
  <tr>
    <td style="width:40%;"><h1>Aur☼ora</h1></td>
    <td style="width:60%; text-align:right;">
      Elīna Pole<br>
      Per. kods: xxxxxx-xxxxxx<br>
      Ikšķiles iela 2-3D, Ogre, LV-5001<br>
      (+371) 12345678<br>
      Konta Nr.: <?= h($BANK_IBAN_NB) ?>
    </td>
  </tr>
</table>

<hr>

<!-- Virsraksts + datums -->
<table class="titlebar" style="width:100%; margin-top:6pt;">
  <tr>
    <td><h3>Rēķins sagatavots samaksai ar pārskaitījumu</h3></td>
    <td class="right"><h3>Rēķina Nr.: <span class="nowrap"><?= h($invNoDisplay) ?></span></h3></td>
  </tr>
  <tr>
    <td></td>
    <td class="right">Datums: <span class="nowrap"><?= h($dateDisplay) ?></span></td>
  </tr>
</table>

<!-- Maksātājs -->
<div style="margin-top:10pt; font-weight:600;">Maksātājs</div>
<table class="kv">
  <?php foreach ($payerRows as [$label,$value]): ?>
    <tr>
      <?php if ($label!==''): ?>
        <td class="lbl"><?= h($label) ?>:</td>
        <td class="val"><?= h($value) ?></td>
      <?php else: ?>
        <td colspan="2" class="val"><?= h($value) ?></td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
</table>

<!-- Pakalpojumi -->
<table class="tbl">
  <thead>
    <tr>
      <th>Nosaukums</th>
      <th style="width:80px;" class="right">Daudz.</th>
      <th style="width:110px;" class="right">Cena (€)</th>
      <th style="width:110px;" class="right">Summa (€)</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($items): foreach ($items as $r): ?>
      <tr>
        <td><?= h($r['name']) ?></td>
        <td class="right"><?= money($r['qty']) ?></td>
        <td class="right"><?= money($r['price']) ?></td>
        <td class="right"><?= money($r['total']) ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="4">Nav pakalpojumu rindu.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- Kopsummas -->
<table class="totals">
  <tr>
    <td class="lbl">Starpsumma:</td>
    <td class="line"></td>
    <td class="amt"><?= money($subtotal) ?> €</td>
  </tr>
  <tr>
    <td class="lbl">PVN<?= $vatRate>0 ? ' ('.(int)round($vatRate*100).'%)' : ' 0%'; ?>:</td>
    <td class="line"></td>
    <td class="amt"><?= money($vat) ?> €</td>
  </tr>
  <tr>
    <td class="lbl"><strong>Kopā:</strong></td>
    <td class="line"></td>
    <td class="amt"><strong><?= money($total) ?> €</strong></td>
  </tr>
</table>

<?php if ($periodText !== ''): ?>
  <div class="period-note">
    Rēķins sagatavots par pakalpojumiem, kas sniegti <?= h($periodText) ?>.
  </div>
<?php endif; ?>

<!-- Smalkā druka — kājene pie apakšas -->
<div class="footer">Rēķins ir sagatavots elektroniski un derīgs bez paraksta.</div>

</body>
</html>
