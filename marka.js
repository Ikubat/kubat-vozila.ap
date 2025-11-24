// marka.js — lista + modal + pick-mode (?pick=1) + paginacija
(function () {
  function init(){
    if (!document.body.classList.contains('marke')) return;

    // -------- pick mode --------
    const params   = new URLSearchParams(location.search);
    const PICK     = params.get('pick') === '1';
    if (PICK) document.body.classList.add('pick');

    // -------- API --------
    const apiRoots = (() => {
      const prefix = location.pathname.includes('/app/') ? '../' : './';
      const roots = [prefix];
      const apiPrefix = prefix.endsWith('/') ? `${prefix}api/` : `${prefix}/api/`;
      roots.push(apiPrefix);
      return roots;
    })();

    const API = {
      search : apiRoots.map(r => r + 'marka_search.php'),      // GET ?q=&page=&page_size=
      create : apiRoots.map(r => r + 'marka_create.php'),
      update : apiRoots.map(r => r + 'marka_update.php'),
      delete : apiRoots.map(r => r + 'marka_delete.php'),
      marke  : apiRoots.map(r => r + 'marka_distinct.php'),
      vrste  : apiRoots.map(r => r + 'vrsta_list_auto.php'),    // GET ?all=1 (fallback na vrsta_list.php ispod)
      vrsteFallback: apiRoots.map(r => r + 'vrsta_list.php')
    };

    // -------- elementi --------
    const $q          = document.getElementById('q');
    const $list       = document.getElementById('list');
    const $empty      = document.getElementById('empty');
    const $pageInfo   = document.getElementById('pageInfo');
    const $infoFilter = document.getElementById('infoFilter');
    const $filters    = Array.from(document.querySelectorAll('.filter-row [data-filter]'));

    // modal
    const $wrap   = document.getElementById('mWrap');
    const $title  = document.getElementById('mTitle');
    const $close  = document.getElementById('mClose');
    const $save   = document.getElementById('mSave');
    const $cancel = document.getElementById('mCancel');
    const $msg    = document.getElementById('mMsg');

    const $id          = document.getElementById('m_id');
    const $vrsta       = document.getElementById('m_vrsta');
    const $serija      = document.getElementById('m_serija');
    const $nazivSelect = document.getElementById('m_naziv_select');
    const $model       = document.getElementById('m_model');
    const $oblik       = document.getElementById('m_oblik');
    const $mjenjac     = document.getElementById('m_mjenjac');
    const $pogon       = document.getElementById('m_pogon');
    const $snaga       = document.getElementById('m_snaga');
    const $zapremina   = document.getElementById('m_zapremina');
    const $vrata       = document.getElementById('m_vrata');
    const $god_modela  = document.getElementById('m_god_modela');
    const $god_kraj    = document.getElementById('m_god_kraj');
    const $kataloska   = document.getElementById('m_kataloska');

    // Ukloni eventualno zastarjelo tekstualno polje za marku ako je ostalo u DOM-u
    const $staroPoljeMarka = document.getElementById('m_naziv');
    if ($staroPoljeMarka?.closest('div')) {
      $staroPoljeMarka.closest('div').remove();
    } else if ($staroPoljeMarka) {
      $staroPoljeMarka.remove();
    }

    if(!$wrap || !$id || !$nazivSelect){
      console.error('Nedostaju elementi modala za marke.');
      return;
    }

    const $addTop = document.getElementById('btnAddTop');
    const $addMarka = document.getElementById('btnAddMarka');
    const $addModel = document.getElementById('btnAddModel');
    const $addOblik = document.getElementById('btnAddOblik');
    const $addVrata = document.getElementById('btnAddVrata');
    const $addPogon = document.getElementById('btnAddPogon');
    const $addMjenjac = document.getElementById('btnAddMjenjac');
    const $pickVrsta = document.getElementById('btnPickVrsta');
    const vrstaPickerCandidates = location.pathname.includes('/app/')
      ? ['../vrsta.html', 'vrsta.html', '../app/vrsta.html', './vrsta.html']
      : ['vrsta.html', 'app/vrsta.html', './vrsta.html', '../vrsta.html'];
    let vrstaPickerBaseUrl = null;

    // util
    const esc  = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
    const escAttr = esc;
    const escCss = s => {
      if(!s && s!==0) return '';
      if(window.CSS?.escape) return CSS.escape(String(s));
      return String(s).replace(/(["'\\])/g, '\\$1').replace(/\0/g,'\uFFFD');
    };
    const show = (el,on) => { if(!el) return; el.style.display = on ? '' : 'none'; };

    // ===== DODANO: MAPE I PLACEHOLDERI ZA DINAMIČKE LISTE =====
    const optionMaps = {
      model:   new Map(),
      oblik:   new Map(),
      vrata:   new Map(),
      pogon:   new Map(),
      mjenjac: new Map()
    };

    const placeholders = {
      model:   '— odaberi model —',
      oblik:   '— odaberi oblik —',
      vrata:   '— odaberi —',
      pogon:   '— odaberi pogon —',
      mjenjac: '— odaberi mjenjač —'
    };

    function normalizeVal(v){
      return (v ?? '').toString().trim();
    }

    function toKey(v){
      return normalizeVal(v).toLowerCase();
    }
    // ===== KRAJ DODANOG BLOKA =====

    // -------- stanje liste --------
    const state = { q:'', filters:{}, page:1, pageSize:50, total:0, pages:0, loading:false };

    function updateInfo() {
      if($infoFilter){
        const labels = {
          vrsta:'Vrsta', naziv:'Marka', model:'Model', serija:'Serija', god_modela:'Modelska godina', god_kraj:'Kraj proizvodnje',
          oblik:'Oblik', vrata:'Vrata', snaga:'Snaga', zapremina:'Zapremina', pogon:'Pogon', mjenjac:'Mjenjač', kataloska:'Kataloška'
        };
        const parts = [];
        if(state.q) parts.push(`Tekst: "${state.q}"`);
        Object.entries(state.filters || {}).forEach(([key,val])=>{
          if(!val) return;
          parts.push(`${labels[key]||key}: "${val}"`);
        });
        $infoFilter.textContent = parts.length ? `Filteri: ${parts.join(', ')}` : '';
      }
      if($pageInfo){
        const shown = ($list?.querySelectorAll('.card-row').length || 0);
        $pageInfo.textContent = `Prikazano: ${shown} od ukupno ${state.total || '…'}`;
      }
    }

    function registerOption(field, value){
      const map = optionMaps[field];
      if(!map) return;
      const clean = normalizeVal(value);
      if(!clean) return;
      const key = toKey(clean);
      if(!map.has(key)) map.set(key, clean);
    }

    function readFiltersFromUI(){
      const filters = {};
      $filters.forEach(inp=>{
        const key = inp.dataset.filter;
        if(!key) return;
        const val = (inp.value||'').trim();
        filters[key] = val;
      });
      return filters;
    }

    function setSelectOptions($sel, map, placeholder, preselect=''){
      if(!$sel || !map) return;
      const seen = new Set();
      const unique = [];
      for(const val of map.values()){
        const key = toKey(val);
        if(!key || seen.has(key)) continue;
        seen.add(key);
        unique.push(val);
      }
      $sel.innerHTML = `<option value="">${placeholder}</option>` +
        unique.map(v=>`<option value="${escAttr(v)}">${esc(v)}</option>`).join('');

      const preVal = normalizeVal(preselect);
      if(preVal){
        const key = toKey(preVal);
        if(!map.has(key)) map.set(key, preVal);
        if(!$sel.querySelector(`option[value="${escCss(preVal)}"]`)){
          const opt = document.createElement('option');
          opt.value = preVal;
          opt.textContent = preVal;
          $sel.appendChild(opt);
        }
        $sel.value = preVal;
      } else {
        $sel.value = '';
      }
    }

    function refreshDynamicSelects(preselects={}){
      setSelectOptions($model,   optionMaps.model,   placeholders.model,   preselects.model   ?? $model?.value   ?? '');
      setSelectOptions($oblik,   optionMaps.oblik,   placeholders.oblik,   preselects.oblik   ?? $oblik?.value   ?? '');
      setSelectOptions($vrata,   optionMaps.vrata,   placeholders.vrata,   preselects.vrata   ?? $vrata?.value   ?? '');
      setSelectOptions($pogon,   optionMaps.pogon,   placeholders.pogon,   preselects.pogon   ?? $pogon?.value   ?? '');
      setSelectOptions($mjenjac, optionMaps.mjenjac, placeholders.mjenjac, preselects.mjenjac ?? $mjenjac?.value ?? '');
    }

    async function fetchJsonWithFallback(urls, options={}){
      const list = Array.isArray(urls) ? urls : [urls];
      let lastErr = null;
      for(const url of list){
        try{
          const res = await fetch(url, options);
          if(!res.ok){
            lastErr = new Error('HTTP '+res.status);
            continue;
          }
          const data = await res.json();
          return {url, data};
        }catch(err){
          lastErr = err;
        }
      }
      throw lastErr || new Error('Nepoznata greška');
    }

    // -------- vrste (select) --------
    async function loadVrste(preselectId=null){
      if(!$vrsta) return;
      try{
        const res = await fetchJsonWithFallback(API.vrste.map(u=>u+'?all=1'), {cache:'no-store'});
        let rows = Array.isArray(res.data) ? res.data : (res.data?.data||[]);
        if(!rows.length){
          const fallback = await fetchJsonWithFallback(API.vrsteFallback.map(u=>u+'?all=1'), {cache:'no-store'});
          rows = Array.isArray(fallback.data) ? fallback.data : (fallback.data?.data||[]);
        }
        rows = rows.map(v=>({
          id: v.id ?? v.ID ?? v.vrsta_id ?? v.Id,
          naziv: v.naziv ?? v.naziv_vrste ?? v.Naziv ?? v.name ?? ''
        })).filter(v=>v.id!=null);
        rows.sort((a,b)=> String(a.naziv||'').localeCompare(String(b.naziv||''),'hr'));
        $vrsta.innerHTML = '<option value="">— odaberi —</option>' +
          rows.map(v=>`<option value="${v.id}">${esc(v.naziv)}</option>`).join('');
        if(preselectId!=null && preselectId!=='') $vrsta.value = String(preselectId);
      }catch(e){
        $vrsta.innerHTML = '<option value="">(greška kod učitavanja)</option>';
        console.error('loadVrste:', e);
      }
    }

    // -------- marke (select) --------
    async function loadMarke(preselectNaziv=''){
      if(!$nazivSelect) return;
      try{
        const res = await fetchJsonWithFallback(API.marke, {cache:'no-store'});
        const seen = new Set();
        const list = [];
        const rows = Array.isArray(res.data) ? res.data : [];
        for(const n of rows){
          const clean = normalizeVal(n);
          if(!clean) continue;
          const key = toKey(clean);
          if(seen.has(key)) continue;
          seen.add(key);
          list.push(clean);
        }
        $nazivSelect.innerHTML = '<option value="">— odaberi marku —</option>' +
          list.map(n => `<option value="${escAttr(n)}">${esc(n)}</option>`).join('');

        if(preselectNaziv){
          const val = escCss(preselectNaziv);
          if(val && !$nazivSelect.querySelector(`option[value="${val}"]`)){
            const opt = document.createElement('option');
            opt.value = preselectNaziv;
            opt.textContent = preselectNaziv;
            $nazivSelect.appendChild(opt);
          }
          $nazivSelect.value = preselectNaziv;
        }
      }catch(e){
        $nazivSelect.innerHTML = '<option value="">(greška kod učitavanja)</option>';
        console.error('loadMarke:', e);
      }
    }

    function addMarkaToSelect(){
      if(!$nazivSelect) return;
      const proposed = ($nazivSelect.value || '').trim();
      const naziv = prompt('Naziv nove marke', proposed) || '';
      const clean = naziv.trim();
      if(!clean) return;

      const existing = Array.from($nazivSelect.options).find(o => o.value.toLowerCase() === clean.toLowerCase());
      if(existing){
        $nazivSelect.value = existing.value;
        return;
      }

      const opt = document.createElement('option');
      opt.value = clean;
      opt.textContent = clean;
      $nazivSelect.appendChild(opt);
      $nazivSelect.value = clean;
    }

    function promptAddOption(field, $select, promptText, validator=null){
      if(!$select) return;
      const proposed = normalizeVal($select.value);
      const val = normalizeVal(prompt(promptText, proposed) || '');
      if(!val) return;
      if(typeof validator === 'function'){
        const valid = validator(val);
        if(valid !== true){
          alert(valid || 'Vrijednost nije valjana.');
          return;
        }
      }
      const map = optionMaps[field];
      if(map){
        const key = toKey(val);
        if(map.has(key)){
          $select.value = map.get(key);
          return;
        }
        map.set(key, val);
      }
      refreshDynamicSelects({ [field]: val });
    }

    // -------- render reda (sve kolone) --------
    function rowToHTML(m){
      const valOrDash = v => (v ?? v === 0) && String(v).trim() !== '' ? esc(v) : '—';
      const numOrDash = v => (v ?? v === 0) && String(v).trim() !== '' ? esc(v) : '—';

      return `
      <div class="card-row" data-id="${m.id}"
           data-vrsta_id="${m.vrsta_id||''}"
           data-naziv="${escAttr(m.naziv||'')}" data-model="${escAttr(m.model||'')}"
           data-serija="${escAttr(m.serija||'')}" data-oblik="${escAttr(m.oblik||'')}"
           data-vrata="${escAttr(m.vrata??'')}" data-mjenjac="${escAttr(m.mjenjac||'')}"
           data-pogon="${escAttr(m.pogon||'')}" data-snaga="${escAttr(m.snaga??'')}"
           data-zapremina="${escAttr(m.zapremina??'')}" data-god_modela="${escAttr(m.god_modela??'')}"
           data-god_kraj="${escAttr(m.god_kraj??'')}" data-kataloska="${escAttr(m.kataloska??'')}">
        <div>${valOrDash(m.vrsta_naz || m.vrsta || '')}</div>
        <div>${valOrDash(m.naziv || '')}</div>
        <div>${valOrDash(m.model || '')}</div>
        <div>${valOrDash(m.serija)}</div>
        <div>${numOrDash(m.god_modela)}</div>
        <div>${numOrDash(m.god_kraj)}</div>
        <div>${valOrDash(m.oblik)}</div>
        <div>${numOrDash(m.vrata)}</div>
        <div>${numOrDash(m.snaga)}</div>
        <div>${numOrDash(m.zapremina)}</div>
        <div>${valOrDash(m.pogon)}</div>
        <div>${valOrDash(m.mjenjac)}</div>
        <div>${numOrDash(m.kataloska)}</div>
        <div class="acts">
          ${PICK ? '' : `
            <button class="act edit" title="Uredi"><i class="fa-solid fa-pen"></i></button>
            <button class="act del"  title="Obriši"><i class="fa-solid fa-trash"></i></button>
          `}
        </div>
      </div>
    `;
    }

    // -------- dohvat jedne stranice --------
    async function fetchPage(){
      if(state.loading) return;
      state.loading = true;

      const qs = new URLSearchParams({ q: state.q, page:String(state.page), page_size:String(state.pageSize) });
      Object.entries(state.filters||{}).forEach(([k,v])=>{ if(v) qs.set(k,v); });
      try{
        const res = await fetchJsonWithFallback(API.search.map(u=>u+'?'+qs.toString()), {cache:'no-store'});
        const out = res.data;
        const rows = Array.isArray(out) ? out : (out.data||[]);
        state.total = Array.isArray(out) ? rows.length : (out.total ?? 0);
        state.pages = Array.isArray(out) ? 1 : (out.pages ?? 0);

        $list.innerHTML = rows.map(rowToHTML).join('');
        rows.forEach(r => {
          registerOption('model', r.model);
          registerOption('oblik', r.oblik);
          registerOption('vrata', r.vrata);
          registerOption('pogon', r.pogon);
          registerOption('mjenjac', r.mjenjac);
        });
        refreshDynamicSelects({
          model: $model?.value || '',
          oblik: $oblik?.value || '',
          vrata: $vrata?.value || '',
          pogon: $pogon?.value || '',
          mjenjac: $mjenjac?.value || ''
        });
        show($empty, rows.length===0);
        updateInfo();
      }catch(e){
        console.error(e);
        $list.innerHTML = '';
        if($empty){
          show($empty,true);
          $empty.textContent = 'Greška pri dohvaćanju podataka.';
        }
      }finally{
        state.loading = false;
      }
    }

    // -------- search --------
    let t=null;
    function resetAndLoad(q){
      state.q = q||'';
      state.page=1; state.loading=false;
      fetchPage();
    }
    $q?.addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(()=>resetAndLoad($q.value.trim()), 250); });
    $q?.addEventListener('keydown', e=>{ if(e.key==='Enter'){ clearTimeout(t); resetAndLoad($q.value.trim()); } });

    // -------- filters --------
    let ft=null;
    function resetFiltersAndLoad(){
      state.filters = readFiltersFromUI();
      state.page=1; state.loading=false;
      fetchPage();
    }
    $filters.forEach(inp=>{
      inp.addEventListener('input', ()=>{ clearTimeout(ft); ft=setTimeout(resetFiltersAndLoad, 250); });
      inp.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); clearTimeout(ft); resetFiltersAndLoad(); } });
    });

    // -------- modal open/close --------
    function openNew(){
      if(PICK) return; // u pick modu nema dodavanja
      $title.textContent='Nova marka';
      $id.value='';
      $serija.value=''; $nazivSelect.value=''; $model.value='';
      $mjenjac.value=''; $pogon.value='';
      $snaga.value=''; $zapremina.value=''; $vrata.value='';
      $god_modela.value=''; $god_kraj.value=''; $kataloska.value='';
      refreshDynamicSelects({model:'', oblik:'', vrata:'', pogon:'', mjenjac:''});
      loadVrste('');
      loadMarke('');
      $msg.style.display='none';
      $wrap.classList.add('show');
      $nazivSelect.focus();
    }
    function openEdit(row){
      if(PICK) return;
      const d = row.dataset;
      $title.textContent='Uredi marku / vozilo';
      $id.value = d.id || '';
      $nazivSelect.value = d.naziv || '';
      $model.value = d.model || '';
      $serija.value = d.serija || '';
      $oblik.value = d.oblik || '';
      $vrata.value = d.vrata || '';
      $mjenjac.value = d.mjenjac || '';
      $pogon.value = d.pogon || '';
      $snaga.value = d.snaga || '';
      $zapremina.value = d.zapremina || '';
      $god_modela.value = d.god_modela || '';
      $god_kraj.value = d.god_kraj || '';
      $kataloska.value = d.kataloska || '';
      registerOption('model', d.model);
      registerOption('oblik', d.oblik);
      registerOption('vrata', d.vrata);
      registerOption('pogon', d.pogon);
      registerOption('mjenjac', d.mjenjac);
      refreshDynamicSelects({
        model: d.model || '',
        oblik: d.oblik || '',
        vrata: d.vrata || '',
        pogon: d.pogon || '',
        mjenjac: d.mjenjac || ''
      });
      loadVrste(row.dataset.vrsta_id || '');
      loadMarke(d.naziv || '');
      $msg.style.display='none';
      $wrap.classList.add('show');
      $nazivSelect.focus();
    }
    function closeModal(){ $wrap.classList.remove('show'); }
    $addTop?.addEventListener('click', openNew);
    $addMarka?.addEventListener('click', addMarkaToSelect);
    $addModel?.addEventListener('click', ()=>promptAddOption('model', $model, 'Naziv novog modela'));
    $addOblik?.addEventListener('click', ()=>promptAddOption('oblik', $oblik, 'Naziv novog oblika'));
    $addVrata?.addEventListener('click', ()=>promptAddOption('vrata', $vrata, 'Broj vrata', v => /^\d{1,2}$/.test(v) ? true : 'Unesi broj vrata (1-2 znamenke).'));
    $addPogon?.addEventListener('click', ()=>promptAddOption('pogon', $pogon, 'Opis pogona'));
    $addMjenjac?.addEventListener('click', ()=>promptAddOption('mjenjac', $mjenjac, 'Opis mjenjača'));
    $cancel?.addEventListener('click', closeModal);
    $close?.addEventListener('click', closeModal);
    $wrap?.addEventListener('click', e=>{ if(e.target===$wrap) closeModal(); });
    document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeModal(); });

    async function resolveVrstaPickerUrl(){
      if(vrstaPickerBaseUrl) return vrstaPickerBaseUrl;
      for(const candidate of vrstaPickerCandidates){
        try{
          const testUrl = new URL(candidate, location.href);
          let res = await fetch(testUrl, {method:'HEAD', cache:'no-store'});
          if(res.status === 405){
            res = await fetch(testUrl, {method:'GET', cache:'no-store'});
          }
          if(res.ok){
            vrstaPickerBaseUrl = testUrl.href;
            return vrstaPickerBaseUrl;
          }
        }catch(err){
          console.debug('Vrsta picker candidate failed:', candidate, err);
        }
      }
      return null;
    }

    async function openVrstaPicker(){
      const base = await resolveVrstaPickerUrl();
      if(!base){
        alert('Stranica s vrstama vozila nije pronađena.');
        return;
      }
      const pickerUrl = new URL(base);
      pickerUrl.searchParams.set('pick','1');
      const url = pickerUrl.toString();
      const w = window.open(url, 'vrstePicker', 'width=1100,height=760,menubar=no,toolbar=no');
      if(w) w.focus(); else location.href = url;
    }
    $pickVrsta?.addEventListener('click', openVrstaPicker);
    window.addEventListener('focus', ()=>{
      loadVrste($vrsta?.value || '');
      loadMarke($nazivSelect?.value || '');
    });

    // -------- spremi (create/update) --------
    async function saveMarka(){
      const naziv = ($nazivSelect?.value || '').trim();
      const body = {
        id: $id.value ? +$id.value : undefined,
        vrsta_id: $vrsta.value ? +$vrsta.value : null,
        serija: $serija.value.trim(),
        naziv,
        model:  $model.value.trim(),
        oblik:  $oblik.value.trim(),
        mjenjac:$mjenjac.value.trim(),
        pogon:  $pogon.value.trim(),
        snaga:  $snaga.value ? +$snaga.value : null,
        zapremina: $zapremina.value ? +$zapremina.value : null,
        vrata:  $vrata.value ? +$vrata.value : null,
        god_modela: $god_modela.value ? +$god_modela.value : null,
        god_kraj:   $god_kraj.value ? +$god_kraj.value : null,
        kataloska:  $kataloska.value ? +$kataloska.value : null
      };
      if(!body.naziv){
        $msg.textContent='Marka je obavezna.'; $msg.style.display='block'; return;
      }
      const url = body.id ? API.update : API.create;
      try{
        const res = await fetchJsonWithFallback(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
        const out = res.data;
        if(!out.ok){ throw new Error(out.error||'Greška.'); }
        closeModal();
        state.page=1; $list.innerHTML=''; fetchPage();
      }catch(e){
        $msg.textContent = e.message || 'Greška pri spremanju.'; $msg.style.display='block';
        console.error(e);
      }
    }
    $save?.addEventListener('click', saveMarka);

    // -------- delegacija klikova na listu --------
    document.addEventListener('click', async (e)=>{
      const row = e.target.closest('.card-row[data-id]');
      if(!row) return;

      // PICK: odabir za mobilne i desktop
      if(PICK && !e.target.closest('.acts')){
        selectRow(row);
        if (e.detail === 2) commitPick(row);    // dvoklik desktop
        return;
      }

      if(!PICK && e.target.closest('.act.edit')) { openEdit(row); return; }
      if(!PICK && e.target.closest('.act.del'))  {
        const id = +row.dataset.id;
        if(!confirm('Obrisati marku #'+id+'?')) return;
        try{
          const res = await fetchJsonWithFallback(API.delete, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id})});
          const out = res.data;
          if(!out.ok) throw new Error(out.error||'Greška pri brisanju.');
          row.remove(); updateInfo();
        }catch(err){ alert(err.message||'Greška pri brisanju.'); }
        return;
      }
    });

    // Single-tap/Enter potvrda u PICK modu
    let tapTimer=null;
    document.addEventListener('pointerup', (e)=>{
      if(!PICK) return;
      const row = e.target.closest('.card-row[data-id]');
      if(!row || e.target.closest('.acts')) return;
      selectRow(row);
      clearTimeout(tapTimer);
      tapTimer = setTimeout(()=>commitPick(row), 220); // “tap to pick”
    });
    document.addEventListener('keydown', (e)=>{
      if(!PICK) return;
      if(e.key==='Enter'){
        const row = $list.querySelector('.card-row.selected');
        if(row) commitPick(row);
      }
    });

    function selectRow(row){
      $list.querySelectorAll('.card-row.selected').forEach(r=>r.classList.remove('selected'));
      row.classList.add('selected');
    }
    function commitPick(row){
      const id = row.dataset.id;
      const label = (row.querySelector('.main')?.textContent || '').trim();
      if(window.opener && typeof window.opener.setSelectedVozilo === 'function'){
        window.opener.setSelectedVozilo(id, label);
        window.close();
      }
    }

    // -------- init --------
    refreshDynamicSelects();
    state.filters = readFiltersFromUI();
    if(!PICK){
      loadVrste('');
      loadMarke('');
    }
    resetAndLoad('');
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
