<?php
// Iekļaujam CSS šai sadaļai
echo '<link rel="stylesheet" href="assets/css/darbi.css">';

// Ielādējam tikai klientus no individuālajiem JSON failiem
$dataDir = __DIR__ . '/../dati/';
$clientsList = [];

// Savāc visus klientu failus
$clientFiles = glob($dataDir . 'clients/*.json');
foreach ($clientFiles as $file) {
    $c = json_decode(file_get_contents($file), true);
    if ($c && is_array($c)) {
        $name = isset($c['company_name']) && $c['company_name'] !== ''
            ? $c['company_name']
            : trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
        $clientsList[] = ['id' => $c['id'], 'name' => $name ?: ('Klients #' . $c['id'])];
    }
}
?>
<!-- Divu kolonnu izkārtojums -->
<div class="jobs-container">
  <!-- Kreisā kolonna: Darbu saraksts un jauna darba pievienošanas forma -->
  <div class="job-list-column">
    <h2>Aktīvie darbi</h2>

    <div class="new-job-form">
      <button id="showAddJobFormBtn" class="btn" type="button">➕ Pievienot jaunu darbu</button>
      <div id="addJobFormContainer" style="display:none;">
        <h3>Pievienot jaunu darbu</h3>
        <form id="addJobForm">
          <label for="clientSearch">Meklēt klientu:</label>
          <input type="text" id="clientSearch" placeholder="Sāc rakstīt vārdu">
          <br>
          <label for="clientSelect">Klients:</label>
          <select id="clientSelect" name="client_id" required>
            <option value="">-- Izvēlieties klientu --</option>
            <?php foreach ($clientsList as $client): ?>
              <option value="<?= htmlspecialchars($client['id']) ?>">
                <?= htmlspecialchars($client['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <br>

          <label for="commentInput">Piezīmes:</label>
          <textarea id="commentInput" name="comment" placeholder="Papildus informācija"></textarea>
          <br>

          <button type="submit">Pievienot darbu</button>
        </form>
      </div>
    </div>

    <!-- Saraksts ar kartītēm (aizpildīs JS) -->
    <div id="job-list"></div>
  </div>

  <!-- Labā kolonna: Darba detaļas (bez statusa/laikiem) -->
  <div class="job-detail-column">
    <h2>Darba detaļas</h2>
    <div id="job-details">
      <p>Lūdzu, izvēlieties darbu no saraksta.</p>
    </div>
  </div>
</div>

<script>
  var clientsList = <?= json_encode($clientsList) ?>;
  var carsList = []; // paliek tukšs drošībai
</script>
<script src="assets/js/darbi.js"></script>
