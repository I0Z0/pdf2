<?php
// includes/generate_invoice.php
declare(strict_types=1);

/**
 * Drop-in: atjaunots tā, lai:
 * - edit=1 atgriež rēķinu uz "Aktīvajiem darbiem" BEZ rēķina numura maiņas.
 * - action=finalize pārvieto rēķinu uz arhīvu.
 * - Pakalpojumi no rēķina (items/services) tiek ielikti darba services.
 */

$dataDir          = __DIR__ . '/../dati/';
$jobsDir          = $dataDir . 'jobs/';
$jobsArchivedDir  = $dataDir . 'jobs_invoiced/'; // ja lieto arhivētos darbus
$invoicesDir      = $dataDir . 'invoices/';
$archiveDir       = $dataDir . 'archive/';      // rēķinu gala arhīvs

foreach ([$jobsDir, $jobsArchivedDir, $invoicesDir, $archiveDir] as $d) {
    if (!is_dir($d)) mkdir($d, 0777, true);
}

// ---- helpers ----------------------------------------------------
function readj(string $file, $def = []) {
    if (!is_file($file)) return $def;
    $s = file_get_contents($file);
    $j = json_decode($s, true);
    return is_array($j) ? $j : $def;
}
function writej(string $file, $data): bool {
    $tmp = $file . '.tmp';
    $ok  = file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    return $ok !== false && @rename($tmp, $file);
}
function items_to_services(array $items): array {
    $out = [];
    foreach ($items as $r) {
        $name  = trim((string)($r['name']  ?? ''));
        $qty   = (float)($r['qty']   ?? 0);
        $price = (float)($r['price'] ?? 0);
        if ($name !== '' && $qty > 0 && $price >= 0) {
            $out[] = ['name' => $name, 'qty' => $qty, 'price' => $price];
        }
    }
    return $out;
}
function legacy_services_to_services(array $services): array {
    // vecais formāts: services[] ar price[, qty]
    $out = [];
    foreach ($services as $r) {
        $name  = trim((string)($r['name']  ?? ''));
        $qty   = (float)($r['qty']   ?? 1);
        $price = (float)($r['price'] ?? 0);
        if ($name !== '' && $qty > 0 && $price >= 0) {
            $out[] = ['name' => $name, 'qty' => $qty, 'price' => $price];
        }
    }
    return $out;
}
function job_path_active($id){ global $jobsDir;         return $jobsDir . preg_replace('~[^0-9A-Za-z_\-]~','_', (string)$id) . '.json'; }
function job_path_archiv($id){ global $jobsArchivedDir; return $jobsArchivedDir . preg_replace('~[^0-9A-Za-z_\-]~','_', (string)$id) . '.json'; }
function invoice_path($id){ global $invoicesDir;        return $invoicesDir . preg_replace('~[^0-9A-Za-z_\-]~','_', (string)$id) . '.json'; }
function invoice_archive_path($id){ global $archiveDir; return $archiveDir . preg_replace('~[^0-9A-Za-z_\-]~','_', (string)$id) . '.json'; }

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

// ---- params -----------------------------------------------------
$id   = $_GET['id']    ?? '';
$edit = $_GET['edit']  ?? '';
$act  = $_GET['action']?? '';

if ($id === '') {
    exit('Trūkst id parametra!');
}

/* ================================================================
   A) LABOT (edit=1): rēķina rindas -> atpakaļ uz darbu
   - Nemainām rēķina numuru: talonējam to job.invoice_draft_id
   ================================================================ */
if ($edit === '1') {
    // Meklē rēķinu aktīvajos vai arhīvā
    $src = invoice_path($id);
    if (!is_file($src)) {
        $src = invoice_archive_path($id);
    }
    if (!is_file($src)) {
        exit('Rēķina dati nav atrasti.');
    }

    $inv = readj($src, null);
    if (!is_array($inv)) {
        exit('Neizdevās nolasīt rēķina failu.');
    }

    $jobId = $inv['job_id'] ?? null;
    if (!$jobId) {
        exit('Rēķinam nav piesaistīts job_id, nevar atgriezt uz darbiem.');
    }

    // Nolasām esošo job (aktīvs vai arhivēts)
    $job = readj(job_path_active($jobId), null);
    if (!is_array($job)) {
        $job = readj(job_path_archiv($jobId), null);
    }
    // Ja job nav, veidojam minimālu no rēķina
    if (!is_array($job)) {
        $job = [
            'id'         => $jobId,
            'client_id'  => $inv['client_id'] ?? null,
            'title'      => $inv['title'] ?? '',
            'comment'    => $inv['comment'] ?? null,
            'services'   => [],
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
    }

    // Pārvēršam rēķina rindas uz job.services
    $services = [];
    if (!empty($inv['items']) && is_array($inv['items'])) {
        $services = items_to_services($inv['items']);
    } elseif (!empty($inv['services']) && is_array($inv['services'])) {
        $services = legacy_services_to_services($inv['services']); // vecais formāts
    }
    $job['services'] = $services;

    // Stabils rēķina Nr.: ieliekam kā draft atsauci (lai create_invoice_from_job to pārizmantotu)
    unset($job['invoice_id']);
    $job['invoice_draft_id'] = $inv['id']; // piem., INV-00007
    $job['updated_at'] = date('c');

    // Saglabājam job aktīvajā mapē
    if (!writej(job_path_active($jobId), $job)) {
        exit('Kļūda atgriežot darbu.');
    }

    // Ja bija arhīvā — iztīrām
    @unlink(job_path_archiv($jobId));

    // Rēķina failu izņemam (tu vēlies rediģēt jobā), bet numurs saglabāts jobā kā draft
    @unlink($src);

    // Atpakaļ uz job paneli
    header('Location: ../dashboard.php?page=jobs');
    exit;
}

/* ================================================================
   B) FINALIZE (action=finalize): pārvieto rēķinu uz arhīvu
   ================================================================ */
if ($act === 'finalize') {
    $src = invoice_path($id);
    if (!is_file($src)) {
        exit('Rēķins nav atrasts vai jau arhivēts.');
    }

    $data = readj($src, null);
    if (!is_array($data)) {
        exit('Kļūda nolasot rēķina datus.');
    }

    $counterFile = $dataDir . 'invoice_counter.json';
    if (empty($data['invoice_no'])) {
        $newNo = next_invoice_no($counterFile);
        $data['invoice_no'] = $newNo;
        $data['id'] = $newNo;
        $data['number'] = $newNo;
        $data['date'] = date('Y-m-d');
    }
    $data['invoice_time'] = $data['invoice_time'] ?? date('Y-m-d H:i:s');

    $dest = invoice_archive_path($data['id']);
    if (!writej($dest, $data)) {
        exit('Arhivēšana neizdevās.');
    }

    @unlink($src);

    // Atjaunojam arhivētā darba ierakstu ar gala rēķina Nr.
    if (!empty($data['job_id'])) {
        $jobFile = job_path_archiv($data['job_id']);
        if (is_file($jobFile)) {
            $jobData = readj($jobFile, null);
            if (is_array($jobData)) {
                $jobData['invoice_id'] = $data['id'];
                writej($jobFile, $jobData);
            }
        }
    }

    header('Location: ../dashboard.php?page=invoices');
    exit;
}

// Ja nekas neizpildījās:
exit('Nederīgs pieprasījums.');
