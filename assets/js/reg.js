document.addEventListener('DOMContentLoaded', () => {

  /* --- Cilņu pārslēgšana --- */
  document.querySelectorAll('.toggle-section').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.toggle-section').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.form-section').forEach(s => s.style.display = 'none');
      btn.classList.add('active');
      document.getElementById(btn.dataset.target).style.display = '';
    });
  });

  /* --- Dinamiska auto pievienošana --- */
  document.querySelectorAll('.add-vehicle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const container = document.getElementById(btn.dataset.container);
      const tpl = document.getElementById('vehicle-template');
      if (container && tpl) {
        const fragment = tpl.content ? tpl.content.cloneNode(true) : document.createRange().createContextualFragment(tpl.innerHTML);
        container.appendChild(fragment);
        container.style.display = '';
      }
    });
  });

  /* --- Auto bloka dzēšana (delegācija) --- */
  document.addEventListener('click', e => {
    if (e.target.classList.contains('remove-vehicle-btn')) {
      const block = e.target.closest('.vehicle-block');
      if (block) block.remove();
    }
  });

  /* --- Esoša klienta izvēle --- */
  const clientSelect = document.getElementById('clientSelect');
  const addBtn       = document.getElementById('addVehicleExistingBtn');
  const cont         = document.getElementById('vehicles-existing');
  if (clientSelect && addBtn && cont) {
    clientSelect.addEventListener('change', function () {
      cont.innerHTML = '';
      if (this.value) {
        cont.style.display = '';
        addBtn.style.display = '';
      } else {
        cont.style.display = 'none';
        addBtn.style.display = 'none';
      }
    });
  }
});           /* v1.1 – AJAX submit noņemts */
