<?php
// includes/process_jobs.php
header('Content-Type: application/json; charset=utf-8');

/**
 * Stabils ID modelis:
 *  - Job ID = paaugstinošs skaitlis (job_counter.json)
 *  - Invoice ID/Number = INV-{job.id ar nullēm}, piem. job 12 => INV-000012
 *    Rezervējam jau create_job brīdī -> job.invoice_draft_id
 *    Nekad nelaižam caur invoice_counter (to varat izdzēst vai ignorēt)
 */

$dataDir          = __DIR__ . '/../dati/';
$jobsDir          = $dataDir . 'jobs/';
$jobsArchivedDir  = $dataDir . 'jobs_invoiced/';
$invoicesDir      = $dataDir . 'invoices/';
$jobCounter       = $dataDir . 'job_counter.json';

// PVN likme (vajag 21% => 0.21)
const VAT_RATE = 0.00;

// Ensure dirs
foreach ([$dataDir, $jobsDir, $jobsArchivedDir, $invoicesDir] as $d) {
  if (!is_dir($d)) mkdir($d, 0777, true);
}

/* ---------------- Helpers ---------------- */
function out($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function readj($file,$def=[]){ if(!is_file($file)) return $def; $s=@file_get_contents($file); $j=json_decode($s,true); return is_array($j)?$j:$def; }
function writej($file,$data){
  $tmp=$file.'.tmp';
  $ok=@file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
  return $ok!==false && @rename($tmp,$file);
}
function nextJobId($file){ $c=readj($file,['last'=>0]); $c['last']=(int)$c['last']+1; writej($file,$c); return $c['last']; }

function jobPath($id){ global $jobsDir; return $jobsDir . preg_replace('~[^0-9A-Za-z_\-]~','_', (string)$id) . '.json'; }
function jobArchivedPath($id){ global $jobsArchivedDir; return $jobsArchivedDir . preg_replace('~[^0-9A-Za-z_\-]~','_', (string)$id) . '.json'; }
function invPath($id){ global $invoicesDir; return $invoicesDir . preg_replace('~[^0-9A-Za-z_\-]~','_', (string)$id) . '.json'; }

/** Deterministisks rēķina Nr. no job.id */
function invIdForJob($jobId, $pad=6){
  $n = (string)$jobId;
  return 'INV-' . str_pad($n, $pad, '0', STR_PAD_LEFT);
}

function loadJob($id){
  $p=jobPath($id); if(is_file($p)){ $j=readj($p,null); if(is_array($j)) return $j; }
  $ap=jobArchivedPath($id); if(is_file($ap)){ $j=readj($ap,null); if(is_array($j)) return $j; }
  return null;
}
function saveJobActive($job){ if(!isset($job['id'])) return false; $job['updated_at']=date('c'); return writej(jobPath($job['id']),$job); }
function saveJobArchived($job){ if(!isset($job['id'])) return false; $job['updated_at']=date('c'); return writej(jobArchivedPath($job['id']),$job); }

function sanitizeServices($list){
  $out=[]; if(!is_array($list)) return $out;
  foreach($list as $r){
    $name=trim((string)($r['name']??'')); $qty=(float)($r['qty']??0); $price=(float)($r['price']??0);
    if($name!=='' && $qty>0 && $price>=0){ $out[]=['name'=>$name,'qty'=>$qty,'price'=>$price]; }
  } return $out;
}
function makeItems(array $services): array {
  $items=[]; foreach (sanitizeServices($services) as $r) {
    $r['total']=round($r['qty']*$r['price'],2);
    $items[]=$r;
  } return $items;
}

/* ---------------- Actions ---------------- */
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
  /* Tikai aktīvie darbi (bez tiem, kam jau ir invoice_id) */
  case 'list_jobs': {
    $files = glob($jobsDir.'*.json') ?: [];
    $jobs  = [];
    foreach ($files as $f) {
      $j=readj($f,null);
      if (!is_array($j) || !isset($j['id'])) continue;
      if (!empty($j['invoice_id'])) continue; // jau nosūtīts uz rēķinu
      $jobs[] = [
        'id'         => $j['id'],
        'client_id'  => $j['client_id'] ?? null,
        'title'      => $j['title'] ?? null,
        'comment'    => $j['comment'] ?? null,
        'created_at' => $j['created_at'] ?? null
      ];
    }
    usort($jobs, fn($a,$b)=>strcmp($b['created_at']??'', $a['created_at']??''));
    out(['status'=>'ok','jobs'=>$jobs]);
  }

  case 'get_job': {
    $jobId = $_POST['job_id'] ?? $_GET['job_id'] ?? '';
    if ($jobId==='') out(['status'=>'error','message'=>'Trūkst job_id.']);
    $j = loadJob($jobId);
    if (!$j) out(['status'=>'error','message'=>'Darbs nav atrasts.']);
    // Sanitizējam vēsturiskos laukus, ja bija
    unset($j['status'], $j['start_time'], $j['end_time'], $j['car_id'], $j['lift'], $j['odo'], $j['make'], $j['model'], $j['plate_no'], $j['vin']);
    out(['status'=>'ok','job'=>$j]);
  }

  case 'create_job': {
    $clientId = trim((string)($_POST['client_id'] ?? ''));
    $comment  = trim((string)($_POST['comment']   ?? ''));
    if ($clientId==='') out(['status'=>'error','message'=>'Nav norādīts klients.']);

    $id  = nextJobId($jobCounter);
    $now = date('c');

    $job = [
      'id'               => $id,
      'client_id'        => $clientId,
      'title'            => '',
      'comment'          => $comment ?: null,
      'services'         => [],
      'invoice_draft_id' => invIdForJob($id), // ← rezervējam stabilu rēķina Nr. jau tagad
      'created_at'       => $now,
      'updated_at'       => $now
    ];
    if (!saveJobActive($job)) out(['status'=>'error','message'=>'Neizdevās saglabāt darbu.']);
    out(['status'=>'ok','job'=>$job]);
  }

case 'delete_job': {
  $jobId = $_POST['job_id'] ?? '';
  if ($jobId === '') out(['status'=>'error','message'=>'Trūkst job_id.']);

  $j = loadJob($jobId);
  if (!$j) {
    // jau nav – uzskatām par ok
    @unlink(jobPath($jobId));
    @unlink(jobArchivedPath($jobId));
    out(['status'=>'ok']);
  }

  // Drošība: darbus, kas jau nosūtīti uz rēķinu, nedzēšam
  if (!empty($j['invoice_id'])) {
    out(['status'=>'error','message'=>'Darbu nevar dzēst – tas jau nosūtīts uz rēķinu.']);
  }

  $ok = true;
  if (is_file(jobPath($jobId))) {
    $ok = @unlink(jobPath($jobId));
  } elseif (is_file(jobArchivedPath($jobId))) {
    // reti, bet ja nejauši nonācis arhīvā bez rēķina – izdzēšam arī tur
    $ok = @unlink(jobArchivedPath($jobId));
  }

  if (!$ok) out(['status'=>'error','message'=>'Neizdevās dzēst darbu.']);
  out(['status'=>'ok']);
}


  case 'upsert_services': {
    $jobId = $_POST['job_id'] ?? '';
    if ($jobId==='') out(['status'=>'error','message'=>'Trūkst job_id.']);
    $j = loadJob($jobId);
    if (!$j) out(['status'=>'error','message'=>'Darbs nav atrasts.']);

    $list = json_decode($_POST['services_json'] ?? '[]', true);
    $j['services'] = sanitizeServices($list);

    $ok = is_file(jobPath($j['id'])) ? saveJobActive($j) : saveJobArchived($j);
    if (!$ok) out(['status'=>'error','message'=>'Neizdevās saglabāt pakalpojumus.']);
    out(['status'=>'ok','job'=>$j]);
  }

  /* Izveido/atjauno rēķinu no darba (IDEMPotents, stabils Nr.) */
  case 'create_invoice_from_job': {
    $jobId = $_POST['job_id'] ?? '';
    if ($jobId==='') out(['status'=>'error','message'=>'Trūkst job_id.']);
    $j = loadJob($jobId);
    if (!$j) out(['status'=>'error','message'=>'Darbs nav atrasts.']);

    // Izlemjam ID vienreiz un uz visiem laikiem
    $invId = $j['invoice_id']
          ?? $j['invoice_draft_id']
          ?? invIdForJob($j['id']);

    // Ņemam rindas no UI, ja atnākušas; citādi no darba
    $incoming = $_POST['services_json'] ?? null;
    $items = $incoming !== null
      ? makeItems(json_decode($incoming, true) ?: [])
      : makeItems($j['services'] ?? []);
    if (!$items) out(['status'=>'error','message'=>'Nav neviena pakalpojuma rindas.']);

    // Aprēķini
    $subtotal = array_reduce($items, fn($s,$r)=>$s + (float)$r['total'], 0.0);
    $vat      = round($subtotal * VAT_RATE, 2);
    $total    = round($subtotal + $vat, 2);

    // Ja rēķins jau eksistē – atjaunojam; citādi izveidojam
    $invoice = readj(invPath($invId), []);
    $invoice['id']         = $invId;
    $invoice['number']     = $invoice['number']     ?? null; // rēķina Nr. piešķir arhivējot
    $invoice['date']       = $invoice['date']       ?? null; // datumu piešķir arhivējot
    $invoice['created_at'] = $invoice['created_at'] ?? date('c');
    $invoice['job_id']     = $j['id'];
    $invoice['client_id']  = $j['client_id'];
    $invoice['items']      = $items;
    $invoice['subtotal']   = round($subtotal,2);
    $invoice['vat_rate']   = VAT_RATE;
    $invoice['vat']        = $vat;
    $invoice['total']      = $total;
    $invoice['status']     = 'issued'; // vai 'draft', ja tā vēlies

    if (!writej(invPath($invId), $invoice)) out(['status'=>'error','message'=>'Neizdevās saglabāt rēķinu.']);

    // Atzīmējam darbu kā nosūtītu uz rēķinu (un pārvietojam uz arhīvu)
    $j['invoice_id'] = $invId;
    unset($j['invoice_draft_id']);
    $j['archived_at'] = date('c');
    saveJobArchived($j);
    @unlink(jobPath($j['id']));

    out(['status'=>'ok','invoice'=>$invoice]);
  }

  /* Atgriezt rēķinu atpakaļ uz darbiem (Labot), saglabājot to pašu rēķina Nr. */
  case 'return_job_from_invoice': {
    $invoiceId = $_POST['invoice_id'] ?? $_GET['invoice_id'] ?? '';
    if ($invoiceId==='') out(['status'=>'error','message'=>'Trūkst invoice_id.']);
    $inv = readj(invPath($invoiceId), null);
    if (!$inv) out(['status'=>'error','message'=>'Rēķins nav atrasts.']);

    $jobId = $inv['job_id'] ?? null;
    if (!$jobId) out(['status'=>'error','message'=>'Rēķinam nav piesaistīts job_id.']);

    // Iegūstam/atjaunojam job
    $j = loadJob($jobId);
    if (!$j) {
      $j = [
        'id'=>$jobId,'client_id'=>$inv['client_id']??null,'title'=>'','comment'=>null,
        'services'=>[],'created_at'=>date('c'),'updated_at'=>date('c')
      ];
    }

    // Rindas atpakaļ darbā
    $services=[];
    foreach (($inv['items'] ?? []) as $r) {
      $name=trim((string)($r['name']??'')); $qty=(float)($r['qty']??0); $price=(float)($r['price']??0);
      if ($name!=='' && $qty>0 && $price>=0) $services[]=['name'=>$name,'qty'=>$qty,'price'=>$price];
    }
    $j['services'] = $services;

    // Stabils rēķina Nr. saglabājas kā draft
    unset($j['invoice_id']);
    $j['invoice_draft_id'] = $invoiceId;

    if (!saveJobActive($j)) out(['status'=>'error','message'=>'Neizdevās atgriezt darbu.']);

    // (Opcija) atstāj rēķinu kā "draft" vai izdzēs no aktīvajiem — izvēlies vienu:
    // 1) Atstāt failu un atzīmēt kā draft:
    $inv['status'] = 'draft';
    writej(invPath($invoiceId), $inv);
    // 2) Ja gribi, lai rēķins vairs nerādās “invoices” skatā, vari arī to izdzēst:
    // @unlink(invPath($invoiceId));

    // iztīrām arhīva job, ja bija
    @unlink(jobArchivedPath($j['id']));

    out(['status'=>'ok','job'=>$j]);
  }

  default:
    out(['status'=>'error','message'=>'Neatbalstīta darbība.']);
}
