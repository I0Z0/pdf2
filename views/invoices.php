<?php
/********************************************************
 *  R Ē Ķ I N U   S A R A K S T S  (jaunā shēma)
 *  - Strādā ar jaunajiem failiem no /dati/invoices/*.json
 *  - AUTO/servisa lauki pilnībā izņemti
 *  - Pievienota "inv_period" iestatīšana ar modāli
 ********************************************************/

$dataDir  = __DIR__ . '/../dati/';
$invoices = $clients = [];

/* Rēķini */
foreach (glob($dataDir . 'invoices/*.json') as $file) {
    $d = json_decode(file_get_contents($file), true);
    if (is_array($d)) $invoices[] = $d;
}
/* Klienti */
foreach (glob($dataDir . 'clients/*.json') as $file) {
    $d = json_decode(file_get_contents($file), true);
    if (is_array($d)) $clients[] = $d;
}

/* Palīgfunkcijas */
function client_display_name($c) {
    if (!$c) return 'Nezināms';
    return ($c['kind'] ?? '') === 'physical'
        ? trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))
        : ($c['company_name'] ?? 'Nezināms');
}
function to_items($inv) {
    if (!empty($inv['items']) && is_array($inv['items'])) return $inv['items'];
    $out = [];
    if (!empty($inv['services']) && is_array($inv['services'])) {
        foreach ($inv['services'] as $s) {
            $name  = $s['name']  ?? '';
            $price = (float)($s['price'] ?? 0);
            $qty   = (float)($s['qty']   ?? 1);
            if ($name !== '' && $qty > 0) {
                $out[] = [
                    'name'  => $name,
                    'qty'   => $qty,
                    'price' => $price,
                    'total' => round($qty * $price, 2),
                ];
            }
        }
    }
    return $out;
}
function money($n) { return number_format((float)$n, 2, '.', ''); }
function format_period(?array $p): string {
    if (!$p) return '';
    if (($p['mode'] ?? '') === 'date' && !empty($p['date'])) {
        return $p['date'];
    }
    if (($p['mode'] ?? '') === 'range' && !empty($p['from']) && !empty($p['to'])) {
        return $p['from'] . ' — ' . $p['to'];
    }
    return '';
}

/* Ātrā pārmapēšana klientiem */
$clientsById = array_column($clients, null, 'id');

/* Kārtojam rēķinus pēc created_at vai date, dilstoši */
usort($invoices, function($a, $b) {
    $ka = $a['created_at'] ?? ($a['date'] ?? '');
    $kb = $b['created_at'] ?? ($b['date'] ?? '');
    return strcmp($kb, $ka);
});
?>
<link rel="stylesheet" href="assets/css/darbi.css">
<style>
.invoices-list { justify-content:center; display:flex; flex-wrap:wrap; gap:32px; }
.invoice-card { width:325px; background:#fff; border-radius:12px; box-shadow:0 0 12px #e3e8f7; padding:24px; margin-bottom:20px; transition: opacity .18s ease, transform .18s ease; }
.invoice-card h3 { margin:0 0 8px; display:flex; align-items:center; justify-content:space-between; gap:8px; }
.invoice-meta,.invoice-totals { font-size:15px; color:#445; }
.invoice-table { width:100%; border-collapse:collapse; margin:10px 0; }
.invoice-table th,.invoice-table td { border:1px solid #ddd; padding:4px 8px; }
.invoice-table th { background:#f2f7fb; }
.invoice-actions { display:flex; gap:8px; margin-top:10px; }
.invoice-actions button { flex:1 1 0; padding:7px 0; border-radius:7px; }
.small-muted { color:#6b7280; font-size:12px; }
.right { text-align:right; }

/* Perioda modālis */
#periodModalBackdrop { position:fixed; inset:0; background:rgba(0,0,0,.35); display:none; align-items:center; justify-content:center; z-index:9999; }
#periodModal { width:min(92vw, 420px); background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.25); padding:16px; }
#periodModal h4 { margin:0 0 10px; }
#periodModal .row { margin:8px 0; }
#periodModal .actions { display:flex; gap:8px; margin-top:12px; }
#periodModal .actions button { flex:1 1 0; padding:8px 0; border-radius:8px; }
button.icon { flex:0 0 auto; padding:4px 8px; border-radius:6px; }
button[disabled]{ opacity:.6; cursor:not-allowed; }
</style>

<h2>Rēķinu saraksts</h2>
<div class="invoices-list">
<?php if (empty($invoices)): ?>
  <p>Nav neviena rēķina.</p>
<?php else:
  foreach ($invoices as $inv):
    $client = $clientsById[$inv['client_id']] ?? null;
    $items  = to_items($inv);

    $subtotal = isset($inv['subtotal']) ? (float)$inv['subtotal'] : array_reduce($items, fn($s,$r)=>$s + (float)($r['total'] ?? ($r['qty']*$r['price'])), 0.0);
    $vatRate  = isset($inv['vat_rate']) ? (float)$inv['vat_rate'] : 0.00;
    $vat      = isset($inv['vat'])      ? (float)$inv['vat']      : round($subtotal * $vatRate, 2);
    $total    = isset($inv['total'])    ? (float)$inv['total']    : round($subtotal + $vat, 2);

    $invNo       = $inv['number'] ?? null;
    $invNoLabel  = $invNo ? ('#' . $invNo) : 'Rēķ. nav arhivēts';
    $date  = $inv['date']   ?? substr(($inv['created_at'] ?? ''), 0, 10);

    $period = $inv['inv_period'] ?? null;
    $periodStr = format_period($period);
    $hasPeriod = $periodStr !== '';
?>
  <div class="invoice-card"
       data-id="<?= htmlspecialchars($inv['id']) ?>"
       data-period-mode="<?= htmlspecialchars($period['mode'] ?? '') ?>"
       data-period-date="<?= htmlspecialchars($period['date'] ?? '') ?>"
       data-period-from="<?= htmlspecialchars($period['from'] ?? '') ?>"
       data-period-to="<?= htmlspecialchars($period['to'] ?? '') ?>">
    <h3>
      <span>Rēķins <?= htmlspecialchars($invNoLabel) ?></span>
      <button class="icon set-period" title="Iestatīt pakalpojuma datumu/periodu" data-id="<?= htmlspecialchars($inv['id']) ?>">📅</button>
    </h3>

    <div class="invoice-meta">
      <div><b>Datums:</b> <?= htmlspecialchars($date ?: '—') ?></div>
      <div><b>Klients:</b> <?= htmlspecialchars(client_display_name($client)) ?></div>
      <?php if ($hasPeriod): ?>
        <div class="small-muted"><b>Periods:</b> <?= htmlspecialchars($periodStr) ?></div>
      <?php else: ?>
        <div class="small-muted">Periods nav iestatīts</div>
      <?php endif; ?>
      <?php if (!empty($inv['job_id'])): ?>
        <div class="small-muted">Darba ID: <?= htmlspecialchars($inv['job_id']) ?></div>
      <?php endif; ?>
      <?php if (!empty($inv['comment'])): ?>
        <div><b>Komentārs:</b> <?= htmlspecialchars($inv['comment']) ?></div>
      <?php endif; ?>
    </div>

    <table class="invoice-table">
      <thead>
        <tr>
          <th>Pakalpojums</th>
          <th class="right" style="width:90px;">Daudz.</th>
          <th class="right" style="width:110px;">Cena (€)</th>
          <th class="right" style="width:110px;">Summa (€)</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($items): foreach ($items as $r):
          $qty   = (float)($r['qty'] ?? 1);
          $price = (float)($r['price'] ?? 0);
          $rowT  = isset($r['total']) ? (float)$r['total'] : ($qty * $price);
        ?>
          <tr>
            <td><?= htmlspecialchars($r['name'] ?? '') ?></td>
            <td class="right"><?= money($qty) ?></td>
            <td class="right"><?= money($price) ?></td>
            <td class="right"><?= money($rowT) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="4">Nav pakalpojumu rindu.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="invoice-totals">
      <div><b>Starpsumma:</b> <?= money($subtotal) ?> €</div>
      <div><b>PVN<?= $vatRate>0 ? ' ('.(int)round($vatRate*100).'%)' : '' ?>:</b> <?= money($vat) ?> €</div>
      <div><b>Kopā:</b> <?= money($total) ?> €</div>
    </div>

    <div class="invoice-actions">
      <button class="preview-invoice" data-id="<?= htmlspecialchars($inv['id']) ?>">Priekšskats</button>
      <button class="pdf-invoice"     data-id="<?= htmlspecialchars($inv['id']) ?>" <?= $hasPeriod ? '' : 'disabled title="Iestatiet periodu pirms arhivēšanas"' ?>>Arhivēt rēķinu</button>
      <button class="edit-invoice"    data-id="<?= htmlspecialchars($inv['id']) ?>">Labot</button>
    </div>
  </div>
<?php endforeach; endif; ?>
</div>

<!-- Perioda modālis (viens uz visu lapu) -->
<div id="periodModalBackdrop" role="dialog" aria-modal="true">
  <div id="periodModal">
    <h4>Iestatīt pakalpojuma datumu / periodu</h4>
    <div class="row">
      <label><input type="radio" name="pmode" value="date"> Vienas dienas datums</label>
      &nbsp; &nbsp;
      <label><input type="radio" name="pmode" value="range"> Periods</label>
    </div>
    <div class="row" id="row-date" style="display:none;">
      <label>Datums: <input type="date" id="pDate"></label>
    </div>
    <div class="row" id="row-range" style="display:none;">
      <label>No: <input type="date" id="pFrom"></label>
      &nbsp;&nbsp;
      <label>Līdz: <input type="date" id="pTo"></label>
    </div>
    <div class="actions">
      <button id="pCancel" style="background:#e5e7eb; color:#111;">Atcelt</button>
      <button id="pSave" class="btn">Saglabāt</button>
    </div>
  </div>
</div>

<script>
// ===== Priekšskatījums
document.querySelectorAll('.preview-invoice').forEach(btn =>{
  btn.onclick = () => window.open(
    'includes/render_invoice_dompdf.php?id=' + encodeURIComponent(btn.dataset.id) + '&action=preview',
    '_blank'
  );
});

// Neliela palīgfunkcija kartīnas noņemšanai
function removeInvoiceCard(id){
  const card = document.querySelector('.invoice-card[data-id="'+id+'"]');
  if (!card) return;
  card.style.opacity = '0';
  card.style.transform = 'scale(0.98)';
  setTimeout(()=>{
    card.remove();
    const wrap = document.querySelector('.invoices-list');
    if (wrap && wrap.querySelectorAll('.invoice-card').length === 0) {
      wrap.innerHTML = '<p>Nav neviena rēķina.</p>';
    }
  }, 180);
}

// ===== Arhivēšana (AJAX, bez lejupielādes; kartīna pazūd uzreiz pēc success)
document.querySelectorAll('.pdf-invoice').forEach(btn =>{
  btn.onclick = async () => {
    const id = btn.dataset.id;
    if (!id) return;
    if (btn.disabled) {
      // ja periods nav iestatīts – atver perioda modāli
      openPeriodModal(id);
      return;
    }
    if (!confirm('Vai tiešām izveidot gala PDF un pārvietot rēķinu uz arhīvu?')) return;

    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Arhivēju…';

    try {
      const url = 'includes/render_invoice_dompdf.php?id=' + encodeURIComponent(id) + '&action=finalize&ajax=1';
      const resp = await fetch(url, { headers: { 'Accept':'application/json' } });
      const data = await resp.json();
      if (!resp.ok || !data.success) throw new Error(data.error || ('Servera kļūda ' + resp.status));

      removeInvoiceCard(id);
      if (data.pdf_url) {
        const a = document.createElement('a');
        a.href = data.pdf_url;
        a.download = '';
        document.body.appendChild(a);
        a.click();
        a.remove();
      }

    } catch (e) {
      alert('Kļūda arhivējot: ' + e.message);
      btn.disabled = false;
      btn.textContent = orig;
      return;
    }
  };
});

// ===== Perioda modālis
const modalBackdrop = document.getElementById('periodModalBackdrop');
const rowDate  = document.getElementById('row-date');
const rowRange = document.getElementById('row-range');
const pDate = document.getElementById('pDate');
const pFrom = document.getElementById('pFrom');
const pTo   = document.getElementById('pTo');
let currentInvoiceId = null;

function openPeriodModal(invId){
  currentInvoiceId = invId;
  // Prefill no kartīnas data-*
  const card = document.querySelector('.invoice-card[data-id="'+invId+'"]');
  const mode = card?.dataset.periodMode || '';
  const d    = card?.dataset.periodDate || '';
  const f    = card?.dataset.periodFrom || '';
  const t    = card?.dataset.periodTo   || '';

  document.querySelectorAll('input[name="pmode"]').forEach(r => r.checked = false);
  rowDate.style.display = 'none';
  rowRange.style.display = 'none';
  pDate.value = d || '';
  pFrom.value = f || '';
  pTo.value   = t || '';

  if (mode === 'date') {
    document.querySelector('input[name="pmode"][value="date"]').checked = true;
    rowDate.style.display = '';
  } else if (mode === 'range') {
    document.querySelector('input[name="pmode"][value="range"]').checked = true;
    rowRange.style.display = '';
  }

  modalBackdrop.style.display = 'flex';
}
function closePeriodModal(){
  modalBackdrop.style.display = 'none';
  currentInvoiceId = null;
}

document.querySelectorAll('.set-period').forEach(btn=>{
  btn.addEventListener('click', ()=> openPeriodModal(btn.dataset.id));
});
document.getElementById('pCancel').onclick = closePeriodModal;

document.querySelectorAll('input[name="pmode"]').forEach(r=>{
  r.addEventListener('change', ()=>{
    if (r.value === 'date') {
      rowDate.style.display = '';
      rowRange.style.display = 'none';
    } else {
      rowRange.style.display = '';
      rowDate.style.display = 'none';
    }
  });
});

document.getElementById('pSave').onclick = async ()=>{
  if (!currentInvoiceId) return;
  const modeEl = document.querySelector('input[name="pmode"]:checked');
  if (!modeEl) { alert('Izvēlies režīmu: datums vai periods.'); return; }
  const mode = modeEl.value;

  const fd = new URLSearchParams();
  fd.append('action','save_period');
  fd.append('id', currentInvoiceId);
  fd.append('mode', mode);
  if (mode === 'date') {
    if (!pDate.value) { alert('Norādi datumu.'); return; }
    fd.append('date', pDate.value);
  } else {
    if (!pFrom.value || !pTo.value) { alert('Norādi periodu (no/līdz).'); return; }
    fd.append('from', pFrom.value);
    fd.append('to',   pTo.value);
  }

  try {
    const resp = await fetch('includes/invoice_actions.php', { method:'POST', body: fd });
    const data = await resp.json();
    if (!resp.ok || !data.success) throw new Error(data.error || ('Servera kļūda ' + resp.status));

    // UI atjauninājumi uz kartīnas
    const card = document.querySelector('.invoice-card[data-id="'+currentInvoiceId+'"]');
    if (card) {
      card.dataset.periodMode = data.inv_period.mode || '';
      card.dataset.periodDate = data.inv_period.date || '';
      card.dataset.periodFrom = data.inv_period.from || '';
      card.dataset.periodTo   = data.inv_period.to   || '';

      const meta = card.querySelector('.invoice-meta');
      let line = meta.querySelector('.small-muted b + text'); // nestrādās; tāpēc atrod pēc <div> ar “Periods:”
      let periodDiv = Array.from(meta.querySelectorAll('div')).find(d => d.textContent.trim().startsWith('Periods:') || d.textContent.includes('Periods nav iestatīts'));
      const periodStr = data.inv_period.mode === 'date'
        ? data.inv_period.date
        : (data.inv_period.from + ' — ' + data.inv_period.to);

      if (periodDiv) {
        periodDiv.innerHTML = '<b>Periods:</b> ' + periodStr;
        periodDiv.classList.add('small-muted');
      } else {
        const nd = document.createElement('div');
        nd.className = 'small-muted';
        nd.innerHTML = '<b>Periods:</b> ' + periodStr;
        meta.insertBefore(nd, meta.firstChild.nextSibling); // aiz Datums
      }

      // Atļaujam arhivēt
      const archBtn = card.querySelector('.pdf-invoice');
      if (archBtn) {
        archBtn.disabled = false;
        archBtn.removeAttribute('title');
      }
    }

    closePeriodModal();
  } catch (e) {
    alert('Neizdevās saglabāt periodu: ' + e.message);
  }
};

// Noklikšķinot ārpus modāļa – aizver
modalBackdrop.addEventListener('click', (e)=>{
  if (e.target === modalBackdrop) closePeriodModal();
});

// ===== Labot – kā līdz šim
document.querySelectorAll('.edit-invoice').forEach(btn =>{
  btn.addEventListener('click', () => {
    if (!confirm('Atvērt rēķinu labošanu?')) return;
    const id = btn.dataset.id;
    window.location = `includes/generate_invoice.php?id=${encodeURIComponent(id)}&edit=1`;
  });
});
</script>
