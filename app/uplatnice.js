// uplatnice.js – lista + modal za uplatnice
(function () {
  function init() {
    if (!document.body.classList.contains('uplatnice')) return;

    // API baza: pokušaj prvo /api/ pa padni na root (radi lokacije /app/ i /)
    const baseRoots = location.pathname.includes('/app/')
      ? ['../api/', '../']
      : ['./api/', './'];
    // korištenje html baze (bez api/) za otvaranje novih prozora
    const basePageRoot = baseRoots[0].replace(/api\/?$/, '');

    const API = {
      list:    'uplatnica_list.php',
      create:  'uplatnica_create.php',
      update:  'uplatnica_update.php',
      delete:  'uplatnica_delete.php',
      partneri:'partneri_list.php',
      svrhe:   'svrha_list.php',
      svrhaCreate: 'svrha_create.php'
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
    const $id             = document.getElementById('u_id');
    const $uplatilacId    = document.getElementById('u_uplatilac_id');
    const $uplatilacLabel = document.getElementById('u_uplatilac_label');
    const $uplatilacTekst = document.getElementById('u_uplatilac_tekst');
    const $primateljId    = document.getElementById('u_primatelj_id');
    const $primateljLabel = document.getElementById('u_primatelj_label');
    const $primateljTekst = document.getElementById('u_primatelj_tekst');
    const $btnPickUplat   = document.getElementById('u_pick_uplatilac');
    const $btnPickPrim    = document.getElementById('u_pick_primatelj');
    const $svrhaSel          = document.getElementById('u_svrha_id');
    const $svrhaNew          = document.getElementById('u_svrha_new');
    const $svrhaNewBtn       = document.getElementById('u_svrha_new_btn');
    const $svrhaNewMsg       = document.getElementById('u_svrha_new_msg');
    const $svrhaModal        = document.getElementById('u_svrha_modal');
    const $svrhaModalSave    = document.getElementById('u_svrha_modal_save');
    const $svrhaModalCancel  = document.getElementById('u_svrha_modal_cancel');
    const $svrhaModalClose   = document.getElementById('u_svrha_modal_close');

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
    '"': '&quot;',
    "'": '&#39;'
  }[m]));

    function show(el, on) {
      if (!el) return;
      el.style.display = on ? '' : 'none';
    }

    // ---- helper za fetch JSON ----
    async function fetchJson(path, options = {}) {
      let lastErr = null;

      for (let i = 0; i < baseRoots.length; i++) {
        const base = baseRoots[i];
        const isLast = i === baseRoots.length - 1;
        const url = path.startsWith('http') || path.startsWith('/')
          ? path
          : base + path;
        try {
          const res = await fetch(url, options);

          if (!res.ok) {
            let errMsg = 'HTTP ' + res.status;
            try {
              const body = await res.json();
              if (body && typeof body.error === 'string' && body.error.trim()) {
                errMsg = body.error.trim();
              }
            } catch (_) {
              // fallback to default errMsg
            }

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
          const retryable = (err && err.status === 404) || err?.name === 'TypeError';
          if (!retryable || isLast) break;
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

        rows.forEach(p => {
          const id = parseInt(p.id, 10);
          if (!id) return;
          // ime + prezime ili naziv
          const label = (p.naziv && p.naziv.trim())
            || [p.ime, p.prezime].filter(Boolean).join(' ')
            || ('Partner #' + id);
          const mjestoPoreznaSifra = p.mjesto_porezna_sifra
            || p.porezna_sifra_mjesta
            || p.porezna_sifra_mjesto
            || p.porezna_sifra
            || '';
          state.partners.set(id, {
            id,
            label,
            broj_racuna: p.broj_racuna || p.racun || '',
            id_broj: p.id_broj || p.idbroj || '',
            porezni_broj: p.porezni_broj || p.porezni || '',
            opcina_sifra: p.opcina_sifra || mjestoPoreznaSifra || '',
            mjesto_naziv: p.mjesto_naziv || '',
            mjesto_porezna_sifra: mjestoPoreznaSifra
          });
        });

        refreshPartnerLabels();
      } catch (err) {
        console.error('loadPartneri error', err);
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
    function updateBrojPoreskogObveznika(partner) {
      const p = partner || state.partners.get(Number($uplatilacId.value)) || null;
      if (!p) return;

      const svrhaText = ($svrha.value + ' ' + $svrha1.value).toLowerCase();
      const hasUvoz = svrhaText.includes('uvoz');

      const labelUpper = (p.label || '').toUpperCase();
      const vrstaUpper = (p.vrsta_partnera || p.vrsta || '').toUpperCase();
      const vrstaLower = (p.vrsta_partnera || p.vrsta || '').toLowerCase();
      const isStr = ['STR', 'SZR', 'OBRT', 'OBR'].some(k => labelUpper.includes(k) || vrstaUpper.includes(k));

      const hasImePrezime = !!(p.ime || p.prezime);
      const isVrstaFizicka = vrstaLower.includes('fizi');
      const isVrstaPravna = vrstaLower.includes('prav');

      const isFizicka = isVrstaFizicka || (!isVrstaPravna && !isStr && hasImePrezime);
      const isPravna = isVrstaPravna || (!isVrstaFizicka && !isStr && !hasImePrezime);

      const poreznaSifra = p.mjesto_porezna_sifra
        || p.porezna_sifra_mjesta
        || p.porezna_sifra_mjesto
        || p.opcina_sifra
        || '';

      if (hasUvoz) {
        if (poreznaSifra) $opcina.value = poreznaSifra;
        if (isFizicka) {
          $brojPorezni.value = '0010000000019';
        } else if (isPravna) {
          const idBroj = (p.id_broj || '').trim();
          const idBrojZamijenjen = idBroj ? idBroj.replace(/^\d/, '0') : '';
          $brojPorezni.value = idBrojZamijenjen || p.porezni_broj || '';
        } else {
          $brojPorezni.value = '';
        }
      } else if (!$brojPorezni.value) {
        const idBroj = p.id_broj || p.idBroj || '';
        $brojPorezni.value = idBroj || p.porezni_broj || '';
      }
    }

    function applyUplatilacDefaults(p) {
      if (!p) return;
      if (!$mjesto.value)        $mjesto.value = p.mjesto_naziv || '';
      if (!$racunPos.value)      $racunPos.value = p.broj_racuna || '';
      if (!$opcina.value)        $opcina.value = p.opcina_sifra || '';

      updateBrojPoreskogObveznika(p);
    }

    function applyPrimateljDefaults(p) {
      if (!p) return;
      if (!$racunPrim.value) $racunPrim.value = p.broj_racuna || '';
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

      updateBrojPoreskogObveznika();
    }

    function setSvrhaNewMsg(msg = '', isError = false) {
      if (!$svrhaNewMsg) return;
      $svrhaNewMsg.textContent = msg;
      if (msg) {
        $svrhaNewMsg.style.display = '';
        $svrhaNewMsg.style.color = isError ? '#b91c1c' : '#4b5563';
      } else {
        $svrhaNewMsg.style.display = 'none';
      }
    }

    function openSvrhaModal() {
      if (!$svrhaModal) return;
      setSvrhaNewMsg('');
      if ($svrhaNew) {
        $svrhaNew.value = '';
        $svrhaNew.focus();
      }
      $svrhaModal.classList.add('show');
    }

    function closeSvrhaModal() {
      if ($svrhaModal) $svrhaModal.classList.remove('show');
    }

    async function addSvrha() {
      if (!$svrhaNew || !$svrhaModalSave) return;
      const naziv = $svrhaNew.value.trim();
      setSvrhaNewMsg('');
      if (!naziv) {
        setSvrhaNewMsg('Unesi naziv nove svrhe.', true);
        $svrhaNew.focus();
        return;
      }

      $svrhaModalSave.disabled = true;
      try {
        const body = {
          naziv,
          vrsta_prihoda_sifra: $vrstaPrihoda.value.trim() || undefined,
          budzetska_org_sifra: $budzetska.value.trim() || undefined,
          poziv_na_broj_default: $poziv.value.trim() || undefined
        };
        const out = await fetchJson(API.svrhaCreate, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body)
        });
        const newId = out && (out.id || (out.data && out.data.id));
        await loadSvrhe();
        if (newId) {
          $svrhaSel.value = newId;
          onSvrhaChange();
        }
        $svrhaNew.value = '';
        closeSvrhaModal();
      } catch (err) {
        console.error('addSvrha error', err);
        const msg = err && err.message ? err.message : 'Greška pri dodavanju svrhe.';
        setSvrhaNewMsg(msg, true);
      } finally {
        $svrhaModalSave.disabled = false;
      }
    }

    function setPartner(target, id, label, partnerData) {
      const isPrimatelj = target === 'primatelj';
      const $idField    = isPrimatelj ? $primateljId : $uplatilacId;
      const $labelField = isPrimatelj ? $primateljLabel : $uplatilacLabel;

      $idField.value = id ? String(id) : '';

      let partner = null;
      if (partnerData && partnerData.id) {
        partner = {
          id: Number(partnerData.id),
          label: partnerData.label || label || partnerData.naziv || ('Partner #' + partnerData.id),
          ime: partnerData.ime || '',
          prezime: partnerData.prezime || '',
          naziv: partnerData.naziv || '',
          vrsta_partnera: partnerData.vrsta_partnera || partnerData.vrsta || '',
          id_broj: partnerData.id_broj || partnerData.idbroj || '',
          broj_racuna: partnerData.broj_racuna || partnerData.racun || partnerData.racun_pos || '',
          porezni_broj: partnerData.porezni_broj || partnerData.porezni || '',
          opcina_sifra: partnerData.opcina_sifra || '',
          mjesto_naziv: partnerData.mjesto || partnerData.mjesto_naziv || ''
        };
        state.partners.set(partner.id, partner);
      } else if (id) {
        partner = state.partners.get(Number(id)) || null;
      }

      if ($labelField) {
        $labelField.value = (partner && partner.label) || label || '';
      }

      if (partner) {
        if (isPrimatelj) {
          applyPrimateljDefaults(partner);
        } else {
          applyUplatilacDefaults(partner);
        }
      }
    }

    function refreshPartnerLabels() {
      setPartner('uplatilac', $uplatilacId.value ? parseInt($uplatilacId.value, 10) : null, $uplatilacLabel.value || '');
      setPartner('primatelj', $primateljId.value ? parseInt($primateljId.value, 10) : null, $primateljLabel.value || '');
    }

    window.setSelectedPartner = function (id, naziv, partner) {
      if (!id) return;
      const target = pickTarget === 'primatelj' ? 'primatelj' : 'uplatilac';
      const label  = naziv || (partner && (partner.label || partner.naziv)) || ('Partner #' + id);

      setPartner(target, id, label, partner);
    };

    function openPartnerPicker(target) {
      pickTarget = target;
      const w = 1100, h = 800;
      const x = Math.max(0, (screen.width - w) / 2);
      const y = Math.max(0, (screen.height - h) / 2);
      const url = basePageRoot + 'partneri.html?pick=1';
      window.open(url, 'pick_partner_' + target,
        `width=${w},height=${h},left=${x},top=${y},resizable=yes,scrollbars=yes`);
    }

    $svrhaSel.addEventListener('change', onSvrhaChange);
    $svrhaNewBtn?.addEventListener('click', (e) => { e.preventDefault(); openSvrhaModal(); });
    $svrhaModalSave?.addEventListener('click', addSvrha);
    $svrhaModalCancel?.addEventListener('click', closeSvrhaModal);
    $svrhaModalClose?.addEventListener('click', closeSvrhaModal);
    $svrhaModal?.addEventListener('click', (e) => { if (e.target === $svrhaModal) closeSvrhaModal(); });
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

      const html = state.all.map(u => `
        <div class="row" data-id="${u.id}">
          <div>${esc(u.datum || '')}</div>
          <div>${esc(u.uplatilac_naziv || '')}</div>
          <div>${esc(u.primatelj_naziv || '')}</div>
          <div>${esc(u.svrha_tekst || '')}</div>
          <div>${esc(u.svrha1 || '')}</div>
          <div>${u.iznos != null ? esc(u.iznos.toFixed(2)) : ''}</div>
          <div class="acts">
            <button class="act edit" title="Uredi"><i class="fa-solid fa-pen"></i></button>
            <button class="act del"  title="Obriši"><i class="fa-solid fa-trash"></i></button>
          </div>
        </div>
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

      const uId = r.uplatilac_id ? parseInt(r.uplatilac_id, 10) : null;
      const pId = r.primatelj_id ? parseInt(r.primatelj_id, 10) : null;

      const pU = uId ? state.partners.get(uId) : null;
      const pP = pId ? state.partners.get(pId) : null;

      return {
        id: parseInt(r.id, 10),

        datum: r.datum || r.datum_uplate || '',

        uplatilac_id: uId,
        // prvo uzmi što dođe iz backenda, ako je prazno – iz partnera:
        uplatilac_naziv:
          r.uplatilac_naziv || r.uplatilac ||
          (pU && pU.label) || '',
        uplatilac_tekst: r.uplatilac_tekst || '',

        primatelj_id: pId,
        primatelj_naziv:
          r.primatelj_naziv || r.primatelj ||
          (pP && pP.label) || '',
        primatelj_tekst: r.primatelj_tekst || '',

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
      $uplatilacId.value = '';
      $uplatilacLabel.value = '';
      if ($uplatilacTekst) $uplatilacTekst.value = '';
      $primateljId.value = '';
      $primateljLabel.value = '';
      if ($primateljTekst) $primateljTekst.value = '';
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
      const $focusTarget = $btnPickUplat || $uplatilacLabel;
      if ($focusTarget) $focusTarget.focus();
    }

    function openEdit(row) {
      const id = parseInt(row.dataset.id, 10);
      const item = state.all.find(x => x.id === id);
      if (!item) return;

      $title.textContent = 'Uredi uplatnicu #' + id;
      $id.value = id;

      setPartner('uplatilac', item.uplatilac_id || null, item.uplatilac_naziv || '');
      setPartner('primatelj', item.primatelj_id || null, item.primatelj_naziv || '');
      if ($uplatilacTekst) $uplatilacTekst.value = item.uplatilac_tekst || '';
      if ($primateljTekst) $primateljTekst.value = item.primatelj_tekst || '';
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
        uplatilac_id:  $uplatilacId.value ? parseInt($uplatilacId.value, 10) : null,
        primatelj_id:  $primateljId.value ? parseInt($primateljId.value, 10) : null,
        svrha_id:      $svrhaSel.value ? parseInt($svrhaSel.value, 10) : null,
        uplatilac_tekst: $uplatilacTekst ? $uplatilacTekst.value.trim() : '',
        primatelj_tekst: $primateljTekst ? $primateljTekst.value.trim() : '',
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
      if (!body.racun_primatelja) {
        $msg.textContent = 'Račun primatelja je obavezan.';
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
