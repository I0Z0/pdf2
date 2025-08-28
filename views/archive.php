<?php
// Ielādē datus PHP pusē
$clients = [];
$invoices = [];

// Klienti
foreach (glob(__DIR__ . '/../dati/clients/*.json') as $file) {
    if ($c = json_decode(file_get_contents($file), true)) {
        $clients[] = $c;
    }
}
// Arhivētie rēķini
foreach (glob(__DIR__ . '/../dati/archive/*.json') as $file) {
    if ($inv = json_decode(file_get_contents($file), true)) {
        $invoices[] = $inv;
    }
}
?>
<link rel="stylesheet" href="assets/css/darbi.css">
<style>
.archive-container {
    display: flex;
    align-items: flex-start;
    gap: 32px;
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
}
.archive-left {
    width: 25%;
    min-width: 220px;
    max-width: 400px;
    border-right: 1px solid #ddd;
    padding: 10px;
    box-sizing: border-box;
}
.archive-right {
    width: 75%;
    min-width: 320px;
    padding: 10px;
    box-sizing: border-box;
    align-self: flex-start;
    position: sticky;
    top: 24px;
    height: fit-content;
}
#archive-details { /* no fixed height or overflow */ }
@media (max-width: 950px) {
  .archive-container {
    flex-direction: column;
    gap: 0;
    max-width: 100vw;
  }
  .archive-left,
  .archive-right {
    width: 100%;
    min-width: 0;
    max-width: none;
    border-right: none;
    border-bottom: 1px solid #ddd;
  }
  .archive-right {
    position: static;
    top: auto;
    height: auto;
  }
}
/* Papildu stili */
.client-list { display: flex; flex-direction: column; gap: 10px; }
.client-card {
    padding: 10px 15px;
    border: 1px solid #ccc;
    border-radius: 6px;
    background: #f7faff;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.12s;
}
.client-card.active {
    background: #e3f1ff;
    border-color: #007bff;
}
.archive-invoices { margin: 16px 0 0 0; }
.archive-invoice {
    border-bottom: 1px solid #eef2f8;
    padding: 7px 0;
}
.archive-invoice .actions { margin-left: 8px; display: inline; }
input, textarea, select {
    border-radius: 4px;
    border: 1px solid #ccc;
    margin: 2px 0;
}
.editing input, .editing textarea {
    background: #fffbe6;
}
button, .btn {
    background: #007bff;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 5px 9px;
    margin-left: 3px;
    cursor: pointer;
    font-size: 13px;
}
button:hover, .btn:hover { background: #005fa3; }
.archive-invoice a.btn { background: #17a2b8; }
.archive-invoice a.btn:hover { background: #11707c; }
</style>

<div class="archive-container">
  <!-- Kreisā kolonna: Klientu saraksts -->
  <div class="archive-left">
    <h3>Klienti</h3>
    <input type="text" id="archiveClientSearch" placeholder="Meklēt klientu">
    <div class="client-list" id="client-list"></div>
  </div>
  <!-- Labā kolonna: Klienta detaļas un arhīvs -->
  <div class="archive-right">
    <div id="archive-details"><p>Lūdzu, izvēlieties klientu no saraksta.</p></div>
  </div>
</div>

<script>
const clientsList = <?= json_encode($clients) ?>;
const archiveList = <?= json_encode($invoices) ?>;
</script>
<script src="assets/js/archive.js"></script>
