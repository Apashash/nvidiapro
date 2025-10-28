<?php
session_start();
require_once 'db.php';
include 'menu.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupération des données utilisateur et niveau VIP
$user = $db->prepare("
    SELECT u.*, v.niveau, v.pourcentage, v.invitations_requises, v.invitations_actuelles 
    FROM utilisateurs u 
    LEFT JOIN vip v ON u.id = v.user_id 
    WHERE u.id = ?
");
$user->execute([$user_id]);
$user = $user->fetch();

// Définition des salaires par niveau VIP
$salaires_par_niveau = [
    0 => 0,      // Niveau 0: 0 FCFA
    1 => 700,    // Niveau 1: 700 FCFA/jour
    2 => 1200,   // Niveau 2: 1200 FCFA/jour
    3 => 4000,   // Niveau 3: 4000 FCFA/jour
    4 => 5000,   // Niveau 4: 5000 FCFA/jour
    5 => 7500    // Niveau 5: 7500 FCFA/jour
];

$niveau_actuel = $user['niveau'] ?? 0;
$salaire_actuel = $salaires_par_niveau[$niveau_actuel];
$invitations_restantes = max(0, ($user['invitations_requises'] ?? 3) - ($user['invitations_actuelles'] ?? 0));

// Traitement de la réclamation du salaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reclamer_salaire'])) {
    if ($niveau_actuel == 0) {
        $message_erreur = "Vous devez atteindre le niveau VIP 1 pour recevoir un salaire.";
    } else {
        // Afficher le message de blacklist
        $message_blacklist = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salaire - NVIDIA Technology</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* VARIABLES THEME NVIDIA */
    :root {
        --nvidia-green: #76B900; 
        --nvidia-dark: #1e1e1e;
        --nvidia-black: #000000;
        --nvidia-light-gray: #f5f5f5;
        --nvidia-gray: #aaaaaa;
        --text-white: #ffffff;
        --text-light-gray: #e0e0e0;
        --error: #ef4444;
        --warning: #f59e0b;
        --transition: all 0.3s ease;
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
    }
    
    body {
        /* Fond sombre et technique */
        background-color: var(--nvidia-dark); 
        color: var(--text-light-gray);
        min-height: 100vh;
        overflow-x: hidden;
    }
    
    .container {
        max-width: 430px;
        margin: 0 auto;
        background: transparent;
        padding: 20px;
    }
    
    /* Header */
    .header {
        text-align: center;
        margin-bottom: 30px;
        animation: fadeInUp 0.8s ease-out;
    }
    
    .header h1 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 8px;
        /* Titre en vert NVIDIA */
        color: var(--nvidia-green); 
    }
    
    .header p {
        font-size: 16px;
        color: var(--nvidia-gray);
    }
    
    /* Carte Salaire Actuel */
    .salaire-card {
        background: var(--nvidia-black);
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 25px;
        /* Ombre technique verte */
        box-shadow: 0 0 20px rgba(118, 185, 0, 0.3);
        border: 1px solid rgba(118, 185, 0, 0.5);
        text-align: center;
        animation: fadeInUp 1s ease-out;
        position: relative;
        overflow: hidden;
    }
    
    .salaire-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        /* Motif subtil */
        background: radial-gradient(circle at center, rgba(118, 185, 0, 0.05) 1%, transparent 70%);
        z-index: -1;
    }
    
    .niveau-badge {
        display: inline-block;
        background-color: var(--nvidia-green);
        color: var(--nvidia-black);
        padding: 6px 15px;
        border-radius: 15px;
        font-weight: 700;
        font-size: 13px;
        margin-bottom: 15px;
        box-shadow: 0 4px 10px rgba(118, 185, 0, 0.4);
    }
    
    .salaire-montant {
        font-size: 40px;
        font-weight: 900;
        color: var(--text-white);
        margin: 15px 0;
        text-shadow: 0 0 10px rgba(118, 185, 0, 0.5);
    }
    
    .salaire-periode {
        color: var(--nvidia-gray);
        font-size: 14px;
        font-weight: 600;
    }
    
    /* Bouton Récupérer */
    .btn-recupérer {
        background-color: var(--nvidia-green);
        color: var(--nvidia-black);
        border: none;
        border-radius: 10px;
        padding: 16px 30px;
        font-size: 18px;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        width: 100%;
        margin: 20px 0;
        box-shadow: 0 4px 15px rgba(118, 185, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }
    
    .btn-recupérer:hover:not(:disabled) {
        transform: scale(1.02);
        box-shadow: 0 6px 20px rgba(118, 185, 0, 0.7);
    }
    
    .btn-recupérer:disabled {
        background: var(--nvidia-gray);
        color: #444;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    /* Section Progression et Information (style de carte unifié) */
    .progression-section, .info-section {
        background: var(--nvidia-black);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.05);
        animation: fadeInUp 1.2s ease-out;
    }
    
    .section-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--nvidia-green);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 2px solid rgba(118, 185, 0, 0.3);
        padding-bottom: 10px;
    }
    
    .niveau-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    
    .niveau-item:last-child {
        border-bottom: none;
    }
    
    .niveau-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .niveau-numero {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        background: rgba(118, 185, 0, 0.1);
        color: var(--nvidia-green);
        border: 1px solid rgba(118, 185, 0, 0.5);
    }
    
    .niveau-item.actuel .niveau-numero {
        background-color: var(--nvidia-green);
        color: var(--nvidia-black);
    }
    
    .niveau-salaire {
        font-weight: 700;
        color: var(--text-white);
    }
    
    .info-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
    }
    
    .info-icon {
        width: 35px;
        height: 35px;
        border-radius: 8px;
        background: rgba(118, 185, 0, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--nvidia-green);
        font-size: 16px;
        flex-shrink: 0;
        border: 1px solid rgba(118, 185, 0, 0.3);
    }
    
    .info-text {
        flex: 1;
        font-size: 14px;
        color: var(--text-light-gray);
    }
    
    /* Messages d'alerte */
    .alert {
        padding: 15px;
        border-radius: 10px;
        margin: 15px 0;
        font-weight: 600;
        text-align: center;
        animation: fadeInUp 0.5s ease-out;
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid var(--error);
        color: var(--error);
    }
    
    .alert-warning {
        background: rgba(245, 158, 11, 0.15);
        border: 1px solid var(--warning);
        color: var(--warning);
    }
    
    /* Popup Blacklist */
    .popup {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(5px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: fadeIn 0.3s ease;
    }
    
    .popup-content {
        background: var(--nvidia-black);
        width: 100%;
        max-width: 380px;
        border-radius: 15px;
        overflow: hidden;
        animation: popupIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 0 30px rgba(239, 68, 68, 0.5);
        border: 2px solid var(--error);
    }
    
    .popup-header {
        padding: 20px;
        background-color: var(--error);
        color: var(--text-white);
        text-align: center;
    }
    
    .popup-header h3 {
        font-weight: 700;
        font-size: 20px;
        margin: 0;
    }
    
    .popup-body {
        padding: 25px 20px 20px 20px;
        text-align: center;
    }
    
    .popup-icon {
        font-size: 48px;
        color: var(--error);
        margin-bottom: 15px;
    }
    
    .popup-message {
        font-size: 16px;
        color: var(--text-light-gray);
        line-height: 1.6;
        margin-bottom: 20px;
    }
    
    .popup-btn {
        background-color: var(--error);
        color: var(--text-white);
        border: none;
        padding: 15px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        width: 100%;
        margin-top: 10px;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }
    
    .popup-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(239, 68, 68, 0.6);
    }
    
    /* Animations (conservées) */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes fadeInUp {
        from { 
            opacity: 0;
            transform: translateY(20px);
        }
        to { 
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes popupIn {
        from { transform: scale(0.9); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
    
    /* Responsive (conservé) */
    @media (max-width: 480px) {
        .container {
            padding: 15px;
        }
        
        .header h1 {
            font-size: 24px;
        }
        
        .salaire-montant {
            font-size: 36px;
        }
        
        .btn-recupérer {
            padding: 16px 25px;
            font-size: 16px;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Salaire Quotidien - NVIDIA Technology</h1>
            <p>Recevez votre rémunération quotidienne selon votre niveau VIP</p>
        </div>
        
        <div class="salaire-card">
            <div class="niveau-badge">
                ACCÈS VIP <?= $niveau_actuel ?>
            </div>
            <div class="salaire-montant">
                <?= number_format($salaire_actuel, 0, ',', ' ') ?> FCFA
            </div>
            <div class="salaire-periode">
                Versement Journalier
            </div>
        </div>
        
        <?php if (isset($message_erreur)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $message_erreur ?>
            </div>
        <?php endif; ?>
        
        <?php if ($niveau_actuel == 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i> Invitez 3 utilisateurs pour activer votre premier versement (Niveau 1)
            </div>
        <?php endif; ?>
        
        <button class="btn-recupérer" 
                onclick="reclamerSalaire()" 
                <?= ($niveau_actuel == 0) ? 'disabled' : '' ?>>
            <i class="fas fa-money-bill-wave"></i>
            Récupérer Mon Salaire
        </button>
        
        <div class="progression-section">
            <div class="section-title">
                <i class="fas fa-rocket"></i>
                Progression des Rémunérations
            </div>
            
            <?php foreach ($salaires_par_niveau as $niveau => $salaire): ?>
                <div class="niveau-item <?= $niveau == $niveau_actuel ? 'actuel' : '' ?>">
                    <div class="niveau-info">
                        <div class="niveau-numero"><?= $niveau ?></div>
                        <div>Niveau VIP <?= $niveau ?></div>
                    </div>
                    <div class="niveau-salaire"><?= number_format($salaire, 0, ',', ' ') ?> FCFA/jour</div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="info-section">
            <div class="section-title">
                <i class="fas fa-microchip"></i>
                Critères d'Accès VIP
            </div>
            
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="info-text">
                    <strong>Niveau VIP 1:</strong> 3 invitations validées → 700 FCFA/jour
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="info-text">
                    <strong>Niveau VIP 2:</strong> 7 invitations validées → 1 200 FCFA/jour
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="info-text">
                    <strong>Niveau VIP 3:</strong> 12 invitations validées → 4 000 FCFA/jour
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="info-text">
                    <strong>Niveau VIP 4:</strong> 20 invitations validées → 5 000 FCFA/jour
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-gem"></i>
                </div>
                <div class="info-text">
                    <strong>Niveau VIP 5:</strong> 40 invitations validées → 7 500 FCFA/jour
                </div>
            </div>
        </div>
    </div>
    
    <div class="popup" id="blacklistPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h3>ACCÈS SYSTÈME BLOQUÉ</h3>
            </div>
            <div class="popup-body">
                <div class="popup-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="popup-message">
                    Votre accès a été **temporairement restreint** en raison d'une détection d'activité non conforme aux protocoles NVIDIA Technology (activité non autorisée sur certains niveaux inférieurs).<br><br>
                    Pour rétablir l'accès et recevoir votre versement, contactez le support technique NVIDIA Technology immédiatement.
                </div>
                <button class="popup-btn" onclick="closePopup()">
                    <i class="fas fa-headset"></i> Contacter le Support Technique
                </button>
            </div>
        </div>
    </div>

    <script>
        function reclamerSalaire() {
            // Afficher le popup de blacklist
            document.getElementById('blacklistPopup').style.display = 'flex';
        }
        
        function closePopup() {
            document.getElementById('blacklistPopup').style.display = 'none';
            // Rediriger vers le service client (URL conservée)
            window.open('https://t.me/blackrock_support', '_blank');
        }
        
        // Fermer le popup en cliquant à l'extérieur
        window.onclick = function(event) {
            const popup = document.getElementById('blacklistPopup');
            if (event.target === popup) {
                closePopup();
            }
        }
        
        // Empêcher le zoom sur mobile (conservé)
        document.addEventListener('wheel', function(e) {
            if (e.ctrlKey) e.preventDefault();
        }, { passive: false });
        
        document.addEventListener('gesturestart', function(e) {
            e.preventDefault();
        });
    </script>
</body>
</html>