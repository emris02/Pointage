// Minimal justification modal handler — shows modal when data is present
(function(){
    function openJustification(data) {
        if (!data) return;
        // Create modal if not present
        var modalEl = document.getElementById('justificationModal');
        if (!modalEl) {
            var html = `
            <div class="modal fade" id="justificationModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Justification de retard</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <p id="justificationText">Veuillez fournir une justification pour votre retard.</p>
                    <div class="mb-3">
                      <label class="form-label">Raison</label>
                      <select id="justificationReason" class="form-select">
                        <option value="">Sélectionner</option>
                        <option value="transport">Problème de transport</option>
                        <option value="sante">Problème de santé</option>
                        <option value="autre">Autre</option>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Détails (optionnel)</label>
                      <textarea id="justificationDetails" class="form-control" rows="3"></textarea>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" id="submitJustificationBtn" class="btn btn-primary">Envoyer</button>
                  </div>
                </div>
              </div>
            </div>`;
            var div = document.createElement('div'); div.innerHTML = html;
            document.body.appendChild(div.firstElementChild);
            modalEl = document.getElementById('justificationModal');
        }

        // Fill info
        var txt = document.getElementById('justificationText');
        if (txt) txt.textContent = `Pointage : ${data.date_heure || ''} — Retard : ${data.retard_minutes || 0} min`;

        var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: false });
        bsModal.show();

        // Submit handler
        var submitBtn = document.getElementById('submitJustificationBtn');
        if (submitBtn) {
            submitBtn.onclick = function(){
                var reason = document.getElementById('justificationReason').value;
                var details = document.getElementById('justificationDetails').value;
                if (!reason) {
                    alert('Veuillez sélectionner une raison.'); return;
                }
                // Submit via fetch to justifier_pointage.php (legacy endpoint)
                var fd = new FormData();
                fd.append('pointage_id', data.pointage_id || '');
                fd.append('employe_id', data.employe_id || '');
                fd.append('raison', reason);
                fd.append('details', details);

                fetch('justifier_pointage.php', { method: 'POST', body: fd })
                .then(r => r.text())
                .then(() => { window.location.reload(); })
                .catch(() => { alert('Erreur réseau'); });
            };
        }
    }

    // Auto-open if the server adds a global var `JUSTIFICATION_PENDING`
    if (window.JUSTIFICATION_PENDING) {
        try { openJustification(window.JUSTIFICATION_PENDING); } catch(e){console.error(e);} 
    }

    // Expose
    window.openJustification = openJustification;
})();
