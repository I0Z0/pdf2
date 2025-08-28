<?php
// includes/render_invoice_dompdf.php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$dataDir   = __DIR__ . '/../dati/';
$invoiceId = $_GET['id']     ?? null;
$origInvoiceId = $invoiceId; // sākotnējais ID (pirms arhivēšanas piešķir gala Nr.)
$action    = $_GET['action'] ?? 'preview';
$ajax      = !empty($_GET['ajax']);

if (!$invoiceId) {
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'error'=>'Trūkst id parametra!']);
        exit;
    }
    exit('Trūkst id parametra!');
}

/* ---------- Palīgi ---------- */
function flush_all_output_buffers(): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}
function json_fail(string $msg): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Nākamais rēķina Nr. no skaitītāja */
function next_invoice_no(string $counterFile): string {
    $c = ['last'=>0];
    if (is_file($counterFile)) {
        $j = json_decode((string)file_get_contents($counterFile), true);
        if (is_array($j) && isset($j['last'])) {
            $c['last'] = (int)$j['last'];
        }
    }
    $c['last']++;
    file_put_contents($counterFile, json_encode($c, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    return 'INV-' . str_pad((string)$c['last'], 6, '0', STR_PAD_LEFT);
}

/* ---------- Ielasa rēķinu (vispirms aktīvos, tad arhīvu) ---------- */
$invoiceFile = $dataDir . 'invoices/' . $invoiceId . '.json';
if (!is_file($invoiceFile)) {
    $invoiceFile = $dataDir . 'archive/' . $invoiceId . '.json';
    if (!is_file($invoiceFile)) {
        $ajax ? json_fail('Rēķins nav atrasts!') : exit('Rēķins nav atrasts!');
    }
}
$inv = json_decode((string)file_get_contents($invoiceFile), true);
if (!is_array($inv)) {
    $ajax ? json_fail('Nevar nolasīt rēķina datus!') : exit('Nevar nolasīt rēķina datus!');
}

$counterFile = $dataDir . 'invoice_counter.json';
if ($action === 'finalize' && empty($inv['invoice_no'])) {
    $invoiceId = $inv['invoice_no'] = $inv['id'] = $inv['number'] = next_invoice_no($counterFile);
    $inv['date'] = date('Y-m-d');
    $inv['invoice_time'] = date('Y-m-d H:i:s');
}

/* ---------- Klienta sanitizācija (bez PVN/tālr./e-pasta) ---------- */
function sanitize_client_for_invoice(array $c): array {
    $kind = strtolower((string)($c['kind'] ?? ''));
    if ($kind !== 'legal' && $kind !== 'physical') {
        $kind = !empty($c['company_name']) ? 'legal' : 'physical';
    }
    $out = [
        'kind'            => $kind,
        'company_name'    => $c['company_name']    ?? '',
        'registration_no' => $c['registration_no'] ?? ($c['reg_no'] ?? ''),
        'first_name'      => $c['first_name']      ?? '',
        'last_name'       => $c['last_name']       ?? '',
        'personal_code'   => $c['personal_code']   ?? '',
        'address'         => $c['address']         ?? '',
        'bank'            => $c['bank']            ?? '',
        'account_no'      => $c['account_no']      ?? '',
    ];
    if ($out['account_no'] !== '') {
        $out['account_no'] = strtoupper(preg_replace('/\s+/', '', $out['account_no']));
    }
    return $out;
}

/* ---------- Ielasa klientu pēc client_id ---------- */
$client = [];
if (!empty($inv['client_id'])) {
    $candidates = glob($dataDir . 'clients/*-' . $inv['client_id'] . '.json');
    if ($candidates) {
        $raw = json_decode((string)file_get_contents($candidates[0]), true);
        if (is_array($raw)) $client = $raw;
    }
}
$client = sanitize_client_for_invoice($client);

/* ---------- HTML no veidnes ---------- */
ob_start();
include __DIR__ . '/../views/templates/invoice_template.php'; // izmanto $inv un $client
$html = ob_get_clean();

/* ---------- Dompdf ---------- */
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');
$dompdf->setBasePath(__DIR__ . '/../assets/css/');
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->render();

/* ===========================================================
   FINALIZE: arhivēšana + (AJAX => JSON) / (parasts => lejupielāde)
   =========================================================== */
if ($action === 'finalize') {
    // Arhivējam TIKAI, ja rēķins ir AKTĪVAJOS
    $activeSrc = $dataDir . 'invoices/' . $origInvoiceId . '.json';
    if (!is_file($activeSrc)) {
        $ajax ? json_fail('Rēķins nav aktīvs vai nav atrasts.') : exit('Rēķins nav aktīvs vai nav atrasts.');
    }

    // Jābūt iestatītam inv_period (viena diena vai periods)
    $period = $inv['inv_period'] ?? null;
    $okPeriod = is_array($period) && (
        (($period['mode'] ?? '') === 'date'  && !empty($period['date'])) ||
        (($period['mode'] ?? '') === 'range' && !empty($period['from']) && !empty($period['to']))
    );
    if (!$okPeriod) {
        $ajax ? json_fail('Pirms arhivēšanas iestatiet pakalpojuma periodu.') : exit('Pirms arhivēšanas iestatiet pakalpojuma periodu.');
    }

    // Saglabā PDF publiskajā mapē
    $pdfOutput    = $dompdf->output();
    $publicPdfDir = __DIR__ . '/../assets/invoices/';
    if (!is_dir($publicPdfDir)) mkdir($publicPdfDir, 0777, true);
    $pdfFileName  = $invoiceId . '.pdf';
    file_put_contents($publicPdfDir . $pdfFileName, $pdfOutput);

    // Pārvieto JSON no invoices/ uz archive/ + ieliek pdf_url
    $inv['id']      = $invoiceId;
    $inv['number']  = $invoiceId;
    $inv['pdf_url'] = 'assets/invoices/' . $pdfFileName;
    file_put_contents($dataDir . 'archive/' . $invoiceId . '.json', json_encode($inv, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    @unlink($activeSrc);

    // Atjaunojam arhivētā darba ierakstu ar gala rēķina Nr.
    if (!empty($inv['job_id'])) {
        $jobFile = $dataDir . 'jobs_invoiced/' . $inv['job_id'] . '.json';
        if (is_file($jobFile)) {
            $jobData = json_decode((string)file_get_contents($jobFile), true);
            if (is_array($jobData)) {
                $jobData['invoice_id'] = $invoiceId;
                file_put_contents($jobFile, json_encode($jobData, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            }
        }
    }

    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>true, 'id'=>$invoiceId, 'pdf_url'=>$inv['pdf_url']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Ne-AJAX finalize => atdodam lejupielādi
    flush_all_output_buffers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Rekins_' . $invoiceId . '.pdf"');
    echo $pdfOutput;
    exit;
}

/* ===========================================================
   PREVIEW: droši rādām inline PDF (vai ?debug=1 parādīs HTML)
   =========================================================== */
if ($action === 'preview') {
    if (!empty($_GET['debug'])) {
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
    $pdfOutput = $dompdf->output();
    flush_all_output_buffers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Rekins_' . $invoiceId . '.pdf"');
    echo $pdfOutput;
    exit;
}

/* ---------- Noklusējums ---------- */
flush_all_output_buffers();
header('Content-Type: text/plain; charset=utf-8');
echo "Nederīgs action.";
