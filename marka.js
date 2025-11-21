// marka.js — lista + modal + pick-mode (?pick=1) + paginacija
(function () {
  if (!document.body.classList.contains('marke')) return;

  // -------- pick mode --------
  const params   = new URLSearchParams(location.search);
  const PICK     = params.get('pick') === '1';
  if (PICK) document.body.classList.add('pick');

  // -------- API --------
  const ROOT_URL = document.currentScript?.src
    ? new URL('.', document.currentScript.src)
    : new URL('./', location.href);
  const API = {
    search : new URL('marka_search.php', ROOT_URL).toString(),      // GET ?q=&page=&page_size=
    create : new URL('marka_create.php', ROOT_URL).toString(),
    update : new URL('marka_update.php', ROOT_URL).toString(),
    delete : new URL('marka_delete.php', ROOT_URL).toString(),
    vrste  : new URL('vrsta_list_auto.php', ROOT_URL).toString()    // GET ?all=1 (fallback na vrsta_list.php ispod)
  };

  // -------- elementi --------
  const $q          = document.getElementById('q');
  const $list       = document.getElementById('list');
  const $empty      = document.getElementById('empty');
  const $pageInfo   = document.getElementById('pageInfo');
  const $infoFilter = document.getElementById('infoFilter');

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
  const $naziv       = document.getElementById('m_naziv');
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

  const $addTop = document.getElementById('btnAddTop');
  const $pickVrsta = document.getElementById('btnPickVrsta');
  const vrstaPickerCandidates = location.pathname.includes('/app/')
    ? ['../vrsta.html', 'vrsta.html', '../app/vrsta.html', './vrsta.html']
    : ['vrsta.html', 'app/vrsta.html', './vrsta.html', '../vrsta.html'];
  let vrstaPickerBaseUrl = null;

  // util
  const esc  = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
  const escAttr = esc;
  const show = (el,on) => { if(!el) return; el.style.display = on ? '' : 'none'; };

  // -------- stanje liste --------
  const state = { q:'', page:1, pageSize:50, total:0, pages:0, loading:false };

  function updateInfo() {
    if($infoFilter){
      $infoFilter.textContent = state.q ? `Filter: "${state.q}"` : '';
    }
    if($pageInfo){
      const shown = ($list?.querySelectorAll('.card-row').length || 0);
      $pageInfo.textContent = `Prikazano: ${shown} od ukupno ${state.total || '…'}`;
    }
  }

  // -------- vrste (select) --------
  async function loadVrste(preselectId=null){
    if(!$vrsta) return;
    try{
      let r = await fetch(API.vrste + '?all=1', {cache:'no-store'});
      if(!r.ok) throw new Error('HTTP '+r.status);
      let out = await r.json();
      let rows = Array.isArray(out) ? out : (out.data||[]);
      if(!rows.length){
        // fallback na stariji endpoint
        r = await fetch(new URL('vrsta_list.php?all=1', ROOT_URL), {cache:'no-store'});
        out = await r.json();
        rows = Array.isArray(out) ? out : (out.data||[]);
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
        <div>${valOrDash(m.oblik)}</div>
        <div>${numOrDash(m.vrata)}</div>
        <div>${valOrDash(m.mjenjac)}</div>
        <div>${valOrDash(m.pogon)}</div>
        <div>${numOrDash(m.snaga)}</div>
        <div>${numOrDash(m.zapremina)}</div>
        <div>${numOrDash(m.god_modela)}</div>
        <div>${numOrDash(m.god_kraj)}</div>
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
    try{
      const r = await fetch(API.search + '?' + qs.toString(), {cache:'no-store'});
      if(!r.ok) throw new Error('HTTP '+r.status);
      const out = await r.json();
      const rows = Array.isArray(out) ? out : (out.data||[]);
      state.total = Array.isArray(out) ? rows.length : (out.total ?? 0);
      state.pages = Array.isArray(out) ? 1 : (out.pages ?? 0);

      $list.innerHTML = rows.map(rowToHTML).join('');
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

  // -------- modal open/close --------
  function openNew(){
    if(PICK) return; // u pick modu nema dodavanja
    $title.textContent='Nova marka';
    $id.value='';
    $serija.value=''; $naziv.value=''; $model.value='';
    $oblik.value=''; $mjenjac.value=''; $pogon.value='';
    $snaga.value=''; $zapremina.value=''; $vrata.value='';
    $god_modela.value=''; $god_kraj.value=''; $kataloska.value='';
    loadVrste('');
    $msg.style.display='none';
    $wrap.classList.add('show');
    $naziv.focus();
  }
  function openEdit(row){
    if(PICK) return;
    const d = row.dataset;
    $title.textContent='Uredi marku / vozilo';
    $id.value = d.id || '';
    $naziv.value = d.naziv || '';
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
    loadVrste(row.dataset.vrsta_id || '');
    $msg.style.display='none';
    $wrap.classList.add('show');
    $naziv.focus();
  }
  function closeModal(){ $wrap.classList.remove('show'); }
  $addTop?.addEventListener('click', openNew);
  $cancel?.addEventListener('click', closeModal);
  $close ?.addEventListener('click', closeModal);
  $wrap  ?.addEventListener('click', e=>{ if(e.target===$wrap) closeModal(); });
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
  window.addEventListener('focus', ()=>{ loadVrste($vrsta?.value || ''); });

  // -------- spremi (create/update) --------
  async function saveMarka(){
    const body = {
      id: $id.value ? +$id.value : undefined,
      vrsta_id: $vrsta.value ? +$vrsta.value : null,
      serija: $serija.value.trim(),
      naziv:  $naziv.value.trim(),
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
      const r = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
      const text = await r.text();
      let out; try{ out=JSON.parse(text); }catch(_){ throw new Error('Neispravan JSON: '+text); }
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
        const r = await fetch(API.delete, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id})});
        const out = await r.json();
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
  if(!PICK) loadVrste('');
  resetAndLoad('');
})();
