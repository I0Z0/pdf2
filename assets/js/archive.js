// ===== ARCHIVE PANEL =====

let selectedClientId = null;
let filteredClients = clientsList.slice();

// Uzrāda klientu sarakstu
function renderClientList() {
    const cont = document.getElementById('client-list');
    cont.innerHTML = '';
    filteredClients.forEach(client => {
        const div = document.createElement('div');
        div.className = 'client-card';
        div.dataset.id = client.id;
        div.innerHTML = (client.company_name || (client.first_name + ' ' + client.last_name));
        if (client.id == selectedClientId) div.classList.add('active');
        div.onclick = () => {
            selectedClientId = client.id;
            document.querySelectorAll('.client-card').forEach(x => x.classList.remove('active'));
            div.classList.add('active');
            renderClientDetails(client.id);
        };
        cont.appendChild(div);
    });
}

// Uzrāda klienta detaļas (informācija + arhivētie rēķini)
function renderClientDetails(clientId) {
    const det = document.getElementById('archive-details');
    const client = clientsList.find(c => c.id == clientId);
    if (!client) { 
        det.innerHTML = "<p>Kļūda: klients nav atrasts!</p>";
        return;
    }

    // Dinamiska lauku forma atkarībā no klienta veida (juridiska / fiziska persona)
    let fieldsForm = '';
    if (client.company_name !== undefined) {
        // Juridiskā persona (uzņēmums)
        fieldsForm = `
        <form id="edit-client-form" class="editing">
            <p><b>Uzņēmuma nosaukums:</b> <input name="company_name" value="${client.company_name || ''}" required></p>
            <p><b>Reģistrācijas Nr.:</b> <input name="registration_no" value="${client.registration_no || ''}" required></p>
            <p><b>PVN Nr.:</b> <input name="vat_no" value="${client.vat_no || ''}"></p>
            <p><b>Banka:</b> <input name="bank" value="${client.bank || ''}"></p>
            <p><b>Konta Nr.:</b> <input name="account_no" value="${client.account_no || ''}"></p>
            <p><b>Adrese:</b> <input name="address" value="${client.address || ''}"></p>
            <p><b>Tālrunis:</b> <input name="phone" value="${client.phone || ''}" required></p>
            <p><b>E‑pasts:</b> <input name="email" value="${client.email || ''}"></p>
            <p><b>Piezīme:</b> <textarea name="comment" rows="2">${client.comment || ''}</textarea></p>
            <button type="submit" class="btn">Saglabāt</button>
            <button type="button" class="btn" style="background:#e44;" onclick="deleteClient(${client.id})">Dzēst klientu</button>
        </form>`;
    } else {
        // Fiziskā persona
        fieldsForm = `
        <form id="edit-client-form" class="editing">
            <p><b>Vārds:</b> <input name="first_name" value="${client.first_name || ''}" required></p>
            <p><b>Uzvārds:</b> <input name="last_name" value="${client.last_name || ''}" required></p>
            <p><b>Personas kods:</b> <input name="personal_code" value="${client.personal_code || ''}"></p>
            <p><b>Banka:</b> <input name="bank" value="${client.bank || ''}"></p>
            <p><b>Konta Nr.:</b> <input name="account_no" value="${client.account_no || ''}"></p>
            <p><b>Tālrunis:</b> <input name="phone" value="${client.phone || ''}" required></p>
            <p><b>Adrese:</b> <input name="address" value="${client.address || ''}"></p>
            <p><b>E‑pasts:</b> <input name="email" value="${client.email || ''}"></p>
            <p><b>Piezīme:</b> <textarea name="comment" rows="2">${client.comment || ''}</textarea></p>
            <button type="submit" class="btn">Saglabāt</button>
            <button type="button" class="btn" style="background:#e44;" onclick="deleteClient(${client.id})">Dzēst klientu</button>
        </form>`;
    }

    det.innerHTML = `
      <h3>${client.company_name || (client.first_name + ' ' + client.last_name)}</h3>
      <div id="client-fields">${fieldsForm}</div>
      <hr>
      <div class="archive-invoices">
        <h4>Arhivētie rēķini</h4>
        <div id="archive-invoice-list"></div>
      </div>`;

    // Uzreiz parāda arhivēto rēķinu sarakstu šim klientam
    renderArchiveInvoices(clientId);

    // Saglabāt klienta izmaiņas (gan fiziskas, gan juridiskas personas lauki)
    const form = det.querySelector('#edit-client-form');
    if (form) {
        form.onsubmit = function(e) {
            e.preventDefault();
            const postData = new URLSearchParams();
            postData.append('action', 'edit_client');
            postData.append('id', client.id);
            ['company_name','first_name','last_name','registration_no','vat_no',
             'bank','account_no','address','phone','email','personal_code','comment']
                .forEach(k => { if (form[k]) postData.append(k, form[k].value); });
            fetch('includes/process_archive.php', { method:'POST', body:postData })
            .then(r => r.json())
            .then(j => {
                if (!j.success) {
                    alert('Kļūda: ' + j.error);
                    return;
                }
                // Atjaunina lokālo klienta objektu un pārzīmē informāciju
                Object.assign(client, j.client);
                renderClientDetails(client.id);
                renderClientList();
            });
        };
    }

    // Dzēst klientu (ar visu saistīto informāciju)
    window.deleteClient = function(clientId) {
        if (!confirm("Vai tiešām dzēst šo klientu? Tiks dzēsta visa ar šo klientu saistītā informācija — visi rēķini, arhīva dati u.c.!")) return;
        fetch('includes/process_archive.php', {
            method: 'POST',
            body: 'action=delete_client&id=' + encodeURIComponent(clientId),
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}
        })
        .then(r => r.json())
        .then(j => {
            if (!j.success) {
                alert('Kļūda: ' + j.error);
                return;
            }
            // Neliels aiztures laiks pirms lapas pārlādes (UX uzlabošanai)
            setTimeout(() => location.reload(), 300);
        });
    };
}

// Funkcija arhivēto rēķinu saraksta attēlošanai
function renderArchiveInvoices(clientId) {
    const cont = document.getElementById('archive-invoice-list');
    const myInvs = archiveList.filter(inv => inv.client_id == clientId);
    if (!myInvs.length) {
        cont.innerHTML = "<i>Nav arhivētu rēķinu.</i>";
        return;
    }
    cont.innerHTML = '';
    myInvs.forEach(inv => {
        cont.innerHTML += `
          <div class="archive-invoice">
            <b>Rēķins #${inv.invoice_no || inv.id}</b>
            <span style="margin-left:10px">Datums: ${inv.invoice_time || inv.date || ''}</span>
            <span style="margin-left:10px">Kopsumma: ${inv.total || inv.sum || ''} €</span>
            <span class="actions">
                <a href="assets/invoices/${inv.pdf_file || (inv.id + '.pdf')}" target="_blank" class="btn">Lejupielādēt</a>
            </span>
          </div>
        `;
    });
}

// Inicializācija: uzreiz ielādē klientu sarakstu
document.addEventListener('DOMContentLoaded', () => {
    renderClientList();
    const search = document.getElementById('archiveClientSearch');
    if (search) {
        search.addEventListener('input', () => {
            const q = search.value.toLowerCase();
            filteredClients = clientsList.filter(c => {
                const name = c.company_name || (c.first_name + ' ' + c.last_name);
                return name.toLowerCase().includes(q);
            });
            renderClientList();
        });
    }
});
