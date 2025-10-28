<?php
// Activation des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Démarrage de session
session_start();

// Inclusion de la DB
require_once 'db.php';

// Liste des pays éligibles (Indicatif => Pays)
$pays_eligibles = [
    '+229' => 'Bénin',
    '+226' => 'Burkina Faso',
    '+237' => 'Cameroun',
    '+221' => 'Senegal',
    '+225' => 'Côte d\'Ivoire',
    '+223' => 'Mali',
    '+228' => 'Togo'
];

// Mappage (Code Pays ISO 3166-1 alpha-2 => Indicatif) pour la détection JavaScript
// Cette liste est cruciale pour le script de détection automatique côté client.
$iso_to_indicatif_map = [
    'BJ' => '+229', // Bénin
    'BF' => '+226', // Burkina Faso
    'CM' => '+237', // Cameroun
    'CG' => '+242', // Congo
    'CI' => '+225', // Côte d'Ivoire
    'ML' => '+223', // Mali
    'TG' => '+228'  // Togo
];

// Fonction pour déterminer l'indicatif par défaut (maintenant un simple fallback)
function get_default_indicatif($pays_eligibles) {
    // Fallback par défaut si la détection JS ou les données de session échouent.
    $default_code = '+237'; 
    if (isset($_POST['indicatif']) && array_key_exists($_POST['indicatif'], $pays_eligibles)) {
        return $_POST['indicatif'];
    }
    return $default_code;
}

$default_indicatif = get_default_indicatif($pays_eligibles);


// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $indicatif = $_POST['indicatif'];
        $telephone = preg_replace('/[^0-9]/', '', $_POST['telephone']);
        $full_tel = $indicatif . $telephone;
        $mot_de_passe = $_POST['mot_de_passe'];

        // Stocker les données de formulaire pour les re-remplir en cas d'erreur
        $_SESSION['form_data'] = [
            'indicatif' => $indicatif,
            'telephone' => $_POST['telephone'] // Garder le format initial pour l'affichage
        ];

        // Validation
        if (empty($indicatif) || empty($telephone) || empty($mot_de_passe)) {
            throw new Exception("Tous les champs sont obligatoires");
        }

        // Vérification du format
        if (!array_key_exists($indicatif, $pays_eligibles)) {
            throw new Exception("Code pays non valide.");
        }
        if (!preg_match('/^\d{5,15}$/', $telephone)) { 
            throw new Exception("Numéro de téléphone invalide.");
        }
        
        $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE telephone = ?");
        $stmt->execute([$full_tel]);
        $user = $stmt->fetch();

        if (!$user) throw new Exception("Aucun compte trouvé avec ce numéro");
        
        // ATTENTION: C'EST UNE VULNÉRABILITÉ MAJEURE ! Utilisez password_verify()
        if ($user['mot_de_passe'] !== $mot_de_passe) throw new Exception("Mot de passe incorrect");

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nom'] = $user['nom'];
        
        unset($_SESSION['form_data']); // Succès: nettoyer les données
        header("Location: index.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        // Redirection vers la page de connexion pour afficher l'erreur
        header("Location: connexion.php");
        exit();
    }
}

// Récupération des données du formulaire en cas d'erreur
$form_data = $_SESSION['form_data'] ?? ['indicatif' => $default_indicatif, 'telephone' => ''];
unset($_SESSION['form_data']); // Nettoyer après récupération
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Connexion - NVIDIA Technologie</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Couleurs NVIDIA */
            --nvidia-green: #76B900; /* Vert NVIDIA */
            --nvidia-black: #000000; /* Noir NVIDIA */
            --primary-white: #ffffff;
            
            /* Couleurs de fond/texte inversées pour le thème sombre */
            --background-dark: #0a0a0a; /* Fond très sombre */
            --text-light: var(--primary-white); /* Texte principal blanc */
            --text-dark: var(--nvidia-green); /* Texte accentué en vert */
            
            --card-bg: rgba(10, 10, 10, 0.95); /* Fond de la carte sombre transparent */
            --border-color: rgba(255, 255, 255, 0.1);
            --error: #dc3545;
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
            /* Nouveau fond noir/sombre */
            background: var(--background-dark); 
            color: var(--text-light); /* Texte blanc */
            line-height: 1.6;
            position: relative;
            overflow-y: auto;
            -webkit-text-size-adjust: 100%;
            touch-action: manipulation;
        }
        
        /* Arrière-plan stylisé NVIDIA (plus sombre) */
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                /* Dégradé de fond sombre */
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
            opacity: 0.05;
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
        
        .black-accent {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            filter: blur(80px);
            opacity: 0.1;
            z-index: -1;
        }

        /* Animations (maintenues) */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideDown { 0% { opacity: 0; transform: translateY(-50px); } 100% { opacity: 1; transform: translateY(0); } }
        @keyframes formAppear { 0% { opacity: 0; transform: scale(0.9) translateY(20px); } 100% { opacity: 1; transform: scale(1) translateY(0); } }
        @keyframes fadeInUp { 0% { opacity: 0; transform: translateY(20px); } 100% { opacity: 1; transform: translateY(0); } }
        @keyframes patternShift { 
            0% { background-position: 0 0; } 
            100% { background-position: 40px 40px; } 
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); } 
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        
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
            background: var(--nvidia-green);
            border: none;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-white); /* Texte blanc sur vert */
            font-weight: 700;
            font-size: 24px;
            box-shadow: 0 8px 25px rgba(118, 185, 0, 0.6);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            letter-spacing: -2px;
        }
        
        .logo-icon:hover {
            transform: rotate(0deg) scale(1.05);
            box-shadow: 0 12px 35px rgba(118, 185, 0, 0.8);
        }
        
        .logo-text {
            font-family: 'Inter', sans-serif;
            font-weight: 800;
            font-size: 32px;
            color: var(--text-light); /* Texte blanc */
            letter-spacing: -0.5px;
        }
        
        .logo-subtext {
            font-size: 14px;
            color: var(--nvidia-green);
            margin-top: -2px;
            letter-spacing: 3px;
            font-weight: 600;
        }
        
        /* Section formulaire */
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
            /* Fond sombre transparent */
            background: var(--card-bg);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 40px 35px;
            /* Bordure et ombre adaptées au thème sombre */
            border: 1px solid rgba(255, 255, 255, 0.1); 
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.5),
                0 15px 30px rgba(118, 185, 0, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
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
            background: var(--nvidia-green);
            box-shadow: 0 0 15px rgba(118, 185, 0, 0.8);
        }
        
        .form-title {
            font-family: 'Inter', sans-serif;
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
            color: var(--text-light); /* Titre en blanc */
            position: relative;
        }
        
        .form-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
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
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-light); /* Étiquette en blanc */
            font-size: 14px;
            letter-spacing: 0.5px;
        }
        
        .form-input {
            width: 100%;
            padding: 16px;
            /* Input sombre */
            background: #1e1e1e; 
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            font-size: 16px;
            color: var(--text-light); /* Texte dans l'input en blanc */
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--nvidia-green);
            box-shadow: 0 0 0 3px rgba(118, 185, 0, 0.3), 0 4px 15px rgba(118, 185, 0, 0.2);
            background: #252525;
            transform: translateY(-2px);
        }
        
        .phone-input-container {
            display: flex;
            gap: 10px;
        }
        
        .indicatif-select {
            flex: 0 0 110px;
            position: relative;
        }
        
        .indicatif-select select {
            width: 100%;
            /* Select sombre */
            background: #1e1e1e; 
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            color: var(--text-light); /* Texte en blanc */
            height: 100%;
            padding: 16px 10px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .indicatif-select::after {
            content: "\f0d7"; /* Font Awesome Chevron Down */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 12px;
            color: var(--nvidia-green);
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }
        
        .indicatif-select select:focus {
            border-color: var(--nvidia-green);
            box-shadow: 0 0 0 3px rgba(118, 185, 0, 0.3);
            transform: translateY(-2px);
            background: #252525;
        }
        
        .phone-input {
            flex: 1;
        }
        
        .btn-submit {
            background: var(--nvidia-green);
            color: var(--nvidia-black); /* Texte noir sur vert */
            border: 1px solid var(--nvidia-black);
            padding: 18px;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: var(--transition);
            margin-top: 15px;
            box-shadow: 
                0 8px 25px rgba(118, 185, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.5);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out 0.6s both;
            letter-spacing: 1px;
        }
        
        .btn-submit:hover {
            background: var(--nvidia-black);
            color: var(--nvidia-green);
            transform: translateY(-5px);
            box-shadow: 
                0 15px 30px rgba(118, 185, 0, 0.6),
                inset 0 1px 0 rgba(118, 185, 0, 0.5);
        }
        
        .signup-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid rgba(255, 255, 255, 0.1); /* Bordure claire pour fond sombre */
            animation: fadeInUp 0.8s ease-out 0.7s both;
        }
        
        .signup-text {
            color: rgba(255, 255, 255, 0.7); /* Texte gris clair */
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .btn-signup-form {
            background: transparent;
            color: var(--text-light); /* Texte blanc */
            border: 2px solid var(--text-light); /* Bordure blanche */
            padding: 14px 25px;
            border-radius: 10px;
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
        
        .btn-signup-form:hover {
            background: var(--nvidia-green);
            border-color: var(--nvidia-green);
            color: var(--nvidia-black);
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(118, 185, 0, 0.4);
        }
        
        /* Messages d'erreur/bienvenue */
        .error-message {
            color: var(--error);
            background: rgba(220, 53, 69, 0.2);
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--error);
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
            animation: shake 0.5s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .welcome-message {
            background: rgba(118, 185, 0, 0.15);
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--nvidia-green);
            color: var(--text-light); /* Texte blanc */
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .phone-input-container {
                flex-direction: column;
                gap: 10px;
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
    <div class="black-accent"></div>
    
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
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= htmlspecialchars($_SESSION['error']) ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <div class="welcome-message">
                    <i class="fas fa-microchip"></i> Accédez à votre console NVIDIA
                </div>
                
                <h2 class="form-title">Connectez-vous</h2>
                
                <form action="connexion.php" method="post" id="login-form">
                    <div class="form-group">
                        <label class="form-label" for="telephone">Numéro de Téléphone</label>
                        <div class="phone-input-container">
                            <div class="indicatif-select">
                                <select id="indicatif" name="indicatif" required>
                                    <option value="">Code</option>
                                    <?php foreach($pays_eligibles as $code => $pays): ?>
                                        <option value="<?= $code ?>" <?= ($form_data['indicatif'] === $code) ? 'selected' : '' ?>>
                                            <?= $code ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="tel" id="telephone" name="telephone" class="form-input phone-input" placeholder="Numéro (sans l'indicatif)" value="<?= htmlspecialchars($form_data['telephone']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="mot_de_passe">Mot de passe</label>
                        <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-input" placeholder="Votre mot de passe" required>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-sign-in-alt"></i> ACCÈS TECHNOLOGIE
                    </button>
                </form>
                
                <div class="signup-link">
                    <p class="signup-text">Pas encore un utilisateur NVIDIA?</p>
                    <a href="inscription1.php" class="btn-signup-form">
                        <i class="fas fa-user-plus"></i> CRÉER UN COMPTE
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animation d'entrée améliorée
            const formGroups = document.querySelectorAll('.form-group');
            formGroups.forEach((group, index) => {
                group.style.animationDelay = `${0.1 + (index * 0.1)}s`;
            });

            // ---------------------------------------------------------------------
            // LOGIQUE DE DÉTECTION AUTOMATIQUE DU PAYS (CLIENT-SIDE)
            // ---------------------------------------------------------------------

            const indicatifSelect = document.getElementById('indicatif');
            
            // Récupère le mappage des codes pays ISO vers les indicatifs depuis PHP
            const isoToIndicatifMap = JSON.parse('<?= json_encode($iso_to_indicatif_map) ?>');

            /**
             * Tente de détecter le code pays (ISO) à partir de la locale du navigateur.
             * @returns {string|null} Le code pays (ex: 'CI') ou null.
             */
            function detectUserCountry() {
                try {
                    // Tente de récupérer le code pays à partir de la locale du navigateur (ex: fr-CI, en-US)
                    const locale = navigator.language || navigator.userLanguage;
                    
                    // Extrait la partie pays (les deux dernières lettres après le tiret)
                    const localeParts = locale.split('-');
                    if (localeParts.length > 1) {
                        let countryCode = localeParts[localeParts.length - 1].toUpperCase();
                        
                        // S'assure que c'est un code pays valide dans notre mappage
                        if (countryCode.length === 2 && isoToIndicatifMap.hasOwnProperty(countryCode)) {
                            return countryCode;
                        }
                    }
                } catch (e) {
                    // En cas d'erreur ou si l'API n'est pas disponible (anciens navigateurs)
                    console.error("Erreur lors de la détection du pays :", e);
                }
                return null; // Échec de la détection
            }

            // Détection et sélection
            const detectedCountryISO = detectUserCountry();

            // L'indicatif défini par défaut en PHP (s'il y a eu une erreur de formulaire ou si c'est la première visite)
            const phpFormIndicatif = '<?= $form_data['indicatif'] ?>';
            const phpDefaultIndicatifFallback = '<?= $default_indicatif ?>';

            // Détermine si le champ a déjà été pré-rempli par PHP (suite à une erreur ou par défaut)
            const isFieldAlreadySetByPHP = phpFormIndicatif !== '' && phpFormIndicatif !== phpDefaultIndicatifFallback;


            if (detectedCountryISO) {
                const requiredIndicatif = isoToIndicatifMap[detectedCountryISO];
                
                // Si l'indicatif est trouvé et que le champ n'a pas déjà été rempli par l'utilisateur (via une erreur de formulaire)
                if (requiredIndicatif && !isFieldAlreadySetByPHP) {
                    indicatifSelect.value = requiredIndicatif;
                }
            }
        });
    </script>
</body>
</html>