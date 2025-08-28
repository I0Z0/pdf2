<?php
/** Klientu reģistrācijas skats (dashboard.php?page=registration)
 * Vienkāršota versija: tikai fiziskās un juridiskās personas reģistrācija.
 * Viss, kas saistīts ar auto pievienošanu, ir izņemts.
 */

$successMsg = isset($_GET['success']) ? 'Dati veiksmīgi saglabāti!' : '';
$errorMsg   = isset($_GET['error'])   ? 'Kļūda saglabājot datus!'   : '';
?>
<h2>Klientu reģistrācija</h2>

<?php if ($successMsg): ?>
  <div class="success"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
  <div class="error"><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<div class="toggle-buttons">
  <button type="button" class="toggle-section active" data-target="fiz-section">Fiziska persona</button>
  <button type="button" class="toggle-section" data-target="jur-section">Juridiska persona</button>
</div>

<!-- 1) Fiziskā persona -->
<div id="fiz-section" class="form-section">
  <form id="fizForm" method="post" action="dashboard.php?page=registration">
    <input type="hidden" name="form_type" value="person">
    <?php include __DIR__ . '/regPhp/fiz.php'; ?>
    <button type="submit">Saglabāt klientu</button>
  </form>
</div>

<!-- 2) Juridiskā persona -->
<div id="jur-section" class="form-section" style="display:none;">
  <form id="jurForm" method="post" action="dashboard.php?page=registration">
    <input type="hidden" name="form_type" value="company">
    <?php include __DIR__ . '/regPhp/jur.php'; ?>
    <button type="submit">Saglabāt klientu</button>
  </form>
</div>
