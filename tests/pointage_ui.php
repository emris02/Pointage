<?php 
require_once __DIR__ . '/../src/config/bootstrap.php';

// Récupération employés et admins
$stmt = $pdo->query("SELECT id, nom, prenom FROM employes WHERE statut = 'actif' ORDER BY nom, prenom");
$employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->query("SELECT id, nom, prenom FROM admins ORDER BY nom, prenom");
$admins = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Test Pointage (UI Amélioré)</title>
<style>
body{font-family:Arial,sans-serif;margin:20px;background:#f2f2f2}
.box{max-width:720px;margin:0 auto;padding:20px;border-radius:8px;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,0.08);}
select,input,button{padding:8px;margin:6px 0;width:100%;border-radius:4px;border:1px solid #ccc}
button{cursor:pointer;transition:0.3s}
button.active{background:#2b7a2b;color:#fff;border:none}
.row{display:flex;gap:8px}
.row > *{flex:1}
pre{background:#f5f5f5;padding:12px;border-radius:4px;white-space:pre-wrap}
.scan-zone{border:2px dashed #bbb;border-radius:8px;padding:18px;margin:8px 0;text-align:center;cursor:pointer;transition:0.3s}
.scan-pulse{display:flex;align-items:center;justify-content:center;width:260px;height:120px;border-radius:8px;background:linear-gradient(90deg,#f8f9fa,#fff);box-shadow:0 0 0 rgba(0,0,0,0.05);animation:pulse 2s infinite;}
@keyframes pulse{0%{box-shadow:0 0 0 rgba(43,122,43,0.4)}50%{box-shadow:0 0 20px rgba(43,122,43,0.6)}100%{box-shadow:0 0 0 rgba(43,122,43,0.4)}}
#toast{display:none;position:fixed;right:20px;bottom:20px;padding:14px;border-radius:6px;color:#fff;box-shadow:0 6px 18px rgba(0,0,0,0.12);z-index:9999;transition:0.3s}
.status-card{padding:12px;border-radius:6px;background:#fafafa;border:1px solid #eee;transition:0.3s}
.status-ok{background:#e0f8e9;border-color:#2b7a2b}
.status-warning{background:#fff4e5;border-color:#ff9800}
.status-error{background:#fdecea;border-color:#a12b2b}
.modal-overlay{position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;z-index:1000}
.modal-content{background:#fff;padding:20px;border-radius:8px;max-width:420px;width:92%;}
.modal-content h3{margin-top:0}
.modal-actions{display:flex;gap:8px;margin-top:12px}
.modal-actions button{flex:1}
</style>
</head>
<body>
<div class="box">
  <h2>Test visuel de pointage</h2>

  <label>Type d'utilisateur</label>
  <select id="userType" data-test-id="userType">
    <option value="employe">Employé</option>
    <option value="admin">Admin</option>
  </select>

  <div id="employeSelect">
    <label>Choisir employé</label>
    <select id="employeId" data-test-id="employeId">
      <?php foreach($employes as $e): ?>
        <option value="<?= htmlspecialchars($e['id']) ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div id="adminSelect" style="display:none">
    <label>Choisir admin</label>
    <select id="adminId" data-test-id="adminId">
      <?php foreach($admins as $a): ?>
        <option value="<?= htmlspecialchars($a['id']) ?>"><?= htmlspecialchars($a['nom'] . ' ' . $a['prenom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <label>Méthode de pointage</label>
  <div class="row">
    <button id="methodQR" class="method active" data-test-id="methodQR">📷 Scanner QR Code</button>
    <button id="methodToken" class="method" data-test-id="methodToken">🔑 Token</button>
  </div>

  <div class="scan-zone" id="scanZone" data-test-id="scanZone">
    <div class="scan-pulse" id="scanPulse">
      <div id="scanText">Cliquez ici pour simuler le scan</div>
    </div>
  </div>

  <div class="row">
    <button id="btnPoint" data-test-id="btnPoint">Pointer</button>
    <button id="btnAdminPoint" data-test-id="btnAdminPoint">Pointer Admin (direct)</button>
  </div>

  <h3>État actuel</h3>
  <div id="statusCard" class="status-card" data-test-id="statusCard">
    <div id="statusContent">Aucun utilisateur sélectionné.</div>
  </div>

  <h3>Résultat / Feedback</h3>
  <div id="toast"></div>
  <pre id="result" style="display:none">Aucun test effectué.</pre>
</div>

<div id="modalContainer"></div>

<script>
const appState = {
  currentUser: null,
  currentMethod: 'qr',
  lastScanToken: null
};

const userTypeEl = document.getElementById('userType');
const employeSelect = document.getElementById('employeSelect');
const adminSelect = document.getElementById('adminSelect');
const btnPoint = document.getElementById('btnPoint');
const btnAdminPoint = document.getElementById('btnAdminPoint');
const scanPulse = document.getElementById('scanPulse');
const statusCard = document.getElementById('statusCard');

function showToast(msg, ok=true){
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = ok?'#2b7a2b':'#a12b2b';
  t.style.display='block';
  setTimeout(()=>t.style.display='none',4000);
}

function setStatusCard(data){
  const el = document.getElementById('statusContent');
  if(!data || !data.success){
    el.innerHTML='<b>Utilisateur non sélectionné ou introuvable.</b>';
    statusCard.className='status-card status-error';
    return;
  }
  const d = data.data;
  el.innerHTML=`🟢 Dernière action : <b>${d.last_type||'Aucun'}</b><br>⏱️ Heure : <b>${d.last_time||'-'}</b><br>📍 Statut : <b>${d.status||'-'}</b>`;
  const statusLower = (d.status||'').toLowerCase();
  if(statusLower==='présent') statusCard.className='status-card status-ok';
  else if(statusLower==='en pause') statusCard.className='status-card status-warning';
  else statusCard.className='status-card status-error';
}

async function safeFetch(url, options){
  try{
    const res = await fetch(url, options);
    const text = await res.text();
    let parsed = null;
    try{ parsed = text ? JSON.parse(text) : null; } catch(e){ showToast('Erreur serveur: réponse invalide', false); console.error(text); parsed = null; }
    return { status: res.status, body: parsed };
  }catch(e){ showToast('Erreur réseau: '+e.message, false); return { status: 0, body: null }; }
}

async function refreshStatus(){
  const type = userTypeEl.value;
  const id = type==='admin'?document.getElementById('adminId').value:document.getElementById('employeId').value;
  const r = await safeFetch('get_status.php?type='+encodeURIComponent(type)+'&id='+encodeURIComponent(id));
  setStatusCard(r.body);
}

// Switch type utilisateur
userTypeEl.addEventListener('change', ()=>{
  employeSelect.style.display = userTypeEl.value==='employe'?'block':'none';
  adminSelect.style.display = userTypeEl.value==='admin'?'block':'none';
  refreshStatus();
});

// Méthode QR / Token
document.getElementById('methodQR').addEventListener('click',()=>{ appState.currentMethod='qr'; document.getElementById('methodQR').classList.add('active'); document.getElementById('methodToken').classList.remove('active'); });
document.getElementById('methodToken').addEventListener('click',()=>{ appState.currentMethod='token'; document.getElementById('methodToken').classList.add('active'); document.getElementById('methodQR').classList.remove('active'); });

// Scan badge
async function scanBadge(){
  const type = userTypeEl.value;
  const id = type==='admin'?document.getElementById('adminId').value:document.getElementById('employeId').value;
  showToast('Simulation de scan en cours...',true);
  try{
    const tResp = await safeFetch('get_token.php?id='+encodeURIComponent(id)+'&type='+encodeURIComponent(type));
    if(!tResp || tResp.status !== 200 || !tResp.body || tResp.body.status !== 'success') throw new Error(tResp?.body?.message||'Erreur génération token');
    const token = tResp.body.token;
    appState.lastScanToken = token;

    const endpoint = appState.currentMethod==='qr'?'../api/scan_qr.php':'../validate_badge.php';
    const res = await safeFetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({badge_data:token,badge_token:token})});
    if(!res) return;

    // Conflict or duplicate -> request confirmation
    if(res.status === 409 || (res.body && (res.body.code === 'NEEDS_CONFIRMATION' || res.body.code === 'POINTAGE_DUPLICATE' || res.body.success === false))){
      showDuplicateModal(res.body || { message: 'Pointage en conflit' });
      return;
    }

    // Success
    const body = res.body || {};
    if(body.success === true || body.status === 'success' || body.status === 'ok'){
      showToast('Pointage réussi: '+(body.data?.type||body.message||'OK'),true);
      await refreshStatus();
    } else {
      showToast('Pointage échoué', false);
    }
  }catch(e){ showToast('Erreur: '+e.message,false); }
}

btnPoint.addEventListener('click',scanBadge);
scanPulse.addEventListener('click',scanBadge);

// Admin direct
btnAdminPoint.addEventListener('click', async ()=>{
  const id = document.getElementById('adminId').value;
  const r = await safeFetch('do_admin_pointage.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({admin_id:id})});
  if(r?.body && (r.body.status==='success' || r.body.success===true)){ showToast(r.body.message,true); await refreshStatus(); }
  else showToast(r?.body?.message||'Erreur',false);
});

// Duplicate modal
function showDuplicateModal(payload){
  let modal = document.getElementById('dupModal');
  if(!modal){
    modal = document.createElement('div'); modal.id='dupModal'; modal.className='modal-overlay';
    modal.innerHTML=`<div class="modal-content">
      <h3>Action détectée</h3>
      <p id="dupMsg">${payload.message||'Pointage déjà effectué'}</p>
      <div class="modal-actions">
        <button id="dupPause">⏸️ Pause</button>
        <button id="dupDepart">🚪 Départ anticipé</button>
        <button id="dupCancel">❌ Annuler</button>
      </div>
    </div>`;
    document.getElementById('modalContainer').appendChild(modal);
    document.getElementById('dupCancel').addEventListener('click',()=>modal.remove());
    document.getElementById('dupPause').addEventListener('click', async ()=>{
      const mins = prompt('Durée de la pause (minutes)','30'); if(!mins) return;
      const type = userTypeEl.value; const id = type==='admin'?document.getElementById('adminId').value:document.getElementById('employeId').value;
      const r = await safeFetch('do_pause.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type,id,minutes:mins})});
      showToast(r?.body?.message||'Pause enregistrée', r?.body?.status==='success'); modal.remove(); await refreshStatus();
    });
    document.getElementById('dupDepart').addEventListener('click', async ()=>{
      const reason = prompt('Motif du départ anticipé (obligatoire)'); if(!reason) return alert('Motif requis');
      const type = userTypeEl.value; const id = type==='admin'?document.getElementById('adminId').value:document.getElementById('employeId').value;
      const r = await safeFetch('do_depart.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type,id,reason})});
      showToast(r?.body?.message||'Départ enregistré', r?.body?.status==='success'); modal.remove(); await refreshStatus();
    });
  } else {
    document.getElementById('dupMsg').textContent = payload.message||'Pointage déjà effectué';
    modal.style.display='flex';
  }
}

// Initial load
refreshStatus();
</script>
</body>
</html>
