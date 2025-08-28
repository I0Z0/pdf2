<?php
// Skats plānotāja modulim

// Iekļaujam CSS
echo '<link rel="stylesheet" href="assets/css/planner.css">';

$dataDir = __DIR__ . '/../dati/';
$events = [];
$notes  = [];
$unpaid = [];

if (is_file($dataDir . 'events.json')) {
    $events = json_decode(file_get_contents($dataDir . 'events.json'), true) ?: [];
}
if (is_file($dataDir . 'notes.json')) {
    $notes = json_decode(file_get_contents($dataDir . 'notes.json'), true) ?: [];
}
if (is_dir($dataDir . 'archive')) {
    foreach (glob($dataDir . 'archive/*.json') as $f) {
        $inv = json_decode(file_get_contents($f), true);
        if (!$inv || !empty($inv['paid'])) continue;
        $client = $inv['client_name'] ?? ('Klients #' . ($inv['client_id'] ?? ''));
        $unpaid[] = [
            'invoice_no' => $inv['invoice_no'] ?? ($inv['id'] ?? basename($f, '.json')),
            'date'       => $inv['date'] ?? '',
            'client'     => $client,
            'total'      => $inv['total'] ?? 0
        ];
    }
}
?>
<div class="planner">
  <div class="row">
    <div class="col month-view">
      <button id="addEventBtn" class="add-event-btn" type="button">+</button>
      <div class="calendar-nav">
        <button id="prevMonth" type="button">&lt;</button>
        <span id="monthLabel"></span>
        <button id="nextMonth" type="button">&gt;</button>
      </div>
      <div id="calendar"></div>
    </div>
    <div class="col day-view">
      <h3 id="dayLabel"></h3>
      <div id="dayEvents">Šai dienai nav notikumu</div>
    </div>
  </div>
  <div class="row">
    <div class="col notes">
      <h3>Piezīmes</h3>
      <form id="noteForm" accept-charset="UTF-8">
        <input type="text" id="noteText" name="text" placeholder="Piezīme" required>
        <input type="color" id="noteColor" name="color" value="#ffff88">
        <button type="submit">Pievienot</button>
      </form>
      <ul id="notesList"></ul>
    </div>
    <div class="col unpaid">
      <h3>Neapmaksātie rēķini</h3>
      <table id="unpaidInvoices">
        <thead>
          <tr><th>Nr.</th><th>Datums</th><th>Klients</th><th>Summa</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($unpaid as $inv): ?>
          <tr data-inv="<?= htmlspecialchars($inv['invoice_no']) ?>">
            <td><?= htmlspecialchars($inv['invoice_no']) ?></td>
            <td><?= htmlspecialchars($inv['date']) ?></td>
            <td><?= htmlspecialchars($inv['client']) ?></td>
            <td><?= htmlspecialchars(number_format((float)$inv['total'],2,'.','')) ?></td>
            <td><button class="pay-btn" type="button">Apmaksāts</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<div id="eventModal" class="modal hidden">
  <div class="modal-content">
    <button type="button" class="close">×</button>
    <form id="eventForm" accept-charset="UTF-8">
      <h3>Pievienot notikumu</h3>
      <label>Sākuma datums: <input type="date" name="date" required></label>
      <label>Beigu datums: <input type="date" name="end_date"></label>
      <label>Sākums: <input type="time" name="start"></label>
      <label>Beigas: <input type="time" name="end"></label>
      <label>Nosaukums: <input type="text" name="title" required></label>
      <label>Krāsa: <input type="color" name="color" value="#0000ff"></label>
      <button type="submit">Pievienot</button>
    </form>
  </div>
</div>
<script>
var plannerEvents = <?= json_encode($events, JSON_UNESCAPED_UNICODE) ?>;
var plannerNotes  = <?= json_encode($notes, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/js/planner.js"></script>
