<?php
// includes/invoice_actions.php
// Saglabā rēķina "inv_period" datus aktīvajā rēķinā (dati/invoices/<id>.json)
header('Content-Type: application/json; charset=utf-8');

$dataDir = __DIR__ . '/../dati/';

function bad($msg){ echo json_encode(['success'=>false, 'error'=>$msg]); exit; }
function ok($arr){ echo json_encode(['success'=>true] + $arr); exit; }
function readj($f){ if(!is_file($f)) return null; $j=json_decode(file_get_contents($f), true); return is_array($j)?$j:null; }
function writej($f,$d){ return file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) !== false; }
function valid_date($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); }

$action = $_POST['action'] ?? '';
if ($action !== 'save_period') bad('Neatbalstīta darbība');

$id = $_POST['id'] ?? '';
if ($id === '') bad('Trūkst rēķina ID');

$invFile = $dataDir . 'invoices/' . basename($id) . '.json'; // tikai aktīvajos
$inv = readj($invFile);
if (!$inv) bad('Rēķins nav atrasts (aktīvajos)');

$mode = $_POST['mode'] ?? '';
$period = null;

if ($mode === 'date') {
    $d = $_POST['date'] ?? '';
    if (!valid_date($d)) bad('Nederīgs datums');
    $period = ['mode'=>'date', 'date'=>$d];
} elseif ($mode === 'range') {
    $from = $_POST['from'] ?? '';
    $to   = $_POST['to']   ?? '';
    if (!valid_date($from) || !valid_date($to)) bad('Nederīgs periods');
    if ($from > $to) bad('Perioda robežas nav pareizas (no > līdz)');
    $period = ['mode'=>'range', 'from'=>$from, 'to'=>$to];
} else {
    bad('Norādiet režīmu (date / range)');
}

$inv['inv_period'] = $period;
if (!writej($invFile, $inv)) bad('Neizdevās saglabāt periodu');

ok(['inv_period'=>$period]);
