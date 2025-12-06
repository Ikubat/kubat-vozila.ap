// uplatnice.js – lista + modal za uplatnice
(function () {
  function init() {
    if (!document.body.classList.contains('uplatnice')) return;

    // API root
    const baseRoots = location.pathname.includes('/app/')
      ? ['../api/', '../']
      : ['./api/', './'];

    const basePageRoot = baseRoots[0].replace(/api\/?$/, '');

    const API = {
      list: 'uplatnica_list.php',
      create: 'uplatnica_create.php',
      update: 'uplatnica_update.php',
      delete: 'uplatnica_delete.php',
      partneri: 'partneri_list.php',
      svrhe: 'svrha_list.php',
      svrhaCreate: 'svrha_create.php'
    };

    // ==== DOM elementi ====
    const $q = document.getElementById('u_q');
    const $list = document.getElementById('u_list');
    const $empty = document.getElementById('u_empty');
    const $btnAdd = document.getElementById('u_btnAdd');
    const $infoCnt = document.getElementById('u_infoCount');
    const $infoFilt = document.getElementById('u_infoFilter');

    const $wrap = document.getElementById('u_wrap');
    const $title = document.getElementById('u_title');
    const $close = document.getElementById('u_close');
    const $save = document.getElementById('u_save');
    const $cancel = document.getElementById('u_cancel');
    const $msg = document.getElementById('u_msg');

    // polja u modalu
    const $id = document.getElementById('u_id');
    const $uplatilacId = document.getElementById('u_uplatilac_id');
    const $uplatilacLabel = document.getElementById('u_uplatilac_label');
    const $uplatilacTekst = document.getElementById('u_uplatilac_tekst');

    const $primateljId = document.getElementById('u_primatelj_id');
    const $primateljLabel = document.getElementById('u_primatelj_label');
    const $primateljTekst = document.getElementById('u_primatelj_tekst');

    const $btnPickUplat = document.getElementById('u_pick_uplatilac');
    const $btnPickPrim = document.getElementById('u_pick_primatelj');

    const $svrhaSel = document.getElementById('u_svrha_id');
    const $svrha = document.getElementById('u_svrha');
    const $svrha1 = document.getElementById('u_svrha1');

    const $mjesto = document.getElementById('u_mjesto');
    const $datum = document.getElementById('u_datum');
    const $iznos = document.getElementById('u_iznos');
    const $valuta = document.getElementById('u_valuta');
    const $racunPos = document.getElementById('u_racun_pos');
    const $racunPrim = document.getElementById('u_racun_prim');
    const $brojPorezni = document.getElementById('u_broj_poreskog');
    const $vrstaPrihoda = document.getElementById('u_vrsta_prihoda');
    const $opcina = document.getElementById('u_opcina');
    const $budzetska = document.getElementById('u_budzetska');
    const $poziv = document.getElementById('u_poziv');
    const $napomena = document.getElementById('u_napomena');

    const $svrhaNewBtn = document.getElementById('u_svrha_new_btn');
    const $svrhaNew = document.getElementById('u_svrha_new');
    const $svrhaNewMsg = document.getElementById('u_svrha_new_msg');
    const $svrhaModal = document.getElementById('u_svrha_modal');
    const $svrhaModalSave = document.getElementById('u_svrha_modal_save');
    const $svrhaModalCancel = document.getElementById('u_svrha_modal_cancel');
    const $svrhaModalClose = document.getElementById('u_svrha_modal_close');

    const state = {
      q: '',
      all: [],
      partners: new Map(), // id -> partner
      svrhe: new Map()
    };

    let pickTarget = 'uplatilac';

    const esc = s =>
      String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      }[m]));

    function show(el, on) {
      if (el) el.style.display = on ? '' : 'none';
    }

    // fetch JSON helper
    async function fetchJson(path, options = {}) {
      let lastErr = null;
      for (let i = 0; i < baseRoots.length; i++) {
        const base = baseRoots[i];
        const isLast = i === baseRoots.length - 1;
        const url = base + path;
        try {
          const res = await fetch(url, options);

          if (!res.ok) {
            let errMsg = 'HTTP ' + res.status;
            try {
              const body = await res.json();
              if (body?.error) errMsg = body.error;
            } catch (_) { }

            const err = new Error(errMsg);
            err.status = res.status;
            if (res.status === 404 && !isLast) {
              lastErr = err;
              continue;
            }
            throw err;
          }

          return await res.json();
        } catch (err) {
          lastErr = err;
          const retryable = err.status === 404 || err.name === 'TypeError';
          if (!retryable || isLast) break;
        }
      }
      throw lastErr || new Error("Fetch error");
    }

    // ==== LOAD PARTNERI ====
    async function loadPartneri() {
      try {
        const out = await fetchJson(API.partneri + '?all=1');
        const rows = Array.isArray(out) ? out : (out.data || []);

        state.partners.clear();

        rows.forEach(p => {
          const id = parseInt(p.id, 10);
          if (!id) return;

          const naziv = p.naziv?.trim() || '';
          const ime = p.ime || '';
          const prezime = p.prezime || '';
          const vrsta = p.vrsta || ''; // Pravno lice, Fizička osoba, Obrt

          const label = naziv || [ime, prezime].filter(Boolean).join(' ') || ('Partner #' + id);

          state.partners.set(id, {
            id,
            label,
            vrsta, // <= KLJUČNO
            broj_racuna: p.broj_racuna || '',
            porezni_broj: p.porezni_broj || '',
            opcina_sifra: p.opcina_sifra || '',
            mjesto_naziv: p.mjesto_naziv || ''
          });
        });

        refreshPartnerLabels();
      } catch (err) {
        console.error("loadPartneri error", err);
      }
    }

    // ==== LOAD SVRHE ====
    async function loadSvrhe() {
      try {
        const out = await fetchJson(API.svrhe + '?all=1');
        const rows = Array.isArray(out) ? out : (out.data || []);
        state.svrhe.clear();

        let options = '<option value="">— odaberi svrhu —</option>';
        rows.forEach(s => {
          const id = parseInt(s.id, 10);
          if (!id) return;

          state.svrhe.set(id, {
            id,
            naziv: s.naziv || '',
            naziv2: s.naziv2 || s.naziv_2 || '',
            vrsta_prihoda: s.vrsta_prihoda || '',
            budzetska: s.budzetska_organizacija || '',
            poziv: s.poziv_na_broj || ''
          });

          options += `<option value="${id}">${esc(s.naziv)}</option>`;
        });

        $svrhaSel.innerHTML = options;
      } catch (err) {
        console.error("loadSvrhe error", err);
      }
    }

    // ==== AUTO POPUNA PO PARTNERU ====

    function applyUplatilacDefaults(p) {
      if (!p) return;

      if (!$mjesto.value) $mjesto.value = p.mjesto_naziv || '';
      if (!$racunPos.value) $racunPos.value = p.broj_racuna || '';

      // === AUTO BROJ POREZNOG O. ===

      const svrhaTxt = ($svrha.value + " " + $svrha1.value).toLowerCase();

      const vrsta = (p.vrsta || '').toLowerCase();

      const isPravna = vrsta.includes("pravno");
      const isFizicka = vrsta.includes("fizi");
      const isObrt = vrsta.includes("obrt");

      if (svrhaTxt.includes("uvoz")) {

        if (isFizicka) {
          $brojPorezni.value = "0010000000019";
        } else if (isPravna) {
          $brojPorezni.value = p.porezni_broj || "";
        } else if (isObrt) {
          $brojPorezni.value = "";
        }

      } else {
        if (!$brojPorezni.value) {
          $brojPorezni.value = p.porezni_broj || "";
        }
      }

      if (!$opcina.value) $opcina.value = p.opcina_sifra || '';
    }

    function applyPrimateljDefaults(p) {
      if (!p) return;
      if (!$racunPrim.value) $racunPrim.value = p.broj_racuna || '';
    }

    // ==== LISTA ====

    function renderList() {
      if (!state.all.length) {
        $list.innerHTML = '';
        show($empty, true);
        updateInfo();
        return;
      }

      show($empty, false);

      const html = state.all.map(u => `
        <div class="row" data-id="${u.id}">
          <div>${esc(u.datum)}</div>
          <div>${esc(u.uplatilac_naziv)}</div>
          <div>${esc(u.primatelj_naziv)}</div>
          <div>${esc(u.svrha_tekst)}</div>
          <div>${esc(u.svrha1)}</div>
          <div>${u.iznos != null ? esc(u.iznos.toFixed(2)) : ''}</div>
          <div class="acts">
            <button class="act edit"><i class="fa-solid fa-pen"></i></button>
            <button class="act del"><i class="fa-solid fa-trash"></i></button>
          </div>
        </div>
      `).join('');

      $list.innerHTML = html;
      updateInfo();
    }

    function updateInfo() {
      $infoCnt.textContent = "Prikazano: " + state.all.length;
      $infoFilt.textContent = state.q ? `Filter: "${state.q}"` : '';
    }

    // ==== LOAD UPLATNICE ====
    async function loadUplatnice() {
      try {
        const q = state.q ? ('?q=' + encodeURIComponent(state.q)) : '';
        const out = await fetchJson(API.list + q);
        const rows = Array.isArray(out) ? out : (out.data || []);

        state.all = rows.map(r => ({
          id: parseInt(r.id, 10),
          datum: r.datum || r.datum_uplate || '',
          uplatilac_id: r.uplatilac_id,
          uplatilac_naziv: r.uplatilac_naziv || '',
          primatelj_id: r.primatelj_id,
          primatelj_naziv: r.primatelj_naziv || '',
          svrha_tekst: r.svrha || r.svrha_tekst || '',
          svrha1: r.svrha1 || '',
          iznos: r.iznos ? parseFloat(r.iznos) : null
        }));

        renderList();
      } catch (err) {
        console.error("loadUplatnice error", err);
        state.all = [];
        renderList();
      }
    }

    // ==== SEARCH ====
    let tSearch = null;
    $q.addEventListener('input', () => {
      clearTimeout(tSearch);
      tSearch = setTimeout(() => {
        state.q = $q.value.trim();
        loadUplatnice();
      }, 250);
    });

    // ==== MODAL ====

    function clearForm() {
      $id.value = '';
      $uplatilacId.value = '';
      $uplatilacLabel.value = '';
      $primateljId.value = '';
      $primateljLabel.value = '';

      $uplatilacTekst.value = '';
      $primateljTekst.value = '';

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
      $title.textContent = "Nova uplatnica";
      clearForm();
      const d = new Date();
      $datum.value = d.toISOString().slice(0, 10);
      $wrap.classList.add('show');
    }

    function openEdit(row) {
      const id = parseInt(row.dataset.id, 10);
      const u = state.all.find(x => x.id === id);
      if (!u) return;

      $title.textContent = "Uredi uplatnicu #" + id;
      $id.value = id;

      setPartner('uplatilac', u.uplatilac_id, u.uplatilac_naziv);
      setPartner('primatelj', u.primatelj_id, u.primatelj_naziv);

      $svrha.value = u.svrha_tekst || '';
      $svrha1.value = u.svrha1 || '';
      $datum.value = u.datum || '';
      $iznos.value = u.iznos != null ? u.iznos : '';
      $wrap.classList.add('show');
    }

    function closeModal() {
      $wrap.classList.remove('show');
    }

    $close.addEventListener('click', closeModal);
    $cancel.addEventListener('click', closeModal);
    $wrap.addEventListener('click', e => {
      if (e.target === $wrap) closeModal();
    });

    // ==== PARTNER PICKER ====
    window.setSelectedPartner = function (id, naziv, partner) {
      if (!id) return;
      const target = pickTarget;
      const data = partner || state.partners.get(id);

      setPartner(target, id, naziv || (data?.label), data);
    };

    function setPartner(target, id, label, partnerData) {
      const isPrim = target === 'primatelj';
      const $idField = isPrim ? $primateljId : $uplatilacId;
      const $labelField = isPrim ? $primateljLabel : $uplatilacLabel;

      $idField.value = id || '';
      $labelField.value = label || '';

      const p = partnerData || state.partners.get(id);
      if (!p) return;

      if (isPrim) applyPrimateljDefaults(p);
      else applyUplatilacDefaults(p);
    }

    $btnPickUplat.addEventListener('click', () => {
      pickTarget = 'uplatilac';
      window.open(basePageRoot + 'partneri.html?pick=1', '_blank', 'width=1100,height=800');
    });

    $btnPickPrim.addEventListener('click', () => {
      pickTarget = 'primatelj';
      window.open(basePageRoot + 'partneri.html?pick=1', '_blank', 'width=1100,height=800');
    });

    // ==== SAVE ====
    async function save() {
      const id = parseInt($id.value || 0, 10);

      const body = {
        id: id || undefined,
        uplatilac_id: parseInt($uplatilacId.value, 10),
        primatelj_id: parseInt($primateljId.value, 10),
        svrha: $svrha.value.trim(),
        svrha1: $svrha1.value.trim(),
        datum_uplate: $datum.value,
        iznos: $iznos.value ? parseFloat($iznos.value) : null
      };

      if (!body.uplatilac_id) {
        $msg.textContent = "Uplatilac je obavezan.";
        show($msg, true);
        return;
      }
      if (!body.primatelj_id) {
        $msg.textContent = "Primatelj je obavezan.";
        show($msg, true);
        return;
      }
      if (!body.svrha) {
        $msg.textContent = "Svrha je obavezna.";
        show($msg, true);
        return;
      }
      if (!body.iznos || isNaN(body.iznos)) {
        $msg.textContent = "Iznos nije validan.";
        show($msg, true);
        return;
      }

      try {
        $save.disabled = true;
        const out = await fetchJson(id ? API.update : API.create, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(body)
        });

        if (!out.ok) throw new Error(out.error || "Greška pri spremanju");

        closeModal();
        await loadUplatnice();
      } catch (err) {
        $msg.textContent = err.message;
        show($msg, true);
      } finally {
        $save.disabled = false;
      }
    }

    $save.addEventListener('click', save);

    // ==== DELETE ====
    document.addEventListener('click', async e => {
      const row = e.target.closest('.row[data-id]');
      if (!row) return;

      if (e.target.closest('.edit')) {
        openEdit(row);
        return;
      }

      if (e.target.closest('.del')) {
        const id = parseInt(row.dataset.id, 10);
        if (!confirm(`Obrisati uplatnicu #${id}?`)) return;

        try {
          const out = await fetchJson(API.delete, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id })
          });

          if (!out.ok) throw new Error(out.error);

          row.remove();
          state.all = state.all.filter(x => x.id !== id);
          updateInfo();
        } catch (err) {
          alert(err.message);
        }
      }
    });

    // ==== BOOTSTRAP ====
    (async function () {
      await Promise.all([loadPartneri(), loadSvrhe()]);
      await loadUplatnice();
    })();

  }

  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", init);
  else
    init();

})();
