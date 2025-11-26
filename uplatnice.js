// uplatnice.js – lista + modal za uplatnice
(function () {
  function init() {
    if (!document.body.classList.contains('uplatnice')) return;

    // API baza: pokušaj prvo /api/ pa padni na root (radi lokacije /app/ i /)
    const baseRoots = location.pathname.includes('/app/')
      ? ['../api/', '../']
      : ['./api/', './'];

    const API = {
      list:    'uplatnica_list.php',
      create:  'uplatnica_create.php',
      update:  'uplatnica_update.php',
      delete:  'uplatnica_delete.php',
      partneri:'partneri_list.php',
      svrhe:   'svrha_list.php'
    };

    // ---- DOM ----
    const $q        = document.getElementById('u_q');
    const $list     = document.getElementById('u_list');
    const $empty    = document.getElementById('u_empty');
    const $btnAdd   = document.getElementById('u_btnAdd');
    const $infoCnt  = document.getElementById('u_infoCount');
    const $infoFilt = document.getElementById('u_infoFilter');

    const $wrap   = document.getElementById('u_wrap');
    const $title  = document.getElementById('u_title');
    const $close  = document.getElementById('u_close');
    const $save   = document.getElementById('u_save');
    const $cancel = document.getElementById('u_cancel');
    const $msg    = document.getElementById('u_msg');

    // polja u modalu
    const $id            = document.getElementById('u_id');
    const $uplatilacSel  = document.getElementById('u_uplatilac_id');
    const $primateljSel  = document.getElementById('u_primatelj_id');
    const $btnPickUplat  = document.getElementById('u_pick_uplatilac');
    const $btnPickPrim   = document.getElementById('u_pick_primatelj');
    const $svrhaSel      = document.getElementById('u_svrha_id');

    const $svrha         = document.getElementById('u_svrha');
    const $svrha1        = document.getElementById('u_svrha1');
    const $mjesto        = document.getElementById('u_mjesto');
    const $datum         = document.getElementById('u_datum');
    const $iznos         = document.getElementById('u_iznos');
    const $valuta        = document.getElementById('u_valuta');
    const $racunPos      = document.getElementById('u_racun_pos');
    const $racunPrim     = document.getElementById('u_racun_prim');
    const $brojPorezni   = document.getElementById('u_broj_poreskog');
    const $vrstaPrihoda  = document.getElementById('u_vrsta_prihoda');
    const $opcina        = document.getElementById('u_opcina');
    const $budzetska     = document.getElementById('u_budzetska');
    const $poziv         = document.getElementById('u_poziv');
    const $napomena      = document.getElementById('u_napomena');

    const state = {
      q: '',
      all: [],
      partners: new Map(), // id -> partner
      svrhe: new Map()     // id -> svrha
    };

    let pickTarget = 'uplatilac';

    const esc = s =>
      String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;'
      }[m]));

    function show(el, on) {
      if (!el) return;
      el.style.display = on ? '' : 'none';
    }

    // ---- helper za fetch JSON ----
    async function fetchJson(path, options = {}) {
      let lastErr = null;

      for (const base of baseRoots) {
        const url = path.startsWith('http') || path.startsWith('/')
          ? path
          : base + path;
        try {
          const res = await fetch(url, options);
          if (!res.ok) throw new Error('HTTP ' + res.status);
          return await res.json();
        } catch (err) {
          lastErr = err;
          // pokušaj idući base samo na mrežne greške / 404;
          // ako smo već probali zadnji, baci grešku.
        }
      }

      throw lastErr || new Error('Nepoznata greška pri fetchu.');
    }

    // ---- učitaj partnere (za selecte) ----
    async function loadPartneri() {
      try {
        const out = await fetchJson(API.partneri + '?all=1');
        const rows = Array.isArray(out) ? out : (out.data || out.rows || []);
        state.partners.clear();

        let options = '<option value="">— odaberi —</option>';
        rows.forEach(p => {
          const id = parseInt(p.id, 10);
          if (!id) return;
          // ime + prezime ili naziv
          const label = (p.naziv && p.naziv.trim())
            || [p.ime, p.prezime].filter(Boolean).join(' ')
            || ('Partner #' + id);
          state.partners.set(id, {
            id,
            label,
            racun: p.racun || '',
            porezni_broj: p.porezni_broj || p.porezni || '',
            opcina_sifra: p.opcina_sifra || '',
            mjesto_naziv: p.mjesto_naziv || ''
          });
          options += `<option value="${id}">${esc(label)}</option>`;
        });

        $uplatilacSel.innerHTML = options;
        $primateljSel.innerHTML = options;
      } catch (err) {
        console.error('loadPartneri error', err);
        $uplatilacSel.innerHTML = '<option value="">(greška kod učitavanja)</option>';
        $primateljSel.innerHTML = '<option value="">(greška kod učitavanja)</option>';
      }
    }

    // ---- učitaj svrhe uplate ----
    async function loadSvrhe() {
      try {
        const out = await fetchJson(API.svrhe + '?all=1');
        const rows = Array.isArray(out) ? out : (out.data || out.rows || []);
        state.svrhe.clear();
        let options = '<option value="">— odaberi svrhu —</option>';
        rows.forEach(s => {
          const id = parseInt(s.id, 10);
          if (!id) return;
          const label = s.naziv || ('Svrha #' + id);
          state.svrhe.set(id, {
            id,
            naziv: s.naziv || '',
            naziv2: s.naziv2 || s.naziv_2 || '',
            vrsta_prihoda: s.vrsta_prihoda || '',
            budzetska: s.budzetska_organizacija || s.budzetska || '',
            poziv: s.default_poziv_na_broj || s.poziv_na_broj || ''
          });
          options += `<option value="${id}">${esc(label)}</option>`;
        });
        $svrhaSel.innerHTML = options;
      } catch (err) {
        console.error('loadSvrhe error', err);
        $svrhaSel.innerHTML = '<option value="">(greška kod učitavanja)</option>';
      }
    }

    // ---- popuni polja kad korisnik odabere uplatilaca / primatelja / svrhu ----
    function onUplatilacChange() {
      const id = parseInt($uplatilacSel.value, 10);
      const p = state.partners.get(id);
      if (!p) return;

      if (!$mjesto.value)        $mjesto.value = p.mjesto_naziv || '';
      if (!$racunPos.value)      $racunPos.value = p.racun || '';
      if (!$brojPorezni.value)   $brojPorezni.value = p.porezni_broj || '';
      if (!$opcina.value)        $opcina.value = p.opcina_sifra || '';
    }

    function onPrimateljChange() {
      const id = parseInt($primateljSel.value, 10);
      const p = state.partners.get(id);
      if (!p) return;

      if (!$racunPrim.value) $racunPrim.value = p.racun || '';
    }

    function onSvrhaChange() {
      const id = parseInt($svrhaSel.value, 10);
      const s = state.svrhe.get(id);
      if (!s) return

      if (!$svrha.value)   $svrha.value = s.naziv || '';
      if (!$svrha1.value)  $svrha1.value = s.naziv2 || '';
      if (!$vrstaPrihoda.value) $vrstaPrihoda.value = s.vrsta_prihoda || '';
      if (!$budzetska.value)    $budzetska.value = s.budzetska || '';
      if (!$poziv.value)        $poziv.value = s.poziv || '';
    }

    function ensureOption(select, id, label) {
      if (!select) return;
      const exists = Array.from(select.options).some(o => o.value === String(id));
      if (!exists) {
        const opt = document.createElement('option');
        opt.value = String(id);
        opt.textContent = label || ('Partner #' + id);
        select.appendChild(opt);
      }
    }

    window.setSelectedPartner = function (id, naziv, partner) {
      if (!id) return;
      const target = pickTarget === 'primatelj' ? $primateljSel : $uplatilacSel;
      const label  = naziv || (partner && partner.label) || ('Partner #' + id);

      ensureOption(target, id, label);
      if (target) {
        target.value = String(id);
        target.dispatchEvent(new Event('change'));
      }

      if (partner && partner.id) {
        state.partners.set(Number(partner.id), {
          id: Number(partner.id),
          label: label,
          racun: partner.racun || partner.racun_pos || '',
          porezni_broj: partner.porezni_broj || partner.porezni || '',
          opcina_sifra: partner.opcina_sifra || '',
          mjesto_naziv: partner.mjesto || partner.mjesto_naziv || ''
        });
      }

      if (pickTarget === 'uplatilac') {
        onUplatilacChange();
      } else {
        onPrimateljChange();
      }
    };

    function openPartnerPicker(target) {
      pickTarget = target;
      const w = 1100, h = 800;
      const x = Math.max(0, (screen.width - w) / 2);
      const y = Math.max(0, (screen.height - h) / 2);
      const url = baseRoot + 'partneri.html?pick=1';
      window.open(url, 'pick_partner_' + target,
        `width=${w},height=${h},left=${x},top=${y},resizable=yes,scrollbars=yes`);
    }

    $uplatilacSel.addEventListener('change', onUplatilacChange);
    $primateljSel.addEventListener('change', onPrimateljChange);
    $svrhaSel.addEventListener('change', onSvrhaChange);
    $btnPickUplat?.addEventListener('click', () => openPartnerPicker('uplatilac'));
    $btnPickPrim?.addEventListener('click', () => openPartnerPicker('primatelj'));

    // ---- lista uplatnica ----
    function setEmpty(on) {
      if ($empty) $empty.style.display = on ? 'block' : 'none';
    }

    function updateInfo() {
      if ($infoCnt) {
        const n = state.all.length;
        $infoCnt.textContent = 'Prikazano: ' + n;
      }
      if ($infoFilt) {
        $infoFilt.textContent = state.q ? `Filter: "${state.q}"` : '';
      }
    }

    function renderList() {
      if (!state.all.length) {
        $list.innerHTML = '';
        setEmpty(true);
        updateInfo();
        return;
      }
      setEmpty(false);

      const html = state.all.map(u => `␊
        <div class="row" data-id="${u.id}">␊
          <div>${esc(u.datum || '')}</div>
          <div>${esc(u.uplatilac_naziv || '')}</div>
          <div>${esc(u.primatelj_naziv || '')}</div>
          <div>${esc(u.svrha_tekst || '')}</div>
          <div class="acts">␊
            <button class="act edit" title="Uredi"><i class="fa-solid fa-pen"></i></button>␊
            <button class="act del"  title="Obriši"><i class="fa-solid fa-trash"></i></button>␊
          </div>␊
        </div>␊
      `).join('');

      $list.innerHTML = html;
      updateInfo();
    }

    async function loadUplatnice() {
      try {
        const qs = new URLSearchParams();
        if (state.q) qs.set('q', state.q);
        const out = await fetchJson(API.list + (state.q ? ('?' + qs.toString()) : ''));
        const rows = Array.isArray(out) ? out : (out.data || out.rows || []);
        state.all = rows.map(r => {
          const iznosVal = r.iznos !== undefined ? parseFloat(r.iznos) : null;
          return {
          id: parseInt(r.id, 10),
          datum: r.datum || r.datum_uplate || '',
          uplatilac_id: r.uplatilac_id ? parseInt(r.uplatilac_id, 10) : null,
          uplatilac_naziv: r.uplatilac_naziv || r.uplatilac || '',
          primatelj_id: r.primatelj_id ? parseInt(r.primatelj_id, 10) : null,
          primatelj_naziv: r.primatelj_naziv || r.primatelj || '',
          svrha_id: r.svrha_id ? parseInt(r.svrha_id, 10) : null,
          svrha_tekst: r.svrha_tekst || r.svrha || '',
          svrha1: r.svrha1 || '',
          mjesto: r.mjesto_uplate || r.mjesto || '',
          iznos: Number.isFinite(iznosVal) ? iznosVal : null,
          valuta: r.valuta || '',
          racun_posiljaoca: r.racun_posiljaoca || r.racun_platioca || '',
          racun_primatelja: r.racun_primatelja || r.racun_primaoca || '',
          broj_poreskog_obv: r.broj_poreskog_obv || r.porezni_broj || '',
          vrsta_prihoda_sifra: r.vrsta_prihoda_sifra || r.vrsta_prihoda || '',
          opcina_sifra: r.opcina_sifra || r.opcina || '',
          budzetska_org_sifra: r.budzetska_org_sifra || r.budzetska || '',
          poziv_na_broj: r.poziv_na_broj || r.poziv || '',
          napomena: r.napomena || ''
        };
        });
        renderList();
      } catch (err) {
        console.error('loadUplatnice error', err);
        state.all = [];
        renderList();
        if ($infoCnt) $infoCnt.textContent = 'Greška pri dohvaćanju podataka.';
      }
    }

    // ---- search ----
    let t = null;
    $q.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        state.q = $q.value.trim();
        loadUplatnice();
      }, 250);
    });

    // ---- modal open/close ----
    function clearForm() {
      $id.value = '';
      $uplatilacSel.value = '';
      $primateljSel.value = '';
      $svrhaSel.value = '';
      $svrha.value = '';
      $svrha1.value = '';
      $mjesto.value = '';
      $datum.value = '';
      $iznos.value = '';
      $valuta.value = 'KM';
      $racunPos.value = '';
      $racunPrim.value = '';
      $brojPorezni.value = '';
      $vrstaPrihoda.value = '';
      $opcina.value = '';
      $budzetska.value = '';
      $poziv.value = '';
      $napomena.value = '';
      show($msg, false);
    }

    function openNew() {
      $title.textContent = 'Nova uplatnica';
      clearForm();
      // default današnji datum
      if (!$datum.value) {
        const d = new Date();
        const iso = d.toISOString().slice(0, 10);
        $datum.value = iso;
      }
      $wrap.classList.add('show');
      $uplatilacSel.focus();
    }

    function openEdit(row) {
      const id = parseInt(row.dataset.id, 10);
      const item = state.all.find(x => x.id === id);
      if (!item) return;

      $title.textContent = 'Uredi uplatnicu #' + id;
      $id.value = id;

      $uplatilacSel.value = item.uplatilac_id || '';
      $primateljSel.value = item.primatelj_id || '';
      $svrhaSel.value = item.svrha_id || '';

      $svrha.value = item.svrha_tekst || '';
      $svrha1.value = item.svrha1 || '';
      $mjesto.value = item.mjesto || '';
      $datum.value = item.datum || '';
      $iznos.value = (item.iznos ?? '') === '' ? '' : String(item.iznos);
      $valuta.value = item.valuta || 'KM';
      $racunPos.value = item.racun_posiljaoca || '';
      $racunPrim.value = item.racun_primatelja || '';
      $brojPorezni.value = item.broj_poreskog_obv || '';
      $vrstaPrihoda.value = item.vrsta_prihoda_sifra || '';
      $opcina.value = item.opcina_sifra || '';
      $budzetska.value = item.budzetska_org_sifra || '';
      $poziv.value = item.poziv_na_broj || '';
      $napomena.value = item.napomena || '';

      show($msg, false);
      $wrap.classList.add('show');
    }

    function closeModal() {
      $wrap.classList.remove('show');
    }

    $btnAdd.addEventListener('click', openNew);
    $cancel.addEventListener('click', closeModal);
    $close.addEventListener('click', closeModal);
    $wrap.addEventListener('click', e => {
      if (e.target === $wrap) closeModal();
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeModal();
    });

    // ---- spremi (create / update) ----
    async function save() {
      const id = $id.value ? parseInt($id.value, 10) : 0;

      const body = {
        id: id || undefined,
        uplatilac_id:  $uplatilacSel.value ? parseInt($uplatilacSel.value, 10) : null,
        primatelj_id:  $primateljSel.value ? parseInt($primateljSel.value, 10) : null,
        svrha_id:      $svrhaSel.value ? parseInt($svrhaSel.value, 10) : null,
        svrha:         $svrha.value.trim(),
        svrha1:        $svrha1.value.trim(),
        mjesto_uplate: $mjesto.value.trim(),
        datum_uplate:  $datum.value,
        iznos:         $iznos.value ? parseFloat($iznos.value) : null,
        valuta:        $valuta.value.trim() || 'KM',
        racun_posiljaoca: $racunPos.value.trim(),
        racun_primatelja: $racunPrim.value.trim(),
        broj_poreskog_obv: $brojPorezni.value.trim(),
        vrsta_prihoda_sifra: $vrstaPrihoda.value.trim(),
        opcina_sifra:       $opcina.value.trim(),
        budzetska_org_sifra:$budzetska.value.trim(),
        poziv_na_broj:      $poziv.value.trim(),
        napomena:           $napomena.value.trim()
      };

      if (!body.uplatilac_id) {
        $msg.textContent = 'Uplatilac je obavezan.';
        show($msg, true);
        return;
      }
      if (!body.primatelj_id) {
        $msg.textContent = 'Primatelj je obavezan.';
        show($msg, true);
        return;
      }
      if (!body.svrha) {
        $msg.textContent = 'Svrha uplate je obavezna.';
        show($msg, true);
        return;
      }
      if (!body.iznos || isNaN(body.iznos)) {
        $msg.textContent = 'Unesi ispravan iznos.';
        show($msg, true);
        return;
      }

      try {
        $save.disabled = true;
        const out = await fetchJson((id ? API.update : API.create), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body)
        });
        if (!out.ok && out.error) {
          throw new Error(out.error);
        }
        closeModal();
        await loadUplatnice();
      } catch (err) {
        console.error('save uplatnica error', err);
        $msg.textContent = err.message || 'Greška pri spremanju.';
        show($msg, true);
      } finally {
        $save.disabled = false;
      }
    }

    $save.addEventListener('click', save);

    // ---- delegacija klikova na listu (edit / delete) ----
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
        if (!confirm('Obrisati uplatnicu #' + id + ' ?')) return;
        try {
          const out = await fetchJson(API.delete, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
          });
          if (!out.ok && out.error) {
            throw new Error(out.error);
          }
          row.remove();
          state.all = state.all.filter(u => u.id !== id);
          updateInfo();
        } catch (err) {
          alert(err.message || 'Greška pri brisanju.');
        }
      }
    });

    // ---- inicijalizacija ----
    (async function bootstrap() {
      await Promise.all([loadPartneri(), loadSvrhe()]);
      await loadUplatnice();
    })();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
