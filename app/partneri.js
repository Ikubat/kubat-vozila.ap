// partneri.js
// - Lista partnera (ime, prezime, kontakt, email, adresa, mjesto)
// - Dodavanje / uređivanje / brisanje
// - Pick mode (?pick=1): klik na red vrati partnera u obracun (setSelectedPartner)
//   + u pick modu i dalje radi dodavanje/uređivanje

// API baza: ista logika kao u mjesta.js i vrsta.js␊␊
// API skripte su u /api/ poddirektoriju (app → ../api/, inače ./api/).
const ROOT_PATH = location.pathname.includes('/app/') ? '../api/' : './api/';
const mjSelect = document.getElementById('p_mjesto');
const MJ_API = ROOT_PATH + 'mjesta_list.php';
loadMjesta();

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, m => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    "\"": "&quot;",
    "'": "&#39;"
  }[m]));
}

async function loadMjesta() {
  if (!mjSelect) return;
  try {
    const r = await fetch(MJ_API, { cache: 'no-store' });
    const text = await r.text();
    let out;
    try {
      out = JSON.parse(text);
    } catch (e) {
      console.error('mjesta_list nije JSON:', text);
      mjSelect.innerHTML = '<option value="">(greška učitavanja mjesta)</option>';
      return;
    }

    const rows = out.data || out.rows || (Array.isArray(out) ? out : []);
    if (!rows.length) {
      mjSelect.innerHTML = '<option value="">(nema mjesta)</option>';
      return;
    }

    mjSelect.innerHTML =
      '<option value="">— odaberi mjesto —</option>' +
      rows.map(m => {
        const id =
          m.id ??
          m.ID ??
          m.mjesto_id ??
          m.Id ??
          null;

        const name =
          m.naziv ??
          m.mjesto ??
          m.naziv_mjesta ??
          m.name ??
          '';

        if (!id || !name) return '';
        return `<option value="${esc(id)}">${esc(name)}</option>`;
      }).join('');

  } catch (e) {
    console.error('Greška mjesta_list:', e);
    mjSelect.innerHTML = '<option value="">(greška učitavanja mjesta)</option>';
  }
}

// pozovi odmah na loadu
loadMjesta();



(function () {
  if (!document.body.classList.contains('partneri')) return;

  // -------- API base --------
const baseApi = ROOT_PATH;

  const API = {
    list:        baseApi + 'partneri_list.php',
    create:      baseApi + 'partneri_create.php',
    update:      baseApi + 'partneri_update.php',
    delete:      baseApi + 'partneri_delete.php',
    mjesta_list: baseApi + 'mjesta_list.php',
    mjesta_create: baseApi + 'mjesta_create.php',
    vrste_list: baseApi + 'vrsta_partnera_list.php',
    vrste_create: baseApi + 'vrsta_partnera_create.php'
  };

  // -------- pick mode --------
  const params   = new URLSearchParams(window.location.search);
  const pickMode = params.get('pick') === '1';

  // -------- elementi --------
  const $q       = document.getElementById('q');
  const $list    = document.getElementById('list');
  const $empty   = document.getElementById('empty');
  const $pageInfo= document.getElementById('pageInfo');
  const $addTop  = document.getElementById('btnAddTop');
  const $headRow = document.getElementById('headRow');

  // modal partner
  const $wrap    = document.getElementById('pWrap');
  const $pTitle  = document.getElementById('pTitle');
  const $pId     = document.getElementById('p_id');
  const $pIme    = document.getElementById('p_ime');
  const $pPrez   = document.getElementById('p_prezime');
  const $pVrsta  = document.getElementById('p_vrsta');
  const $pIdBroj = document.getElementById('p_id_broj');
  const $pBrojR  = document.getElementById('p_broj_racuna');
  const $pKont   = document.getElementById('p_kontakt');
  const $pEmail  = document.getElementById('p_email');
  const $pAdr    = document.getElementById('p_adresa');
  const $pMj     = document.getElementById('p_mjesto');
  const $pMsg    = document.getElementById('p_msg');
  const $pSave   = document.getElementById('pSave');
  const $pCancel = document.getElementById('pCancel');
  const $pClose  = document.getElementById('pClose');

  // mini modal mjesto
  const $dlgMj   = document.getElementById('dlgMjesto');
  const $mNaziv  = document.getElementById('m_naziv');
  const $mSifra  = document.getElementById('m_sifra');
  const $mKanton = document.getElementById('m_kanton');
  const $mMsg    = document.getElementById('mMsg');
  const $mSave   = document.getElementById('mSave');
  const $mCancel = document.getElementById('mCancel');
  const $mClose  = document.getElementById('mClose');
  const $btnAddMjesto = document.getElementById('btnAddMjesto');
  const $btnAddVrsta = document.getElementById('btnAddVrsta');

  // mini modal vrsta partnera
  const $dlgVrsta = document.getElementById('dlgVrsta');
  const $vNaziv   = document.getElementById('v_naziv');
  const $vMsg     = document.getElementById('vMsg');
  const $vSave    = document.getElementById('vSave');
  const $vCancel  = document.getElementById('vCancel');
  const $vClose   = document.getElementById('vClose');

  const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'
  }[m]));

  let allRows = [];
  let mjesta = [];
  let vrstePartnera = [];

  const columns = [
    { key: 'ime',     label: 'Ime',            className: 'c-ime',     get: p => p.ime || '' },
    { key: 'prezime', label: 'Prezime',        className: 'c-prezime', get: p => p.prezime || '' },
    { key: 'vrsta',   label: 'Vrsta partnera', className: 'c-vrsta',   get: p => p.vrsta_partnera || p.vrsta || '' },
    { key: 'idBroj',  label: 'ID broj',        className: 'c-idbroj',  get: p => p.id_broj || p.idbroj || '' },
    { key: 'brojRac', label: 'Broj računa',    className: 'c-brojrac', get: p => p.broj_racuna || p.brojracuna || '' },
    { key: 'kontakt', label: 'Kontakt',        className: 'c-kontakt', get: p => p.kontakt || p.telefon || '' },
    { key: 'email',   label: 'Email',          className: 'c-email',   get: p => p.email || '' },
    { key: 'adresa',  label: 'Adresa',         className: 'c-adresa',  get: p => p.adresa || '' },
    { key: 'mjesto',  label: 'Mjesto',         className: 'c-mjesto',  get: p => p.mjesto || '' },
  ];
  const columnGetters = columns.reduce((acc, col) => {
    acc[col.key] = col.get;
    return acc;
  }, {});

  function renderHeader() {
    if (!$headRow) return;
    const headerCells = columns.map(col => `<div>${esc(col.label)}</div>`);
    headerCells.push('<div class="acts-head" aria-hidden="true">Akcije</div>');
    $headRow.innerHTML = headerCells.join('');
  }

  renderHeader();

  // stub za slučaj da nije iz obračuna
  if (typeof window.setSelectedPartner !== 'function') {
    window.setSelectedPartner = function () {};
  }

  // -------- Vrste partnera: load za dropdown --------
  async function loadVrstePartnera() {
    if (!$pVrsta) return;
    try {
      const r = await fetch(API.vrste_list, { cache: 'no-store' });
      const t = await r.text();
      let out;
      try { out = JSON.parse(t); } catch (e) {
        console.error('vrsta_partnera_list nije JSON:', t);
        return;
      }
      if (!out.ok) {
        console.error('vrsta_partnera_list greška:', out.error);
        return;
      }
      vrstePartnera = out.data || out.rows || [];
      vrstePartnera.sort((a, b) => String(a.naziv || '').localeCompare(String(b.naziv || ''), 'hr'));
      const opts = ['<option value="">— odaberi vrstu —</option>'];
      for (const v of vrstePartnera) {
        if (!v || !v.naziv) continue;
        opts.push(`<option value="${esc(v.naziv)}" data-id="${esc(v.id ?? '')}">${esc(v.naziv)}</option>`);
      }
      $pVrsta.innerHTML = opts.join('');
    } catch (err) {
      console.error('Greška pri dohvaćanju vrsta partnera:', err);
    }
  }

  function selectVrstaByText(naziv) {
    if (!$pVrsta || !naziv) return;
    $pVrsta.value = naziv;
    if ($pVrsta.value === naziv) return;
    const opt = Array.from($pVrsta.options).find(o => o.textContent.trim() === naziv.trim());
    if (opt) $pVrsta.value = opt.value;
    else {
      const extra = document.createElement('option');
      extra.value = naziv;
      extra.textContent = naziv;
      $pVrsta.appendChild(extra);
      $pVrsta.value = naziv;
    }
  }

  // -------- Mjesta: load za dropdown --------
  async function loadMjesta() {
    if (!$pMj) return;
    try {
      const r = await fetch(API.mjesta_list, { cache: 'no-store' });
      const t = await r.text();
      let out;
      try { out = JSON.parse(t); } catch (e) {
        console.error('mjesta_list nije JSON:', t);
        return;
      }
      if (!out.ok) {
        console.error('mjesta_list greška:', out.error);
        return;
      }
      mjesta = out.data || out.rows || [];
      mjesta.sort((a,b)=> String(a.naziv||a.mjesto||'').localeCompare(String(b.naziv||b.mjesto||''), 'hr'));
      $pMj.innerHTML = '<option value="">— odaberi mjesto —</option>' +
        mjesta.map(m => `<option value="${esc(m.id)}">${esc(m.naziv || m.mjesto || '')}</option>`).join('');
    } catch (err) {
      console.error('Greška pri dohvaćanju mjesta:', err);
    }
  }

  // pomoć: set dropdown po id-u ili nazivu
  function selectMjestoByData(mjesto_id, mjestoNaziv) {
    if (!$pMj) return;
    if (mjesto_id) {
      $pMj.value = String(mjesto_id);
      if ($pMj.value === String(mjesto_id)) return;
    }
    if (mjestoNaziv) {
      const opt = Array.from($pMj.options).find(o => o.textContent.trim() === mjestoNaziv.trim());
      if (opt) $pMj.value = opt.value;
    }
  }

  // -------- render liste --------
  function renderList(rows) {
    if (!$list) return;

    if (!rows || !rows.length) {
      $list.innerHTML = '';
      $list.innerHTML = '';
      if ($empty) $empty.style.display = 'block';
      if ($pageInfo) $pageInfo.textContent = 'Prikazano: 0';
      return;
    }

    if ($empty) $empty.style.display = 'none';
    if ($pageInfo) $pageInfo.textContent = 'Prikazano: ' + rows.length;

    const bodyHtml = rows.map(p => {
      const ime     = p.ime || '';
      const prezime = p.prezime || '';
      const kontakt = p.kontakt || p.telefon || '';
      const email   = p.email || '';
      const adresa  = p.adresa || '';
      const vrsta   = p.vrsta_partnera || p.vrsta || '';
      const idBroj  = p.id_broj || p.idbroj || '';
      const brojR   = p.broj_racuna || p.brojracuna || '';
      const mjesto  = p.mjesto || '';
      const label   = (ime || prezime) ? (ime + ' ' + prezime).trim() : (p.naziv || '');

      return `
        <div class="card-row${pickMode ? ' pickable' : ''}"
             data-id="${esc(p.id)}"
             data-ime="${esc(ime)}"
             data-prezime="${esc(prezime)}"
             data-vrsta="${esc(vrsta)}"
             data-idbroj="${esc(idBroj)}"
             data-broj-racuna="${esc(brojR)}"
             data-kontakt="${esc(kontakt)}"
             data-email="${esc(email)}"
             data-adresa="${esc(adresa)}"
             data-mjesto="${esc(mjesto)}"
             data-label="${esc(label)}">
          <div class="c-ime">${esc(ime)}</div>
          <div class="c-prezime">${esc(prezime)}</div>
          <div class="c-vrsta">${esc(vrsta)}</div>
          <div class="c-idbroj">${esc(idBroj)}</div>
          <div class="c-brojrac">${esc(brojR)}</div>
          <div class="c-kontakt">${esc(kontakt)}</div>
          <div class="c-email">${esc(email)}</div>
          <div class="c-adresa">${esc(adresa)}</div>
          <div class="c-mjesto">${esc(mjesto)}</div>
          <div class="acts">
            <button class="act edit" title="Uredi"><i class="fa-solid fa-pen"></i></button>
            <button class="act del"  title="Obriši"><i class="fa-solid fa-trash"></i></button>
          </div>
        </div>
      `;
    }).join('');

    $list.innerHTML = bodyHtml;
  }

  // -------- dohvat liste --------
  async function loadList() {
    try {
      const res = await fetch(API.list, { cache: 'no-store' });
      const text = await res.text();

      let out;
      try {
        out = JSON.parse(text);
      } catch (e) {
        console.error('partneri_list nije JSON:', text);
        allRows = [];
        renderList(allRows);
        if ($empty) {
          $empty.style.display = 'block';
          $empty.textContent = 'Greška: partneri_list.php ne vraća ispravan JSON.';
        }
        return;
      }

      if (!out.ok) {
        console.error('partneri_list greška:', out.error);
        allRows = [];
        renderList(allRows);
        if ($empty) {
          $empty.style.display = 'block';
          $empty.textContent = 'Greška: ' + (out.error || 'Neuspješno dohvaćanje partnera.');
        }
        return;
      }

      allRows = out.data || out.rows || [];
      console.log('[PARTNERI] sample red:', allRows[0]);
      renderList(allRows);

    } catch (err) {
      console.error('Greška pri dohvaćanju partnera:', err);
      allRows = [];
      renderList(allRows);
      if ($empty) {
        $empty.style.display = 'block';
        $empty.textContent = 'Greška pri komunikaciji sa serverom.';
      }
    }
  }

  // -------- lokalni filter --------
  if ($q) {
    let t = null;
    $q.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        const term = $q.value.trim().toLowerCase();
        if (!term) {
          renderList(allRows);
          return;
        }
        const rows = allRows.filter(p => {
          return (
            String(p.id).includes(term) ||
            (p.ime      && p.ime.toLowerCase().includes(term)) ||
            (p.prezime  && p.prezime.toLowerCase().includes(term)) ||
            (p.vrsta_partnera && p.vrsta_partnera.toLowerCase().includes(term)) ||
            (p.id_broj && String(p.id_broj).toLowerCase().includes(term)) ||
            (p.broj_racuna && String(p.broj_racuna).toLowerCase().includes(term)) ||
            (p.kontakt  && p.kontakt.toLowerCase().includes(term)) ||
            (p.email    && p.email.toLowerCase().includes(term)) ||
            (p.adresa   && p.adresa.toLowerCase().includes(term)) ||
            (p.mjesto   && p.mjesto.toLowerCase().includes(term))
          );
        });
        renderList(rows);
      }, 200);
    });
  }

  // -------- modal partner open/close --------
  function openNew() {
    if (!$wrap) return;
    $pTitle && ($pTitle.textContent = 'Novi partner');
    $pId && ($pId.value = '');
    $pIme && ($pIme.value = '');
    $pPrez && ($pPrez.value = '');
    $pVrsta && ($pVrsta.value = '');
    $pIdBroj && ($pIdBroj.value = '');
    $pBrojR && ($pBrojR.value = '');
    $pKont && ($pKont.value = '');
    $pEmail && ($pEmail.value = '');
    $pAdr && ($pAdr.value = '');
    if ($pMj) $pMj.value = '';
    if ($pMsg) { $pMsg.textContent = ''; $pMsg.style.display = 'none'; }
    $wrap.classList.add('show');
    $pIme && $pIme.focus();
  }

  function openEdit(row) {
    if (!$wrap || !row) return;

    const d = row.dataset;

    $pTitle && ($pTitle.textContent = 'Uredi partnera');
    $pId && ($pId.value = d.id || '');
    $pIme && ($pIme.value = d.ime || row.querySelector('.c-ime')?.textContent.trim() || '');
    $pPrez && ($pPrez.value = d.prezime || row.querySelector('.c-prezime')?.textContent.trim() || '');
    $pVrsta && selectVrstaByText(d.vrsta || row.querySelector('.c-vrsta')?.textContent.trim() || '');
    $pIdBroj && ($pIdBroj.value = d.idbroj || row.querySelector('.c-idbroj')?.textContent.trim() || '');
    $pBrojR && ($pBrojR.value = d.brojRacuna || row.querySelector('.c-brojrac')?.textContent.trim() || '');
    $pKont && ($pKont.value = d.kontakt || row.querySelector('.c-kontakt')?.textContent.trim() || '');
    $pEmail && ($pEmail.value = d.email || row.querySelector('.c-email')?.textContent.trim() || '');
    $pAdr && ($pAdr.value = d.adresa || row.querySelector('.c-adresa')?.textContent.trim() || '');

    if ($pMj) {
      const mj = d.mjesto || row.querySelector('.c-mjesto')?.textContent.trim() || '';
      // pokušaj pronaći po nazivu (ako nema id)
      selectMjestoByData(d.mjesto_id, mj);
    }

    if ($pMsg) { $pMsg.textContent = ''; $pMsg.style.display = 'none'; }
    $wrap.classList.add('show');
    $pIme && $pIme.focus();
  }

  function closeModal() {
    if ($wrap) $wrap.classList.remove('show');
  }

  if ($addTop) {
    $addTop.addEventListener('click', openNew); // radi i u pickMode
  }
  $pCancel?.addEventListener('click', closeModal);
  $pClose?.addEventListener('click', closeModal);
  $wrap?.addEventListener('click', e => { if (e.target === $wrap) closeModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

  // -------- spremi partnera --------
  async function savePartner() {
    if (!$pIme && !$pPrez) return;

    const ime     = ($pIme?.value || '').trim();
    const prezime = ($pPrez?.value || '').trim();
    if (!ime && !prezime) {
      if ($pMsg) { $pMsg.textContent = 'Ime ili prezime je obavezno.'; $pMsg.style.display = 'block'; }
      return;
    }

    const body = {
      id:       $pId && $pId.value ? parseInt($pId.value, 10) : undefined,
      ime:      ime,
      prezime:  prezime,
      vrsta_partnera: $pVrsta ? $pVrsta.value.trim() : '',
      id_broj:  $pIdBroj ? $pIdBroj.value.trim() : '',
      broj_racuna: $pBrojR ? $pBrojR.value.trim() : '',
      kontakt:  $pKont ? $pKont.value.trim() : '',
      email:    $pEmail ? $pEmail.value.trim() : '',
      adresa:   $pAdr ? $pAdr.value.trim() : '',
      mjesto_id:$pMj && $pMj.value ? parseInt($pMj.value,10) : null
    };

    const url = body.id ? API.update : API.create;

    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const text = await res.text();

      let out;
      try { out = JSON.parse(text); }
      catch (e) {
        console.error('savePartner nije JSON:', text);
        if ($pMsg) { $pMsg.textContent = 'Server nije vratio ispravan odgovor.'; $pMsg.style.display = 'block'; }
        return;
      }

      if (!out.ok) {
        if ($pMsg) { $pMsg.textContent = out.error || 'Greška pri spremanju.'; $pMsg.style.display = 'block'; }
        return;
      }

      closeModal();
      await loadList();

    } catch (err) {
      console.error('Greška pri spremanju partnera:', err);
      if ($pMsg) { $pMsg.textContent = 'Greška pri komunikaciji sa serverom.'; $pMsg.style.display = 'block'; }
    }
  }

  if ($pSave) {
    $pSave.addEventListener('click', savePartner);
  }

  // -------- mini modal mjesto --------
  function openMjestoModal() {
    if (!$dlgMj) return;
    $mNaziv && ($mNaziv.value = '');
    $mSifra && ($mSifra.value = '');
    $mKanton && ($mKanton.value = '');
    if ($mMsg) { $mMsg.textContent = ''; $mMsg.style.display = 'none'; }
    $dlgMj.classList.add('show');
    $mNaziv && $mNaziv.focus();
  }
  function closeMjestoModal() {
    if ($dlgMj) $dlgMj.classList.remove('show');
  }

  async function saveMjesto() {
    const naziv = ($mNaziv?.value || '').trim();
    if (!naziv) {
      if ($mMsg) { $mMsg.textContent = 'Naziv mjesta je obavezan.'; $mMsg.style.display = 'block'; }
      return;
    }
    const body = {
      naziv,
      sifra:  $mSifra ? $mSifra.value.trim() : '',
      kanton: $mKanton ? $mKanton.value.trim() : ''
    };
    try {
      const res = await fetch(API.mjesta_create, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const text = await res.text();
      let out;
      try { out = JSON.parse(text); }
      catch (e) {
        console.error('mjesta_create nije JSON:', text);
        if ($mMsg) { $mMsg.textContent = 'Server nije vratio ispravan JSON.'; $mMsg.style.display = 'block'; }
        return;
      }
      if (!out.ok) {
        if ($mMsg) { $mMsg.textContent = out.error || 'Greška pri spremanju mjesta.'; $mMsg.style.display = 'block'; }
        return;
      }
      // ponovno učitaj mjesta i selektiraj novo (ako vraća id)
      await loadMjesta();
      if (out.id && $pMj) {
        $pMj.value = String(out.id);
      }
      closeMjestoModal();
    } catch (err) {
      console.error('Greška pri spremanju mjesta:', err);
      if ($mMsg) { $mMsg.textContent = 'Greška pri komunikaciji sa serverom.'; $mMsg.style.display = 'block'; }
    }
  }

  // -------- mini modal vrsta partnera --------
  function openVrstaModal() {
    if (!$dlgVrsta) {
      console.warn('Nema dijaloga za vrstu partnera.');
      return;
    }
    $vNaziv && ($vNaziv.value = '');
    if ($vMsg) { $vMsg.textContent = ''; $vMsg.style.display = 'none'; }
    // osiguraj da dropdown ima zadnji popis prije dodavanja nove vrste
    if (vrstePartnera.length === 0) {
      loadVrstePartnera();
    }
    $dlgVrsta.classList.add('show');
    $vNaziv && $vNaziv.focus();
  }

  function closeVrstaModal() {
    if ($dlgVrsta) $dlgVrsta.classList.remove('show');
  }

  async function saveVrsta() {
    const naziv = ($vNaziv?.value || '').trim();
    if (!naziv) {
      if ($vMsg) { $vMsg.textContent = 'Naziv vrste je obavezan.'; $vMsg.style.display = 'block'; }
      return;
    }

    try {
      const res = await fetch(API.vrste_create, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ naziv })
      });
      const text = await res.text();
      let out;
      try { out = JSON.parse(text); }
      catch (e) {
        console.error('vrsta_partnera_create nije JSON:', text);
        if ($vMsg) { $vMsg.textContent = 'Server nije vratio ispravan JSON.'; $vMsg.style.display = 'block'; }
        return;
      }
      if (!out.ok) {
        if ($vMsg) { $vMsg.textContent = out.error || 'Greška pri spremanju vrste.'; $vMsg.style.display = 'block'; }
        return;
      }

      await loadVrstePartnera();
      if (out.id && $pVrsta) {
        const byId = Array.from($pVrsta.options).find(o => (o.dataset.id || '') === String(out.id));
        if (byId) {
          $pVrsta.value = byId.value;
        } else {
          selectVrstaByText(naziv);
        }
      } else {
        selectVrstaByText(naziv);
      }
      closeVrstaModal();
    } catch (err) {
      console.error('Greška pri spremanju vrste partnera:', err);
      if ($vMsg) { $vMsg.textContent = 'Greška pri komunikaciji sa serverom.'; $vMsg.style.display = 'block'; }
    }
  }

  if ($btnAddMjesto) $btnAddMjesto.addEventListener('click', openMjestoModal);
  $mCancel?.addEventListener('click', closeMjestoModal);
  $mClose?.addEventListener('click', closeMjestoModal);
  $dlgMj?.addEventListener('click', e => { if (e.target === $dlgMj) closeMjestoModal(); });
  $mSave?.addEventListener('click', saveMjesto);

  if ($btnAddVrsta) {
    $btnAddVrsta.addEventListener('click', (e) => {
      e.preventDefault();
      openVrstaModal();
    });
  }
  $vCancel?.addEventListener('click', closeVrstaModal);
  $vClose?.addEventListener('click', closeVrstaModal);
  $dlgVrsta?.addEventListener('click', e => { if (e.target === $dlgVrsta) closeVrstaModal(); });
  $vSave?.addEventListener('click', saveVrsta);

  // -------- pick helper --------
  function pickPartner(row) {
    const ime     = row.dataset.ime || row.querySelector('.c-ime')?.textContent.trim() || '';
    const prezime = row.dataset.prezime || row.querySelector('.c-prezime')?.textContent.trim() || '';
    const label   = row.dataset.label || (ime + ' ' + prezime).trim() || '';

    const partner = {
      id: row.dataset.id,
      label,
      ime,
      prezime,
      vrsta: row.dataset.vrsta || row.querySelector('.c-vrsta')?.textContent.trim() || '',
      id_broj: row.dataset.idbroj || row.querySelector('.c-idbroj')?.textContent.trim() || '',
      broj_racuna: row.dataset.brojRacuna || row.querySelector('.c-brojrac')?.textContent.trim() || '',
      kontakt: row.dataset.kontakt || row.querySelector('.c-kontakt')?.textContent.trim() || '',
      email:   row.dataset.email   || row.querySelector('.c-email')?.textContent.trim()   || '',
      adresa:  row.dataset.adresa  || row.querySelector('.c-adresa')?.textContent.trim()  || '',
      mjesto:  row.dataset.mjesto  || row.querySelector('.c-mjesto')?.textContent.trim()  || ''
    };

    let sent = false;

    if (window.opener && typeof window.opener.setSelectedPartner === 'function') {
      try {
        window.opener.setSelectedPartner(partner.id, partner.label, partner);
        sent = true;
      } catch (err) {
        console.warn('window.opener.setSelectedPartner error:', err);
      }
    }
    if (!sent && window.parent && window.parent !== window &&
        typeof window.parent.setSelectedPartner === 'function') {
      try {
        window.parent.setSelectedPartner(partner.id, partner.label, partner);
        sent = true;
      } catch (err) {
        console.warn('window.parent.setSelectedPartner error:', err);
      }
    }

    if (!sent) {
      console.warn('[PICK] Nije moguće vratiti partnera (nema opener/parent handlera).');
      alert('Partner odabran, ali nije moguće vratiti podatke u obrazac. '
          + 'Provjeri da je otvoreno iz obrasca obračuna.');
    }

    try { window.close(); } catch (e2) {}
  }

  // -------- klikovi na listi (pick + edit/delete) --------
  document.addEventListener('click', async (e) => {
    if (!$list) return;
    const row = e.target.closest('.card-row[data-id]');
    if (!row) return;

    const id = row.dataset.id;

    // PICK MODE: klik na red (izvan akcija) = odaberi partnera
    if (pickMode && !e.target.closest('.acts') && !e.target.closest('.act')) {
      pickPartner(row);
      return;
    }

    // NORMALNO / PICK: UREDI
    if (e.target.closest('.act.edit')) {
      openEdit(row);
      return;
    }

    // NORMALNO / PICK: BRIŠI
    if (e.target.closest('.act.del')) {
      if (!confirm('Obrisati partnera #' + id + ' ?')) return;
      try {
        const res = await fetch(API.delete, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: parseInt(id, 10) })
        });
        const text = await res.text();
        let out;
        try { out = JSON.parse(text); }
        catch (e2) {
          console.error('delete nije JSON:', text);
          alert('Greška pri brisanju.');
          return;
        }
        if (!out.ok) {
          alert(out.error || 'Greška pri brisanju.');
          return;
        }
        allRows = allRows.filter(p => String(p.id) !== String(id));
        renderList(allRows);
      } catch (err) {
        console.error('Greška pri brisanju:', err);
        alert('Greška pri komunikaciji sa serverom.');
      }
      return;
    }
  });

  // double-click za povrat u pick modu
  $list?.addEventListener('dblclick', (e) => {
    if (!pickMode) return;
    const row = e.target.closest('.card-row[data-id]');
    if (!row || e.target.closest('.acts') || e.target.closest('.act')) return;
    pickPartner(row);
  });

  // -------- init --------
  loadMjesta();
  loadVrstePartnera();
  loadList();

})();



