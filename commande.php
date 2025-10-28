<?php
session_start();
require_once 'db.php';
include 'menu.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer les commandes de l'utilisateur avec les détails des plans d'investissement
$commandes = $db->prepare("
    SELECT c.*, p.nom as plan_nom, p.image_url, p.serie, p.description
    FROM commandes c
    JOIN planinvestissement p ON c.plan_id = p.id
    WHERE c.user_id = ?
    ORDER BY c.date_debut DESC
");
$commandes->execute([$user_id]);
$commandes = $commandes->fetchAll();

// Fonction pour calculer le nombre de jours restants
function joursRestants($date_fin) {
    $now = new DateTime();
    $end = new DateTime($date_fin);
    if ($end < $now) return 0;
    return $end->diff($now)->days;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mes Portefeuilles - NVIDIA Technologies</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght=600;700&display=swap" rel="stylesheet">
    <style>
        /* Couleurs et Thème NVIDIA */
        :root {
            --primary-black: #000000;
            --soft-black: #1a1a1a;
            --warm-gray: #1f1f1f;
            --dark-gray: #333333;
            --accent-green: #76B900; /* Vert NVIDIA */
            --green-light: #90d800;
            --green-dark: #5c8e00;
            --text-light-green: #90ff00;
            --text-dark: #ffffff; /* Texte clair sur fond sombre */
            --text-gray: #aaaaaa;
            --text-light: #cccccc;
            --card-bg: rgba(25, 25, 25, 0.95);
            --border-color: rgba(118, 185, 0, 0.3);
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --nvidia-green: #76B900;
            --deep-black: #000000;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        html, body {
            height: 100%;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--primary-black) 0%, var(--soft-black) 50%, var(--warm-gray) 100%);
            color: var(--text-dark);
            line-height: 1.6;
            position: relative;
            -webkit-text-size-adjust: 100%;
            touch-action: pan-y;
            padding: 15px;
            user-select: none;
            -webkit-user-select: none;
        }
        
        /* Arrière-plan inspiré NVIDIA - Thème sombre */
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 10% 20%, rgba(118, 185, 0, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(118, 185, 0, 0.1) 0%, transparent 20%),
                linear-gradient(135deg, var(--primary-black) 0%, var(--soft-black) 100%);
            z-index: -3;
        }
        
        .geometric-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(30deg, rgba(118, 185, 0, 0.08) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.08) 87.5%, rgba(118, 185, 0, 0.08) 0),
                linear-gradient(150deg, rgba(118, 185, 0, 0.08) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.08) 87.5%, rgba(118, 185, 0, 0.08) 0),
                linear-gradient(60deg, rgba(118, 185, 0, 0.1) 25%, transparent 25.5%, transparent 75%, rgba(118, 185, 0, 0.1) 75%, rgba(118, 185, 0, 0.1) 0);
            background-size: 100px 175px;
            background-position: 0 0, 50px 87.5px;
            z-index: -2;
            animation: patternShift 30s linear infinite;
        }
        
        .blue-accent, .purple-accent {
            display: none; /* Supprimé ou remplacé pour le thème NVIDIA */
        }

        .green-accent-corner {
            position: fixed;
            top: 0;
            right: 0;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(118, 185, 0, 0.25) 0%, transparent 70%);
            filter: blur(80px);
            z-index: -1;
        }
        
        .light-shimmer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                125deg,
                transparent 0%,
                rgba(118, 185, 0, 0.1) 40%,
                transparent 50%,
                rgba(118, 185, 0, 0.1) 60%,
                transparent 100%
            );
            opacity: 0.2;
            z-index: -1;
            animation: lightShine 15s infinite linear;
        }
        
        /* Header compact */
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            animation: slideDown 0.8s ease-out;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-icon {
            width: 45px;
            height: 45px;
            background: var(--accent-green);
            border: none;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-black);
            font-weight: bold;
            font-size: 18px;
            box-shadow: 0 8px 25px rgba(118, 185, 0, 0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            /* Style inspiré du symbole de l'œil NVIDIA */
            border: 2px solid var(--primary-black); 
        }
        
        .logo-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(0, 0, 0, 0.4), transparent);
            transform: rotate(45deg);
            animation: logoShine 3s infinite;
        }
        
        .logo-icon:hover {
            transform: rotate(5deg) scale(1.05);
            box-shadow: 0 12px 35px rgba(118, 185, 0, 0.6);
        }
        
        .logo-text {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 22px;
            color: var(--text-dark); /* Blanc pour un fond sombre */
            letter-spacing: -0.5px;
            text-shadow: 0 0 5px rgba(118, 185, 0, 0.5);
        }
        
        .logo-subtext {
            font-size: 12px;
            color: var(--accent-green);
            margin-top: -2px;
            letter-spacing: 2px;
            font-weight: 500;
        }
        
        /* Section principale */
        .main-section {
            margin-bottom: 20px;
            animation: fadeInUp 0.8s ease-out;
        }
        
        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-subtitle {
            color: var(--text-gray);
            font-size: 14px;
            max-width: 600px;
            margin-bottom: 20px;
        }
        
        .status-badge {
            background: var(--accent-green);
            color: var(--primary-black);
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(118, 185, 0, 0.3);
        }
        
        /* Grille des portefeuilles */
        .portfolios-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .portfolio-card {
            background: var(--card-bg);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
        }
        
        .portfolio-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(118, 185, 0, 0.2);
        }
        
        .portfolio-header {
            padding: 20px 20px 15px 20px;
            background: rgba(118, 185, 0, 0.05);
            border-bottom: 1px solid var(--border-color);
        }
        
        .portfolio-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-light-green);
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .portfolio-serie {
            background: var(--accent-green);
            color: var(--primary-black);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .portfolio-description {
            color: var(--text-gray);
            font-size: 13px;
            line-height: 1.5;
        }
        
        .portfolio-content {
            padding: 20px;
        }
        
        .portfolio-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-light);
        }
        
        .meta-item i {
            color: var(--accent-green);
            font-size: 14px;
            width: 16px;
        }
        
        .progress-container {
            margin-bottom: 20px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
            color: var(--text-light);
        }
        
        .progress-bar {
            height: 10px;
            background: var(--dark-gray);
            border-radius: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--accent-green);
            border-radius: 5px;
            transition: width 1s ease-in-out;
        }
        
        .portfolio-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--border-color);
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .portfolio-amount {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-light-green);
        }
        
        .portfolio-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: rgba(118, 185, 0, 0.15);
            color: var(--accent-green);
            border: 1px solid var(--accent-green);
        }
        
        .status-completed {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid var(--warning);
        }
        
        .no-portfolios {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            animation: fadeInUp 1s ease-out;
        }
        
        .no-portfolios i {
            font-size: 48px;
            color: var(--accent-green);
            margin-bottom: 20px;
        }
        
        .no-portfolios h3 {
            color: var(--text-dark);
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .no-portfolios p {
            color: var(--text-gray);
            margin-bottom: 25px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .invest-btn {
            display: inline-block;
            background: var(--accent-green);
            color: var(--primary-black);
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(118, 185, 0, 0.4);
        }
        
        .invest-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(118, 185, 0, 0.6);
            background: var(--green-light);
        }
        
        /* Animations */
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
        
        @keyframes slideDown {
            from { 
                opacity: 0;
                transform: translateY(-30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Ajustement de l'animation patternShift pour le thème sombre */
        @keyframes patternShift {
            0% {
                background-position: 0 0, 50px 87.5px;
            }
            100% {
                background-position: 100px 175px, 150px 262.5px;
            }
        }
        
        @keyframes lightShine {
            0% {
                transform: translateX(-100%) translateY(-100%) rotate(45deg);
            }
            100% {
                transform: translateX(100%) translateY(100%) rotate(45deg);
            }
        }
        
        @keyframes logoShine {
            0% {
                transform: translateX(-100%) translateY(-100%) rotate(45deg);
            }
            100% {
                transform: translateX(100%) translateY(100%) rotate(45deg);
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .portfolios-grid {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 20px;
            }
            
            .portfolio-card {
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 12px;
            }
            
            .portfolio-meta {
                flex-direction: column;
                gap: 8px;
            }
            
            .portfolio-footer {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="background"></div>
    <div class="geometric-pattern"></div>
    <div class="green-accent-corner"></div> 
    <div class="light-shimmer"></div>
    
    <div class="container">
        <div class="header">
            <div class="logo">
                <div class="logo-icon">N</div>
                <div>
                    <div class="logo-text">NVIDIA</div>
                    <div class="logo-subtext">TECHNOLOGIES</div>
                </div>
            </div>
        </div>
        
        <div class="main-section">
            <div class="page-title">
                <i class="fas fa-microchip"></i> Mes Portefeuilles
                <div class="status-badge">
                    <i class="fas fa-server"></i> PLATINUM TIER
                </div>
            </div>
            <p class="page-subtitle">Surveillez la performance de tous vos investissements et leur progression en temps réel</p>
            
            <?php if (empty($commandes)): ?>
                <div class="no-portfolios">
                    <i class="fas fa-folder-open"></i>
                    <h3>Aucun investissement actif</h3>
                    <p>Vous n'avez pas encore créé d'action d'investissement avec NVIDIA Technologies.</p>
                    <a href="investissement.php" class="invest-btn">INVESTIR MAINTENANT</a>
                </div>
            <?php else: ?>
                <div class="portfolios-grid">
                    <?php foreach ($commandes as $commande): 
                        $jours_restants = joursRestants($commande['date_fin']);
                        $jours_total = (new DateTime($commande['date_fin']))->diff(new DateTime($commande['date_debut']))->days;
                        $jours_passes = $jours_total - $jours_restants;
                        // S'assurer que le pourcentage est entre 0 et 100
                        $pourcentage = $jours_total > 0 ? min(100, max(0, ($jours_passes / $jours_total) * 100)) : 0;
                    ?>
                        <div class="portfolio-card">
                            <div class="portfolio-header">
                                <div class="portfolio-title">
                                    <?= htmlspecialchars($commande['plan_nom']) ?>
                                    <div class="portfolio-serie"><?= htmlspecialchars($commande['serie']) ?></div>
                                </div>
                                <p class="portfolio-description"><?= htmlspecialchars(substr($commande['description'], 0, 150)) ?>...</p>
                            </div>
                            
                            <div class="portfolio-content">
                                <div class="portfolio-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        Début: <?= date('d/m/Y', strtotime($commande['date_debut'])) ?>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-check"></i>
                                        Fin: <?= date('d/m/Y', strtotime($commande['date_fin'])) ?>
                                    </div>
                                </div>
                                
                                <div class="progress-container">
                                    <div class="progress-label">
                                        <span>Progression du portefeuille</span>
                                        <span><?= round($pourcentage) ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $pourcentage ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="portfolio-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-coins"></i>
                                        Rendement journalier: <?= number_format($commande['gain_journalier'], 2, ',', ' ') ?> FCFA
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-wallet"></i>
                                        Capital initial: <?= number_format($commande['montant'], 2, ',', ' ') ?> FCFA
                                    </div>
                                </div>
                                
                                <div class="portfolio-footer">
                                    <div class="portfolio-amount">
                                        <?= number_format($commande['gain_journalier'] * $jours_passes, 2, ',', ' ') ?> FCFA générés
                                    </div>
                                    <div class="portfolio-status <?= $jours_restants > 0 ? 'status-active' : 'status-completed' ?>">
                                        <?= $jours_restants > 0 ? 'Actif (' . $jours_restants . 'j restants)' : 'Terminé' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Animation des barres de progression
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });
        });
    </script>
</body>
</html>