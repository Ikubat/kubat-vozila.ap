// mjesta.js — M-KILL-VAL-v106 (bez validacija)
(function () {
  const VER = 'M-KILL-VAL-v106';
  console.log('[Mjesta] JS verzija =', VER);
  document.body.setAttribute('data-mjesta-js', VER);

  if (!document.body.classList.contains('mjesta')) return;

  // API baza: kod nas su API skripte u rootu (app → ../, inače ./)
  const baseApi = location.pathname.includes('/app/') ? '../' : './';
  const API = {
    search: baseApi + 'mjesta_search.php',
    create: baseApi + 'mjesta_create.php',
    update: baseApi + 'mjesta_update.php',
    del:    baseApi + 'mjesta_delete.php'
  };

  const $s        = document.getElementById('search');
  const $list     = document.getElementById('list');
  const $btnAdd   = document.getElementById('btnAdd');
  const $fabAdd   = document.getElementById('fabAdd');

  const $wrap     = document.getElementById('mWrap');
  const $title    = document.getElementById('mTitle');
  const $id       = document.getElementById('m_id');
  const $naziv    = document.getElementById('m_naziv');
  const $sifra    = document.getElementById('m_sifra');
  const $kanton   = document.getElementById('m_kanton');
  const $msg      = document.getElementById('mMsg');
  const $save     = document.getElementById('mSave');
  const $cancel   = document.getElementById('mCancel');
  const $close    = document.getElementById('mClose');

  const $infoCount  = document.getElementById('infoCount');
  const $infoFilter = document.getElementById('infoFilter');

  const esc = s =>
    String(s ?? '').replace(/[&<>"']/g, m => ({
      '&':'&amp;',
      '<':'&lt;',
      '>':'&gt;',
      '"':'&quot;'
    }[m]));

  function show(el, yes = true) {
    if (el) el.style.display = yes ? '' : 'none';
  }
  function openWrap() { $wrap.classList.add('show'); }
  function closeWrap() { $wrap.classList.remove('show'); }

  function updateInfo(q, count) {
    if ($infoCount) {
      $infoCount.textContent = 'Prikazano: ' + (count ?? 0);
    }
    if ($infoFilter) {
      $infoFilter.textContent = q ? `Filter: "${q}"` : '';
    }
  }

  async function loadKantoni(preselect = '') {
    if (!window.fillKantonSelect) return;
    window.fillKantonSelect($kanton, preselect);
  }

  async function load(q = '') {
    const url = API.search + (q ? ('?q=' + encodeURIComponent(q)) : '');
    try {
      const r   = await fetch(url, { cache: 'no-store' });
      const txt = await r.text();

      if (!r.ok) {
        console.error('[Mjesta] API error', r.status, r.statusText, txt);
        render([]);
        updateInfo(q, 0);
        if ($infoCount) $infoCount.textContent = 'Greška pri dohvaćanju podataka.';
        return;
      }

      let rows;
      try {
        rows = JSON.parse(txt);
      } catch (e) {
        console.error('[Mjesta] JSON parse error', e, 'RAW:', txt);
        rows = [];
      }

      if (!Array.isArray(rows)) rows = [];
      render(rows);
      updateInfo(q, rows.length);
    } catch (e) {
      console.error('[Mjesta] load error', e);
      render([]);
      updateInfo(q, 0);
      if ($infoCount) $infoCount.textContent = 'Greška pri dohvaćanju podataka.';
    }
  }

  function render(rows) {
    $list.innerHTML = (rows && rows.length)
      ? rows.map(m => `
        <div class="card-row" data-id="${m.id}"
             data-naziv="${esc(m.naziv_mjesta)}"
             data-sifra="${esc(m.porezna_sifra)}"
             data-kanton="${esc(m.kanton)}">
          <div>${esc(m.naziv_mjesta)}</div>
          <div>${esc(m.porezna_sifra)}</div>
          <div>${esc(m.kanton)}</div>
          <div class="acts">
            <button class="act edit" title="Uredi">
              <i class="fa-solid fa-pen"></i>
            </button>
            <button class="act del"  title="Obriši">
              <i class="fa-solid fa-trash"></i>
            </button>
          </div>
        </div>
      `).join('')
      : `<div class="hint">Nema rezultata.</div>`;
  }

  let t = null;
  $s?.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(() => load($s.value.trim()), 200);
  });

  function openNew() {
    $title.textContent = 'Novo mjesto';
    $id.value    = '';
    $naziv.value = '';
    $sifra.value = '';
    loadKantoni('');
    show($msg, false);
    openWrap();
    $naziv.focus();
  }

  function openEdit(row) {
    $title.textContent = 'Uredi mjesto';
    $id.value    = row.dataset.id;
    $naziv.value = row.dataset.naziv;
    $sifra.value = row.dataset.sifra;
    loadKantoni(row.dataset.kanton);
    show($msg, false);
    openWrap();
    $naziv.focus();
  }

  $btnAdd?.addEventListener('click', openNew);
  $fabAdd?.addEventListener('click', openNew);

  $cancel?.addEventListener('click', closeWrap);
  $close?.addEventListener('click', closeWrap);
  $wrap?.addEventListener('click', e => { if (e.target === $wrap) closeWrap(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeWrap(); });

  document.addEventListener('click', async (e) => {
    const row = e.target.closest('.card-row[data-id]');
    const ed  = e.target.closest('.act.edit');
    const dl  = e.target.closest('.act.del');

    if (ed && row) {
      openEdit(row);
      return;
    }

    if (dl && row) {
      const id = +row.dataset.id;
      if (!id) return;
      if (!confirm('Obrisati mjesto #' + id + ' ?')) return;
      try {
        const r = await fetch(API.del, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id })
        });
        const out = await r.json();
        if (out.ok) {
          load($s?.value.trim() || '');
        } else {
          alert('Brisanje nije uspjelo.');
        }
      } catch (err) {
        console.error(err);
        alert('Greška pri brisanju.');
      }
    }
  });

  $save?.addEventListener('click', async () => {
    const body = {
      id: +($id.value || 0) || undefined,
      naziv_mjesta: $naziv.value.trim(),
      porezna_sifra: $sifra.value.trim(),
      kanton: ($kanton.value || '').trim()
    };
    const url = body.id ? API.update : API.create;

    $save.disabled = true;
    show($msg, false);
    try {
      const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const out = await r.json();
      if (out.ok) {
        closeWrap();
        load($s?.value.trim() || '');
      } else {
        $msg.textContent =
          out.error ||
          (out.errors || []).join(', ') ||
          'Greška pri spremanju.';
        show($msg, true);
      }
    } catch {
      $msg.textContent = 'Greška pri spremanju na server.';
      show($msg, true);
    } finally {
      $save.disabled = false;
    }
  });

  // INIT
  load('');
})();


