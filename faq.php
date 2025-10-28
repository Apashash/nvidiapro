<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tutoriel - NVIDIA Technologies</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        /* Couleurs inspirées de NVIDIA */
        :root {
            --primary-dark: #1e293b; /* Gris foncé principal */
            --soft-dark: #2d3748; /* Fond plus doux */
            --warm-white: #fefefe;
            --light-gray: #f1f5f9;
            --accent-green: #76b900; /* Vert émeraude NVIDIA */
            --green-light: #90d300;
            --green-dark: #589a00;
            --accent-black: #000000;
            --text-white: #e2e8f0; /* Texte clair sur fond foncé */
            --text-dark: #1e293b;
            --text-gray: #a0aec0; /* Gris clair pour les sous-textes */
            --text-light: #cbd5e0;
            --card-bg: rgba(255, 255, 255, 0.95); /* Cartes claires pour un contraste fort */
            --border-color: rgba(255, 255, 255, 0.3);
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --premium-color: #76b900;
            --deep-color: #589a00;
            --secondary-dark: #374151; /* Couleur de fond des cartes foncées */
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
            /* Fond inspiré de l'esthétique "noir et vert" */
            background: linear-gradient(135deg, var(--soft-dark) 0%, var(--primary-dark) 100%);
            color: var(--text-white);
            line-height: 1.6;
            position: relative;
            -webkit-text-size-adjust: 100%;
            touch-action: pan-y;
            padding: 15px;
            user-select: none;
            -webkit-user-select: none;
        }
        
        /* Arrière-plan - Thème sombre avec accents verts */
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 10% 20%, rgba(118, 185, 0, 0.15) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(0, 0, 0, 0.5) 0%, transparent 20%),
                radial-gradient(circle at 20% 30%, rgba(118, 185, 0, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(0, 0, 0, 0.3) 0%, transparent 50%),
                linear-gradient(135deg, var(--soft-dark) 0%, var(--primary-dark) 100%);
            z-index: -3;
        }
        
        .geometric-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(30deg, rgba(118, 185, 0, 0.1) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.1) 87.5%, rgba(118, 185, 0, 0.1) 0),
                linear-gradient(150deg, rgba(0, 0, 0, 0.2) 12%, transparent 12.5%, transparent 87%, rgba(0, 0, 0, 0.2) 87.5%, rgba(0, 0, 0, 0.2) 0),
                linear-gradient(30deg, rgba(118, 185, 0, 0.06) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.06) 87.5%, rgba(118, 185, 0, 0.06) 0),
                linear-gradient(150deg, rgba(0, 0, 0, 0.15) 12%, transparent 12.5%, transparent 87%, rgba(0, 0, 0, 0.15) 87.5%, rgba(0, 0, 0, 0.15) 0),
                linear-gradient(60deg, rgba(118, 185, 0, 0.12) 25%, transparent 25.5%, transparent 75%, rgba(118, 185, 0, 0.12) 75%, rgba(118, 185, 0, 0.12) 0),
                linear-gradient(60deg, rgba(0, 0, 0, 0.25) 25%, transparent 25.5%, transparent 75%, rgba(0, 0, 0, 0.25) 75%, rgba(0, 0, 0, 0.25) 0);
            background-size: 100px 175px;
            background-position: 0 0, 0 0, 50px 87.5px, 50px 87.5px, 0 0, 50px 87.5px;
            z-index: -2;
            animation: patternShift 30s linear infinite;
        }
        
        .blue-accent, .purple-accent {
            display: none; /* Remplacé par des accents verts/noirs */
        }

        .green-glow {
            position: fixed;
            top: 0;
            right: 0;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(118, 185, 0, 0.15) 0%, transparent 70%);
            filter: blur(60px);
            z-index: -1;
        }

        .dark-shadow {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(0, 0, 0, 0.25) 0%, transparent 70%);
            filter: blur(60px);
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
                rgba(118, 185, 0, 0.08) 40%,
                rgba(0, 0, 0, 0.3) 50%,
                rgba(118, 185, 0, 0.08) 60%,
                transparent 100%
            );
            opacity: 0.3;
            z-index: -1;
            animation: lightShine 15s infinite linear;
        }
        
        /* Header compact */
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
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
            background: var(--accent-green); /* Vert NVIDIA */
            border: none;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-black); /* Couleur noire pour le texte N ou V */
            font-weight: bold;
            font-size: 18px;
            box-shadow: 0 8px 25px rgba(118, 185, 0, 0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            /* Style NVIDIA: angle coupé */
            clip-path: polygon(0 0, 100% 0, 100% 75%, 75% 100%, 0 100%);
        }
        
        .logo-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(0, 0, 0, 0.3), transparent);
            transform: rotate(45deg);
            animation: logoShine 3s infinite;
        }
        
        .logo-text {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 22px;
            background: linear-gradient(135deg, var(--accent-green), var(--green-light));
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
        
        /* Section principale */
        .main-section {
            margin-bottom: 40px;
            animation: fadeInUp 0.8s ease-out;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--accent-green); /* Titre en Vert */
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-subtitle {
            color: var(--text-gray);
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        /* Contenu principal - Carte avec fond clair pour lisibilité */
        .tutorial-content {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(118, 185, 0, 0.15);
            border: 1px solid rgba(118, 185, 0, 0.2);
            margin-bottom: 30px;
            color: var(--text-dark); /* Texte sombre sur carte claire */
        }
        
        .content-section {
            margin-bottom: 35px;
            padding-bottom: 30px;
            border-bottom: 1px solid rgba(118, 185, 0, 0.2);
        }
        
        .content-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title i {
            color: var(--accent-green);
            font-size: 24px;
        }
        
        .section-content {
            color: var(--text-dark);
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 15px;
        }
        
        .highlight-box {
            background: linear-gradient(135deg, rgba(118, 185, 0, 0.08), rgba(0, 0, 0, 0.05));
            border: 1px solid rgba(118, 185, 0, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .highlight-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--green-dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .info-item {
            background: rgba(118, 185, 0, 0.05);
            border: 1px solid rgba(118, 185, 0, 0.15);
            border-radius: 10px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .info-item i {
            color: var(--accent-green);
            font-size: 18px;
            width: 24px;
        }
        
        .info-text {
            flex: 1;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
        }
        
        .info-value {
            color: var(--green-dark);
            font-weight: 700;
            font-size: 15px;
        }
        
        .steps-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin: 20px 0;
        }
        
        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            background: rgba(118, 185, 0, 0.05);
            border-radius: 10px;
            border: 1px solid rgba(118, 185, 0, 0.15);
        }
        
        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--accent-green); /* Vert NVIDIA */
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-black); /* Numéro en noir pour le contraste */
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(118, 185, 0, 0.4);
        }
        
        .step-content {
            flex: 1;
        }
        
        .step-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
            font-size: 15px;
        }
        
        .step-description {
            color: var(--text-gray); /* Gris foncé sur carte claire */
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Bouton Histoire */
        .history-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--accent-black);
            color: var(--accent-green);
            border: 2px solid var(--accent-green);
            padding: 14px 26px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(118, 185, 0, 0.3);
            margin: 15px 0 25px 0;
            font-size: 15px;
        }
        
        .history-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(118, 185, 0, 0.4);
            background: var(--accent-green);
            color: var(--accent-black);
        }
        
        /* Boutons d'action */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--secondary-dark); /* Boutons action sombres */
            color: var(--text-white);
            padding: 14px 22px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(118, 185, 0, 0.2);
            font-size: 14px;
        }
        
        .action-btn.primary {
            background: var(--accent-green);
            color: var(--accent-black);
            font-weight: 700;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(118, 185, 0, 0.3);
        }
        
        /* Animations et Responsive inchangés pour conserver la structure */
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
        
        @keyframes patternShift {
            0% {
                background-position: 0 0, 0 0, 50px 87.5px, 50px 87.5px, 0 0, 50px 87.5px;
            }
            100% {
                background-position: 100px 175px, 100px 175px, 150px 262.5px, 150px 262.5px, 100px 175px, 150px 262.5px;
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
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                justify-content: center;
            }
            
            .step-item {
                flex-direction: column;
                text-align: center;
            }
            
            .step-number {
                align-self: center;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 12px;
            }
            
            .tutorial-content {
                padding: 20px;
            }
            
            .page-title {
                font-size: 24px;
            }
            
            .section-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="background"></div>
    <div class="geometric-pattern"></div>
    <div class="green-glow"></div>
    <div class="dark-shadow"></div>
    <div class="light-shimmer"></div>
    
    <div class="container">
        <div class="header">
            <div class="logo">
                <div class="logo-icon">NV</div>
                <div>
                    <div class="logo-text">NVIDIA</div>
                    <div class="logo-subtext">TECHNOLOGIES</div>
                </div>
            </div>
        </div>
        
        <div class="main-section">
            <h1 class="page-title">
                <i class="fas fa-graduation-cap"></i>
                Guide Complet NVIDIA
            </h1>
            <p class="page-subtitle">
                Découvrez tout ce que vous devez savoir pour réussir vos investissements 
                et maximiser vos revenus avec NVIDIA Technologies.
            </p>
            
            <div class="tutorial-content">
                <div class="content-section">
                    <h2 class="section-title">
                        <i class="fas fa-rocket"></i> Présentation de NVIDIA
                    </h2>
                    
                    <p class="section-content">
                        NVIDIA est une plateforme d'investissement internationale ouverte à plusieurs pays, 
                        offrant des opportunités d'investissement accessibles à tous. Notre plateforme vous 
                        permet d'investir dans des <strong>Actions VIP</strong> ou des <strong>Actions Flex</strong> 
                        (bientôt disponibles) pour générer des revenus quotidiens stables et croissants.
                    </p>
                    
                    <p class="section-content">
                        Avec une vision long terme qui s'étend au-delà de 2026, NVIDIA s'engage à fournir 
                        une plateforme stable et durable pour vos investissements. Notre objectif est de 
                        démocratiser l'investissement et de permettre à chacun de générer des revenus 
                        passifs importants sur le long terme.
                    </p>
                    
                    <a href="https://fr.wikipedia.org/wiki/Nvidia" class="history-btn" target="_blank">
                        <i class="fas fa-history"></i> Découvrir notre Histoire sur Wikipédia
                    </a>
                </div>
                
                <div class="content-section">
                    <h2 class="section-title">
                        <i class="fas fa-play-circle"></i> Comment Investir sur NVIDIA
                    </h2>
                    
                    <p class="section-content">
                        Le processus d'investissement sur NVIDIA est simple, rapide et sécurisé. 
                        Suivez ces étapes pour commencer à générer des revenus dès aujourd'hui :
                    </p>
                    
                    <div class="steps-container">
                        <div class="step-item">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <div class="step-title">Effectuer un Dépôt</div>
                                <div class="step-description">
                                    Commencez par effectuer un dépôt sur votre compte NVIDIA. 
                                    Le dépôt minimum est de <strong>2 500 FCFA</strong>. Les dépôts 
                                    sont automatiques et instantanés.
                                </div>
                            </div>
                        </div>
                        
                        <div class="step-item">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <div class="step-title">Choisir un Plan d'Investissement</div>
                                <div class="step-description">
                                    Rendez-vous dans la page <strong>Actions</strong> et sélectionnez 
                                    le plan qui correspond à vos objectifs financiers.
                                </div>
                            </div>
                        </div>
                        
                        <div class="step-item">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <div class="step-title">Effectuer l'Achat</div>
                                <div class="step-description">
                                    Procédez à l'achat de votre plan d'investissement choisi. 
                                    À chaque investissement, vous gagnez un tour gratuit sur 
                                    notre <strong>Roue de Fortune</strong>.
                                </div>
                            </div>
                        </div>
                        
                        <div class="step-item">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <div class="step-title">Valider les Paiements Quotidiens</div>
                                <div class="step-description">
                                    Chaque 24 heures, rendez-vous sur votre page de compte 
                                    pour valider votre paiement et recevoir vos gains.
                                </div>
                            </div>
                        </div>
                        
                        <div class="step-item">
                            <div class="step-number">5</div>
                            <div class="step-content">
                                <div class="step-title">Effectuer un Retrait</div>
                                <div class="step-description">
                                    Une fois vos gains accumulés, lancez votre retrait. 
                                    Les retraits sont traités en moins de <strong>30 minutes</strong>.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="content-section">
                    <h2 class="section-title">
                        <i class="fas fa-file-contract"></i> Conditions et Tarifs
                    </h2>
                    
                    <p class="section-content">
                        NVIDIA offre des conditions transparentes et compétitives pour 
                        garantir la meilleure expérience d'investissement à ses utilisateurs.
                    </p>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <i class="fas fa-arrow-down"></i>
                            <div class="info-text">
                                <div class="info-label">Dépôt Minimum</div>
                                <div class="info-value">3 000 FCFA</div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-arrow-up"></i>
                            <div class="info-text">
                                <div class="info-label">Retrait Minimum</div>
                                <div class="info-value">1 200 FCFA</div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-percentage"></i>
                            <div class="info-text">
                                <div class="info-label">Frais de Retrait</div>
                                <div class="info-value">12%</div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-clock"></i>
                            <div class="info-text">
                                <div class="info-label">Délai de Retrait</div>
                                <div class="info-value">Moins de 30 min</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="highlight-box">
                        <div class="highlight-title">
                            <i class="fas fa-shield-alt"></i> Règles de la Plateforme
                        </div>
                        <p class="section-content">
                            Pour garantir une expérience optimale à tous nos utilisateurs, 
                            nous demandons à chacun de respecter nos conditions d'utilisation 
                            et de maintenir un comportement respectueux dans nos communautés. 
                            En cas de problème, contactez immédiatement notre service client dédié.
                        </p>
                    </div>
                </div>
                
                <div class="content-section">
                    <h2 class="section-title">
                        <i class="fas fa-users"></i> Programme de Parrainage
                    </h2>
                    
                    <p class="section-content">
                        Gagnez encore plus avec notre programme de parrainage à 3 niveaux 
                        et notre système de codes cadeaux exclusifs.
                    </p>
                    
                    <div class="highlight-box">
                        <div class="highlight-title">
                            <i class="fas fa-user-friends"></i> Commissions de Parrainage
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <i class="fas fa-user"></i>
                                <div class="info-text">
                                    <div class="info-label">Niveau 1</div>
                                    <div class="info-value">20% de commission</div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-user-friends"></i>
                                <div class="info-text">
                                    <div class="info-label">Niveau 2</div>
                                    <div class="info-value">5% de commission</div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-users"></i>
                                <div class="info-text">
                                    <div class="info-label">Niveau 3</div>
                                    <div class="info-value">2% de commission</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="highlight-box">
                        <div class="highlight-title">
                            <i class="fas fa-gift"></i> Codes Cadeaux
                        </div>
                        <p class="section-content">
                            Les utilisateurs avec au moins <strong>50 filleuls inscrits</strong> 
                            ou <strong>10 filleuls investisseurs</strong> peuvent créer leurs 
                            propres codes cadeaux. Recevez <strong>40% de commission</strong> 
                            sur chaque code cadeau utilisé par les membres de votre équipe.
                        </p>
                    </div>
                </div>
                
                <div class="content-section">
                    <h2 class="section-title">
                        <i class="fas fa-headset"></i> Support Client
                    </h2>
                    
                    <p class="section-content">
                        Notre équipe de support est disponible pour vous accompagner à chaque 
                        étape de votre parcours d'investissement.
                    </p>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <i class="fas fa-clock"></i>
                            <div class="info-text">
                                <div class="info-label">Service Client</div>
                                <div class="info-value">12h/jour</div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <i class="fas fa-comments"></i>
                            <div class="info-text">
                                <div class="info-label">Groupe Discussion</div>
                                <div class="info-value">12h/jour minimum</div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <i class="fab fa-telegram"></i>
                            <div class="info-text">
                                <div class="info-label">Plateforme</div>
                                <div class="info-value">Exclusif Telegram</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="highlight-box">
                        <div class="highlight-title">
                            <i class="fas fa-exclamation-triangle"></i> En Cas de Problème
                        </div>
                        <p class="section-content">
                            Si vous rencontrez un problème quelconque, n'hésitez pas à contacter 
                            immédiatement notre service client dédié. Nous sommes là pour vous 
                            accompagner et résoudre rapidement toute difficulté.
                        </p>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="https://t.me/officielcanalBlackRock" class="action-btn primary" target="_blank">
                            <i class="fab fa-telegram"></i> Rejoindre le Canal
                        </a>
                        <a href="https://t.me/groupeofficielblackrock" class="action-btn" target="_blank">
                            <i class="fas fa-users"></i> Groupe de Discussion
                        </a>
                        <a href="https://t.me/Blackrockserviceclient" class="action-btn" target="_blank">
                            <i class="fas fa-headset"></i> Support Client
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Animation des éléments au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const stepItems = document.querySelectorAll('.step-item');
            const infoItems = document.querySelectorAll('.info-item');
            
            stepItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
            });
            
            infoItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.05}s`;
            });
        });
    </script>
</body>
</html>