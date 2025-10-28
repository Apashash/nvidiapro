<?php
session_start();
require 'db.php';
include 'menu.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fonction pour récupérer les filleuls par niveau
function getFilleulsByLevel($db, $user_id, $level) {
    $filleuls = [];
    
    if (!is_numeric($user_id) || !is_numeric($level) || $level < 1) {
        return $filleuls;
    }

    $current_level = [$user_id];
    
    for ($i = 1; $i <= $level; $i++) {
        if (empty($current_level)) {
            break;
        }
        
        try {
            $placeholders = implode(',', array_fill(0, count($current_level), '?'));
            $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE parrain_id IN ($placeholders)");
            $stmt->execute($current_level);
            $current_level = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            
            if ($i == $level) {
                $filleuls = $current_level;
            }
        } catch (PDOException $e) {
            error_log("Erreur getFilleulsByLevel: ".$e->getMessage());
            break;
        }
    }
    
    return $filleuls;
}

// Fonction pour calculer l'investissement actif
function getInvestissementActif($db, $user_ids) {
    $total = 0;
    
    if (!is_array($user_ids) || empty($user_ids)) {
        return $total;
    }

    $valid_ids = array_filter($user_ids, 'is_numeric');
    if (empty($valid_ids)) {
        return $total;
    }

    try {
        $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(montant), 0) 
            FROM commandes 
            WHERE user_id IN ($placeholders) 
            AND statut = 'actif'
            AND date_fin >= CURDATE()
        ");
        $stmt->execute($valid_ids);
        $total = (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur getInvestissementActif: ".$e->getMessage());
    }
    
    return $total;
}

// Calcul des données
$filleuls_niveau1 = getFilleulsByLevel($db, $user_id, 1);
$filleuls_niveau2 = getFilleulsByLevel($db, $user_id, 2);
$filleuls_niveau3 = getFilleulsByLevel($db, $user_id, 3);

$count_niveau1 = count($filleuls_niveau1);
$count_niveau2 = count($filleuls_niveau2);
$count_niveau3 = count($filleuls_niveau3);

$invest_niveau1 = getInvestissementActif($db, $filleuls_niveau1);
$invest_niveau2 = getInvestissementActif($db, $filleuls_niveau2);
$invest_niveau3 = getInvestissementActif($db, $filleuls_niveau3);

$gains_niveau1 = $invest_niveau1 * 0.15;
$gains_niveau2 = $invest_niveau2 * 0.05;
$gains_niveau3 = $invest_niveau3 * 0.02;
$gains_totaux = $gains_niveau1 + $gains_niveau2 + $gains_niveau3;
$total_filleuls = $count_niveau1 + $count_niveau2 + $count_niveau3;

// Récupération du lien de parrainage
try {
    $stmt = $db->prepare("SELECT lien_parrainage, nom FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $lien_parrainage = $user_data['lien_parrainage'] ?? '';
    $nom_utilisateur = $user_data['nom'] ?? '';
} catch (PDOException $e) {
    $lien_parrainage = '';
    $nom_utilisateur = '';
    error_log("Erreur récupération lien parrainage: ".$e->getMessage());
}

// Récupération des filleuls directs
try {
    $stmt = $db->prepare("
        SELECT u.id, u.nom, u.date_inscription
        FROM utilisateurs u
        WHERE u.parrain_id = ?
        ORDER BY u.date_inscription DESC
    ");
    $stmt->execute([$user_id]);
    $filleuls_directs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $filleuls_directs = [];
    error_log("Erreur récupération filleuls directs: ".$e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon équipe - NVIDIA Technologie</title> <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Couleurs NVIDIA (Vert, Noir, Charbon) */
            --primary-black: #111111; /* Fond principal plus sombre */
            --soft-black: #1a1a1a;    /* Pour le fond des cartes */
            --light-gray: #2c2c2c;    /* Pour les bordures et séparateurs */
            --accent-green: #76b900;  /* Le vert emblématique de NVIDIA */
            --green-light: #90d300;
            --green-dark: #5a8c00;
            --text-light: #ffffff;    /* Texte principal blanc */
            --text-gray: #a0a0a0;     /* Texte secondaire/gris */
            --card-bg: rgba(26, 26, 26, 0.95); /* Arrière-plan des cartes avec une légère transparence */
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
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
            /* Fond Noir Charbon avec un gradient subtil */
            background: linear-gradient(135deg, var(--primary-black) 0%, var(--soft-black) 50%, #0a0a0a 100%);
            color: var(--text-light);
            line-height: 1.6;
            position: relative;
            -webkit-text-size-adjust: 100%;
            touch-action: pan-y;
            padding: 15px;
        }
        
        /* Arrière-plan technologique/géométrique */
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            /* Gradient radial subtil */
            background: 
                radial-gradient(circle at 10% 20%, rgba(118, 185, 0, 0.1) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(118, 185, 0, 0.08) 0%, transparent 20%),
                linear-gradient(135deg, var(--primary-black) 0%, var(--soft-black) 100%);
            z-index: -3;
        }
        
        .geometric-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            /* Motif Hexagonal subtil en vert */
            background-image: 
                linear-gradient(100deg, rgba(118, 185, 0, 0.05) 1px, transparent 1px),
                linear-gradient(20deg, rgba(118, 185, 0, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(118, 185, 0, 0.05) 1px, transparent 1px);
            background-size: 40px 40px;
            z-index: -2;
            opacity: 0.8; /* Réduire l'opacité pour que ce soit subtil */
        }
        
        .container {
            max-width: 430px;
            margin: 0 auto;
            animation: fadeIn 0.8s ease-out;
        }
        
        /* Header */
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            animation: slideInDown 0.6s ease-out;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-icon {
            width: 45px;
            height: 45px;
            /* Gradient de vert */
            background: linear-gradient(135deg, var(--accent-green), var(--green-dark));
            border: none;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-black); /* Le texte dans l'icône est noir pour le contraste */
            font-weight: bold;
            font-size: 18px;
            box-shadow: 0 8px 25px rgba(118, 185, 0, 0.4);
        }
        
        .logo-text {
            font-weight: 700;
            font-size: 22px;
            /* Couleur de texte accentuée */
            color: var(--accent-green);
            letter-spacing: -0.5px;
        }
        
        .logo-subtext {
            font-size: 12px;
            color: var(--text-gray);
            margin-top: -2px;
            letter-spacing: 2px;
            font-weight: 500;
        }
        
        /* Section stats principales */
        .stats-section {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            animation: slideInUp 0.6s ease-out 0.2s both;
        }
        
        .stat-card {
            flex: 1;
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(118, 185, 0, 0.1); /* Bordure subtile en vert */
            text-align: center;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(118, 185, 0, 0.2);
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--accent-green); /* Couleur principale verte */
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-gray);
            font-weight: 600;
        }
        
        /* Section message */
        .message-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(118, 185, 0, 0.1);
            animation: slideInUp 0.6s ease-out 0.4s both;
        }
        
        .message-content {
            font-size: 14px;
            color: var(--text-light);
            line-height: 1.6;
            text-align: center;
        }
        
        .highlight {
            color: var(--accent-green); /* Mise en évidence en vert */
            font-weight: 700;
        }
        
        /* Section lien de parrainage */
        .referral-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(118, 185, 0, 0.1);
            animation: slideInUp 0.6s ease-out 0.6s both;
        }
        
        .referral-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 15px;
            text-align: center;
        }
        
        .referral-link-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .referral-link {
            flex: 1;
            /* Fond sombre/charbon */
            background: rgba(118, 185, 0, 0.05); 
            border: 1px solid rgba(118, 185, 0, 0.2);
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 14px;
            color: var(--text-light);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .copy-btn {
            /* Bouton en dégradé de vert */
            background: linear-gradient(135deg, var(--accent-green), var(--green-dark));
            color: var(--primary-black); /* Texte du bouton en noir */
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .copy-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(118, 185, 0, 0.3);
        }
        
        /* Section statistiques de parrainage */
        .levels-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(118, 185, 0, 0.1);
            animation: slideInUp 0.6s ease-out 0.8s both;
        }
        
        .levels-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 15px;
            text-align: center;
        }
        
        .level-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(118, 185, 0, 0.05); /* Fond très léger en vert */
            border-radius: 12px;
            margin-bottom: 10px;
            transition: var(--transition);
        }
        
        .level-item:hover {
            background: rgba(118, 185, 0, 0.1);
        }
        
        .level-number {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, var(--accent-green), var(--green-dark));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-black); /* Numéro en noir */
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .level-content {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .level-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .level-percentage {
            font-size: 16px;
            font-weight: 700;
            color: var(--accent-green);
        }
        
        .level-stats {
            text-align: right;
        }
        
        .level-count {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 5px;
        }
        
        .level-invest {
            font-size: 14px;
            color: var(--text-gray);
            font-weight: 600;
        }
        
        /* Section filleuls directs */
        .directs-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(118, 185, 0, 0.1);
            animation: slideInUp 0.6s ease-out 1s both;
        }
        
        .directs-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 15px;
            text-align: center;
        }
        
        .directs-list {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .direct-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(118, 185, 0, 0.1);
        }
        
        .direct-item:last-child {
            border-bottom: none;
        }
        
        .direct-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-light);
        }
        
        .direct-date {
            font-size: 12px;
            color: var(--text-gray);
        }
        
        .empty-message {
            text-align: center;
            color: var(--text-gray);
            font-style: italic;
            padding: 20px;
        }
        
        /* Notification de copie */
        .copy-notification {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            /* Fond en dégradé de vert */
            background: linear-gradient(135deg, var(--accent-green), var(--green-dark));
            color: var(--primary-black); /* Texte en noir */
            padding: 12px 24px;
            border-radius: 25px;
            box-shadow: 0 8px 25px rgba(118, 185, 0, 0.4);
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 14px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            pointer-events: none;
        }
        
        .copy-notification.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        
        .copy-notification i {
            font-size: 16px;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Scrollbar personnalisée */
        .directs-list::-webkit-scrollbar {
            width: 4px;
        }
        
        .directs-list::-webkit-scrollbar-track {
            background: rgba(118, 185, 0, 0.1);
            border-radius: 10px;
        }
        
        .directs-list::-webkit-scrollbar-thumb {
            background: var(--accent-green);
            border-radius: 10px;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            body { padding: 12px; }
            .stats-section { gap: 8px; }
            .stat-card { padding: 15px; }
            .stat-value { font-size: 20px; }
            
            .message-section,
            .referral-section,
            .levels-section,
            .directs-section { padding: 15px; }
            
            .copy-notification {
                bottom: 20px;
                padding: 10px 20px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="background"></div>
    <div class="geometric-pattern"></div>
    
    <div class="copy-notification" id="copyNotification">
        <i class="fas fa-check-circle"></i>
        Lien copié avec succès !
    </div>
    
    <div class="container">
        <div class="header">
            <div class="logo">
                <div class="logo-icon">NV</div> 
                <div>
                    <div class="logo-text">NVIDIA</div> 
                    <div class="logo-subtext">TECHNOLOGIE</div> 
                </div>
            </div>
        </div>
        
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-value"><?= $total_filleuls ?></div>
                <div class="stat-label">Mon Équipe</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?= number_format($gains_totaux, 0, ',', ' ') ?> XOF</div>
                <div class="stat-label">Gains Réseau</div>
            </div>
        </div>
        
        <div class="message-section">
            <div class="message-content">
                Pour chaque personne que vous invitez à investir sur <span class="highlight">NVIDIA Technologie</span>, vous gagnez 
                <span class="highlight">20%</span> de son investissement, <span class="highlight">5%</span> de l'investissement de ses filleuls, 
                et <span class="highlight">2%</span> de l'investissement des filleuls de troisième génération.
            </div>
        </div>
        
        <div class="referral-section">
            <div class="referral-title">Votre Lien de Parrainage</div>
            <div class="referral-link-container">
                <div class="referral-link" title="<?= htmlspecialchars($lien_parrainage) ?>">
                    <?= 
                        strlen($lien_parrainage) > 40 
                        ? substr($lien_parrainage, 0, 40) . '...' 
                        : $lien_parrainage 
                    ?>
                </div>
                <button class="copy-btn" onclick="copyToClipboard('<?= htmlspecialchars($lien_parrainage) ?>')">
                    <i class="fas fa-copy"></i>
                    Copier
                </button>
            </div>
        </div>
        
        <div class="levels-section">
            <div class="levels-title">Statistiques de Parrainage</div>
            
            <div class="level-item">
                <div class="level-number">1</div>
                <div class="level-content">
                    <div class="level-info">
                        <div class="level-percentage">20%</div>
                    </div>
                    <div class="level-stats">
                        <div class="level-count"><?= $count_niveau1 ?> filleuls</div>
                        <div class="level-invest"><?= number_format($invest_niveau1, 0, ',', ' ') ?> </div>
                    </div>
                </div>
            </div>
            
            <div class="level-item">
                <div class="level-number">2</div>
                <div class="level-content">
                    <div class="level-info">
                        <div class="level-percentage">5%</div>
                    </div>
                    <div class="level-stats">
                        <div class="level-count"><?= $count_niveau2 ?> filleuls</div>
                    </div>
                </div>
            </div>
            
            <div class="level-item">
                <div class="level-number">3</div>
                <div class="level-content">
                    <div class="level-info">
                        <div class="level-percentage">2%</div>
                    </div>
                    <div class="level-stats">
                        <div class="level-count"><?= $count_niveau3 ?> filleuls</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="directs-section">
            <div class="directs-title">Filleuls Directs</div>
            
            <div class="directs-list">
                <?php if (count($filleuls_directs) > 0): ?>
                    <?php foreach ($filleuls_directs as $filleul): ?>
                        <div class="direct-item">
                            <div class="direct-name"><?= htmlspecialchars($filleul['nom']) ?></div>
                            <div class="direct-date"><?= date('d/m/Y', strtotime($filleul['date_inscription'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-message">
                        Aucun filleul direct pour l'instant.<br>
                        Partagez votre lien de parrainage pour agrandir votre équipe !
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour copier dans le presse-papier avec notification
        function copyToClipboard(content) {
            navigator.clipboard.writeText(content).then(function() {
                showCopyNotification();
            }).catch(function(err) {
                // Fallback pour les navigateurs plus anciens
                const textArea = document.createElement('textarea');
                textArea.value = content;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    showCopyNotification();
                } catch (err) {
                    console.error('Erreur lors de la copie: ', err);
                }
                document.body.removeChild(textArea);
            });
        }

        // Fonction pour afficher la notification de copie
        function showCopyNotification() {
            const notification = document.getElementById('copyNotification');
            
            // Afficher la notification
            notification.classList.add('show');
            
            // Masquer la notification après 2 secondes
            setTimeout(() => {
                notification.classList.remove('show');
            }, 2000);
        }

        // Empêcher le zoom
        document.addEventListener('wheel', function(e) {
            if (e.ctrlKey) {
                e.preventDefault();
            }
        }, { passive: false });

        document.addEventListener('gesturestart', function(e) {
            e.preventDefault();
        });

        // Animation du bouton copier au clic
        document.addEventListener('DOMContentLoaded', function() {
            const copyBtn = document.querySelector('.copy-btn');
            
            if (copyBtn) {
                copyBtn.addEventListener('click', function() {
                    // Animation de pulse sur le bouton
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            }
        });
    </script>
</body>
</html>