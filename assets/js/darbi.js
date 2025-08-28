// assets/js/darbi.js
(function () {
  const listEl   = document.getElementById('job-list');
  const details  = document.getElementById('job-details');
  const formWrap = document.getElementById('addJobFormContainer');
  const toggleBtn= document.getElementById('showAddJobFormBtn');
  const form     = document.getElementById('addJobForm');
  const clientSelect = document.getElementById('clientSelect');
  const clientSearch = document.getElementById('clientSearch');

  // ---------------- Helpers ----------------
  function api(payload) {
    return fetch('includes/process_jobs.php', {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      body: new URLSearchParams(payload)
    }).then(r => r.json());
  }
  function fmt(dt) {
    if (!dt) return '—';
    try { const d = new Date(dt); return isNaN(d) ? dt : d.toLocaleString(); }
    catch { return dt; }
  }
  function money(v) {
    const n = Number(v);
    return isFinite(n) ? n.toFixed(2) : '0.00';
  }
  function escapeHtml(str) {
    return String(str ?? '')
      .replaceAll('&','&amp;').replaceAll('<','&lt;')
      .replaceAll('>','&gt;').replaceAll('"','&quot;')
      .replaceAll("'",'&#039;');
  }
  function clientNameById(id) {
    if (!window.clientsList) return String(id);
    const hit = window.clientsList.find(c => String(c.id) === String(id));
    return hit ? hit.name : String(id);
  }

  function renderClientOptions(q = '') {
    if (!clientSelect) return;
    const term = q.toLowerCase();
    clientSelect.innerHTML = '<option value="">-- Izvēlieties klientu --</option>';
    (window.clientsList || [])
      .filter(c => c.name.toLowerCase().includes(term))
      .forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name;
        clientSelect.appendChild(opt);
      });
  }

  if (clientSearch) {
    renderClientOptions();
    clientSearch.addEventListener('input', () => renderClientOptions(clientSearch.value));
  }

  // --------------- List & Cards ---------------
  function renderCard(j) {
    const div = document.createElement('div');
    div.className = 'job-card';
    div.dataset.jobId = j.id;

    const title = j.title && j.title.trim() ? j.title : `Darbs #${j.id}`;
    div.innerHTML = `
      <div class="job-card-header">
        <strong>${escapeHtml(title)}</strong>
      </div>
      <div class="job-card-body">
        <div><small>Klients:</small> ${escapeHtml(clientNameById(j.client_id))}</div>
        <div><small>Izveidots:</small> ${escapeHtml(fmt(j.created_at))}</div>
      </div>
    `;

    div.addEventListener('click', () => {
      selectCard(div);
      loadDetails(j.id);
    });
    return div;
  }

  function selectCard(card) {
    listEl.querySelectorAll('.job-card').forEach(c => c.classList.remove('active'));
    if (card) card.classList.add('active');
  }

  function renderList(jobs) {
    listEl.innerHTML = '';
    if (!jobs || !jobs.length) {
      listEl.innerHTML = '<p>Nav neviena aktīva darba.</p>';
      return;
    }
    jobs.forEach(j => listEl.appendChild(renderCard(j)));
  }

  // --------------- Details + Services ---------------
  function renderDetails(j) {
    const title = j.title && j.title.trim() ? j.title : `Darbs #${j.id}`;

    details.innerHTML = `
      <div class="job-details-section">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
          <h3 style="margin:0;">${escapeHtml(title)}</h3>
          <div class="job-actions" style="display:flex; gap:8px;">
            <button id="delete-job" class="btn btn-danger" type="button" title="Dzēst darbu">Dzēst darbu</button>
          </div>
        </div>
        <p style="margin-top:8px;"><strong>Klients:</strong> ${escapeHtml(clientNameById(j.client_id))}</p>
        <p><strong>Izveidots:</strong> ${escapeHtml(fmt(j.created_at))}</p>
        <p><strong>Piezīmes:</strong><br>${escapeHtml(j.comment || '—')}</p>
      </div>

      <div class="job-details-section">
        <h4>Pakalpojumi</h4>
        <div id="services-area">
          <table class="services-table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th style="text-align:left; padding:6px; border-bottom:1px solid #ddd;">Nosaukums</th>
                <th style="text-align:right; padding:6px; border-bottom:1px solid #ddd; width:120px;">Daudzums</th>
                <th style="text-align:right; padding:6px; border-bottom:1px solid #ddd; width:140px;">Cena</th>
                <th style="width:32px;"></th>
              </tr>
            </thead>
            <tbody id="srv-rows"></tbody>
          </table>

          <button id="srv-add" class="btn btn-secondary" type="button" style="margin-top:8px;">+ Pievienot rindu</button>

          <div id="srv-totals" style="margin-top:12px;">
            <div><strong>Starpsumma:</strong> <span id="subtotal">0.00</span></div>
            <div><strong>PVN:</strong> <span id="vat">0.00</span></div>
            <div><strong>Kopā:</strong> <span id="total">0.00</span></div>
          </div>

          <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
            <button id="srv-save" class="btn" type="button">Saglabāt pakalpojumus</button>
            <button id="make-invoice" class="btn btn-primary" type="button">Izveidot rēķinu</button>
          </div>

          <div id="invoice-result" style="margin-top:8px;"></div>
        </div>
      </div>
    `;

    // Delete button handler
    const delBtn = details.querySelector('#delete-job');
    if (j.invoice_id) {
      // drošības pēc, gan jau aktīvajos neparādās
      delBtn.disabled = true;
      delBtn.title = 'Darbu nevar dzēst – tas jau nosūtīts uz rēķinu';
    }
    delBtn.addEventListener('click', () => {
      if (!confirm('Vai tiešām dzēst šo darbu? Šo darbību nevarēs atsaukt.')) return;
      api({ action: 'delete_job', job_id: j.id })
        .then(res => {
          if (res && res.status === 'ok') {
            // izņemam kartīti no saraksta un notīrām detaļas
            const card = listEl.querySelector(`.job-card[data-job-id="${CSS.escape(String(j.id))}"]`);
            if (card) card.remove();
            details.innerHTML = '<p>Darbs izdzēsts.</p>';
            // ja saraksts tukšs – parādi ziņu
            if (!listEl.querySelector('.job-card')) renderList([]);
            else loadList(); // pārlādē drošībai
          } else {
            alert((res && res.message) ? res.message : 'Neizdevās dzēst darbu.');
          }
        })
        .catch(() => alert('Neizdevās dzēst darbu.'));
    });

    // Ielādējam esošās rindas
    const tbody = details.querySelector('#srv-rows');
    (j.services || []).forEach(addSrvRow.bind(null, tbody));
    if (!tbody.querySelector('tr')) addSrvRow(tbody, { name: '', qty: 1, price: 0 });

    // Eventi pakalpojumiem
    details.querySelector('#srv-add').addEventListener('click', () => {
      addSrvRow(tbody, { name: '', qty: 1, price: 0 });
      recalcTotals(details);
    });

    tbody.addEventListener('input', () => recalcTotals(details));
    tbody.addEventListener('click', (e) => {
      if (e.target && e.target.classList.contains('srv-del')) {
        e.target.closest('tr')?.remove();
        if (!tbody.querySelector('tr')) addSrvRow(tbody, { name: '', qty: 1, price: 0 });
        recalcTotals(details);
      }
    });

    // Saglabāt pakalpojumus
    details.querySelector('#srv-save').addEventListener('click', () => {
      const services = readSrvRows(tbody);
      api({ action: 'upsert_services', job_id: j.id, services_json: JSON.stringify(services) })
        .then(res => {
          if (res && res.status === 'ok') {
            if (res.job) renderDetails(res.job);
            alert('Pakalpojumi saglabāti.');
          } else {
            alert((res && res.message) ? res.message : 'Neizdevās saglabāt pakalpojumus.');
          }
        })
        .catch(() => alert('Neizdevās saglabāt pakalpojumus.'));
    });

    // Izveidot rēķinu (ar dubultklikšķa aizsardzību)
    const makeBtn = details.querySelector('#make-invoice');
    makeBtn.addEventListener('click', () => {
      const services = readSrvRows(tbody);
      if (!services.length || services.every(r => !r.name || Number(r.qty) <= 0 || Number(r.price) < 0)) {
        alert('Pievieno vismaz vienu aizpildītu pakalpojuma rindu.');
        return;
      }
      makeBtn.disabled = true;
      makeBtn.textContent = 'Veido rēķinu…';

      api({ action: 'create_invoice_from_job', job_id: j.id, services_json: JSON.stringify(services) })
        .then(res => {
          const box = details.querySelector('#invoice-result');
          if (res && res.status === 'ok' && res.invoice) {
            box.innerHTML = `<div class="success">Rēķins izveidots: <strong>${escapeHtml(res.invoice.number || res.invoice.id)}</strong></div>`;
            // Darbs tiek arhivēts serverī un pazūd no saraksta
            details.innerHTML = `<p>Darbs nosūtīts uz rēķinu paneli.</p>`;
            listEl.querySelectorAll('.job-card').forEach(c => c.classList.remove('active'));
            loadList(); // pārlādē sarakstu
          } else {
            box.innerHTML = `<div class="error">${escapeHtml(res && res.message ? res.message : 'Neizdevās izveidot rēķinu')}</div>`;
            makeBtn.disabled = false;
            makeBtn.textContent = 'Izveidot rēķinu';
          }
        })
        .catch(() => {
          const box = details.querySelector('#invoice-result');
          box.innerHTML = `<div class="error">Neizdevās izveidot rēķinu.</div>`;
          makeBtn.disabled = false;
          makeBtn.textContent = 'Izveidot rēķinu';
        });
    });

    recalcTotals(details);
  }

  function addSrvRow(tbody, row) {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="padding:6px; border-bottom:1px solid #f0f0f0;">
        <input type="text" class="srv-name" placeholder="Nosaukums" value="${escapeHtml(row.name || '')}" style="width:100%;">
      </td>
      <td style="padding:6px; border-bottom:1px solid #f0f0f0; text-align:right;">
        <input type="number" class="srv-qty" min="0" step="0.01" value="${Number(row.qty ?? 1)}" style="width:100px; text-align:right;">
      </td>
      <td style="padding:6px; border-bottom:1px solid #f0f0f0; text-align:right;">
        <input type="number" class="srv-price" min="0" step="0.01" value="${Number(row.price ?? 0)}" style="width:120px; text-align:right;">
      </td>
      <td style="padding:6px; border-bottom:1px solid #f0f0f0; text-align:center;">
        <button type="button" class="srv-del btn btn-danger" title="Dzēst">✕</button>
      </td>
    `;
    tbody.appendChild(tr);
  }

  function readSrvRows(tbody) {
    const rows = [];
    tbody.querySelectorAll('tr').forEach(tr => {
      const name  = tr.querySelector('.srv-name')?.value.trim() || '';
      const qty   = parseFloat(tr.querySelector('.srv-qty')?.value || '0');
      const price = parseFloat(tr.querySelector('.srv-price')?.value || '0');
      if (name && isFinite(qty) && qty > 0 && isFinite(price) && price >= 0) {
        rows.push({ name, qty, price });
      }
    });
    return rows;
  }

  function recalcTotals(root) {
    const rows = readSrvRows(root.querySelector('#srv-rows'));
    const subtotal = rows.reduce((s, r) => s + r.qty * r.price, 0);
    const vat = 0.00; // informatīvi (serveris rēķina īsto PVN)
    const total = subtotal + vat;
    root.querySelector('#subtotal').textContent = money(subtotal);
    root.querySelector('#vat').textContent = money(vat);
    root.querySelector('#total').textContent = money(total);
  }

  // --------------- Loaders ---------------
  function loadList(selectNewest = false) {
    api({ action: 'list_jobs' }).then(res => {
      if (!res || res.status !== 'ok') {
        listEl.innerHTML = '<p>Kļūda ielādējot darbus.</p>';
        return;
      }
      const jobs = res.jobs || [];
      renderList(jobs);
      if (selectNewest && jobs.length) {
        const firstCard = listEl.querySelector('.job-card');
        if (firstCard) { selectCard(firstCard); loadDetails(firstCard.dataset.jobId); }
      }
    }).catch(() => {
      listEl.innerHTML = '<p>Kļūda ielādējot darbus.</p>';
    });
  }

  function loadDetails(jobId) {
    api({ action: 'get_job', job_id: jobId }).then(res => {
      if (res && res.status === 'ok' && res.job) renderDetails(res.job);
      else details.innerHTML = '<p>Kļūda ielādējot darba detaļas.</p>';
    }).catch(() => {
      details.innerHTML = '<p>Kļūda ielādējot darba detaļas.</p>';
    });
  }

  // --------------- Create Job form ---------------
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const payload = {
        action: 'create_job',
        client_id: fd.get('client_id') || '',
        comment: fd.get('comment') || ''
      };
      if (!payload.client_id) { alert('Lūdzu izvēlieties klientu.'); return; }

      api(payload).then(res => {
        if (res && res.status === 'ok') {
          form.reset();
          if (formWrap) formWrap.style.display = 'none';
          loadList(true);
        } else {
          alert((res && res.message) ? res.message : 'Neizdevās izveidot darbu.');
        }
      }).catch(() => alert('Neizdevās izveidot darbu.'));
    });
  }

  if (toggleBtn && formWrap) {
    toggleBtn.addEventListener('click', () => {
      const v = formWrap.style.display;
      formWrap.style.display = (!v || v === 'none') ? 'block' : 'none';
    });
  }

  // Start
  loadList();
})();
