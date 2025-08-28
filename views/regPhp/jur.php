<fieldset>
  <legend>Juridiskās personas dati</legend>

  <label>Uzņēmuma nosaukums: <input type="text" name="company_name" required></label><br>
  <label>Reģistrācijas Nr.:  <input type="text" name="registration_no" required></label><br>
  <label>PVN Nr.:            <input type="text" name="vat_no"></label><br>

  <?php
  // Banku saraksts (pilnais nosaukums + BIC)
  $BANKS = [
    'Swedbank, AS — HABALV22',
    'SEB banka, AS — UNLALV2X',
    'Citadele banka, AS — PARXLV22',
    'Luminor Bank, AS (LV filiāle) — RIKOLV2X',
    'Rietumu Banka, AS — RTMBLV2X',
    'BluOr Bank, AS — CBBRLV22',
    'Signet Bank, AS — LLBBLV2X',
    'LPB Bank, AS — LAPBLV2X',
    'Industra Bank, AS — MULTLV2X',
    'PrivatBank, AS — PRTTLV22',
    'Revolut Bank, UAB — REVOLT21',
    'Wise Europe, S.A. — TRWIBEB1',
    'Paysera LT, UAB — EVIULT2V',
    'N26 Bank, SE — NTSBDEB1',
  ];
  ?>

  <!-- Banka (dropdown + "Cita" brīvai ievadei) -->
  <label>Banka:
    <select id="bankSelectJur" class="bank-select">
      <option value="">-- izvēlieties --</option>
      <?php foreach ($BANKS as $label): ?>
        <option value="<?= htmlspecialchars($label) ?>"><?= htmlspecialchars($label) ?></option>
      <?php endforeach; ?>
      <option value="__OTHER__">Cita (ievadīt manuāli)</option>
    </select>
  </label><br>

  <label id="bankFreeWrapJur" style="display:none;">Banka (brīvi):
    <input type="text" id="bankFreeJur" placeholder="piem., Revolut Bank, UAB — REVOLT21">
  </label>
  <input type="hidden" name="bank" id="bankValueJur">

  <!-- Konta Nr. (IBAN) uzreiz aiz bankas -->
  <label>Konta Nr. (IBAN):
    <input
      type="text"
      name="account_no"
      id="accountNoJur"
      placeholder="piem., LV97 HABA 0000 1234 5678 9"
      maxlength="34"
      autocomplete="off"
      spellcheck="false">
  </label><br>

  <label>Adrese:    <input type="text" name="address"></label><br>
  <label>Tālrunis:  <input type="tel"  name="phone" required></label><br>
  <label>E-pasts:   <input type="email" name="email"></label><br>
  <label>Piezīmes:  <textarea name="comment" rows="2"></textarea></label>
</fieldset>

<script>
(function(){
  // Dropdown -> hidden 'bank' + brīvā ievade
  function syncBank(selectEl, freeWrapId, freeId, hiddenId){
    const v = selectEl.value;
    const wrap = document.getElementById(freeWrapId);
    const free = document.getElementById(freeId);
    const hid  = document.getElementById(hiddenId);
    if (!hid) return;

    if (v === '__OTHER__') {
      if (wrap) wrap.style.display = '';
      if (free) {
        free.focus();
        free.oninput = () => { hid.value = free.value.trim(); };
        hid.value = free.value.trim();
      } else {
        hid.value = '';
      }
    } else {
      if (wrap) wrap.style.display = 'none';
      hid.value = v || '';
    }
  }

  const sel = document.getElementById('bankSelectJur');
  if (sel) {
    sel.addEventListener('change', ()=>syncBank(sel,'bankFreeWrapJur','bankFreeJur','bankValueJur'));
    // init (pirmajā ielādē)
    syncBank(sel,'bankFreeWrapJur','bankFreeJur','bankValueJur');
  }

  // IBAN: uppercase + vizuāla grupēšana pa 4; submitā sūtām bez atstarpēm
  const iban = document.getElementById('accountNoJur');
  if (iban && iban.form) {
    iban.addEventListener('input', ()=>{
      const raw = iban.value.toUpperCase().replace(/[^A-Z0-9]/g,'');
      iban.dataset.raw = raw; // glabājam tīro
      iban.value = raw.replace(/(.{4})/g,'$1 ').trim(); // skaists skats
    });
    iban.form.addEventListener('submit', ()=>{
      if (iban.dataset.raw) iban.value = iban.dataset.raw; // sūtām tīro
    });
  }
})();
</script>
