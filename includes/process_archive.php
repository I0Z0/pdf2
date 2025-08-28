<?php
// process_archive.php – API darbības klientu rediģēšanai (arhīva panelī)
header('Content-Type: application/json; charset=utf-8');
$dataDir    = __DIR__ . '/../dati/';
$clientsDir = $dataDir . 'clients/';

function findClientFile($id) {
    foreach (glob(__DIR__ . '/../dati/clients/*.json') as $f) {
        $d = json_decode(file_get_contents($f), true);
        if ($d && isset($d['id']) && $d['id'] == $id) {
            return $f;
        }
    }
    return false;
}

// --- DZĒST KLIENTU UN VISUS SAISTĪTOS DATUMS ---
if ($_POST['action'] === 'delete_client') {
    $id = $_POST['id'] ?? '';
    if (!$id) {
        echo json_encode(['success'=>false, 'error'=>'Nav norādīts klienta ID']);
        exit;
    }
    // Dzēš klienta failu
    foreach (glob(__DIR__ . '/../dati/clients/*-' . $id . '.json') as $f) {
        unlink($f);
    }
    // Dzēš klienta auto failus (ja tādi ir)
    foreach (glob(__DIR__ . '/../dati/cars/*-' . $id . '-*.json') as $f) {
        unlink($f);
    }
    // Dzēš aktīvos darbus, rēķinus un arhīva datus, kas saistīti ar šo klientu
    foreach (glob(__DIR__ . '/../dati/jobs/*.json') as $f) {
        $d = json_decode(file_get_contents($f), true);
        if ($d && isset($d['client_id']) && $d['client_id'] == $id) {
            unlink($f);
        }
    }
    foreach (glob(__DIR__ . '/../dati/invoices/*.json') as $f) {
        $d = json_decode(file_get_contents($f), true);
        if ($d && isset($d['client_id']) && $d['client_id'] == $id) {
            unlink($f);
        }
    }
    foreach (glob(__DIR__ . '/../dati/archive/*.json') as $f) {
        $d = json_decode(file_get_contents($f), true);
        if ($d && isset($d['client_id']) && $d['client_id'] == $id) {
            // Dzēš arī PDF failu, ja tāds ir
            if (!empty($d['pdf_url'])) {
                $pdfFile = __DIR__ . '/../' . $d['pdf_url'];
                if (file_exists($pdfFile)) unlink($pdfFile);
            }
            unlink($f);
        }
    }
    echo json_encode(['success'=>true]);
    exit;
}

// --- REDIĢĒ KLIENTU ---
if ($_POST['action'] === 'edit_client') {
    $id = $_POST['id'] ?? '';
    $f  = findClientFile($id);
    if (!$f) {
        echo json_encode(['success'=>false, 'error'=>'Klients nav atrasts']);
        exit;
    }
    $c = json_decode(file_get_contents($f), true);
    // Atjaunina laukus atkarībā no veida (juridiska/fiziska persona)
    if (isset($c['kind']) && $c['kind'] === 'legal') {
        // Juridiska persona (uzņēmums)
        foreach (['company_name','registration_no','vat_no','bank','account_no',
                  'address','phone','email','comment'] as $k) {
            if (isset($_POST[$k])) {
                $c[$k] = $_POST[$k];
            }
        }
    } else {
        // Fiziska persona
        foreach (['first_name','last_name','personal_code','bank','account_no',
                  'address','phone','email','comment'] as $k) {
            if (isset($_POST[$k])) {
                $c[$k] = $_POST[$k];
            }
        }
    }
    file_put_contents($f, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success'=>true, 'client'=>$c]);
    exit;
}

// Ja pieprasītā darbība neatbilst nevienai no augstāk definētajām
echo json_encode(['success'=>false, 'error'=>'Neatbalstīta darbība']);
