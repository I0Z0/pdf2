<?php
// includes/process_planner.php - API darbības plānotāja moduļa vajadzībām
header('Content-Type: application/json; charset=utf-8');

$dataDir     = __DIR__ . '/../dati/';
$eventsFile  = $dataDir . 'events.json';
$notesFile   = $dataDir . 'notes.json';
$archiveDir  = $dataDir . 'archive/';

if (!is_dir($dataDir)) {
  mkdir($dataDir, 0777, true);
}
if (!file_exists($eventsFile)) {
  file_put_contents($eventsFile, '[]');
}
if (!file_exists($notesFile)) {
  file_put_contents($notesFile, '[]');
}

function readj(string $file): array {
  $s = @file_get_contents($file);
  $j = json_decode($s, true);
  return is_array($j) ? $j : [];
}
function writej(string $file, array $data): bool {
  return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) !== false;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'addEvent': {
      $date  = trim($_POST['date']  ?? '');
      $endDate = trim($_POST['end_date'] ?? '');
      $title = trim($_POST['title'] ?? '');
      if ($date === '' || $title === '') {
        echo json_encode(['success'=>false,'error'=>'Trūkst datums vai nosaukums']);
        break;
      }
      if ($endDate !== '' && $endDate < $date) {
        $endDate = $date;
      }
      $start = trim($_POST['start'] ?? '');
      $end   = trim($_POST['end']   ?? '');
      $color = trim($_POST['color'] ?? '#000000');
    $events = readj($eventsFile);
    $id = 1;
    foreach ($events as $e) {
      if (($e['id'] ?? 0) >= $id) {
        $id = $e['id'] + 1;
      }
    }
      $new = ['id'=>$id,'date'=>$date,'title'=>$title,'color'=>$color];
      if ($endDate !== '') $new['end_date'] = $endDate;
      if ($start !== '') $new['start'] = $start;
      if ($end   !== '') $new['end']   = $end;
    $events[] = $new;
    writej($eventsFile, $events);
    echo json_encode(['success'=>true,'event'=>$new], JSON_UNESCAPED_UNICODE);
    break;
  }
  case 'deleteEvent': {
    $id = (int)($_POST['id'] ?? 0);
    $events = readj($eventsFile);
    $events = array_values(array_filter($events, fn($e)=>($e['id'] ?? 0) !== $id));
    writej($eventsFile, $events);
    echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
    break;
  }
  case 'addNote': {
    $text  = trim($_POST['text'] ?? '');
    if ($text === '') {
      echo json_encode(['success'=>false,'error'=>'Nav teksta']);
      break;
    }
    $color = trim($_POST['color'] ?? '#ffff88');
    $notes = readj($notesFile);
    $id = 1;
    foreach ($notes as $n) {
      if (($n['id'] ?? 0) >= $id) {
        $id = $n['id'] + 1;
      }
    }
    $new = ['id'=>$id,'text'=>$text,'color'=>$color];
    $notes[] = $new;
    writej($notesFile, $notes);
    echo json_encode(['success'=>true,'note'=>$new], JSON_UNESCAPED_UNICODE);
    break;
  }
  case 'deleteNote': {
    $id = (int)($_POST['id'] ?? 0);
    $notes = readj($notesFile);
    $notes = array_values(array_filter($notes, fn($n)=>($n['id'] ?? 0) !== $id));
    writej($notesFile, $notes);
    echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
    break;
  }
  case 'payInvoiceNo': {
    $invNo = trim($_POST['invoice_no'] ?? '');
    if ($invNo === '') {
      echo json_encode(['success'=>false,'error'=>'Nav rēķina Nr']);
      break;
    }
    $found = false;
    foreach (glob($archiveDir . '*.json') as $f) {
      $inv = json_decode(@file_get_contents($f), true);
      if (is_array($inv)) {
        $id = $inv['invoice_no'] ?? ($inv['id'] ?? '');
        if ($id === $invNo) {
          $inv['paid'] = true;
          file_put_contents($f, json_encode($inv, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
          $found = true;
          break;
        }
      }
    }
    echo json_encode(['success'=>$found], JSON_UNESCAPED_UNICODE);
    break;
  }
  default:
    echo json_encode(['success'=>false,'error'=>'Neatbalstīta darbība'], JSON_UNESCAPED_UNICODE);
}
