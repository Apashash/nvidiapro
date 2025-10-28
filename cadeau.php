<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupération des données utilisateur
$stmt = $db->prepare("SELECT u.*, s.solde FROM utilisateurs u LEFT JOIN soldes s ON u.id = s.user_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$devise = ($user['pays'] == 'Cameroun') ? 'XAF' : 'XOF';

// Vérifier l'éligibilité pour créer des codes cadeau
$est_eligible = false;
$message_eligibilite = '';

// Vérifier s'il a 10 filleuls actifs (qui ont investi)
$stmt = $db->prepare("SELECT COUNT(*) FROM filleuls_actifs WHERE user_id = ? AND niveau1_actifs >= 10");
$stmt->execute([$user_id]);
$filleuls_actifs = $stmt->fetchColumn();

// Vérifier s'il a 50 filleuls inscrits
$stmt = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE parrain_id = ?");
$stmt->execute([$user_id]);
$filleuls_inscrits = $stmt->fetchColumn();

if ($filleuls_actifs >= 10 || $filleuls_inscrits >= 50) {
    $est_eligible = true;
} else {
    $message_eligibilite = "Pour créer vos propres codes cadeau, vous devez avoir soit 10 filleuls actifs (ayant investi), soit 50 filleuls inscrits sur NVIDIA Technologies.";
}

// Gestion de l'utilisation d'un code cadeau
$code_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code_cadeau'])) {
    $code_saisi = trim($_POST['code_cadeau']);
    
    // Vérifier si le code a déjà été utilisé par cet utilisateur
    $stmt = $db->prepare("SELECT * FROM codes_utilises WHERE user_id = ? AND code = ?");
    $stmt->execute([$user_id, $code_saisi]);
    
    if ($stmt->rowCount() === 0) {
        // Codes cadeaux prédéfinis avec leurs montants
        $codes_cadeaux = [
            'BR556' => 40,
            'BOOST2024' => 250,
            'INVESTISSEMENT' => 500,
            'VIPGIFT' => 1000
        ];
        
        if (array_key_exists($code_saisi, $codes_cadeaux)) {
            $montant = $codes_cadeaux[$code_saisi];
            
            $db->beginTransaction();
            try {
                // Ajouter le montant au solde
                $stmt = $db->prepare("UPDATE soldes SET solde = solde + ? WHERE user_id = ?");
                $stmt->execute([$montant, $user_id]);
                
                // Enregistrer l'utilisation du code
                $stmt = $db->prepare("INSERT INTO codes_utilises (user_id, code, montant) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $code_saisi, $montant]);
                
                $db->commit();
                
                $code_message = '<div class="alert success">Code valide! ' . number_format($montant, 0, ',', ' ') . ' ' . $devise . ' ont été ajoutés à votre solde.</div>';
                
                // Recharger les données utilisateur
                $stmt = $db->prepare("SELECT u.*, s.solde FROM utilisateurs u LEFT JOIN soldes s ON u.id = s.user_id WHERE u.id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
            } catch (Exception $e) {
                $db->rollBack();
                $code_message = '<div class="alert error">Erreur lors du traitement: ' . $e->getMessage() . '</div>';
            }
        } else {
            $code_message = '<div class="alert error">Code invalide.</div>';
        }
    } else {
        $code_message = '<div class="alert error">Ce code a déjà été utilisé.</div>';
    }
}

// Gestion de la création de code cadeau (simulation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_code'])) {
    if ($est_eligible) {
        $code_message = '<div class="alert error">Votre compte a été bloqué à cause d\'activités suspectes liées à vos filleuls. Veuillez contacter le service client pour clarifier tout cela.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Cadeaux - NVIDIA Technologies</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Définition des couleurs NVIDIA */
        :root {
            --primary-black: #121212; /* Fond sombre */
            --soft-dark: #1e1e1e;
            --light-gray: #333333; /* Fond des cartes/éléments */
            --accent-green: #76B900; /* Vert NVIDIA principal */
            --green-light: #94d82f;
            --green-dark: #588e00;
            --accent-blue: #3b82f6; /* Garde le bleu pour certaines alertes/éléments secondaires */
            --accent-purple: #8b5cf6; /* Supprimé ou remplacé par une teinte de gris/vert */
            --text-light: #ffffff;
            --text-dark: #e0e0e0;
            --text-gray: #aaaaaa;
            --card-bg: rgba(30, 30, 30, 0.95);
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
            /* Fond sombre avec dégradé subtil */
            background: linear-gradient(135deg, var(--primary-black) 0%, var(--soft-dark) 50%, var(--light-gray) 100%);
            color: var(--text-dark);
            line-height: 1.6;
            position: relative;
            -webkit-text-size-adjust: 100%;
            touch-action: pan-y;
            padding: 15px;
        }
        
        /* Arrière-plan géométrique / Style sombre */
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 10% 20%, rgba(118, 185, 0, 0.1) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(118, 185, 0, 0.1) 0%, transparent 20%),
                radial-gradient(circle at 20% 30%, rgba(118, 185, 0, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(118, 185, 0, 0.05) 0%, transparent 50%),
                linear-gradient(135deg, var(--primary-black) 0%, var(--soft-dark) 100%);
            z-index: -3;
        }
        
        .geometric-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            /* Pattern vert/sombre */
            background-image: 
                linear-gradient(30deg, rgba(118, 185, 0, 0.08) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.08) 87.5%, rgba(118, 185, 0, 0.08) 0),
                linear-gradient(150deg, rgba(118, 185, 0, 0.08) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.08) 87.5%, rgba(118, 185, 0, 0.08) 0),
                linear-gradient(30deg, rgba(118, 185, 0, 0.04) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.04) 87.5%, rgba(118, 185, 0, 0.04) 0),
                linear-gradient(150deg, rgba(118, 185, 0, 0.04) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.04) 87.5%, rgba(118, 185, 0, 0.04) 0),
                linear-gradient(60deg, rgba(118, 185, 0, 0.1) 25%, transparent 25.5%, transparent 75%, rgba(118, 185, 0, 0.1) 75%, rgba(118, 185, 0, 0.1) 0),
                linear-gradient(60deg, rgba(118, 185, 0, 0.1) 25%, transparent 25.5%, transparent 75%, rgba(118, 185, 0, 0.1) 75%, rgba(118, 185, 0, 0.1) 0);
            background-size: 100px 175px;
            background-position: 0 0, 0 0, 50px 87.5px, 50px 87.5px, 0 0, 50px 87.5px;
            z-index: -2;
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
            /* Logo carré vert */
            background: var(--accent-green);
            border: none;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-black); /* Texte noir sur vert */
            font-weight: bold;
            font-size: 18px;
            box-shadow: 0 8px 25px rgba(118, 185, 0, 0.4);
        }
        
        .logo-text {
            font-weight: 700;
            font-size: 22px;
            /* Texte vert/blanc */
            background: linear-gradient(135deg, var(--accent-green), var(--text-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }
        
        .logo-subtext {
            font-size: 12px;
            color: var(--text-gray);
            margin-top: -2px;
            letter-spacing: 2px;
            font-weight: 500;
        }
        
        /* Section choix */
        .choice-section {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
            animation: slideInUp 0.6s ease-out 0.2s both;
        }
        
        .choice-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(118, 185, 0, 0.1);
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .choice-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(118, 185, 0, 0.2);
        }
        
        .choice-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            /* Icône circulaire verte */
            background: var(--accent-green);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px auto;
            color: var(--primary-black); /* Icône noire sur vert */
            font-size: 28px;
        }
        
        .choice-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent-green); /* Titre en vert */
            margin-bottom: 10px;
        }
        
        .choice-description {
            font-size: 14px;
            color: var(--text-gray);
            line-height: 1.5;
        }
        
        /* Section création de code */
        .creation-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(118, 185, 0, 0.1);
            animation: slideInUp 0.6s ease-out 0.4s both;
            display: none;
        }
        
        .creation-section.active {
            display: block;
        }
        
        .creation-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .eligibility-info {
            /* Fond légèrement vert/sombre */
            background: rgba(118, 185, 0, 0.05);
            border: 1px solid rgba(118, 185, 0, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .eligibility-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .eligibility-description {
            font-size: 14px;
            color: var(--text-dark);
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 15px 0;
        }
        
        .stat-item {
            background: var(--soft-dark);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-gray);
            font-weight: 600;
        }
        
        .create-btn {
            /* Bouton principal vert */
            background: var(--accent-green);
            color: var(--primary-black); /* Texte noir sur vert */
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
            display: block;
            width: 100%;
            margin-top: 20px;
        }
        
        .create-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(118, 185, 0, 0.4);
        }
        
        .create-btn:disabled {
            background: #9ca3af;
            color: #4b5563;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Section utilisation de code */
        .usage-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(118, 185, 0, 0.1);
            animation: slideInUp 0.6s ease-out 0.6s both;
            display: none;
        }
        
        .usage-section.active {
            display: block;
        }
        
        .usage-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 15px;
            /* Input sombre */
            background: var(--soft-dark);
            border: 1px solid rgba(118, 185, 0, 0.2);
            border-radius: 10px;
            font-size: 16px;
            color: var(--text-light);
            transition: var(--transition);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 0 2px rgba(118, 185, 0, 0.2);
        }
        
        .submit-btn {
            /* Bouton principal vert */
            background: var(--accent-green);
            color: var(--primary-black);
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
            display: block;
            width: 100%;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(118, 185, 0, 0.4);
        }
        
        /* Alert messages */
        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .alert.success {
            /* Succès en vert NVIDIA */
            background: rgba(118, 185, 0, 0.15);
            border: 1px solid rgba(118, 185, 0, 0.3);
            color: var(--accent-green);
        }
        
        .alert.error {
            /* Erreur en rouge */
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }
        
        .alert.info {
            /* Info en gris/vert léger */
            background: rgba(118, 185, 0, 0.1);
            border: 1px solid rgba(118, 185, 0, 0.2);
            color: var(--accent-green);
        }
        
        /* Back button */
        .back-btn {
            /* Bouton retour vert/sombre */
            background: rgba(118, 185, 0, 0.1);
            color: var(--accent-green);
            border: 1px solid rgba(118, 185, 0, 0.2);
            padding: 12px 25px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            background: rgba(118, 185, 0, 0.2);
            transform: translateX(-5px);
        }
        
        /* Animations et Responsive inchangés */
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
        
        @media (max-width: 480px) {
            body {
                padding: 12px;
            }
            
            .choice-card {
                padding: 20px;
            }
            
            .creation-section,
            .usage-section {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="background"></div>
    <div class="geometric-pattern"></div>
    
    <div class="container">
        <div class="header">
            <div class="logo">
                <div class="logo-icon">NV</div>
                <div>
                    <div class="logo-text">NVIDIA Technologies</div>
                    <div class="logo-subtext">CADEAUX</div>
                </div>
            </div>
        </div>
        
        <div class="choice-section" id="choiceSection">
            <div class="choice-card" onclick="showCreationSection()">
                <div class="choice-icon">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="choice-title">Créer vos codes cadeau</div>
                <div class="choice-description">
                    Créez vos propres codes cadeau et gagnez 40% de commission sur chaque utilisation
                </div>
            </div>
            
            <div class="choice-card" onclick="showUsageSection()">
                <div class="choice-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="choice-title">Utiliser un code cadeau</div>
                <div class="choice-description">
                    Entrez un code cadeau pour recevoir une récompense immédiate
                </div>
            </div>
        </div>
        
        <div class="creation-section" id="creationSection">
            <button class="back-btn" onclick="showChoiceSection()">
                <i class="fas fa-arrow-left"></i> Retour
            </button>
            
            <div class="creation-title">Créer vos codes cadeau</div>
            
            <div class="eligibility-info">
                <div class="eligibility-title">
                    <i class="fas fa-info-circle"></i> Conditions d'éligibilité
                </div>
                <div class="eligibility-description">
                    Pour créer vos propres codes cadeau, vous devez remplir l'une des conditions suivantes :
                </div>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?= $filleuls_actifs ?>/10</div>
                        <div class="stat-label">Filleuls actifs</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $filleuls_inscrits ?>/50</div>
                        <div class="stat-label">Filleuls inscrits</div>
                    </div>
                </div>
                
                <?php if (!$est_eligible): ?>
                    <div class="alert info">
                        <?= $message_eligibilite ?>
                    </div>
                <?php else: ?>
                    <div class="alert success">
                        ✅ Félicitations ! Vous êtes éligible pour créer des codes cadeau.
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="commission-info">
                <div class="eligibility-title">
                    <i class="fas fa-percentage"></i> Commission de 40%
                </div>
                <div class="eligibility-description">
                    Pour chaque personne qui utilise un de vos codes cadeau, vous recevrez 40% de commission sur le montant qu'ils reçoivent.
                </div>
            </div>
            
            <form method="POST">
                <button type="submit" name="creer_code" class="create-btn" <?= $est_eligible ? '' : 'disabled' ?>>
                    <i class="fas fa-plus-circle"></i> Créer mon code cadeau
                </button>
            </form>
        </div>
        
        <div class="usage-section" id="usageSection">
            <button class="back-btn" onclick="showChoiceSection()">
                <i class="fas fa-arrow-left"></i> Retour
            </button>
            
            <div class="usage-title">Utiliser un code cadeau</div>
            
            <?= $code_message ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label" for="code_cadeau">Code cadeau</label>
                    <input type="text" id="code_cadeau" name="code_cadeau" class="form-input" placeholder="Ex: NVIDIA2025" required>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-gift"></i> Utiliser le code
                </button>
            </form>
            
            <div style="margin-top: 25px; padding: 20px; background: rgba(118, 185, 0, 0.05); border-radius: 12px;">
                <div class="eligibility-title">
                    <i class="fas fa-lightbulb"></i> Codes disponibles
                </div>

            </div>
        </div>
    </div>

    <script>
        // Fonctions pour gérer l'affichage des sections
        function showChoiceSection() {
            document.getElementById('choiceSection').style.display = 'flex';
            document.getElementById('creationSection').classList.remove('active');
            document.getElementById('usageSection').classList.remove('active');
        }
        
        function showCreationSection() {
            document.getElementById('choiceSection').style.display = 'none';
            document.getElementById('creationSection').classList.add('active');
            document.getElementById('usageSection').classList.remove('active');
        }
        
        function showUsageSection() {
            document.getElementById('choiceSection').style.display = 'none';
            document.getElementById('creationSection').classList.remove('active');
            document.getElementById('usageSection').classList.add('active');
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
    </script>
</body>
</html>