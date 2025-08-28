<?php
/* ---------- Ceļi ---------- */
$clientDir = __DIR__ . '/../dati/clients/';
if (!is_dir($clientDir)) mkdir($clientDir, 0777, true);

/* ---------- POST tikai ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?page=registration&error=1'); exit;
}
$formType = $_POST['form_type'] ?? '';

/* ---------- Obligāto lauku tabula ---------- */
$required = [
    'person'  => ['first_name','last_name','phone'],
    'company' => ['company_name','registration_no','phone'],
];

/* ---------- Validācija ---------- */
if (!in_array($formType, ['person','company'], true)) {
    header('Location: dashboard.php?page=registration&error=1'); exit;
}
foreach ($required[$formType] as $fld) {
    if (trim((string)($_POST[$fld] ?? '')) === '') {
        header('Location: dashboard.php?page=registration&error=1'); exit;
    }
}

/* ---------- Palīgfunkcijas ---------- */
function maxIdFromGlob(string $pattern, string $delimiter='-'): int {
    $max = 0;
    foreach (glob($pattern) as $f) {
        $parts = explode($delimiter, basename($f, '.json'));
        $num   = (int) end($parts);
        if ($num > $max) $max = $num;
    }
    return $max;
}
function nextClientId(string $clientDir): int {
    $max = 0;
    foreach (['fiz-*.json', 'jur-*.json'] as $pat) {
        $m = maxIdFromGlob($clientDir . $pat);
        if ($m > $max) $max = $m;
    }
    return $max + 1;
}

/* ---------- Saglabāšana ---------- */
$ok = true;

if ($formType === 'person') {
    $newId  = nextClientId($clientDir);
    $prefix = "fiz-$newId";

    // normalizē IBAN: uppercase + bez atstarpēm
    $accountNo = strtoupper(str_replace(' ', '', $_POST['account_no'] ?? ''));

    // SECĪBA = kā formā
    $client = [];
    $client['id']            = $newId;
    $client['kind']          = 'physical';
    $client['first_name']    = $_POST['first_name'];
    $client['last_name']     = $_POST['last_name'];
    $client['personal_code'] = $_POST['personal_code'] ?? '';
    $client['bank']          = $_POST['bank'] ?? '';
    $client['account_no']    = $accountNo;
    $client['phone']         = $_POST['phone'];
    $client['address']       = $_POST['address'] ?? '';
    $client['email']         = $_POST['email'] ?? '';
    $client['comment']       = $_POST['comment'] ?? '';

    $ok = (bool) file_put_contents(
        $clientDir . $prefix . '.json',
        json_encode($client, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}
else /* company */ {
    $newId  = nextClientId($clientDir);
    $prefix = "jur-$newId";

    $accountNo = strtoupper(str_replace(' ', '', $_POST['account_no'] ?? ''));

    // SECĪBA = kā formā
    $client = [];
    $client['id']               = $newId;
    $client['kind']             = 'legal';
    $client['company_name']     = $_POST['company_name'];
    $client['registration_no']  = $_POST['registration_no'];
    $client['vat_no']           = $_POST['vat_no'] ?? '';
    $client['bank']             = $_POST['bank'] ?? '';
    $client['account_no']       = $accountNo;
    $client['address']          = $_POST['address'] ?? '';
    $client['phone']            = $_POST['phone'];
    $client['email']            = $_POST['email'] ?? '';
    $client['comment']          = $_POST['comment'] ?? '';

    $ok = (bool) file_put_contents(
        $clientDir . $prefix . '.json',
        json_encode($client, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

/* ---------- Rezultāts ---------- */
if (!$ok) {
    header('Location: dashboard.php?page=registration&error=1');
} else {
    header('Location: dashboard.php?page=registration&success=1');
}
exit;
