<?php
// Activation des erreurs complète
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Démarrage de session
session_start();

// Inclusion de la DB (Assurez-vous que ce fichier est compatible avec votre nouveau thème si nécessaire)
require_once 'db.php';

// Récupération du code parrain depuis l'URL
if (isset($_GET['p'])) {
    $_SESSION['parrain_code'] = trim($_GET['p']);
}

// Récupération code parrain depuis la session
$code_parrain = isset($_SESSION['parrain_code']) ? $_SESSION['parrain_code'] : '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>NVIDIA Technologie</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Couleurs NVIDIA Thème */
            --primary-black: #000000;
            --soft-black: #0f0f0f;
            --dark-gray: #1a1a1a;
            --light-gray: #2e2e2e; /* Utilisé pour les bords clairs/soft */
            --accent-green: #76b900; /* Le vert iconique de NVIDIA */
            --green-light: #90d400;
            --green-dark: #5c8f00;
            --text-light: #e0e0e0;
            --text-gray: #aaaaaa;
            --text-dark: #ffffff; /* Texte principal en blanc pour le fond sombre */
            --card-bg: rgba(0, 0, 0, 0.8); /* Fond des cartes/éléments semi-transparent sombre */
            --border-color: rgba(118, 185, 0, 0.3); /* Bordure en accent vert */
            --error: #ff4444;
            --success: #76b900; /* Succès en vert NVIDIA */
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
            /* Arrière-plan sombre/noir */
            background: linear-gradient(135deg, var(--soft-black) 0%, var(--primary-black) 100%);
            color: var(--text-dark);
            line-height: 1.6;
            position: relative;
            overflow-y: auto;
            -webkit-text-size-adjust: 100%;
            touch-action: manipulation;
        }
        
        /* Arrière-plan géométrique élégant - Style sombre NVIDIA */
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
                linear-gradient(135deg, var(--soft-black) 0%, var(--primary-black) 100%);
            z-index: -3;
        }
        
        .geometric-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(30deg, rgba(118, 185, 0, 0.05) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.05) 87.5%, rgba(118, 185, 0, 0.05) 0),
                linear-gradient(150deg, rgba(118, 185, 0, 0.05) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.05) 87.5%, rgba(118, 185, 0, 0.05) 0),
                linear-gradient(30deg, rgba(118, 185, 0, 0.03) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.03) 87.5%, rgba(118, 185, 0, 0.03) 0),
                linear-gradient(150deg, rgba(118, 185, 0, 0.03) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.03) 87.5%, rgba(118, 185, 0, 0.03) 0),
                linear-gradient(60deg, rgba(118, 185, 0, 0.08) 25%, transparent 25.5%, transparent 75%, rgba(118, 185, 0, 0.08) 75%, rgba(118, 185, 0, 0.08) 0),
                linear-gradient(60deg, rgba(118, 185, 0, 0.08) 25%, transparent 25.5%, transparent 75%, rgba(118, 185, 0, 0.08) 75%, rgba(118, 185, 0, 0.08) 0);
            background-size: 100px 175px;
            background-position: 0 0, 0 0, 50px 87.5px, 50px 87.5px, 0 0, 50px 87.5px;
            z-index: -2;
            animation: patternShift 30s linear infinite;
        }
        
        .blue-accent, .purple-accent { /* Accent vert unique pour NVIDIA */
            position: fixed;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(118, 185, 0, 0.15) 0%, transparent 70%);
            filter: blur(60px);
            z-index: -1;
        }
        
        .blue-accent {
            top: 0;
            right: 0;
        }
        
        .purple-accent {
            bottom: 0;
            left: 0;
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
                rgba(118, 185, 0, 0.15) 50%, /* L'effet le plus brillant en vert */
                rgba(118, 185, 0, 0.08) 60%,
                transparent 100%
            );
            opacity: 0.3;
            z-index: -1;
            animation: lightShine 15s infinite linear;
        }
        
        .light-beam {
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, transparent 70%);
            animation: rotateBeam 25s linear infinite;
            z-index: -1;
        }
        
        /* Conteneur principal */
        .main-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 20px;
            animation: fadeIn 1.2s ease-out;
        }
        
        /* Header élégant */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 20px;
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
            /* Dégradé de vert NVIDIA */
            background: linear-gradient(135deg, var(--accent-green), var(--green-dark));
            border: none;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-black); /* Icône texte en noir pour contraste */
            font-weight: bold;
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
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.4), transparent);
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
            /* Dégradé de vert NVIDIA */
            background: linear-gradient(135deg, var(--accent-green), var(--green-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }
        
        .logo-subtext {
            font-size: 14px;
            color: var(--text-gray);
            margin-top: -2px;
            letter-spacing: 3px;
            font-weight: 500;
        }
        
        .auth-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn-auth {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-auth::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.7s;
        }
        
        .btn-auth:hover::before {
            left: 100%;
        }
        
        .btn-login {
            background: transparent;
            color: var(--accent-green);
            border: 1px solid var(--accent-green);
        }
        
        .btn-login:hover {
            background: rgba(118, 185, 0, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(118, 185, 0, 0.3);
        }
        
        .btn-register {
            /* Dégradé de vert NVIDIA */
            background: linear-gradient(135deg, var(--accent-green), var(--green-dark));
            color: var(--primary-black); /* Texte noir sur vert pour le bouton principal */
            box-shadow: 0 4px 12px rgba(118, 185, 0, 0.4);
        }
        
        .btn-register:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 6px 16px rgba(118, 185, 0, 0.5);
        }
        
        /* Section principale */
        .hero-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        
        /* Carousel */
        .carousel-container {
            width: 100%;
            max-width: 850px;
            height: 380px;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            box-shadow: 
                0 25px 50px rgba(118, 185, 0, 0.15),
                0 15px 30px rgba(0, 0, 0, 0.5);
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            animation: scaleUp 1.2s ease-out;
        }
        
        .carousel-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1.5s ease;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }
        
        .carousel-slide.active {
            opacity: 1;
        }
        
        .slide-content {
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
            color: white;
            padding: 30px;
            width: 100%;
            text-align: center;
            backdrop-filter: blur(5px);
        }
        
        .slide-title {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            margin-bottom: 12px;
            color: var(--accent-green); /* Titre en vert NVIDIA */
            font-weight: 700;
        }
        
        .slide-text {
            font-size: 16px;
            line-height: 1.5;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
            color: var(--text-light);
        }
        
        /* Bouton d'inscription principal */
        .register-btn-section {
            text-align: center;
            margin-top: 30px;
            animation: fadeInUp 1.5s ease-out 1.7s both;
        }
        
        .btn-register-main {
            /* Dégradé de vert NVIDIA */
            background: linear-gradient(135deg, var(--accent-green), var(--green-dark));
            color: var(--primary-black); /* Texte noir pour contraste */
            border: none;
            padding: 18px 45px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 
                0 8px 25px rgba(118, 185, 0, 0.6),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-register-main::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.7s;
        }
        
        .btn-register-main:hover::before {
            left: 100%;
        }
        
        .btn-register-main:hover {
            transform: translateY(-4px) scale(1.04);
            box-shadow: 
                0 15px 30px rgba(118, 185, 0, 0.8),
                inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        /* Message de parrainage */
        .referral-notice {
            /* Thème sombre et accent vert */
            background: linear-gradient(135deg, rgba(118, 185, 0, 0.15), rgba(0, 0, 0, 0.5));
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid var(--accent-green);
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
            max-width: 500px;
            margin: 20px auto;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideDown {
            from { 
                opacity: 0;
                transform: translateY(-50px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes scaleUp {
            from { 
                opacity: 0;
                transform: scale(0.9);
            }
            to { 
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* Les keyframes ci-dessous n'ont pas besoin d'être modifiés car ils gèrent le mouvement, pas la couleur */
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
        
        @keyframes rotateBeam {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
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
            
            .carousel-container {
                height: 320px;
            }
            
            .slide-title {
                font-size: 24px;
            }
            
            .slide-text {
                font-size: 15px;
            }
            
            .btn-register-main {
                padding: 16px 35px;
                font-size: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .main-container {
                padding: 12px;
            }
            
            .logo-icon {
                width: 45px;
                height: 45px;
                font-size: 18px;
            }
            
            .logo-text {
                font-size: 24px;
            }
            
            .carousel-container {
                height: 280px;
            }
            
            .slide-content {
                padding: 20px;
            }
            
            .slide-title {
                font-size: 20px;
            }
            
            .slide-text {
                font-size: 14px;
            }
            
            .btn-register-main {
                padding: 14px 30px;
                font-size: 15px;
            }
        }

        /* Float animation CSS */
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-5px) scale(1.02); }
        }
    </style>
</head>
<body>
    <div class="background"></div>
    <div class="geometric-pattern"></div>
    <div class="blue-accent"></div>
    <div class="purple-accent"></div>
    <div class="light-shimmer"></div>
    <div class="light-beam"></div>
    
    <div class="main-container">
        <div class="header">
            <div class="logo">
                <div class="logo-icon">NV</div>
                <div>
                    <div class="logo-text">NVIDIA</div>
                    <div class="logo-subtext">TECHNOLOGIE</div>
                </div>
            </div>
            <div class="auth-buttons">
                <a href="connexion.php" class="btn-auth btn-login">Connexion</a>
                <a href="inscription1.php" class="btn-auth btn-register">Inscription</a>
            </div>
        </div>
        
        <div class="hero-section">
            <?php if (!empty($code_parrain)): ?>
                <div class="referral-notice">
                    <i class="fas fa-user-friends"></i> Vous êtes invité par un partenaire NVIDIA
                </div>
            <?php endif; ?>
            
            <div class="carousel-container">
                <div class="carousel-slide active" style="background-image: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1554224155-6726b3ff858f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');">
                    <div class="slide-content">
                        <h2 class="slide-title">Puissance de Calcul Avancée</h2>
                        <p class="slide-text">Explorez la nouvelle ère de la technologie avec nos plateformes de calcul accéléré et nos solutions d'IA.</p>
                    </div>
                </div>
                <div class="carousel-slide" style="background-image: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1553877522-43269d4ea984?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');">
                    <div class="slide-content">
                        <h2 class="slide-title">Innovation en Temps Réel</h2>
                        <p class="slide-text">Déployez des solutions rapides et sécurisées pour la simulation, le rendu et le traitement de données volumineuses.</p>
                    </div>
                </div>
                <div class="carousel-slide" style="background-image: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');">
                    <div class="slide-content">
                        <h2 class="slide-title">NVIDIA - Le Futur de la Technologie</h2>
                        <p class="slide-text">De l'IA aux graphismes de pointe, rejoignez le leader mondial de la technologie.</p>
                    </div>
                </div>
            </div>
            
            <div class="register-btn-section">
                <a href="inscription1.php" class="btn-register-main">
                    <i class="fas fa-microchip"></i> REJOINDRE L'INNOVATION
                </a>
            </div>
        </div>
    </div>

    <script>
        // Carousel functionality
        let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-slide');
        const totalSlides = slides.length;
        
        function showSlide(index) {
            slides.forEach(slide => slide.classList.remove('active'));
            slides[index].classList.add('active');
        }
        
        function nextSlide() {
            currentSlide = (currentSlide + 1) % totalSlides;
            showSlide(currentSlide);
        }
        
        // Change slide every 6 seconds
        setInterval(nextSlide, 6000);
        
        // Enhanced animations on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add subtle floating animation to main button
            const mainBtn = document.querySelector('.btn-register-main');
            if (mainBtn) {
                mainBtn.style.animation = 'fadeInUp 1.5s ease-out 1.7s both, float 3s ease-in-out infinite 2.5s';
            }
            
            // L'animation 'float' est maintenant dans la section <style>
        });
    </script>
</body>
</html>