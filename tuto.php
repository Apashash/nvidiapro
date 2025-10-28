<?php
// Début de la session DOIT être la première chose dans le fichier
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

// Définir la section active par défaut
$active_section = isset($_GET['section']) ? $_GET['section'] : 'depot';
$sections = [
    'depot' => 'Dépôt',
    'retrait' => 'Retrait',
    'investir' => 'Investir',
    'astuces' => 'Astuces',
    'taches' => 'Tâches'
];

// Liens des tutoriels (conservés)
$telegram_depot_xaf = "https://t.me/sonycanal/22";
$whatsapp_depot_xaf = "https://whatsapp.com/channel/0029VbBJRF13gvWScRNojl3e/118";
$telegram_depot_xof = "https://t.me/sonycanal/23"; 
$whatsapp_depot_xof = "https://whatsapp.com/channel/0029VbBJRF13gvWScRNojl3e/118";
$telegram_invest = "https://t.me/sonycanal/21";
$whatsapp_invest = "https://t.me/sonycanal/21";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tutoriels - NVIDIA Technology</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Thème NVIDIA (sombre et vert) */
        :root {
            --primary: #76b900; /* Vert émeraude NVIDIA */
            --primary-light: #eef4e6;
            --primary-dark: #5e9300; /* Vert foncé */
            --secondary: #00bcd4; /* Cyan pour contraste */
            --secondary-light: #e6f9fa;
            --dark: #1a1a1a;
            --dark-light: #b0b0b0; /* Texte clair sur fond sombre */
            --light: #f5f7fa; /* Fond de carte clair sur fond de page sombre */
            --gray: #000000; /* Fond de page principal noir */
            --gray-dark: #333333; /* Séparateurs et bords */
            --success: #4caf50;
            --warning: #ffc107;
            --error: #f44336;
            --border-radius: 12px;
            --box-shadow: 0 4px 18px rgba(118, 185, 0, 0.15); /* Ombre verte subtile */
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --tech-glow: rgba(118, 185, 0, 0.4); /* Utilisé pour le bouton central */
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: var(--gray); /* Fond noir */
            color: var(--light);
            max-width: 430px;
            margin: 0 auto;
            line-height: 1.6;
            padding-bottom: 76px !important;
        }
        
        .tutorial-container {
            background: var(--dark); /* Conteneur sombre */
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            color: var(--light);
        }
        
        /* Navigation améliorée */
        .tutorial-nav {
            display: flex;
            overflow-x: auto;
            background: var(--dark);
            position: sticky;
            top: 0;
            z-index: 10;
            padding: 0 15px;
            border-bottom: 1px solid var(--gray-dark);
            scrollbar-width: none;
        }
        
        .tutorial-nav::-webkit-scrollbar {
            display: none;
        }
        
        .nav-item {
            padding: 16px 12px;
            margin: 0 4px;
            white-space: nowrap;
            font-weight: 500;
            color: var(--dark-light);
            position: relative;
            transition: var(--transition);
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .nav-item.active {
            color: var(--primary);
            font-weight: 600;
        }
        
        .nav-item.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 24px;
            height: 3px;
            background: var(--primary);
            border-radius: 3px 3px 0 0;
            transition: var(--transition);
        }
        
        .nav-item:hover {
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        /* Contenu amélioré */
        .tutorial-content {
            padding: 20px;
        }
        
        .section-title {
            color: var(--primary);
            margin-bottom: 16px;
            font-size: 1.8rem;
            font-weight: 700;
            position: relative;
            padding-left: 12px;
        }
        
        .section-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--primary);
            border-radius: 4px;
        }
        
        .section-description {
            margin-bottom: 24px;
            color: var(--dark-light);
            font-size: 15px;
        }
        
        /* Boutons tutoriels */
        .tutorial-buttons {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin: 20px 0 30px;
        }
        
        .tutorial-btn {
            display: flex;
            align-items: center;
            padding: 18px 20px;
            border-radius: var(--border-radius);
            background: var(--gray-dark); /* Fond légèrement plus clair que le fond de page */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            transition: var(--transition);
            text-decoration: none;
            color: var(--light);
            border: 2px solid transparent;
        }
        
        .tutorial-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(118, 185, 0, 0.2);
            border-color: var(--primary-dark);
        }
        
        .tutorial-btn-telegram {
            background: linear-gradient(135deg, #2AABEE 0%, #229ED9 100%); /* Couleurs Telegram */
            color: white;
        }
        
        .tutorial-btn-whatsapp {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); /* Couleurs WhatsApp */
            color: white;
        }
        
        .tutorial-btn-icon {
            font-size: 28px;
            margin-right: 16px;
            width: 40px;
            text-align: center;
        }
        
        .tutorial-btn-content {
            flex: 1;
        }
        
        .tutorial-btn-content h3 {
            font-size: 1.1rem;
            margin-bottom: 4px;
            font-weight: 600;
        }
        
        .tutorial-btn-content p {
            font-size: 13px;
            opacity: 0.9;
        }
        
        /* Boutons améliorés */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 15px;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--dark); /* Texte sombre sur bouton vert */
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(118, 185, 0, 0.3);
        }
        
        .btn-secondary {
            background: var(--secondary);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #00a4b7;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            box-shadow: none;
        }
        
        .btn-outline:hover {
            background: rgba(118, 185, 0, 0.1);
        }
        
        /* Liste d'astuces améliorée */
        .tips-list {
            margin-top: 24px;
        }
        
        .tip-item {
            background: var(--gray-dark); /* Fond de carte sombre */
            padding: 18px;
            border-radius: var(--border-radius);
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            transition: var(--transition);
            border-left: 4px solid transparent;
        }
        
        .tip-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(118, 185, 0, 0.2);
            border-left-color: var(--primary);
        }
        
        .tip-icon {
            color: var(--secondary);
            font-size: 1.4rem;
            margin-right: 16px;
            min-width: 24px;
        }
        
        .tip-content h3 {
            font-size: 1.1rem;
            margin-bottom: 6px;
            color: var(--light);
        }
        
        .tip-content p {
            color: var(--dark-light);
            font-size: 14px;
        }
        
        /* Section tâches améliorée */
        .task-steps {
            margin-top: 24px;
        }
        
        .step {
            display: flex;
            margin-bottom: 20px;
            align-items: flex-start;
            background: var(--gray-dark); /* Fond de carte sombre */
            padding: 16px;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            transition: var(--transition);
        }
        
        .step:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(118, 185, 0, 0.2);
        }
        
        .step-number {
            background: var(--primary);
            color: var(--dark); /* Numéro en vert, texte en noir */
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            flex-shrink: 0;
            font-weight: 600;
            font-size: 14px;
        }
        
        .step-content {
            flex: 1;
            color: var(--light);
        }
        
        .step-content h3 {
            font-size: 1.1rem;
            margin-bottom: 6px;
            color: var(--light);
        }
        
        .step-content p {
            color: var(--dark-light);
            font-size: 14px;
        }
        
        /* Info box améliorée */
        .info-box {
            margin-top: 30px;
            padding: 20px;
            background: rgba(118, 185, 0, 0.1); /* Fond vert très léger */
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .info-box h3 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
        }
        
        .info-box h3 i {
            margin-right: 8px;
        }
        
        /* Liste stylisée */
        .styled-list {
            margin-left: 20px;
            margin-top: 10px;
            color: var(--dark-light);
        }
        
        .styled-list li {
            margin-bottom: 8px;
            position: relative;
            padding-left: 24px;
            color: var(--dark-light);
        }
        
        .styled-list li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 8px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary);
        }
        
        /* CSS pour le bouton flottant */
        .floating-draggable-btn {
          position: fixed;
          width: 60px;
          height: 60px;
          background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
          color: var(--dark);
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          box-shadow: 0 6px 20px rgba(118, 185, 0, 0.4);
          cursor: pointer;
          z-index: 9999;
          transition: transform 0.3s, box-shadow 0.3s;
          border: none;
          user-select: none;
          touch-action: none;
          font-size: 24px;
        }

        .floating-draggable-btn:hover {
          transform: scale(1.1);
          box-shadow: 0 8px 25px rgba(118, 185, 0, 0.6);
        }

        /* Style pour le bouton Retour */
        .back-button {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1000;
            background: var(--primary);
            color: var(--dark);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(118, 185, 0, 0.3);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }
        
        .back-button:hover {
            transform: scale(1.1);
            background: var(--primary-dark);
        }
        
        /* Styles pour le menu de navigation */
        .nav-container {
            /* ... (styles de nav-container ajustés pour le thème sombre) ... */
            background: linear-gradient(120deg, #1a1a1a99 60%, #1c1c1c 100%);
            backdrop-filter: blur(16px) saturate(1.6);
            border: 1.5px solid #282828;
            box-shadow: 0 6px 30px 0 rgba(0, 0, 0, 0.4), 0 1.5px 10px 0 rgba(10,31,61,.09);
        }

        .nav-item {
            /* ... (styles de nav-item ajustés pour le thème sombre) ... */
            color: #b0b0b0;
        }

        .nav-item.active,
        .nav-item:focus-visible,
        .nav-item:hover {
            color: var(--primary);
            background: linear-gradient(90deg, #1c1c1c 60%, #181818 100%);
            box-shadow: 0 2px 8px 0 rgba(118, 185, 0, 0.1);
        }

        .nav-center {
            /* ... (styles de nav-center ajustés pour le thème sombre) ... */
            background: linear-gradient(135deg, var(--primary) 65%, var(--primary-dark) 100%);
            color: var(--dark); /* Texte noir sur bouton vert */
            box-shadow: 0 5px 18px var(--tech-glow), 0 2px 8px 0 rgba(118, 185, 0, 0.2);
            border: 3.5px solid #1a1a1a;
        }
        
        .nav-center-label {
            /* ... (styles de nav-center-label ajustés pour le thème sombre) ... */
            color: #999;
            background: #1c1c1c99;
            box-shadow: 0 1px 4px 0 rgba(0,0,0,0.3);
        }
        
        .nav-center:hover + .nav-center-label,
        .nav-center:focus-visible + .nav-center-label,
        .nav-center.active + .nav-center-label {
            color: var(--primary);
            background: #1c1c1c99;
        }

        /* Styles pour les sections XAF/XOF */
        .currency-section {
            margin-bottom: 30px;
            padding: 20px;
            background: var(--gray-dark); /* Fond de section sombre */
            border-radius: var(--border-radius);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        }

        .currency-header {
            padding-bottom: 15px;
            border-bottom: 2px solid #555;
        }
        
        .currency-title {
            color: var(--light);
        }

        /* Styles conservés pour les icônes de devises pour clarté */
        .xaf-icon {
            background: linear-gradient(135deg, #009a49 0%, #007236 100%);
        }

        .xof-icon {
            background: linear-gradient(135deg, #ed2939 0%, #cc0000 100%);
        }

    </style>
</head>
<body>
    <a href="index.php" class="back-button" title="Retour à l'accueil">
        <i class="fas fa-arrow-left"></i>
    </a>
  
    <div class="tutorial-container">
        <div class="tutorial-nav">
            <?php foreach ($sections as $key => $title): ?>
                <a href="?section=<?= $key ?>" class="nav-item <?= $active_section === $key ? 'active' : '' ?>">
                    <?= $title ?>
                </a>
            <?php endforeach; ?>
        </div>
   
        <div class="tutorial-content">
            <?php if ($active_section === 'depot'): ?>
                <h1 class="section-title">Dépôt sur NVIDIA Technology</h1>
                <p class="section-description">
                    Effectuez un dépôt en toute sécurité sur votre compte en suivant ce guide étape par étape.
                    Les fonds sont crédités instantanément.
                </p>
                
                <div class="currency-section">
                    <div class="currency-header">
                        <div class="currency-icon xaf-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div>
                            <h2 class="currency-title">Pays en XAF</h2>
                            <p class="currency-countries">Cameroun, Gabon, Guinée Équatoriale, Tchad, République Centrafricaine, Congo</p>
                        </div>
                    </div>
                    
                    <div class="tutorial-buttons">
                        <a href="<?= $telegram_depot_xaf ?>" target="_blank" class="tutorial-btn tutorial-btn-telegram">
                            <div class="tutorial-btn-icon">
                                <i class="fab fa-telegram"></i>
                            </div>
                            <div class="tutorial-btn-content">
                                <h3>Tutoriel Dépôt XAF - Telegram</h3>
                                <p>Guide complet pour les pays utilisant le Franc CFA (XAF)</p>
                            </div>
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        
                        <a href="<?= $whatsapp_depot_xaf ?>" target="_blank" class="tutorial-btn tutorial-btn-whatsapp">
                            <div class="tutorial-btn-icon">
                                <i class="fab fa-whatsapp"></i>
                            </div>
                            <div class="tutorial-btn-content">
                                <h3>Tutoriel Dépôt XAF - WhatsApp</h3>
                                <p>Guide complet pour les pays utilisant le Franc CFA (XAF)</p>
                            </div>
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
                
                <div class="currency-section">
                    <div class="currency-header">
                        <div class="currency-icon xof-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div>
                            <h2 class="currency-title">Pays en XOF</h2>
                            <p class="currency-countries">Côte d'Ivoire, Sénégal, Bénin, Burkina Faso, Mali, Niger, Togo, Guinée-Bissau</p>
                        </div>
                    </div>
                    
                    <div class="tutorial-buttons">
                        <a href="<?= $telegram_depot_xof ?>" target="_blank" class="tutorial-btn tutorial-btn-telegram">
                            <div class="tutorial-btn-icon">
                                <i class="fab fa-telegram"></i>
                            </div>
                            <div class="tutorial-btn-content">
                                <h3>Tutoriel Dépôt XOF - Telegram</h3>
                                <p>Guide complet pour les pays utilisant le Franc CFA (XOF)</p>
                            </div>
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        
                        <a href="<?= $whatsapp_depot_xof ?>" target="_blank" class="tutorial-btn tutorial-btn-whatsapp">
                            <div class="tutorial-btn-icon">
                                <i class="fab fa-whatsapp"></i>
                            </div>
                            <div class="tutorial-btn-content">
                                <h3>Tutoriel Dépôt XOF - WhatsApp</h3>
                                <p>Guide complet pour les pays utilisant le Franc CFA (XOF)</p>
                            </div>
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
                
                <h2 style="margin-bottom: 16px; color: var(--light);">Procédure de dépôt :</h2>
                <div class="task-steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3>Accédez à la section Dépôt</h3>
                            <p>Dans votre espace membre, cliquez sur "Dépôt" dans le menu principal.</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3>Choisissez votre méthode</h3>
                            <p>Sélectionnez parmi les options disponibles (Mobile Money, Carte Bancaire, etc.).</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h3>Entrez le montant</h3>
                            <p>Indiquez le montant que vous souhaitez déposer (minimum 1 000 XOF/XAF).</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h3>Confirmez la transaction</h3>
                            <p>Suivez les instructions pour finaliser le paiement depuis votre appareil.</p>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 24px; display: flex; gap: 12px;">
                    <a href="#" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-wallet"></i> Faire un dépôt
                    </a>
                    <a href="#" class="btn btn-outline" style="flex: 1;">
                        <i class="fas fa-question-circle"></i> Assistance
                    </a>
                </div>

            <?php elseif ($active_section === 'retrait'): ?>
                <h1 class="section-title">Retrait de vos gains</h1>
                <p class="section-description">
                    Retirez facilement votre argent sur votre compte bancaire ou mobile money.
                    Traitement rapide et sécurisé.
                </p>
                
                <div class="info-box">
                    <h3><i class="fas fa-info-circle"></i> Instructions pour effectuer un retrait</h3>
                    <p>Suivez ces étapes simples pour retirer vos fonds :</p>
                    <ol class="styled-list">
                        <li>Remplissez le formulaire de retrait avec vos informations personnelles</li>
                        <li>Indiquez le montant que vous souhaitez retirer</li>
                        <li>Sélectionnez votre pays de résidence</li>
                        <li>Choisissez votre méthode de retrait préférée</li>
                        <li>Entrez vos coordonnées bancaires ou numéro de compte</li>
                        <li>Cliquez sur le bouton "Retrait" pour lancer la demande</li>
                    </ol>
                </div>
                
                <div class="task-steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3>Remplissez le formulaire</h3>
                            <p>Complétez tous les champs requis avec vos informations exactes.</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3>Choisissez le montant</h3>
                            <p>Le minimum de retrait est de 20 000 XOF/XAF.</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h3>Sélectionnez la méthode</h3>
                            <p>Choisissez votre processeur de paiement préféré.</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h3>Confirmez le retrait</h3>
                            <p>Vérifiez toutes les informations avant de soumettre votre demande.</p>
                        </div>
                    </div>
                </div>
                
                <div class="info-box">
                    <h3><i class="fas fa-clock"></i> Conditions importantes</h3>
                    <ul class="styled-list">
                        <li>Montant minimum : 20 000 XOF/XAF</li>
                        <li>Délai de traitement : Moins d'une heure</li>
                        <li>Disponibilité : Du lundi au vendredi, de 12h à 17h</li>
                        <li>Pas de retraits le week-end</li>
                    </ul>
                </div>
                
                <div style="margin-top: 24px;">
                    <a href="#" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-money-bill-wave"></i> Demander un retrait
                    </a>
                </div>

            <?php elseif ($active_section === 'investir'): ?>
                <h1 class="section-title">Investir et Croître</h1>
                <p class="section-description">
                    Maximisez vos rendements avec nos différents plans d'investissement.
                    Commencez dès aujourd'hui avec un montant .
                </p>
                
                <div class="tutorial-buttons">
                    <a href="<?= $telegram_invest ?>" target="_blank" class="tutorial-btn tutorial-btn-telegram">
                        <div class="tutorial-btn-icon">
                            <i class="fab fa-telegram"></i>
                        </div>
                        <div class="tutorial-btn-content">
                            <h3>Regarder sur Telegram</h3>
                            <p>Tutoriel vidéo complet sur notre chaîne Telegram</p>
                        </div>
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    
                    <a href="<?= $whatsapp_invest ?>" target="_blank" class="tutorial-btn tutorial-btn-whatsapp">
                        <div class="tutorial-btn-icon">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <div class="tutorial-btn-content">
                            <h3>Regarder sur WhatsApp</h3>
                            <p>Tutoriel vidéo complet sur notre chaîne WhatsApp</p>
                        </div>
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
                
                <div class="info-box">
                    <h3><i class="fas fa-chart-line"></i> Pourquoi investir avec NVIDIA Technology?</h3>
                    <ul class="styled-list">
                        <li>Rendements élevés et réguliers</li>
                        <li>Plans d'investissement flexibles</li>
                        <li>Support client disponible 24/7</li>
                        <li>Retraits rapides et sécurisés</li>
                    </ul>
                </div>
                
                <div style="margin-top: 24px;">
                    <a href="#" class="btn btn-secondary" style="width: 100%;">
                        <i class="fas fa-chart-line"></i> Voir les plans d'investissement
                    </a>
                </div>

            <?php elseif ($active_section === 'astuces'): ?>
                <h1 class="section-title">Astuces Expertes</h1>
                <p class="section-description">
                    Découvrez les techniques utilisées par nos meilleurs investisseurs pour maximiser leurs gains.
                </p>
                
                <div class="tips-list">
                    <div class="tip-item">
                        <div class="tip-icon"><i class="fas fa-user-friends"></i></div>
                        <div class="tip-content">
                            <h3>Programme de parrainage</h3>
                            <p>Gagnez jusqu'à 15% sur 3 niveaux de filleuls. Plus votre réseau est actif, plus vos revenus augmentes.</p>
                            <a href="#" class="btn btn-outline" style="margin-top: 10px; padding: 8px 12px; font-size: 13px;">
                                Copier mon lien <i class="fas fa-copy"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="tip-item">
                        <div class="tip-icon"><i class="fas fa-bell"></i></div>
                        <div class="tip-content">
                            <h3>Alertes opportunités</h3>
                            <p>Activez les notifications pour ne pas manquer les promotions et opportunités spéciales.</p>
                        </div>
                    </div>
                    
                    <div class="tip-item">
                        <div class="tip-icon"><i class="fas fa-book"></i></div>
                        <div class="tip-content">
                            <h3>Suivez votre performance</h3>
                            <p>Consultez régulièrement vos statistiques pour ajuster votre stratégie.</p>
                        </div>
                    </div>
                </div>

            <?php elseif ($active_section === 'taches'): ?>
                <h1 class="section-title">Tâches Quotidiennes</h1>
                <p class="section-description">
                    Gagnez des revenus supplémentaires en complétant des tâches simples chaque jour.
                </p>
                
                <div class="task-steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3>Accédez aux Tâches</h3>
                            <p>Dans votre espace membre, cliquez sur "Tâches quotidiennes".</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3>Choisissez une tâche</h3>
                            <p>Sélectionnez parmi les options disponibles (partage sur réseaux, invitations, etc.).</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h3>Effectuez la tâche</h3>
                            <p>Par exemple, partagez votre lien de parrainage dans un groupe et prenez une capture.</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h3>Soumettez la preuve</h3>
                            <p>Uploadez la capture d'écran ou le lien demandé pour validation.</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">5</div>
                        <div class="step-content">
                            <h3>Recevez votre récompense</h3>
                            <p>Après vérification (environ 30 minutes), votre solde sera crédité.</p>
                        </div>
                    </div>
                </div>
                
                <div class="info-box">
                    <h3><i class="fas fa-lightbulb"></i> Conseils pratiques</h3>
                    <ul class="styled-list">
                        <li>Les captures doivent montrer clairement le groupe et votre message</li>
                        <li>Plus vous complétez de tâches, plus vos gains augmentent</li>
                        <li>Priorisez les tâches à haute récompense</li>
                    </ul>
                </div>
                
                <div style="margin-top: 24px;">
                    <a href="#" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-tasks"></i> Voir mes tâches disponibles
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
  
    <div class="nav-container" role="navigation" aria-label="Navigation principale">
        <div class="nav-menu">
            <a href="index.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" aria-label="Accueil">
                <i class="fas fa-home"></i>
                <span>Accueil</span>
                <div class="nav-indicator"></div>
            </a>
            <a href="equipe.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'equipe.php' ? 'active' : '' ?>" aria-label="Équipe">
                <i class="fas fa-users"></i>
                <span>Équipe</span>
                <div class="nav-indicator"></div>
            </a>
            <div class="nav-center-wrapper">
                <a href="investissement.php" class="nav-center <?= basename($_SERVER['PHP_SELF']) === 'investissement.php' ? 'active' : '' ?>" aria-label="Investir">
                    <i class="fas fa-chart-line"></i>
                </a>
                <span class="nav-center-label">Investir</span>
            </div>
            <a href="blog.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'blog.php' ? 'active' : '' ?>" aria-label="Blog">
                <i class="fas fa-newspaper"></i>
                <span>Blog</span>
                <div class="nav-indicator"></div>
            </a>
            <a href="compte.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'compte.php' ? 'active' : '' ?>" aria-label="Compte">
                <i class="fas fa-user"></i>
                <span>Compte</span>
                <div class="nav-indicator"></div>
            </a>
        </div>
    </div>

    <a href="index.php" class="floating-draggable-btn" id="draggableBtn" title="Retour à l'accueil">
        <i class="fas fa-home"></i>
    </a>
    
    <script>
        // Animation des cartes au survol (pour desktop)
        if (window.matchMedia("(hover: hover)").matches) {
            const tips = document.querySelectorAll('.tip-item, .step, .tutorial-btn');
            tips.forEach(tip => {
                tip.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 15px 30px rgba(118, 185, 0, 0.15)'; // Ombre verte
                });
                
                tip.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });
        }
        
        // Réserve dynamiquement la place du menu même si le body est dans un container
        function adjustPaddingForMenu() {
            const nav = document.querySelector('.nav-container');
            if (nav) {
                const navHeight = nav.offsetHeight + 24;
                document.body.style.paddingBottom = navHeight + 'px';
            }
        }
        window.addEventListener('resize', adjustPaddingForMenu);
        adjustPaddingForMenu();
        
        // Animation + vérification session
        setInterval(() => {
            fetch('check_session.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.active) window.location.href = 'connexion.php';
                });
        }, 300000);

        // Animation boutons (fluidité, rebond)
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (!this.classList.contains('active')) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    this.style.transform = 'scale(0.93) translateY(1.5px)';
                    setTimeout(() => {
                        this.style.transform = '';
                        window.location.href = href;
                    }, 100);
                }
            });
        });

        const navCenter = document.querySelector('.nav-center');
        if (navCenter) {
            navCenter.addEventListener('click', function(e) {
                if (!this.classList.contains('active')) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    this.style.transform = 'scale(0.92)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                        window.location.href = href;
                    }, 100);
                }
            });
        }
    </script>
</body>
</html>