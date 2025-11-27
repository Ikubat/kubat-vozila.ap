// vrsta.js – CRUD za tablicu vrsta_vozila

(function () {
  if (!document.body.classList.contains('vrste')) return;

  // API skripte su u rootu aplikacije (app → ../, inače ./)
  const baseApi = location.pathname.includes('/app/') ? '../' : './';
  const API = {
    list: baseApi + 'vrsta_list.php',
    create: baseApi + 'vrsta_create.php',
    update: baseApi + 'vrsta_update.php',
    delete: baseApi + 'vrsta_delete.php'
  };

  const $q = document.getElementById('q');
  const $list = document.getElementById('list');
  const $empty = document.getElementById('empty');
  const $infoCount = document.getElementById('infoCount');
  const $infoFilter = document.getElementById('infoFilter');
  const $btnAddVrsta = document.getElementById('btnAddVrsta');

  const $vWrap = document.getElementById('vWrap');
  const $vTitle = document.getElementById('vTitle');
  const $vId = document.getElementById('v_id');
  const $vNaziv = document.getElementById('v_naziv');
  const $vOznaka = document.getElementById('v_oznaka');
  const $vMsg = document.getElementById('vMsg');
  const $vSave = document.getElementById('vSave');
  const $vCancel = document.getElementById('vCancel');
  const $vClose = document.getElementById('vClose');

  const state = {
    all: [],
    filtered: [],
    q: ''
  };

  const esc = s =>
    String(s ?? '').replace(/[&<>"']/g, m => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;'
    }[m]));

  function setEmpty(on) {
    if ($empty) $empty.style.display = on ? 'block' : 'none';
  }

  function updateInfo() {
    const shown = state.filtered.length;
    if ($infoCount) {
      $infoCount.textContent = `Prikazano: ${shown}`;
    }
    if ($infoFilter) {
      $infoFilter.textContent = state.q ? `Filter: "${state.q}"` : '';
    }
  }

  function renderList() {
    // ukloni stare redove (ali ne head-row)
    [...$list.querySelectorAll('.row')].forEach(r => r.remove());

    const rows = state.filtered;
    if (!rows.length) {
      setEmpty(true);
      updateInfo();
      return;
    }

    setEmpty(false);

    const html = rows.map(v => `
      <div class="row" data-id="${v.id}">
        <div>${v.id}</div>
        <div>${esc(v.naziv)}</div>
        <div>${esc(v.oznaka)}</div>
        <div class="acts">
          <button class="act edit" title="Uredi">
            <i class="fa-solid fa-pen"></i>
          </button>
          <button class="act del" title="Obriši">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
      </div>
    `).join('');

    $list.insertAdjacentHTML('beforeend', html);
    updateInfo();
  }

  function applyFilter() {
    const q = state.q.toLowerCase();
    if (!q) {
      state.filtered = [...state.all];
    } else {
      state.filtered = state.all.filter(v =>
        (v.naziv && v.naziv.toLowerCase().includes(q)) ||
        (v.oznaka && v.oznaka.toLowerCase().includes(q))
      );
    }
    renderList();
  }

  async function loadVrste() {
    try {
      const r = await fetch(API.list, { cache: 'no-store' });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const out = await r.json();

      let rows = [];
      if (Array.isArray(out)) {
        rows = out;
      } else if (out && Array.isArray(out.data)) {
        rows = out.data;
      }

      state.all = rows.map(v => ({
        id: parseInt(v.id, 10),
        naziv: v.naziv || '',
        oznaka: v.oznaka || ''
      })).sort((a, b) =>
        String(a.naziv).localeCompare(String(b.naziv), 'hr')
      );

      state.q = $q.value.trim();
      applyFilter();
    } catch (err) {
      console.error('loadVrste error', err);
      state.all = [];
      state.filtered = [];
      renderList();
      if ($infoCount) $infoCount.textContent = 'Greška pri dohvaćanju podataka.';
    }
  }

  // ===== MODAL =====
  function openNew() {
    $vTitle.textContent = 'Nova vrsta';
    $vId.value = '';
    $vNaziv.value = '';
    $vOznaka.value = '';
    $vMsg.style.display = 'none';
    $vSave.disabled = false;
    $vWrap.classList.add('show');
    $vNaziv.focus();
  }

  function openEdit(row) {
    const id = row.dataset.id;
    const cells = row.children;
    $vTitle.textContent = 'Uredi vrstu';
    $vId.value = id;
    $vNaziv.value = cells[1].textContent.trim();
    $vOznaka.value = cells[2].textContent.trim();
    $vMsg.style.display = 'none';
    $vSave.disabled = false;
    $vWrap.classList.add('show');
    $vNaziv.focus();
  }

  function closeModal() {
    $vWrap.classList.remove('show');
  }

  $btnAddVrsta.addEventListener('click', openNew);
  $vClose.addEventListener('click', closeModal);
  $vCancel.addEventListener('click', closeModal);
  $vWrap.addEventListener('click', e => {
    if (e.target === $vWrap) closeModal();
  });

  // ===== SPREMI =====
  $vSave.addEventListener('click', async () => {
    const id = $vId.value ? parseInt($vId.value, 10) : 0;
    const naziv = $vNaziv.value.trim();
    const oznaka = $vOznaka.value.trim();

    if (!naziv) {
      $vMsg.textContent = 'Naziv je obavezan.';
      $vMsg.style.display = 'block';
      return;
    }
    if (!oznaka) {
      $vMsg.textContent = 'Oznaka je obavezna.';
      $vMsg.style.display = 'block';
      return;
    }

    const body = { id, naziv, oznaka };
    const url = id ? API.update : API.create;

    try {
      $vSave.disabled = true;
      const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const out = await r.json();
      $vSave.disabled = false;

      if (!out.ok) {
        $vMsg.textContent = out.error || 'Greška pri spremanju.';
        $vMsg.style.display = 'block';
        return;
      }

      closeModal();
      await loadVrste();
    } catch (err) {
      console.error(err);
      $vSave.disabled = false;
      $vMsg.textContent = 'Greška pri komunikaciji sa serverom.';
      $vMsg.style.display = 'block';
    }
  });

  // ===== DELEGACIJA: EDIT / DELETE =====
  document.addEventListener('click', async e => {
    const row = e.target.closest('.row[data-id]');
    if (!row) return;

    if (e.target.closest('.edit')) {
      openEdit(row);
      return;
    }

    if (e.target.closest('.del')) {
      const id = parseInt(row.dataset.id, 10);
      if (!id) return;
      if (!confirm('Obrisati vrstu #' + id + ' ?\nAko je povezana s markama, brisanje neće biti dozvoljeno.')) {
        return;
      }

      try {
        const r = await fetch(API.delete, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id })
        });
        const out = await r.json();
        if (out.ok) {
          row.remove();
          state.all = state.all.filter(v => v.id !== id);
          state.filtered = state.filtered.filter(v => v.id !== id);
          updateInfo();
          if (!state.filtered.length) setEmpty(true);
        } else {
          alert(out.error || 'Greška pri brisanju.');
        }
      } catch (err) {
        console.error(err);
        alert('Greška pri komunikaciji sa serverom.');
      }
    }
  });

  // ===== FILTER =====
  let t = null;
  $q.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(() => {
      state.q = $q.value.trim();
      applyFilter();
    }, 200);
  });

  // INIT
  loadVrste();
})();
