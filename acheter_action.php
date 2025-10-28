<?php
session_start();
require 'db.php'; // Assurez-vous que ce fichier existe et fonctionne correctement

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

if (!isset($_POST['plan_id']) || !isset($_POST['prix'])) {
    header('Location: investissement.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$plan_id = intval($_POST['plan_id']);
$montant = floatval($_POST['prix']);

// Récupérer le plan choisi
$stmtPlan = $db->prepare("SELECT * FROM planinvestissement WHERE id = ?");
$stmtPlan->execute([$plan_id]);
$plan = $stmtPlan->fetch();

if (!$plan) {
    header('Location: investissement.php');
    exit;
}

// Récupérer le solde utilisateur
$stmtSolde = $db->prepare("SELECT solde FROM soldes WHERE user_id = ?");
$stmtSolde->execute([$user_id]);
$solde = $stmtSolde->fetchColumn();
if ($solde === false) $solde = 0;

if ($solde < $montant) {
    header('Location: depot.php');
    exit;
}

$gain_journalier = $plan['prix'] * ($plan['rendement_journalier'] / 100);
$duree = intval($plan['duree_jours']);

// MODIFICATION : Utiliser NOW() normal et ajouter 7 heures
// 1. Insérer la commande avec NOW() + 7 heures
$stmtCmd = $db->prepare("INSERT INTO commandes (user_id, plan_id, montant, gain_journalier, date_debut, date_fin) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 HOUR), DATE_ADD(DATE_ADD(NOW(), INTERVAL 7 HOUR), INTERVAL ? DAY))");
$stmtCmd->execute([$user_id, $plan_id, $montant, $gain_journalier, $duree]);

// 2. Déduire le solde
$stmtMaj = $db->prepare("UPDATE soldes SET solde = solde - ? WHERE user_id = ?");
$stmtMaj->execute([$montant, $user_id]);

// 3. Récompense parrain (20% - ajusté depuis 15% dans votre code initial)
$stmtParrain = $db->prepare("SELECT parrain_id FROM utilisateurs WHERE id = ?");
$stmtParrain->execute([$user_id]);
$parrain_id = $stmtParrain->fetchColumn();

if ($parrain_id) {
    $bonus = round($montant * 0.20, 2);
    // Vérifie si la ligne existe pour le parrain
    $stmtCheck = $db->prepare("SELECT solde FROM soldes WHERE user_id = ?");
    $stmtCheck->execute([$parrain_id]);
    $soldeParrain = $stmtCheck->fetchColumn();
    if ($soldeParrain === false) {
        // Crée la ligne si elle n'existe pas
        $stmtInsert = $db->prepare("INSERT INTO soldes (user_id, solde) VALUES (?, ?)");
        $stmtInsert->execute([$parrain_id, $bonus]);
    } else {
        $stmtBonus = $db->prepare("UPDATE soldes SET solde = solde + ? WHERE user_id = ?");
        $stmtBonus->execute([$bonus, $parrain_id]);
    }
}

// 4. Récupérer les dates pour l'affichage
$stmtDates = $db->prepare("SELECT date_debut, date_fin FROM commandes WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmtDates->execute([$user_id]);
$dates = $stmtDates->fetch();

$date_debut = $dates['date_debut'];
$date_fin = $dates['date_fin'];

// 5. Récupérer infos pour l'affichage
$plan_name = $plan['nom'];
$plan_img = $plan['image_url'];
$plan_desc = $plan['description'];
?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation d'Investissement - NVIDIA Technologies</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* VARIABLES THEME NVIDIA */
    :root {
        --primary-green: #76b900; /* NVIDIA Green */
        --green-light: #90d300;
        --green-dark: #5a8e00;
        --accent-gray: #4a4a4a; /* Dark Gray for accents */
        --text-dark: #1e1e1e;
        --text-light: #f0f0f0;
        --card-bg: rgba(255, 255, 255, 0.98);
        --hero-bg: #1c1c1c; /* Almost Black */
        --success: #10b981;
        --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
    }
    
    body {
        /* Fond sobre et sombre pour le thème Tech */
        background: radial-gradient(circle at 10% 20%, rgba(28, 28, 28, 0.8) 0%, rgba(10, 10, 10, 1) 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        color: var(--text-dark);
        overflow-x: hidden;
    }
    
    /* Arrière-plan animé - Effet Tech/Circuit */
    .background-animation {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: 
            radial-gradient(circle at 90% 10%, rgba(118, 185, 0, 0.08) 0%, transparent 50%),
            radial-gradient(circle at 10% 80%, rgba(74, 74, 74, 0.1) 0%, transparent 50%);
        z-index: -1;
        animation: float 20s ease-in-out infinite;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-15px) rotate(-1deg); }
    }
    
    .confirmation-container {
        width: 100%;
        max-width: 900px;
        background: var(--card-bg);
        border-radius: 12px; /* Plus angulaire */
        overflow: hidden;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
        border: 2px solid var(--primary-green);
        animation: slideUp 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        display: grid;
        grid-template-columns: 1fr 1.2fr; /* Plus de place pour les détails */
        min-height: 550px;
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(50px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    /* Section gauche - Illustration et message */
    .confirmation-hero {
        background: var(--hero-bg);
        padding: 40px 30px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        position: relative;
        overflow: hidden;
        border-right: 4px solid var(--primary-green); /* Bordure NVIDIA */
    }
    
    .confirmation-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: 
            radial-gradient(circle at 15% 15%, rgba(118, 185, 0, 0.2) 0%, transparent 20%),
            radial-gradient(circle at 85% 85%, rgba(118, 185, 0, 0.2) 0%, transparent 20%);
        z-index: 1;
    }
    
    .success-icon {
        width: 100px;
        height: 100px;
        background: var(--primary-green);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 25px;
        position: relative;
        z-index: 2;
        animation: iconPop 1s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
        box-shadow: 0 10px 30px rgba(118, 185, 0, 0.5);
    }
    
    @keyframes iconPop {
        0% {
            transform: scale(0);
            opacity: 0;
        }
        70% {
            transform: scale(1.2);
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }
    
    .success-icon i {
        font-size: 48px;
        color: var(--hero-bg); /* Couleur sombre sur le vert */
        animation: checkmark 0.6s ease-in-out 0.5s both;
    }
    
    @keyframes checkmark {
        0% {
            transform: scale(0) rotate(-45deg);
        }
        100% {
            transform: scale(1) rotate(0deg);
        }
    }
    
    .hero-title {
        font-size: 32px;
        font-weight: 800;
        color: white;
        margin-bottom: 15px;
        position: relative;
        z-index: 2;
        text-shadow: 0 2px 10px rgba(0,0,0,0.5);
    }
    
    .hero-subtitle {
        font-size: 18px;
        color: var(--text-light);
        line-height: 1.6;
        position: relative;
        z-index: 2;
        max-width: 300px;
    }
    
    /* Section droite - Détails de l'investissement */
    .confirmation-details {
        padding: 40px 35px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    .plan-header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid rgba(118, 185, 0, 0.15); /* Ligne de séparation verte légère */
    }
    
    .plan-image {
        width: 80px;
        height: 80px;
        border-radius: 8px; /* Plus carré */
        overflow: hidden;
        border: 3px solid var(--primary-green);
        box-shadow: 0 8px 20px rgba(118, 185, 0, 0.3);
        flex-shrink: 0;
    }
    
    .plan-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .plan-info h2 {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary-green);
        margin-bottom: 8px;
    }
    
    .plan-info p {
        color: var(--accent-gray);
        font-size: 14px;
        line-height: 1.5;
    }
    
    /* Grille horizontale des statistiques */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 35px;
    }
    
    .stat-card {
        background: var(--text-light);
        border-radius: 10px;
        padding: 20px;
        border-left: 5px solid var(--primary-green); /* Bande de couleur NVIDIA */
        transition: var(--transition);
        text-align: left;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(118, 185, 0, 0.15);
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 800;
        color: var(--text-dark); /* Sombre pour plus de contraste */
        margin-bottom: 5px;
        display: block;
    }
    
    .stat-label {
        font-size: 13px;
        color: var(--primary-green);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }
    
    /* Timeline horizontale - Ajusté pour un look Tech/Progression */
    .timeline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 35px;
        position: relative;
    }
    
    .timeline::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 5%;
        right: 5%;
        height: 4px;
        background: repeating-linear-gradient(-45deg, var(--accent-gray), var(--accent-gray) 10px, #e0e0e0 10px, #e0e0e0 20px);
        z-index: 1;
        border-radius: 2px;
    }
    
    .timeline-point {
        width: 40px;
        height: 40px;
        background: #e0e0e0;
        border: 4px solid var(--accent-gray);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        z-index: 2;
        transition: var(--transition);
    }
    
    .timeline-point.active {
        background: var(--primary-green);
        border-color: var(--green-dark);
        color: white;
        transform: scale(1.15);
    }
    
    .timeline-point i {
        font-size: 16px;
    }
    
    .timeline-label {
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        margin-top: 10px;
        font-size: 12px;
        color: var(--text-dark);
        white-space: nowrap;
        font-weight: 700;
    }
    
    /* Actions */
    .confirmation-actions {
        display: flex;
        gap: 15px;
        margin-top: auto;
    }
    
    .btn {
        flex: 1;
        padding: 16px 24px;
        border: none;
        border-radius: 6px; /* Plus angulaire pour le look tech */
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        text-align: center;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .btn-primary {
        background: var(--primary-green);
        color: var(--hero-bg); /* Texte sombre sur le vert */
        box-shadow: 0 6px 20px rgba(118, 185, 0, 0.4);
    }
    
    .btn-primary:hover {
        background: var(--green-light);
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(118, 185, 0, 0.6);
    }
    
    .btn-secondary {
        background: var(--accent-gray);
        color: white;
    }
    
    .btn-secondary:hover {
        background: #333;
        transform: translateY(-3px);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .confirmation-container {
            grid-template-columns: 1fr;
            max-width: 430px;
        }
        
        .confirmation-hero {
            padding: 30px 20px;
            border-right: none;
            border-bottom: 4px solid var(--primary-green);
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .confirmation-actions {
            flex-direction: column;
        }
        
        .timeline {
            flex-direction: column;
            gap: 30px;
        }
        
        .timeline::before {
            width: 4px;
            height: 90%;
            top: 5%;
            left: 50%;
            right: unset;
            transform: translateX(-50%);
        }
        
        .timeline-label {
            top: 50%;
            left: 100%;
            transform: translateY(-50%);
            margin-top: 0;
            margin-left: 15px;
            text-align: left;
        }
    }
    
    /* Animations */
    .stat-card:nth-child(1) { animation: fadeInUp 0.6s 0.2s both; }
    .stat-card:nth-child(2) { animation: fadeInUp 0.6s 0.3s both; }
    .stat-card:nth-child(3) { animation: fadeInUp 0.6s 0.4s both; }
    .stat-card:nth-child(4) { animation: fadeInUp 0.6s 0.5s both; }
    
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
    
    /* Badge de succès */
    .success-badge {
        position: absolute;
        top: 20px;
        right: 20px;
        background: var(--primary-green);
        color: var(--hero-bg);
        padding: 8px 16px;
        border-radius: 4px; /* Angulaire */
        font-size: 12px;
        font-weight: 700;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    </style>
</head>
<body>
    <div class="background-animation"></div>
    
    <div class="confirmation-container">
        <div class="confirmation-hero">
            <div class="success-badge">ACTIF</div>
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="hero-title">Investissement Confirmé !</h1>
            <p class="hero-subtitle">
                Félicitations ! Votre plan d'investissement est maintenant actif chez NVIDIA Technologies.
            </p>
        </div>
        
        <div class="confirmation-details">
            <div class="plan-header">
                <div class="plan-image">
                    <img src="<?= htmlspecialchars($plan_img ?: 'assets/vip.jpg') ?>" alt="<?= htmlspecialchars($plan_name) ?>">
                </div>
                <div class="plan-info">
                    <h2><?= htmlspecialchars($plan_name) ?></h2>
                    <p><?= htmlspecialchars($plan_desc) ?></p>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-value"><?= number_format($montant, 0, ',', ' ') ?> FCFA</span>
                    <div class="stat-label">Montant Investi</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value">+<?= number_format($gain_journalier, 0, ',', ' ') ?> FCFA</span>
                    <div class="stat-label">Gain Quotidien</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?= $duree ?> jours</span>
                    <div class="stat-label">Durée du Plan</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?= number_format($gain_journalier * $duree, 0, ',', ' ') ?> FCFA</span>
                    <div class="stat-label">Gain Total Estimé</div>
                </div>
            </div>
            
            <div class="timeline">
                <div class="timeline-point active">
                    <i class="fas fa-play"></i>
                    <div class="timeline-label">Début</div>
                </div>
                <div class="timeline-point">
                    <i class="fas fa-sync"></i>
                    <div class="timeline-label">En cours</div>
                </div>
                <div class="timeline-point">
                    <i class="fas fa-trophy"></i>
                    <div class="timeline-label">Terminé</div>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-value"><?= date('d/m/Y H:i', strtotime($date_debut)) ?></span>
                    <div class="stat-label">Date de Début</div>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?= date('d/m/Y H:i', strtotime($date_fin)) ?></span>
                    <div class="stat-label">Date de Fin Estimée</div>
                </div>
            </div>
            
            <div class="confirmation-actions">
                <a href="investissement.php" class="btn btn-primary">
                    <i class="fas fa-chart-line"></i>
                    Nouvel Investissement
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i>
                    Tableau de Bord
                </a>
            </div>
        </div>
    </div>

    <script>
    // Animation supplémentaire pour les éléments
    document.addEventListener('DOMContentLoaded', function() {
        // Animation des points de timeline
        const timelinePoints = document.querySelectorAll('.timeline-point');
        timelinePoints.forEach((point, index) => {
            setTimeout(() => {
                point.style.animation = 'fadeInUp 0.6s both';
            }, index * 300);
        });
        
        // Confetti virtuel - Ajusté pour un effet tech (légère modification du fond)
        setTimeout(() => {
            document.body.style.background = 'radial-gradient(circle at 10% 20%, rgba(28, 28, 28, 0.8) 0%, rgba(10, 10, 10, 1) 100%)';
        }, 1000);
    });
    </script>
</body>
</html>