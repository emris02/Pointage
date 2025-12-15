<?php
// Modals pour le dashboard employé
?>

<!-- Modal Modification Photo -->
<div class="modal fade" id="editPhotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-camera me-2"></i>Changer ma photo de profil
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <img src="<?= !empty($employe['photo']) ? htmlspecialchars($employe['photo']) : 'assets/img/profil.png' ?>" 
                         class="img-fluid rounded-circle mb-3" 
                         alt="Photo actuelle"
                         style="width: 150px; height: 150px; object-fit: cover;">
                    <p class="text-muted small">Photo actuelle</p>
                </div>
                <form id="photoForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="photoFile" class="form-label">Sélectionner une nouvelle photo</label>
                        <input class="form-control" type="file" id="photoFile" name="photo" accept="image/jpeg,image/png,image/jpg">
                    </div>
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Taille maximale: 2MB. Formats acceptés: JPG, PNG.
                        </small>
                    </div>
                    <div id="photoPreview" class="text-center mt-3" style="display:none;">
                        <img id="previewImage" class="img-fluid rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                        <p class="text-muted small mt-2">Aperçu</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Annuler
                </button>
                <button type="button" class="btn btn-primary" id="uploadPhotoBtn">
                    <i class="fas fa-upload me-1"></i>Télécharger
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Modification Mot de Passe -->
<div class="modal fade" id="editPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-lock me-2"></i>Modifier mon mot de passe
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="passwordForm">
                    <div class="mb-3">
                        <label for="currentPassword" class="form-label">Mot de passe actuel</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="currentPassword" required>
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="currentPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">Nouveau mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="newPassword" required>
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="newPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength mt-2">
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar" id="passwordStrengthBar" style="width: 0%"></div>
                            </div>
                            <small id="passwordStrengthText" class="text-muted">Force du mot de passe</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Confirmer le nouveau mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirmPassword" required>
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="passwordMatch" class="mt-2"></div>
                    </div>
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-shield-alt me-1"></i>
                            Le mot de passe doit contenir au moins :
                            <ul class="mb-0 mt-1">
                                <li>8 caractères minimum</li>
                                <li>1 lettre majuscule</li>
                                <li>1 lettre minuscule</li>
                                <li>1 chiffre</li>
                                <li>1 caractère spécial</li>
                            </ul>
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Annuler
                </button>
                <button type="button" class="btn btn-primary" id="savePasswordBtn">
                    <i class="fas fa-save me-1"></i>Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Édition Profil Complet -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit me-2"></i>Modifier mon profil
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="profileForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editPrenom" class="form-label">Prénom</label>
                                <input type="text" class="form-control" id="editPrenom" 
                                       value="<?= htmlspecialchars($employe['prenom']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editNom" class="form-label">Nom</label>
                                <input type="text" class="form-control" id="editNom" 
                                       value="<?= htmlspecialchars($employe['nom']) ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="editEmail" 
                               value="<?= htmlspecialchars($employe['email']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editTelephone" class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" id="editTelephone" 
                               value="<?= htmlspecialchars($employe['telephone']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="editAdresse" class="form-label">Adresse</label>
                        <textarea class="form-control" id="editAdresse" rows="3"><?= htmlspecialchars($employe['adresse']) ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Poste</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($employe['poste']) ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Département</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($departement) ?>" readonly>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Annuler
                </button>
                <button type="button" class="btn btn-primary" id="saveProfileBtn">
                    <i class="fas fa-save me-1"></i>Enregistrer les modifications
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Aide et Support -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-question-circle me-2"></i>Centre d'aide
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <h6>Besoin d'aide ?</h6>
                    <p class="text-muted">Notre équipe support est là pour vous accompagner.</p>
                </div>
                
                <div class="help-options">
                    <div class="d-grid gap-2">
                        <a href="mailto:support@xpertpro.com" class="btn btn-outline-primary text-start">
                            <i class="fas fa-envelope me-2"></i>
                            <div>
                                <strong>Email support</strong>
                                <div class="small text-muted">support@xpertpro.com</div>
                            </div>
                        </a>
                        
                        <a href="tel:+33123456789" class="btn btn-outline-primary text-start">
                            <i class="fas fa-phone me-2"></i>
                            <div>
                                <strong>Support téléphonique</strong>
                                <div class="small text-muted">+33 1 23 45 67 89</div>
                            </div>
                        </a>
                        
                        <button class="btn btn-outline-primary text-start" onclick="openChat()">
                            <i class="fas fa-comments me-2"></i>
                            <div>
                                <strong>Chat en direct</strong>
                                <div class="small text-muted">Du lundi au vendredi, 9h-18h</div>
                            </div>
                        </button>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h6>Questions fréquentes</h6>
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    Comment régénérer mon badge ?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Cliquez sur "Générer un badge" dans la section badge. Un nouveau QR code sera créé et l'ancien deviendra invalide.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Que faire en cas d'oubli de pointage ?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Contactez votre manager ou le service RH pour régulariser votre pointage.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Mon badge ne fonctionne pas
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Vérifiez que votre badge n'est pas expiré. Si le problème persiste, régénérez un nouveau badge.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmation Déconnexion -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-question-circle fa-3x text-warning mb-3"></i>
                <h5>Êtes-vous sûr de vouloir vous déconnecter ?</h5>
                <p class="text-muted">Vous devrez vous reconnecter pour accéder à votre espace.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Annuler
                </button>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt me-1"></i>Se déconnecter
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Information Badge -->
<div class="modal fade" id="badgeInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-id-card me-2"></i>Informations sur le badge
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Fonctionnement du badge</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-qrcode text-primary me-2"></i>
                                <strong>QR Code unique</strong>
                                <p class="small text-muted mb-0">Généré automatiquement pour chaque employé</p>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-clock text-warning me-2"></i>
                                <strong>Validité limitée</strong>
                                <p class="small text-muted mb-0">24 heures de validité par défaut</p>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-sync text-success me-2"></i>
                                <strong>Régénération automatique</strong>
                                <p class="small text-muted mb-0">Nouveau badge après chaque pointage de départ</p>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Utilisation</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-camera text-info me-2"></i>
                                <strong>Scanner le QR code</strong>
                                <p class="small text-muted mb-0">Aux bornes de pointage pour enregistrer présence</p>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-print text-secondary me-2"></i>
                                <strong>Impression possible</strong>
                                <p class="small text-muted mb-0">Pour avoir une version physique</p>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-shield-alt text-danger me-2"></i>
                                <strong>Sécurisé</strong>
                                <p class="small text-muted mb-0">Chiffré et personnel</p>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important :</strong> Votre badge est personnel et ne doit pas être partagé. 
                    En cas de perte ou de vol, régénérez immédiatement un nouveau badge.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <?php if (!$badge_actif): ?>
                    <button type="button" class="btn btn-primary" onclick="generateBadge()">
                        <i class="fas fa-sync-alt me-1"></i>Générer mon badge
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.modal-content {
    border-radius: 15px;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.modal-header {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    border-radius: 15px 15px 0 0;
    border: none;
}

.modal-header .btn-close {
    filter: invert(1);
}

.help-options .btn {
    padding: 15px;
    border-radius: 10px;
    text-align: left;
}

.accordion-button:not(.collapsed) {
    background-color: #e3f2fd;
    color: #1976d2;
}

.password-strength .progress {
    border-radius: 3px;
}

.toggle-password {
    border-left: none;
}
</style>

<script>
// Gestion de la prévisualisation de photo
document.getElementById('photoFile').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('photoPreview');
    const previewImage = document.getElementById('previewImage');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
});

// Toggle visibilité mot de passe
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    });
});

// Vérification force mot de passe
document.getElementById('newPassword').addEventListener('input', function() {
    checkPasswordStrength(this.value);
});

document.getElementById('confirmPassword').addEventListener('input', function() {
    checkPasswordMatch();
});

function checkPasswordStrength(password) {
    const bar = document.getElementById('passwordStrengthBar');
    const text = document.getElementById('passwordStrengthText');
    
    let strength = 0;
    let feedback = '';
    
    if (password.length >= 8) strength += 25;
    if (/[A-Z]/.test(password)) strength += 25;
    if (/[a-z]/.test(password)) strength += 25;
    if (/[0-9]/.test(password)) strength += 15;
    if (/[^A-Za-z0-9]/.test(password)) strength += 10;
    
    bar.style.width = strength + '%';
    
    if (strength < 50) {
        bar.className = 'progress-bar bg-danger';
        feedback = 'Faible';
    } else if (strength < 75) {
        bar.className = 'progress-bar bg-warning';
        feedback = 'Moyen';
    } else {
        bar.className = 'progress-bar bg-success';
        feedback = 'Fort';
    }
    
    text.textContent = 'Force : ' + feedback;
}

function checkPasswordMatch() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (!confirmPassword) {
        matchDiv.innerHTML = '';
        return;
    }
    
    if (newPassword === confirmPassword) {
        matchDiv.innerHTML = '<small class="text-success"><i class="fas fa-check me-1"></i>Les mots de passe correspondent</small>';
    } else {
        matchDiv.innerHTML = '<small class="text-danger"><i class="fas fa-times me-1"></i>Les mots de passe ne correspondent pas</small>';
    }
}

function openChat() {
    alert('Le chat de support sera bientôt disponible !');
    // Implémentation future du chat
}

// Gestion de l'upload de photo
document.getElementById('uploadPhotoBtn').addEventListener('click', function() {
    const fileInput = document.getElementById('photoFile');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Veuillez sélectionner une photo');
        return;
    }
    
    // Vérification de la taille du fichier (2MB max)
    if (file.size > 2 * 1024 * 1024) {
        alert('La photo est trop volumineuse (max 2MB)');
        return;
    }
    
    // Vérification du type de fichier
    if (!['image/jpeg', 'image/png', 'image/jpg'].includes(file.type)) {
        alert('Format de fichier non supporté. Utilisez JPG ou PNG.');
        return;
    }
    
    // Ici, vous ajouterez la logique d'upload
    const formData = new FormData();
    formData.append('photo', file);
    formData.append('employe_id', <?= $employe_id ?>);
    
    // Exemple d'upload AJAX
    fetch('api/upload_photo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Photo mise à jour avec succès !');
            location.reload();
        } else {
            alert('Erreur lors de l\'upload : ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erreur lors de l\'upload de la photo');
    });
});
</script>