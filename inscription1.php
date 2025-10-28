<?php
// Activation des erreurs complète
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Démarrage de session
session_start();

// Inclusion de la DB
require_once 'db.php';

// Fonction pour créer les comptes associés (MODIFIÉE - solde à 250)
function createUserAccounts($db, $user_id) {
    try {
        // Commencer une transaction
        $db->beginTransaction();
        
        // 1. Vérifier que l'utilisateur existe
        $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE id = ?");
        $stmt->execute([$user_id]);
        
        if (!$stmt->fetch()) {
            throw new Exception("L'utilisateur n'existe pas dans la base de données");
        }
        
        // 2. Créer l'entrée dans la table soldes AVEC SOLDE DE 250 FCFA
        $stmt = $db->prepare("INSERT INTO soldes (user_id, solde, solde_precedent) VALUES (?, 250, 250)");
        if (!$stmt->execute([$user_id])) {
            throw new Exception("Erreur lors de la création du solde");
        }
        
        // 3. Créer l'entrée dans la table vip (avec gestion d'erreur)
        try {
            $stmt = $db->prepare("INSERT INTO vip (user_id, niveau, pourcentage, invitations_requises, invitations_actuelles) VALUES (?, 0, 0, 3, 0)");
            $stmt->execute([$user_id]);
        } catch (Exception $e) {
            error_log("Table VIP non disponible: " . $e->getMessage());
            // Continuer sans bloquer
        }
        
        // 4. Créer l'entrée dans la table filleuls
        try {
            $stmt = $db->prepare("INSERT INTO filleuls (user_id) VALUES (?)");
            $stmt->execute([$user_id]);
        } catch (Exception $e) {
            error_log("Table filleuls: " . $e->getMessage());
        }
        
        // 5. Créer l'entrée dans la table connexions_journalieres
        try {
            $stmt = $db->prepare("INSERT INTO connexions_journalieres (user_id, derniere_connexion, prochain_paiement) VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY))");
            $stmt->execute([$user_id]);
        } catch (Exception $e) {
            error_log("Table connexions_journalieres: " . $e->getMessage());
        }
        
        // 6. Créer l'entrée dans la table pieces (optionnelle)
        try {
            $stmt = $db->prepare("INSERT INTO pieces (user_id, solde, solde_precedent, date_maj) VALUES (?, 0, 0, CURRENT_TIMESTAMP)");
            $stmt->execute([$user_id]);
        } catch (Exception $e) {
            error_log("Table pieces non disponible: " . $e->getMessage());
        }
        
        // Valider la transaction si tout s'est bien passé
        $db->commit();
        
        return true;
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        error_log("Erreur lors de la création des comptes: " . $e->getMessage());
        return false;
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupération des données
        $nom = trim($_POST['nom']);
        $pays = $_POST['pays'];
        $indicatif = $_POST['indicatif'];
        $telephone = $indicatif . preg_replace('/[^0-9]/', '', $_POST['telephone']);
        $mot_de_passe = $_POST['mot_de_passe'];
        $confirmation = $_POST['confirmation'];
        
        // Validation (simplifiée selon les demandes)
        if (empty($nom) || empty($pays) || empty($telephone) || empty($mot_de_passe)) {
            throw new Exception("Tous les champs sont obligatoires");
        }
        
        if ($mot_de_passe !== $confirmation) {
            throw new Exception("Les mots de passe ne correspondent pas");
        }

        // Vérification des doublons
        $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE telephone = ?");
        $stmt->execute([$telephone]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("Un compte existe déjà avec ce numéro de téléphone");
        }
        
        // Gestion parrainage
        $parrain_id = null;
        $code_parrain = isset($_SESSION['parrain_code']) ? $_SESSION['parrain_code'] : '';
        
        if (!empty($code_parrain)) {
            $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE code_parrainage = ? OR RIGHT(code_parrainage, 5) = ?");
            $stmt->execute([$code_parrain, $code_parrain]);
            
            if ($stmt->rowCount() > 0) {
                $parrain = $stmt->fetch();
                $parrain_id = $parrain['id'];
            }
        }
        
        // Génération code parrainage
        $code_parrainage = 'NV-' . substr(strtoupper(uniqid()), -5); // Code ajusté à NV-
        $lien_parrainage = "https://nvidiatech2026.iceiy.com/inscription.php?p=".$code_parrainage; // Lien ajusté
        
        // Insertion DB - mot de passe en clair comme demandé
        // MODIFICATION: Initialisation du solde à 250 FCFA
        $stmt = $db->prepare("INSERT INTO utilisateurs 
                            (nom, telephone, pays, mot_de_passe,
                              code_parrainage, parrain_id, lien_parrainage,
                              solde, revenus_totaux, nombre_filleuls, date_inscription)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 250, 0, 0, NOW())");
        
        $result = $stmt->execute([
            $nom, 
            $telephone, 
            $pays, 
            $mot_de_passe, // Stocké en clair selon la demande
            $code_parrainage, 
            $parrain_id, 
            $lien_parrainage
        ]);
        
        if (!$result) {
            throw new Exception("Erreur lors de la création du compte");
        }
        
        $user_id = $db->lastInsertId();
        
        // Création des comptes associés
        if (!createUserAccounts($db, $user_id)) {
            throw new Exception("Erreur lors de la création des comptes associés");
        }
        
        // Mise à jour parrain si nécessaire
        if ($parrain_id) {
            $stmt = $db->prepare("UPDATE utilisateurs SET nombre_filleuls = nombre_filleuls + 1 WHERE id = ?");
            $stmt->execute([$parrain_id]);
            
            // Mettre à jour aussi la table vip si elle existe
            try {
                $stmt = $db->prepare("UPDATE vip SET invitations_actuelles = invitations_actuelles + 1 WHERE user_id = ?");
                $stmt->execute([$parrain_id]);
            } catch (Exception $e) {
                error_log("Erreur mise à jour VIP parrain: " . $e->getMessage());
            }
        }

        // Connexion automatique
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_nom'] = $nom;
        
        // Nettoyer
        unset($_SESSION['parrain_code']);
        if (isset($_SESSION['form_data'])) {
            unset($_SESSION['form_data']);
        }
        
        // Redirection
        header("Location: index.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
    }
}

// Récupération code parrain depuis la session
$code_parrain = isset($_SESSION['parrain_code']) ? $_SESSION['parrain_code'] : '';

// Liste des pays éligibles
$pays_eligibles = [
    '+229' => 'Bénin',
    '+226' => 'Burkina Faso',
    '+237' => 'Cameroun',
    '+221' => 'Senegal',
    '+225' => 'Côte d\'Ivoire',
    '+223' => 'Mali',
    '+228' => 'Togo'
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Inscription - NVIDIA Technologie</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Couleurs NVIDIA */
            --nvidia-green: #76B900; /* Vert NVIDIA */
            --nvidia-black: #000000; /* Noir principal */
            --primary-white: #ffffff;
            
            /* Thème sombre */
            --background-dark: #0a0a0a; /* Fond très sombre */
            --input-dark: #1e1e1e; /* Fond des inputs */
            --text-light: var(--primary-white); /* Texte principal blanc */
            --text-accent: var(--nvidia-green); /* Texte accentué en vert */
            --text-gray: #b0b0b0; /* Texte gris clair */
            
            --card-bg: rgba(10, 10, 10, 0.95); /* Fond de la carte sombre transparent */
            --border-color: rgba(118, 185, 0, 0.15); /* Bordure subtilement verte */
            
            --error: #ef4444; /* Garder le rouge pour les erreurs */
            --success: var(--nvidia-green);
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
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--background-dark); 
            color: var(--text-light); 
            line-height: 1.6;
            position: relative;
            overflow-y: auto;
            -webkit-text-size-adjust: 100%;
            touch-action: manipulation;
        }
        
        /* Arrière-plan NVIDIA (Thème sombre + motifs géométriques) */
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(135deg, #1a1a1a 0%, #000000 100%); 
            z-index: -3;
        }

        .geometric-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                repeating-linear-gradient(45deg, var(--nvidia-green) 0, var(--nvidia-green) 1px, transparent 1px, transparent 20px),
                repeating-linear-gradient(-45deg, var(--nvidia-green) 0, var(--nvidia-green) 1px, transparent 1px, transparent 20px);
            background-size: 40px 40px;
            opacity: 0.08; 
            z-index: -2;
            animation: patternShift 60s linear infinite;
        }
        
        .green-accent {
            position: fixed;
            top: 0;
            right: 0;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, var(--nvidia-green) 0%, transparent 70%);
            filter: blur(80px);
            opacity: 0.2;
            z-index: -1;
        }
        
        /* Cacher les anciens accents non-NVIDIA */
        .blue-accent, .purple-accent, .light-shimmer, .light-beam { display: none !important; }

        /* Conteneur principal */
        .main-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 20px;
            animation: fadeIn 1.2s ease-out;
        }
        
        /* Header NVIDIA */
        .header {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 10px;
            animation: slideDown 1s ease-out;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            background: var(--nvidia-green); /* Vert NVIDIA */
            border: none;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--nvidia-black); /* Texte noir sur vert */
            font-weight: 700;
            font-size: 24px;
            box-shadow: 0 8px 25px rgba(118, 185, 0, 0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .logo-icon::before { 
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.6), transparent);
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
            font-size: 32px;
            color: var(--text-light); /* Texte blanc */
            background: none;
            -webkit-text-fill-color: initial;
            letter-spacing: -0.5px;
        }
        
        .logo-subtext {
            font-size: 14px;
            color: var(--nvidia-green); /* Sous-texte vert */
            margin-top: -2px;
            letter-spacing: 3px;
            font-weight: 500;
        }
        
        /* Section formulaire premium */
        .form-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        
        .form-container {
            max-width: 500px;
            width: 100%;
            background: var(--card-bg); /* Fond sombre transparent */
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 40px 35px;
            border: 1px solid var(--border-color); /* Bordure subtilement verte */
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.5),
                0 15px 30px rgba(118, 185, 0, 0.1);
            position: relative;
            overflow: hidden;
            animation: formAppear 1.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--nvidia-green); /* Ligne verte */
            box-shadow: 0 0 15px rgba(118, 185, 0, 0.5);
        }
        
        .form-container::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.1) 0%, rgba(118, 185, 0, 0.05) 100%);
            pointer-events: none;
            z-index: -1;
        }
        
        .form-title {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
            color: var(--nvidia-green); /* Titre principal vert */
            background: none;
            -webkit-text-fill-color: var(--nvidia-green);
            position: relative;
        }
        
        .form-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--nvidia-green);
            border-radius: 2px;
            box-shadow: 0 0 10px rgba(118, 185, 0, 0.5);
        }
        
        .form-group {
            margin-bottom: 25px;
            animation: fadeInUp 0.8s ease-out;
            position: relative;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }
        
        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-light); /* Label en blanc */
            font-size: 15px;
            letter-spacing: 0.5px;
        }
        
        .form-input {
            width: 100%;
            padding: 18px;
            background: var(--input-dark); /* Fond sombre pour l'input */
            border: 1px solid rgba(118, 185, 0, 0.3);
            border-radius: 12px;
            font-size: 16px;
            color: var(--text-light);
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }
        
        .form-input::placeholder {
            color: var(--text-gray);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--nvidia-green);
            box-shadow: 0 0 0 3px rgba(118, 185, 0, 0.2), 0 4px 15px rgba(118, 185, 0, 0.3);
            background: var(--nvidia-black);
            transform: translateY(-2px);
        }
        
        .phone-input-container {
            display: flex;
            gap: 12px;
        }
        
        .indicatif-select {
            flex: 0 0 120px;
            position: relative;
        }
        
        .indicatif-select::after {
            content: "▼";
            font-size: 12px;
            color: var(--nvidia-green);
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }
        
        .indicatif-select select {
            width: 100%;
            padding: 18px;
            background: var(--input-dark);
            border: 1px solid rgba(118, 185, 0, 0.3);
            border-radius: 12px;
            color: var(--text-light);
            height: 100%;
            -webkit-appearance: none;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }
        
        .phone-input {
            flex: 1;
        }
        
        .btn-submit {
            background: var(--nvidia-green);
            color: var(--nvidia-black); 
            border: none;
            padding: 20px;
            border-radius: 15px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: var(--transition);
            margin-top: 15px;
            box-shadow: 
                0 8px 25px rgba(118, 185, 0, 0.6),
                inset 0 1px 0 rgba(255, 255, 255, 0.5);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out 0.6s both;
            letter-spacing: 1px;
        }
        
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
            transition: left 0.7s;
        }
        
        .btn-submit:hover::before {
            left: 100%;
        }
        
        .btn-submit:hover {
            background: var(--nvidia-black);
            color: var(--nvidia-green);
            transform: translateY(-5px);
            box-shadow: 
                0 15px 30px rgba(118, 185, 0, 0.8),
                inset 0 1px 0 rgba(118, 185, 0, 0.4);
        }
        
        .login-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid var(--border-color);
            animation: fadeInUp 0.8s ease-out 0.7s both;
        }
        
        .login-text {
            color: var(--text-gray);
            font-size: 15px;
            margin-bottom: 15px;
        }
        
        .btn-login-form {
            background: transparent;
            color: var(--nvidia-green);
            border: 1px solid var(--nvidia-green);
            padding: 16px 25px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            text-decoration: none;
            display: block;
            text-align: center;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(118, 185, 0, 0.2), transparent);
            transition: left 0.7s;
        }
        
        .btn-login-form:hover::before {
            left: 100%;
        }
        
        .btn-login-form:hover {
            background: rgba(118, 185, 0, 0.1);
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(118, 185, 0, 0.3);
        }
        
        /* Messages d'erreur améliorés */
        .error-message {
            color: var(--error);
            background: rgba(239, 68, 68, 0.1);
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 15px;
            border-left: 4px solid var(--error);
            text-align: center;
            font-weight: 500;
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.2);
            animation: shake 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            backdrop-filter: blur(10px);
        }
        
        .referral-notice {
            background: rgba(118, 185, 0, 0.1);
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid var(--nvidia-green);
            color: var(--text-light);
            text-align: center;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(118, 185, 0, 0.2);
            animation: pulse 2s infinite;
            backdrop-filter: blur(10px);
        }
        
        .bonus-notice {
            background: rgba(118, 185, 0, 0.1);
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid var(--nvidia-green);
            color: var(--text-light);
            text-align: center;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(118, 185, 0, 0.2);
            backdrop-filter: blur(10px);
        }
        
        /* Animations améliorées */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeInUp { 
            from { opacity: 0; transform: translateY(30px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        @keyframes slideDown { 
            from { opacity: 0; transform: translateY(-50px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        @keyframes formAppear { 
            from { opacity: 0; transform: scale(0.9) translateY(20px); } 
            to { opacity: 1; transform: scale(1) translateY(0); } 
        }
        @keyframes patternShift { 
            0% { background-position: 0 0; } 
            100% { background-position: 40px 40px; } 
        }
        @keyframes logoShine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }
            
            .form-container {
                padding: 35px 25px;
                margin: 0 15px;
            }
            
            .form-title {
                font-size: 32px;
            }
        }
        
        @media (max-width: 480px) {
            .main-container {
                padding: 15px;
            }
            
            .form-container {
                padding: 30px 20px;
            }
            
            .form-title {
                font-size: 28px;
            }
            
            .form-input {
                padding: 16px;
            }
            
            .phone-input-container {
                flex-direction: column;
                gap: 12px;
            }
            
            .indicatif-select {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="background"></div>
    <div class="geometric-pattern"></div>
    <div class="green-accent"></div>
    
    <div class="main-container">
        <div class="header">
            <div class="logo">
                <div class="logo-icon">NV</div>
                <div>
                    <div class="logo-text">NVIDIA</div>
                    <div class="logo-subtext">TECHNOLOGIE</div>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <div class="form-container">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($_SESSION['error']) ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <?php if (!empty($code_parrain)): ?>
                    <div class="referral-notice">
                        <i class="fas fa-user-friends"></i> Vous êtes invité par un membre NVIDIA Technologie
                    </div>
                <?php endif; ?>
                
                <div class="bonus-notice">
                    <i class="fas fa-gift"></i> Bonus de bienvenue : 250 FCFA offerts !
                </div>
                
                <h2 class="form-title">Rejoignez NVIDIA</h2>
                
                <form action="inscription1.php" method="post" id="signup-form">
                    <div class="form-group">
                        <label class="form-label" for="nom">Nom complet</label>
                        <input type="text" id="nom" name="nom" class="form-input" placeholder="Votre nom complet" required
                                value="<?= isset($_SESSION['form_data']['nom']) ? htmlspecialchars($_SESSION['form_data']['nom']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="telephone">Numéro WhatsApp</label>
                        <div class="phone-input-container">
                            <div class="indicatif-select">
                                <select id="indicatif" name="indicatif" required>
                                    <option value="">Code</option>
                                    <?php 
                                    // Déterminer la valeur sélectionnée par défaut (ou la première)
                                    $default_indicatif = isset($_SESSION['form_data']['indicatif']) ? $_SESSION['form_data']['indicatif'] : '+225';
                                    
                                    foreach($pays_eligibles as $code => $pays): ?>
                                        <option value="<?= $code ?>" <?= $default_indicatif === $code ? 'selected' : '' ?>>
                                            <?= $code ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="phone-input">
                                <input type="text" id="telephone" name="telephone" class="form-input" placeholder="Numéro de téléphone (ex: 07 00 00 00 00)" required pattern="[0-9 ]{6,}"
                                    value="<?= isset($_SESSION['form_data']['telephone']) ? htmlspecialchars($_SESSION['form_data']['telephone']) : '' ?>">
                            </div>
                            <input type="hidden" name="pays" id="pays" value=""> 
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="mot_de_passe">Mot de passe</label>
                        <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-input" placeholder="Choisissez un mot de passe" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirmation">Confirmer le mot de passe</label>
                        <input type="password" id="confirmation" name="confirmation" class="form-input" placeholder="Confirmez le mot de passe" required>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-user-plus"></i> CRÉER MON COMPTE
                    </button>
                </form>
                
                <div class="login-link">
                    <p class="login-text">Déjà membre NVIDIA?</p>
                    <a href="connexion.php" class="btn-login-form">
                        <i class="fas fa-sign-in-alt"></i> SE CONNECTER
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour mettre à jour le champ caché 'pays'
        document.addEventListener('DOMContentLoaded', function() {
            const indicatifSelect = document.getElementById('indicatif');
            const paysInput = document.getElementById('pays');
            
            const paysMap = <?= json_encode($pays_eligibles) ?>;
            
            function updatePays() {
                const selectedCode = indicatifSelect.value;
                paysInput.value = paysMap[selectedCode] || '';
            }
            
            // Initialisation et mise à jour lors du changement
            updatePays();
            indicatifSelect.addEventListener('change', updatePays);

            // Animation d'entrée améliorée
            const formGroups = document.querySelectorAll('.form-group');
            formGroups.forEach((group, index) => {
                group.style.animationDelay = `${0.1 + (index * 0.1)}s`;
            });
            
            // Nettoyer les données du formulaire en session après l'affichage (s'assure que PHP fait le nettoyage à la fin)
            // C'est mieux de laisser le nettoyage dans le bloc PHP de traitement en haut du fichier pour garantir que la redirection fonctionne correctement.
        });
    </script>
</body>
</html>
<?php
// Nettoyer les données de formulaire après affichage
if (isset($_SESSION['form_data'])) {
    unset($_SESSION['form_data']);
}
?>