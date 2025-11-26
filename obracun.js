// obracun.js — forma + lista + pickeri (partner / vozilo)
(function(){
  // ----- ELEMENTI -----
  const $datum         = document.getElementById('o_datum');
  const $partnerId     = document.getElementById('o_partner_id');
  const $partnerLabel  = document.getElementById('o_partner_label');
  const $btnPickPartner= document.getElementById('btnPickPartner');
  const $opis          = document.getElementById('o_opis');
  const $btnPickVozilo = document.getElementById('btnPickVozilo');

  const $btnSave       = document.getElementById('oSave');
  const $btnReset      = document.getElementById('oReset');
  const $msgOk         = document.getElementById('oMsgOk');
  const $msgErr        = document.getElementById('oMsgErr');

  const $lQ            = document.getElementById('l_q');
  const $lRefresh      = document.getElementById('lRefresh');
  const $lBody         = document.getElementById('l_body');
  const $lHintEmpty    = document.getElementById('l_hint_empty');
  const $lInfo         = document.getElementById('l_info');

  // ----- API -----
  const baseRoot = location.pathname.includes('/app/') ? '../' : './';
  const baseApi = baseRoot;
  const API = {
    create: baseApi + 'obracun_create.php',
    list  : baseApi + 'obracun_list.php'
  };

  // ----- helper -----
  const show = (el,on)=>{ if(!el) return; el.style.display = on ? '' : 'none'; };

  // ----- datum danas -----
  (function setToday(){
    if(!$datum) return;
    const d = new Date();
    const pad = n => String(n).padStart(2,'0');
    $datum.value = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  })();

  // ====== PICKER: PARTNER ======
  window.setSelectedPartner = function(id, naziv){
    if($partnerId)    $partnerId.value = id;
    if($partnerLabel) $partnerLabel.value = naziv || ('#'+id);
  };

  function openPartnerPicker(){
    const w=1100,h=800, x=Math.max(0,(screen.width-w)/2), y=Math.max(0,(screen.height-h)/2);
    const url = baseRoot + 'partneri.html?pick=1';
    window.open(url,'pick_partner',`width=${w},height=${h},left=${x},top=${y},resizable=yes,scrollbars=yes`);
  }
  $btnPickPartner?.addEventListener('click', openPartnerPicker);

  // ====== PICKER: VOZILO (MARKE) ======
  window.setSelectedVozilo = function(id, label){
    // trenutno samo prikažemo izbor u opisu (ili dodaš posebna polja po želji)
    if($opis) {
      const base = ($opis.value||'').trim();
      $opis.value = base ? `${base}\nVozilo: ${label} (#${id})` : `Vozilo: ${label} (#${id})`;
    }
  };

  function openMarkaPicker(){
    const w=1100,h=800, x=Math.max(0,(screen.width-w)/2), y=Math.max(0,(screen.height-h)/2);
    const url = baseRoot + 'marka.html?pick=1';
    window.open(url,'pick_marka',`width=${w},height=${h},left=${x},top=${y},resizable=yes,scrollbars=yes`);
  }
  $btnPickVozilo?.addEventListener('click', openMarkaPicker);

  // ====== SPREMI OBRACUN ======
  async function saveObracun(){
    try{
      const body = {
        datum: $datum?.value || '',
        partner_id: $partnerId?.value ? +$partnerId.value : null,
        opis: $opis?.value?.trim() || ''
      };
      const r = await fetch(API.create, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
      const text = await r.text();
      let out; try{ out=JSON.parse(text); }catch(_){ throw new Error('Server nije vratio ispravan JSON.'); }
      if(!out.ok) throw new Error(out.error||'Greška pri spremanju.');
      show($msgErr,false); show($msgOk,true);
      loadList();
      // reset forme osim datuma
      $partnerId.value=''; $partnerLabel.value=''; $opis.value='';
      setTimeout(()=>show($msgOk,false), 1500);
    }catch(e){
      $msgErr.textContent = e.message || 'Greška.';
      show($msgErr,true); show($msgOk,false);
    }
  }
  $btnSave?.addEventListener('click', saveObracun);

  $btnReset?.addEventListener('click', ()=>{
    $partnerId.value=''; $partnerLabel.value=''; $opis.value='';
    show($msgOk,false); show($msgErr,false);
  });

  // ====== LISTA OBRACUNA ======
  async function loadList(){
    try{
      const r = await fetch(API.list, {cache:'no-store'});
      const text = await r.text();
      let out; try{ out=JSON.parse(text); }catch(_){ throw new Error('Neispravan JSON iz obracun_list.php'); }
      const rows = out.ok ? (out.data||[]) : [];
      renderList(rows);
    }catch(e){
      console.error(e);
      renderList([]);
    }
  }

  function renderList(rows){
    if(!rows.length){
      $lBody.innerHTML=''; show($lHintEmpty,true); $lInfo.textContent='Prikazano: 0'; return;
    }
    show($lHintEmpty,false);
    const html = rows.map(r=>`
      <div class="card-row">
        <div>${esc(r.datum||'')}</div>
        <div>${esc(r.partner_naz||r.partner||'')}</div>
        <div>${esc(r.opis||'')}</div>
        <div class="mono" style="text-align:right">${r.iznos_km!=null ? Number(r.iznos_km).toFixed(2) : ''}</div>
        <div class="acts">
          <!-- buduće akcije -->
        </div>
      </div>
    `).join('');
    $lBody.innerHTML = html;
    $lInfo.textContent = 'Prikazano: ' + rows.length;
  }
  function esc(s){
    return String(s ?? '').replace(/[&<>"']/g, m => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      "\"": "&quot;",
      "'": "&#39;"
    }[m]));
  }

  // lokalni filter
  let t=null;
  $lQ?.addEventListener('input', ()=>{
    clearTimeout(t);
    t=setTimeout(()=>{
      const term=($lQ.value||'').toLowerCase().trim();
      const cards=Array.from($lBody.children);
      let shown=0;
      cards.forEach(c=>{
        const txt=c.textContent.toLowerCase();
        const ok=!term || txt.includes(term);
        c.style.display=ok?'':'none';
        if(ok) shown++;
      });
      $lInfo.textContent='Prikazano: '+shown;
      show($lHintEmpty, shown===0);
    },200);
  });
  $lRefresh?.addEventListener('click', loadList);

  // ----- init -----
  loadList();
})();

