// common.js (safe & minimal)
console.log('[common] učitan');

const KANTONI = ['KS - Kanton Sarajevo','ZE-DO - Zeničko Dobojski kanton','SBK/KSB - Kanton Središnja Bosna','HNK - Hercegovačko Neretvanski kanton','ZHK - Zapadnohercegovački kanton','USK - Unsko Sanski kanton','BPK - Bosanskopodrinjski kanton','TK - Tuzlanski kanton','PK - Posavski kanton', 'K10 - Kanton 10', '***','RS - Republika Srpska','Inozemstvo'];

window.fillKantonSelect = function(select, preselect=''){
  if(!select) return;
  select.innerHTML = ['<option value="">— odaberi —</option>']
    .concat(KANTONI.map(k=>`<option value="${k}">${k}</option>`))
    .join('');
  if(preselect !== null && preselect !== undefined && preselect!==''){
    select.value = String(preselect);
  }
};
