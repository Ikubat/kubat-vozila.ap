// uplatnice.js – lista + modal za uplatnice
(function () {
  function init() {
    if (!document.body.classList.contains('uplatnice')) return;

    // API baza: pokušaj prvo /api/ pa padni na root (radi lokacije /app/ i /)
    const baseRoots = location.pathname.includes('/app/')
      ? ['../api/', '../']
      : ['./api/', './'];

    // Bazni path za otvaranje HTML stranica (isti direktorij kao trenutna strana)
    const basePageRoot = new URL('.', location.href).href;

    const API = {
      list:        'uplatnica_list.php',
      create:      'uplatnica_create.php',
      update:      'uplatnica_update.php',
      delete:      'uplatnica_delete.php',
      partneri:    'partneri_list.php',
      svrhe:       'svrha_list.php',
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
    const $uplatilacIdBroj = document.getElementById('u_uplatilac_id_broj');
    const $uplatilacKontakt = document.getElementById('u_uplatilac_kontakt');
    const $uplatilacAdresa = document.getElementById('u_uplatilac_adresa');
    const $uplatilacMjesto = document.getElementById('u_uplatilac_mjesto');
    const $primateljId    = document.getElementById('u_primatelj_id');
    const $primateljLabel = document.getElementById('u_primatelj_label');
    const $primateljTekst = document.getElementById('u_primatelj_tekst');
    const $primateljIdBroj = document.getElementById('u_primatelj_id_broj');
    const $primateljKontakt = document.getElementById('u_primatelj_kontakt');
    const $primateljAdresa = document.getElementById('u_primatelj_adresa');
    const $primateljMjesto = document.getElementById('u_primatelj_mjesto');
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

    // --- PRINT gumb ---
    const $btnPrint = document.getElementById('u_print');

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
              // fallback
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

    // --- PRINT payload iz forme ---
    function buildPrintPayloadFromForm() {
      const uplatilacTekst =
        ($uplatilacTekst && $uplatilacTekst.value.trim()) ||
        $uplatilacLabel.value.trim();

      const primateljTekst =
        ($primateljTekst && $primateljTekst.value.trim()) ||
        $primateljLabel.value.trim();

      const valuta = $valuta.value.trim() || 'KM';
      const iznosFull = $iznos.value
        ? `${parseFloat($iznos.value).toFixed(2)} ${valuta}`
        : '';

      return {
        uplatilac: uplatilacTekst,
        uplatilac_tekst: $uplatilacTekst ? $uplatilacTekst.value.trim() : '',
        uplatilac_kontakt: $uplatilacKontakt ? $uplatilacKontakt.value.trim() : '',
        uplatilac_adresa: $uplatilacAdresa ? $uplatilacAdresa.value.trim() : '',
        uplatilac_mjesto: $uplatilacMjesto ? $uplatilacMjesto.value.trim() : '',
        uplatilac_id_broj: $uplatilacIdBroj ? $uplatilacIdBroj.value.trim() : '',
        primatelj: primateljTekst,
        primatelj_kontakt: $primateljKontakt ? $primateljKontakt.value.trim() : '',
        primatelj_adresa: $primateljAdresa ? $primateljAdresa.value.trim() : '',
        primatelj_mjesto: $primateljMjesto ? $primateljMjesto.value.trim() : '',
        primatelj_id_broj: $primateljIdBroj ? $primateljIdBroj.value.trim() : '',
        svrha: $svrha.value.trim(),
        svrha1: $svrha1.value.trim(),
        mjesto: $mjesto.value.trim(),
        datum: $datum.value || '',
        iznos: $iznos.value ? parseFloat($iznos.value).toFixed(2) : '',
        valuta,
        iznos_full: iznosFull,
        racun_posiljaoca: $racunPos.value.trim(),
        racun_primatelja: $racunPrim.value.trim(),
        broj_poreskog_obv: $brojPorezni.value.trim(),
        vrsta_prihoda_sifra: $vrstaPrihoda.value.trim(),
        opcina_sifra: $opcina.value.trim(),
        budzetska_org_sifra: $budzetska.value.trim(),
        poziv_na_broj: $poziv.value.trim(),
        napomena: $napomena.value.trim(),
        valuta_label: valuta
      };
    }

    // --- PRINT payload iz stavke u listi ---
    function buildPrintPayloadFromItem(item) {
      if (!item) return null;
      const valuta = (item.valuta || 'KM').trim() || 'KM';
      const iznosVal = item.iznos === undefined || item.iznos === null
        ? ''
        : parseFloat(item.iznos);
      const iznos = Number.isFinite(iznosVal) ? iznosVal.toFixed(2) : '';

      return {
        id: item.id,
        uplatilac: item.uplatilac_naziv || '',
        uplatilac_tekst: item.uplatilac_tekst || '',
        primatelj: item.primatelj_naziv || '',
        svrha: item.svrha_tekst || item.svrha || '',
        svrha1: item.svrha1 || '',
        mjesto: item.mjesto || item.mjesto_uplate || '',
        datum: item.datum || item.datum_uplate || '',
        iznos: iznos,
        iznos_full: iznos ? `${iznos} ${valuta}` : '',
        valuta,
        uplatilac_kontakt: item.uplatilac_kontakt || '',
        uplatilac_adresa: item.uplatilac_adresa || '',
        uplatilac_mjesto: item.uplatilac_mjesto || '',
        uplatilac_id_broj: item.uplatilac_id_broj || '',
        primatelj_kontakt: item.primatelj_kontakt || '',
        primatelj_adresa: item.primatelj_adresa || '',
        primatelj_mjesto: item.primatelj_mjesto || '',
        primatelj_id_broj: item.primatelj_id_broj || '',
        racun_posiljaoca: item.racun_posiljaoca || '',
        racun_primatelja: item.racun_primatelja || '',
        broj_poreskog_obv: item.broj_poreskog_obv || '',
        vrsta_prihoda_sifra: item.vrsta_prihoda_sifra || '',
        opcina_sifra: item.opcina_sifra || '',
        budzetska_org_sifra: item.budzetska_org_sifra || '',
        poziv_na_broj: item.poziv_na_broj || '',
        napomena: item.napomena || '',
        valuta_label: valuta
      };
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

          const label = (p.naziv && p.naziv.trim())
            || [p.ime, p.prezime].filter(Boolean).join(' ')
            || ('Partner #' + id);

          state.partners.set(id, {
            id,
            label,
            vrsta: p.vrsta_partnera || p.vrsta || '',
            id_broj: p.id_broj || p.idbroj || '',
            broj_racuna: p.broj_racuna || p.racun || '',
            kontakt: p.kontakt || p.telefon || '',
            adresa: p.adresa || '',
            porezni_broj: p.porezni_broj || p.porezni || '',
            mjesto_porezna_sifra: p.mjesto_porezna_sifra || p.porezna_sifra || '',
            opcina_sifra: p.opcina_sifra || '',
            mjesto_naziv: p.mjesto || p.mjesto_naziv || ''
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
    function applyUplatilacDefaults(p) {
      if (!p) return;

      $mjesto.value   = p.mjesto_naziv || '';
      if (!$racunPos.value) $racunPos.value = p.broj_racuna || '';

      const svrhaText = ($svrha.value + ' ' + $svrha1.value).toLowerCase();
      const isCarinaPdvUvoz = svrhaText.includes('uplata carine i pdv (uvoz)');

      const vrstaLower = (p.vrsta || '').toLowerCase();
      const isPravna   = vrstaLower.includes('pravna') || vrstaLower.includes('pravno');
      const isFizicka  = vrstaLower.includes('fizič') || vrstaLower.includes('fizic');
      const isObrt     = vrstaLower.includes('obrt') || vrstaLower.includes('szr') || vrstaLower.includes('str');

      const partnerOpcina =
        p.mjesto_porezna_sifra ||
        p.opcina_sifra ||
        '';

      const idBrojUplatilac = (p.id_broj || '').trim();

      if (isCarinaPdvUvoz && isFizicka) {
        $brojPorezni.value = '0010000000019';
      } else {
        $brojPorezni.value = idBrojUplatilac;
      }

      if (svrhaText.includes('uvoz')) {

        // kod UVOZ u općinu upiši poreznu šifru mjesta uplatioca
        if (partnerOpcina) {
          $opcina.value = partnerOpcina;
        }
      } else {
        if (!$opcina.value && partnerOpcina) {
          $opcina.value = partnerOpcina;
        }
      }
    }

    function applyPrimateljDefaults(p) {
      if (!p) return;
      if (!$racunPrim.value) $racunPrim.value = p.broj_racuna || '';
    }

    // kad promijenimo svrhu tekstom ili iz šifrarnika, ponovno primijeni pravila za uplatitelja
    function recomputeFromSvrha() {
      const uId = $uplatilacId.value ? parseInt($uplatilacId.value, 10) : 0;
      if (!uId) return;
      const p = state.partners.get(uId);
      if (!p) return;
      applyUplatilacDefaults(p);
    }

    function onSvrhaChange() {
      const id = parseInt($svrhaSel.value, 10);
      const s = state.svrhe.get(id);
      if (!s) return;

      if (!$svrha1.value)  $svrha1.value = s.naziv2 || '';
      if (!$vrstaPrihoda.value) $vrstaPrihoda.value = s.vrsta_prihoda || '';
      if (!$budzetska.value)    $budzetska.value = s.budzetska || '';
      if (!$poziv.value)        $poziv.value = s.poziv || '';

      // nakon promjene šifre svrhe, ponovno primijeni pravila za uplatitelja
      recomputeFromSvrha();
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

    // 🔧 setPartner – merge partnerData + postojeći partner iz state.partners␊
    function setPartner(target, id, label, partnerData) {
      const isPrimatelj = target === 'primatelj';
      const $idField    = isPrimatelj ? $primateljId : $uplatilacId;
      const $labelField = isPrimatelj ? $primateljLabel : $uplatilacLabel;

      $idField.value = id ? String(id) : '';

      let partner = null;
      const existing = id ? state.partners.get(Number(id)) || null : null;

      if (partnerData && partnerData.id) {
        const base = existing || {};
        partner = {
          id: Number(partnerData.id),
          label:
            partnerData.label ||
            label ||
            base.label ||
            partnerData.naziv ||
            ('Partner #' + partnerData.id),
          vrsta:
            partnerData.vrsta ||
            partnerData.vrsta_partnera ||
            base.vrsta ||
            '',
          broj_racuna:
            partnerData.broj_racuna ||
            partnerData.racun ||
            partnerData.racun_pos ||
            base.broj_racuna ||
            '',
          id_broj:
            partnerData.id_broj ||
            partnerData.idbroj ||
            base.id_broj ||
            '',
          kontakt:
            partnerData.kontakt ||
            partnerData.telefon ||
            base.kontakt ||
            '',
          adresa:
            partnerData.adresa ||
            base.adresa ||
            '',
          porezni_broj:
            partnerData.porezni_broj ||
            partnerData.porezni ||
            base.porezni_broj ||
            '',
          mjesto_porezna_sifra:
            partnerData.mjesto_porezna_sifra ||
            partnerData.porezna_sifra ||
            base.mjesto_porezna_sifra ||
            '',
          opcina_sifra:
            partnerData.opcina_sifra ||
            base.opcina_sifra ||
            '',
          mjesto_naziv:
            partnerData.mjesto ||
            partnerData.mjesto_naziv ||
            base.mjesto_naziv ||
            ''
        };
        state.partners.set(partner.id, partner);
      } else if (existing) {
        partner = existing;
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

      if (isPrimatelj) {
        if ($primateljIdBroj) $primateljIdBroj.value = partner?.id_broj || '';
        if ($primateljKontakt) $primateljKontakt.value = partner?.kontakt || '';
        if ($primateljAdresa) $primateljAdresa.value = partner?.adresa || '';
        if ($primateljMjesto) $primateljMjesto.value = partner?.mjesto_naziv || '';
      } else {
        if ($uplatilacIdBroj) $uplatilacIdBroj.value = partner?.id_broj || '';
        if ($uplatilacKontakt) $uplatilacKontakt.value = partner?.kontakt || '';
        if ($uplatilacAdresa) $uplatilacAdresa.value = partner?.adresa || '';
        if ($uplatilacMjesto) $uplatilacMjesto.value = partner?.mjesto_naziv || '';
      }
    }

    function refreshPartnerLabels() {
      setPartner(
        'uplatilac',
        $uplatilacId.value ? parseInt($uplatilacId.value, 10) : null,
        $uplatilacLabel.value || ''
      );
      setPartner(
        'primatelj',
        $primateljId.value ? parseInt($primateljId.value, 10) : null,
        $primateljLabel.value || ''
      );
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
      window.open(
        url,
        'pick_partner_' + target,
        `width=${w},height=${h},left=${x},top=${y},resizable=yes,scrollbars=yes`
      );
    }

    // event listenere za svrhu i pickere
    $svrhaSel.addEventListener('change', onSvrhaChange);
    $svrha.addEventListener('input', recomputeFromSvrha);
    $svrha1.addEventListener('input', recomputeFromSvrha);
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
            <button class="act print" title="Ispis"><i class="fa-solid fa-print"></i></button>
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
            uplatilac_naziv:
              r.uplatilac_naziv || r.uplatilac ||
              (pU && pU.label) || '',
            uplatilac_tekst: r.uplatilac_tekst || '',
            uplatilac_kontakt: r.uplatilac_kontakt || '',
            uplatilac_adresa: r.uplatilac_adresa || '',
            uplatilac_mjesto: r.uplatilac_mjesto || '',
            uplatilac_id_broj: r.uplatilac_id_broj || '',

            primatelj_id: pId,
            primatelj_naziv:
              r.primatelj_naziv || r.primatelj ||
              (pP && pP.label) || '',
            primatelj_tekst: r.primatelj_tekst || '',
            primatelj_kontakt: r.primatelj_kontakt || '',
            primatelj_adresa: r.primatelj_adresa || '',
            primatelj_mjesto: r.primatelj_mjesto || '',
            primatelj_id_broj: r.primatelj_id_broj || '',

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
      if ($uplatilacIdBroj) $uplatilacIdBroj.value = '';
      if ($uplatilacKontakt) $uplatilacKontakt.value = '';
      if ($uplatilacAdresa) $uplatilacAdresa.value = '';
      $primateljId.value = '';
      $primateljLabel.value = '';
      if ($primateljTekst) $primateljTekst.value = '';
      if ($primateljIdBroj) $primateljIdBroj.value = '';
      if ($primateljKontakt) $primateljKontakt.value = '';
      if ($primateljAdresa) $primateljAdresa.value = '';
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
      if ($uplatilacKontakt) $uplatilacKontakt.value = item.uplatilac_kontakt || '';
      if ($uplatilacAdresa) $uplatilacAdresa.value = item.uplatilac_adresa || '';
      if ($uplatilacMjesto) $uplatilacMjesto.value = item.uplatilac_mjesto || '';
      if ($uplatilacIdBroj) $uplatilacIdBroj.value = item.uplatilac_id_broj || '';
      if ($primateljTekst) $primateljTekst.value = item.primatelj_tekst || '';
      if ($primateljKontakt) $primateljKontakt.value = item.primatelj_kontakt || '';
      if ($primateljAdresa) $primateljAdresa.value = item.primatelj_adresa || '';
      if ($primateljMjesto) $primateljMjesto.value = item.primatelj_mjesto || '';
      if ($primateljIdBroj) $primateljIdBroj.value = item.primatelj_id_broj || '';
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
        uplatilac_kontakt: $uplatilacKontakt ? $uplatilacKontakt.value.trim() : '',
        uplatilac_adresa: $uplatilacAdresa ? $uplatilacAdresa.value.trim() : '',
        uplatilac_mjesto: $uplatilacMjesto ? $uplatilacMjesto.value.trim() : '',
        uplatilac_id_broj: $uplatilacIdBroj ? $uplatilacIdBroj.value.trim() : '',
        primatelj_kontakt: $primateljKontakt ? $primateljKontakt.value.trim() : '',
        primatelj_adresa: $primateljAdresa ? $primateljAdresa.value.trim() : '',
        primatelj_mjesto: $primateljMjesto ? $primateljMjesto.value.trim() : '',
        primatelj_id_broj: $primateljIdBroj ? $primateljIdBroj.value.trim() : '',
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
        const warning = out && out.warning ? String(out.warning) : '';
        closeModal();
        await loadUplatnice();
        if (warning) {
          alert(warning);
        }
      } catch (err) {
        console.error('save uplatnica error', err);
        $msg.textContent = err.message || 'Greška pri spremanju.';
        show($msg, true);
      } finally {
        $save.disabled = false;
      }
    }

    $save.addEventListener('click', save);

    // ---- otvaranje prozora za print (uplatnica_print.html) ----
    function openPrintWindow(payloadOverride = null, idOverride = null) {
      const payload = payloadOverride || buildPrintPayloadFromForm() || null;
      const hasPayload = payload && Object.keys(payload).length > 0;

      const payloadKey = hasPayload ? ('uplatnica-print-' + Date.now()) : null;
      let payloadEncoded = '';

      if (hasPayload) {
        try {
          payloadEncoded = btoa(unescape(encodeURIComponent(JSON.stringify(payload))));
        } catch (err) {
          console.warn('Serijalizacija podataka za print nije uspjela', err);
          payloadEncoded = '';
        }
      }

      // pokušaj spremiti JSON i u localStorage i u sessionStorage (opcionalno)
      if (hasPayload && payloadKey) {
        try {
          const payloadJson = JSON.stringify(payload);
          try { localStorage.setItem(payloadKey, payloadJson); } catch (err) {
            console.warn('Spremanje podataka za print u localStorage nije uspjelo', err);
          }
          try { sessionStorage.setItem(payloadKey, payloadJson); } catch (err) {
            console.warn('Spremanje podataka za print u sessionStorage nije uspjelo', err);
          }
        } catch (err) {
          console.warn('Spremanje podataka za print nije uspjelo', err);
        }
      }

      const urlParams = new URLSearchParams();
      const currentId = idOverride || parseInt($id && $id.value, 10);
      if (currentId) {
        urlParams.set('id', currentId);
      }

      const hashPart = (() => {
        if (!hasPayload) return '';
        const hashParams = new URLSearchParams();
        if (payloadKey) hashParams.set('payload', payloadKey);
        if (payloadEncoded) hashParams.set('payloadData', payloadEncoded);
        return '#' + hashParams.toString();
      })();

      const url =
        basePageRoot +
        'uplatnica_print.html' +
        (urlParams.toString() ? '?' + urlParams.toString() : '') +
        hashPart;

      const printWin = window.open(url, '_blank', 'noopener');

      if (!printWin) {
        alert('Nije moguće otvoriti prozor za print.');
        return;
      }

      // dodatno pošalji payload preko postMessage, za slučaj da hash/storage ne prođu
      if (hasPayload) {
        const sendPayload = () => {
          try {
            printWin.postMessage({ type: 'uplatnica-data', payload }, '*');
          } catch (err) {
            console.warn('Slanje podataka za print nije uspjelo', err);
          }
        };

        let attempts = 0;
        const timer = setInterval(() => {
          if (printWin.closed || attempts > 15) {
            clearInterval(timer);
            return;
          }
          attempts += 1;
          sendPayload();

          if (printWin.document && printWin.document.readyState === 'complete') {
            clearInterval(timer);
          }
        }, 200);

        sendPayload();
      }
    }

    // toolbar Print – koristi podatke iz trenutnog modala (ako je otvoren)
    if ($btnPrint) {
      $btnPrint.addEventListener('click', () => {
        openPrintWindow();
      });
    }

    // ---- delegacija klikova na listu (edit / print / delete) ----
    document.addEventListener('click', async e => {
      const row = e.target.closest('.row[data-id]');
      if (!row) return;

      if (e.target.closest('.edit')) {
        openEdit(row);
        return;
      }

      if (e.target.closest('.print')) {
        const id = parseInt(row.dataset.id, 10);
        if (!id) return;

        const item = state.all.find(x => x.id === id) || null;
        const payload = buildPrintPayloadFromItem(item);
        openPrintWindow(payload, id);
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
