<?php
session_start();
require 'db.php';
include 'menu.php';
include 'image.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer le solde de l'utilisateur
$stmt = $db->prepare("SELECT solde FROM soldes WHERE user_id = ?");
$stmt->execute([$user_id]);
$solde_data = $stmt->fetch();
$solde = $solde_data['solde'] ?? 0;

// Déterminer le menu actif : 'vip' ou 'commande' (anciennement 'flex')
$menu_actif = isset($_GET['menu']) && in_array($_GET['menu'], ['vip', 'commande']) ? $_GET['menu'] : 'vip';

// RÉCUPÉRER TOUS LES PLANS D'INVESTISSEMENT - SUPPRESSION DE LA LIMITE
$plans = $db->prepare("SELECT * FROM planinvestissement ORDER BY prix ASC");
$plans->execute();
$plans_data = $plans->fetchAll();

// Vérifier et corriger les données
$plans_corriges = [];
foreach ($plans_data as $plan) {
    // S'assurer que chaque plan a un ID unique
    if (!in_array($plan['id'], array_column($plans_corriges, 'id'))) {
        $plans_corriges[] = $plan;
    }
}

// Calculer les revenus journaliers pour chaque plan
foreach ($plans_corriges as &$plan) {
    $plan['revenu_journalier'] = ($plan['prix'] * $plan['rendement_journalier']) / 100;
    $plan['revenu_total'] = $plan['revenu_journalier'] * $plan['duree_jours'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investissement - NVIDIA Technologie</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        /* Couleurs NVIDIA / Thème Sombre */
        --primary-white: #ffffff;
        --soft-white: #f8fafc;
        --warm-white: #fefefe;
        --light-gray: #f1f5f9;

        /* Couleurs NVIDIA */
        --nvidia-green: #76B900; /* Vert signature */
        --nvidia-dark-green: #588A00;
        --accent-dark: #1e293b; /* Bleu très foncé pour le contraste */
        --dark-bg: #0f172a; /* Fond très sombre */
        --dark-card-bg: #1e293b; /* Fond des cartes sombres */
        --text-light: #e2e8f0; /* Texte clair */
        --text-mid: #94a3b8; /* Texte gris */
        --border-dark: rgba(255, 255, 255, 0.1); /* Bordure légère */

        --error: #ef4444;
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
        /* Fond technologique sombre */
        background: linear-gradient(135deg, var(--dark-bg) 0%, #020617 100%);
        color: var(--text-light);
        min-height: 100vh;
        overflow-x: hidden;
    }
    
    .container {
        max-width: 430px;
        margin: 0 auto;
        background: transparent;
    }
    
    /* Arrière-plan géométrique / techno */
    .background {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle at 10% 20%, rgba(118, 185, 0, 0.08) 0%, transparent 20%),
            radial-gradient(circle at 90% 80%, rgba(30, 41, 59, 0.2) 0%, transparent 20%);
        z-index: -3;
    }
    
    .geometric-pattern {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: 
            repeating-linear-gradient(0deg, var(--dark-bg), var(--dark-bg) 1px, transparent 1px, transparent 100px),
            repeating-linear-gradient(90deg, var(--dark-bg), var(--dark-bg) 1px, var(--border-dark) 1px, var(--border-dark) 100px);
        background-size: 100px 100px;
        opacity: 0.1;
        z-index: -2;
        animation: patternShift 60s linear infinite;
    }
    
    .green-accent {
        position: fixed;
        top: 0;
        right: 0;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(118, 185, 0, 0.2) 0%, transparent 70%);
        filter: blur(80px);
        z-index: -1;
    }
    
    .dark-accent {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(30, 41, 59, 0.3) 0%, transparent 70%);
        filter: blur(80px);
        z-index: -1;
    }
    
    /* En-tête avec dégradé vert-sombre */
    .header {
        height: 220px;
        background: linear-gradient(135deg, var(--nvidia-dark-green), var(--accent-dark));
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 25px 20px 70px 20px;
        animation: headerSlide 1s ease-out;
        border-radius: 0 0 20px 20px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5);
    }
    
    @keyframes headerSlide {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .header-content {
        text-align: center;
        z-index: 2;
    }
    
    .header h1 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--primary-white);
        text-shadow: 0 2px 10px rgba(0,0,0,0.5);
    }
    
    .header p {
        font-size: 15px;
        color: rgba(255, 255, 255, 0.8);
        animation: fadeInUp 1s ease-out 0.3s both;
    }
    
    /* Navigation des menus */
    .menu-navigation {
        position: absolute;
        bottom: -25px;
        left: 20px;
        right: 20px;
        display: flex;
        background: var(--dark-card-bg);
        border-radius: 15px;
        padding: 8px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        border: 2px solid var(--nvidia-green);
        animation: menuSlideUp 0.8s ease-out 0.5s both;
    }
    
    @keyframes menuSlideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .menu-btn {
        flex: 1;
        padding: 12px 10px;
        text-align: center;
        cursor: pointer;
        border-radius: 10px;
        font-weight: 600;
        transition: var(--transition);
        color: var(--text-mid);
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        position: relative;
        overflow: hidden;
    }
    
    .menu-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(118, 185, 0, 0.1), transparent);
        transition: left 0.6s ease;
    }
    
    .menu-btn:hover::before {
        left: 100%;
    }
    
    .menu-btn.active {
        background: linear-gradient(135deg, var(--nvidia-green), var(--nvidia-dark-green));
        color: var(--primary-white);
        transform: scale(1.05);
        box-shadow: 0 4px 15px rgba(118, 185, 0, 0.4);
    }
    
    /* Contenu principal */
    .main-content {
        padding: 60px 20px 30px 20px;
        animation: contentFadeIn 1s ease-out 0.7s both;
    }
    
    @keyframes contentFadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    
    /* Cartes d'investissement avec animations */
    .plans-container {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    
    .plan-card {
        background: var(--dark-card-bg);
        border-radius: 16px;
        padding: 20px;
        border: 1px solid rgba(118, 185, 0, 0.2);
        position: relative;
        overflow: hidden;
        opacity: 0;
        transform: translateY(20px);
        animation: cardSlideUp 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
        transition: var(--transition);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    
    /* Animation dynamique pour tous les plans */
    .plan-card:nth-child(1) { animation-delay: 0.8s; }
    .plan-card:nth-child(2) { animation-delay: 0.9s; }
    .plan-card:nth-child(3) { animation-delay: 1.0s; }
    .plan-card:nth-child(4) { animation-delay: 1.1s; }
    .plan-card:nth-child(5) { animation-delay: 1.2s; }
    .plan-card:nth-child(6) { animation-delay: 1.3s; }
    .plan-card:nth-child(7) { animation-delay: 1.4s; }
    .plan-card:nth-child(8) { animation-delay: 1.5s; }
    .plan-card:nth-child(9) { animation-delay: 1.6s; }
    .plan-card:nth-child(10) { animation-delay: 1.7s; }
    
    @keyframes cardSlideUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .plan-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--nvidia-green), var(--nvidia-dark-green), var(--nvidia-green));
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.6s ease;
    }
    
    .plan-card:hover {
        border-color: var(--nvidia-green);
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 15px 35px rgba(118, 185, 0, 0.2);
    }
    
    .plan-card:hover::before {
        transform: scaleX(1);
    }
    
    /* RENDRE LE 11ÈME CADRE INVISIBLE */
    .plan-card:nth-child(11) {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        height: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        border: none !important;
        overflow: hidden !important;
    }
    
    /* Contenu principal de la carte */
    .plan-content {
        display: flex;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 16px;
    }
    
    /* Zone de texte */
    .plan-info {
        flex: 1;
    }
    
    .plan-nom {
        font-size: 20px;
        font-weight: 700;
        color: var(--nvidia-green);
        margin-bottom: 12px;
        position: relative;
        display: inline-block;
    }
    
    .plan-nom::after {
        content: '';
        position: absolute;
        bottom: -4px;
        left: 0;
        width: 0;
        height: 2px;
        background: var(--nvidia-green);
        transition: width 0.4s ease;
    }
    
    .plan-card:hover .plan-nom::after {
        width: 100%;
    }
    
    .plan-details {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
        padding: 4px 0;
        transition: transform 0.3s ease;
    }
    
    .plan-card:hover .detail-item {
        transform: translateX(5px);
    }
    
    .detail-item:nth-child(1) { transition-delay: 0.1s; }
    .detail-item:nth-child(2) { transition-delay: 0.2s; }
    .detail-item:nth-child(3) { transition-delay: 0.3s; }
    
    .detail-label {
        color: var(--text-mid);
    }
    
    .detail-value {
        color: var(--text-light);
        font-weight: 600;
    }
    
    /* Zone d'image */
    .plan-visual {
        width: 90px;
        height: 90px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    
    .plan-image {
        width: 100%;
        height: 100%;
        border-radius: 12px;
        border: 3px solid var(--nvidia-green);
        background: var(--dark-bg);
        overflow: hidden;
        position: relative;
        transition: var(--transition);
        box-shadow: 0 4px 15px rgba(118, 185, 0, 0.2);
    }
    
    .plan-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s ease;
    }
    
    .plan-card:hover .plan-image {
        transform: rotate(5deg) scale(1.1);
        box-shadow: 0 8px 25px rgba(118, 185, 0, 0.3);
    }
    
    .plan-card:hover .plan-image img {
        transform: scale(1.1);
    }
    
    /* Barre d'action */
    .action-bar {
        background: rgba(118, 185, 0, 0.05); /* Arrière-plan subtil */
        border-radius: 12px;
        padding: 15px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid rgba(118, 185, 0, 0.2);
        position: relative;
        overflow: hidden;
        transition: var(--transition);
    }
    
    .plan-card:hover .action-bar {
        background: rgba(118, 185, 0, 0.1);
        border-color: rgba(118, 185, 0, 0.4);
    }
    
    .plan-prix {
        font-size: 22px;
        font-weight: 700;
        color: var(--nvidia-green);
        position: relative;
        z-index: 2;
    }
    
    .btn-investir {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        background: linear-gradient(135deg, var(--nvidia-green), var(--nvidia-dark-green));
        color: var(--primary-white);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
        z-index: 2;
        box-shadow: 0 4px 15px rgba(118, 185, 0, 0.3);
    }
    
    .btn-investir::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        transition: left 0.6s ease;
    }
    
    .btn-investir:hover {
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 6px 20px rgba(118, 185, 0, 0.5);
    }
    
    .btn-investir:hover::before {
        left: 100%;
    }
    
    /* Section Action Flex verrouillée */
    .locked-section {
        text-align: center;
        padding: 80px 30px;
        background: var(--dark-card-bg);
        border-radius: 20px;
        border: 3px dashed rgba(118, 185, 0, 0.4);
        margin-top: 20px;
        animation: pulseGlow 2s ease-in-out infinite;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    
    @keyframes pulseGlow {
        0%, 100% {
            border-color: rgba(118, 185, 0, 0.4);
            box-shadow: 0 0 20px rgba(118, 185, 0, 0.1);
        }
        50% {
            border-color: rgba(118, 185, 0, 0.6);
            box-shadow: 0 0 30px rgba(118, 185, 0, 0.2);
        }
    }
    
    .locked-section::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: conic-gradient(transparent, rgba(118, 185, 0, 0.1), transparent 30%);
        animation: rotate 4s linear infinite;
    }
    
    @keyframes rotate {
        100% {
            transform: rotate(360deg);
        }
    }
    
    .locked-icon {
        font-size: 64px;
        color: var(--nvidia-green);
        margin-bottom: 20px;
        position: relative;
        z-index: 2;
        animation: lockBounce 2s ease-in-out infinite;
    }
    
    @keyframes lockBounce {
        0%, 100% {
            transform: translateY(0) scale(1);
        }
        50% {
            transform: translateY(-10px) scale(1.1);
        }
    }
    
    .locked-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--nvidia-green);
        margin-bottom: 15px;
        position: relative;
        z-index: 2;
    }
    
    .locked-message {
        color: var(--text-mid);
        font-size: 16px;
        line-height: 1.6;
        position: relative;
        z-index: 2;
    }
    
    /* Popup avec animation */
    .popup {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.9);
        backdrop-filter: blur(10px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .popup-content {
        background: var(--dark-card-bg);
        width: 100%;
        max-width: 380px;
        border-radius: 20px;
        overflow: hidden;
        border: 3px solid var(--nvidia-green);
        animation: popupScale 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.6);
    }
    
    @keyframes popupScale {
        from {
            opacity: 0;
            transform: scale(0.8) rotateX(-10deg);
        }
        to {
            opacity: 1;
            transform: scale(1) rotateX(0);
        }
    }
    
    .popup-header {
        padding: 25px;
        background: linear-gradient(135deg, var(--nvidia-green), var(--nvidia-dark-green));
        color: var(--primary-white);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .popup-header h3 {
        font-weight: 700;
        font-size: 20px;
    }
    
    .popup-close {
        background: rgba(255,255,255,0.2);
        border: none;
        color: var(--primary-white);
        font-size: 28px;
        cursor: pointer;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: var(--transition);
    }
    
    .popup-close:hover {
        background: rgba(255,255,255,0.3);
        transform: rotate(90deg);
    }
    
    .popup-body {
        padding: 30px 25px;
    }
    
    .popup-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .popup-detail {
        background: rgba(118, 185, 0, 0.05);
        padding: 15px;
        border-radius: 10px;
        text-align: center;
        border: 1px solid rgba(118, 185, 0, 0.1);
        transition: transform 0.3s ease;
    }
    
    .popup-detail:hover {
        transform: translateY(-3px);
        background: rgba(118, 185, 0, 0.08);
    }
    
    .popup-detail-valeur {
        font-size: 18px;
        font-weight: 700;
        color: var(--nvidia-green);
        margin-bottom: 5px;
    }
    
    .popup-detail-label {
        color: var(--text-mid);
        font-size: 13px;
    }
    
    .popup-btn {
        width: 100%;
        padding: 18px;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        background: linear-gradient(135deg, var(--nvidia-green), var(--nvidia-dark-green));
        color: var(--primary-white);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
        box-shadow: 0 6px 20px rgba(118, 185, 0, 0.4);
    }
    
    .popup-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(118, 185, 0, 0.6);
    }
    
    .popup-btn:active {
        transform: translateY(-1px);
    }
    
    /* Messages d'alerte */
    .alert-message {
        padding: 15px 20px;
        border-radius: 10px;
        margin: 20px 0;
        font-size: 14px;
        text-align: center;
        display: none;
        animation: slideInDown 0.5s ease;
    }
    
    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: var(--error);
    }
    
    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: var(--success);
    }
    
    /* FLOATER POUR SOLDE INSUFFISANT */
    .floater-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(15px);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: floaterFadeIn 0.4s ease;
    }
    
    @keyframes floaterFadeIn {
        from {
            opacity: 0;
            backdrop-filter: blur(0px);
        }
        to {
            opacity: 1;
            backdrop-filter: blur(15px);
        }
    }
    
    .floater-content {
        background: linear-gradient(135deg, var(--dark-card-bg), var(--dark-bg));
        width: 100%;
        max-width: 350px;
        border-radius: 25px;
        padding: 40px 30px;
        text-align: center;
        border: 3px solid var(--nvidia-green);
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
        animation: floaterScale 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        position: relative;
        overflow: hidden;
    }
    
    @keyframes floaterScale {
        from {
            opacity: 0;
            transform: scale(0.7) translateY(50px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    
    .floater-content::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: conic-gradient(transparent, rgba(118, 185, 0, 0.1), transparent 30%);
        animation: rotate 6s linear infinite;
    }
    
    .floater-icon {
        font-size: 80px;
        color: var(--nvidia-green);
        margin-bottom: 25px;
        position: relative;
        z-index: 2;
        animation: iconPulse 2s ease-in-out infinite;
    }
    
    @keyframes iconPulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
    }
    
    .floater-title {
        font-size: 26px;
        font-weight: 700;
        color: var(--nvidia-green);
        margin-bottom: 15px;
        position: relative;
        z-index: 2;
    }
    
    .floater-message {
        color: var(--text-light);
        font-size: 16px;
        line-height: 1.6;
        margin-bottom: 30px;
        position: relative;
        z-index: 2;
    }
    
    .floater-buttons {
        display: flex;
        gap: 15px;
        position: relative;
        z-index: 2;
    }
    
    .floater-btn {
        flex: 1;
        padding: 16px 20px;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }
    
    .floater-btn-primary {
        background: linear-gradient(135deg, var(--nvidia-green), var(--nvidia-dark-green));
        color: var(--primary-white);
        box-shadow: 0 6px 20px rgba(118, 185, 0, 0.4);
    }
    
    .floater-btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-light);
        border: 2px solid rgba(255, 255, 255, 0.2);
    }
    
    .floater-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(118, 185, 0, 0.5);
    }
    
    .floater-btn:active {
        transform: translateY(-1px);
    }
    
    /* Contenu des menus */
    .contenu-menu {
        display: none;
    }
    
    .contenu-menu.active {
        display: block;
    }
    
    /* Animations de fond */
    @keyframes patternShift {
        0% {
            background-position: 0 0, 0 0, 50px 87.5px, 50px 87.5px, 0 0, 50px 87.5px;
        }
        100% {
            background-position: 100px 175px, 100px 175px, 150px 262.5px, 150px 262.5px, 100px 175px, 150px 262.5px;
        }
    }
    
    /* Responsive */
    @media (max-width: 480px) {
        .header {
            height: 200px;
            padding: 20px 15px 60px 15px;
        }
        
        .header h1 {
            font-size: 24px;
        }
        
        .plan-content {
            gap: 15px;
        }
        
        .plan-visual {
            width: 80px;
            height: 80px;
        }
        
        .plan-nom {
            font-size: 18px;
        }
        
        .detail-item {
            font-size: 12px;
        }
        
        .floater-content {
            padding: 30px 20px;
        }
        
        .floater-icon {
            font-size: 60px;
        }
        
        .floater-title {
            font-size: 22px;
        }
        
        .floater-buttons {
            flex-direction: column;
        }
    }
    </style>
</head>
<body>
    <div class="background"></div>
    <div class="geometric-pattern"></div>
    <div class="green-accent"></div>
    <div class="dark-accent"></div>
    
    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1>NVIDIA Technologie</h1>
                <p>Développez votre patrimoine avec des actions de pointe</p>
            </div>
            
            <div class="menu-navigation">
                <div class="menu-btn vip <?= $menu_actif === 'vip' ? 'active' : '' ?>" data-menu="vip">
                    <i class="fas fa-crown"></i>
                    <span>Actions VIP</span>
                </div>
                <div class="menu-btn commande <?= $menu_actif === 'commande' ? 'active' : '' ?>" data-menu="commande">
                    <i class="fas fa-shopping-bag"></i>
                    <span>Mes Commandes</span>
                </div>
            </div>
        </div>
        
        <div class="main-content">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert-message alert-error" style="display: block;">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert-message alert-success" style="display: block;">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <div id="alertMessage" class="alert-message"></div>
            
            <div class="contenu-menu <?= $menu_actif === 'vip' ? 'active' : '' ?>" id="vip-content">
                <div class="plans-container">
                    <?php if (count($plans_corriges) > 0): ?>
                        <?php foreach ($plans_corriges as $index => $plan): ?>
                        <div class="plan-card" style="animation-delay: <?= 0.8 + ($index * 0.1) ?>s;">
                            <div class="plan-content">
                                <div class="plan-info">
                                    <div class="plan-nom"><?= htmlspecialchars($plan['nom']) ?></div>
                                    <div class="plan-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Revenu quotidien:</span>
                                            <span class="detail-value">+<?= number_format($plan['revenu_journalier'], 0) ?> FCFA</span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Jours de revenu:</span>
                                            <span class="detail-value"><?= $plan['duree_jours'] ?> jours</span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Revenu total:</span>
                                            <span class="detail-value"><?= number_format($plan['revenu_total'], 0) ?> FCFA</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="plan-visual">
                                    <div class="plan-image">
                                        <img src="assets/vip.jpg" alt="<?= htmlspecialchars($plan['nom']) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="action-bar">
                                <div class="plan-prix"><?= number_format($plan['prix'], 0) ?> FCFA</div>
                                <button class="btn-investir" 
                                        data-plan-id="<?= $plan['id'] ?>" 
                                        data-nom="<?= htmlspecialchars($plan['nom']) ?>" 
                                        data-prix="<?= $plan['prix'] ?>" 
                                        data-gain-journalier="<?= $plan['revenu_journalier'] ?>" 
                                        data-duree="<?= $plan['duree_jours'] ?>" 
                                        data-gain-total="<?= $plan['revenu_total'] ?>">
                                    Investir
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="locked-section">
                            <div class="locked-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="locked-title">Aucun Plan Disponible</div>
                            <div class="locked-message">
                                Les plans d'investissement ne sont pas encore configurés.<br>
                                Contactez l'administrateur.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="contenu-menu <?= $menu_actif === 'commande' ? 'active' : '' ?>" id="commande-content">
                <div class="locked-section">
                    <div class="locked-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="locked-title">Redirection en cours</div>
                    <div class="locked-message">
                        Veuillez patienter, vous serez redirigé vers la page de vos commandes.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="popup" id="investPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h3 id="popup-titre">Confirmer l'investissement</h3>
                <button class="popup-close" onclick="fermerPopup()">&times;</button>
            </div>
            <div class="popup-body">
                <div class="popup-details">
                    <div class="popup-detail">
                        <div class="popup-detail-valeur" id="popup-prix">0 FCFA</div>
                        <div class="popup-detail-label">Prix d'achat</div>
                    </div>
                    <div class="popup-detail">
                        <div class="popup-detail-valeur" id="popup-gain-journalier">0 FCFA</div>
                        <div class="popup-detail-label">Gain quotidien</div>
                    </div>
                    <div class="popup-detail">
                        <div class="popup-detail-valeur" id="popup-duree">0 jours</div>
                        <div class="popup-detail-label">Durée totale</div>
                    </div>
                    <div class="popup-detail">
                        <div class="popup-detail-valeur" id="popup-gain-total">0 FCFA</div>
                        <div class="popup-detail-label">Gain total</div>
                    </div>
                </div>
                
                <button class="popup-btn" id="btn-confirmer-investissement">
                    <i class="fas fa-check-circle"></i> Confirmer l'investissement
                </button>
            </div>
        </div>
    </div>

    <div class="floater-overlay" id="insufficientBalanceFloater">
        <div class="floater-content">
            <div class="floater-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="floater-title">Solde Insuffisant</div>
            <div class="floater-message">
                Votre solde actuel est insuffisant pour cet investissement.<br>
                Rechargez votre compte pour continuer.
            </div>
            <div class="floater-buttons">
                <button class="floater-btn floater-btn-primary" onclick="redirectToDepot()">
                    <i class="fas fa-arrow-right"></i> Recharger
                </button>
                <button class="floater-btn floater-btn-secondary" onclick="closeFloater()">
                    <i class="fas fa-times"></i> Annuler
                </button>
            </div>
        </div>
    </div>

    <script>
    // Changement de menus
    document.querySelectorAll('.menu-btn').forEach(menu => {
        menu.addEventListener('click', function() {
            const menuCible = this.dataset.menu;
            
            // Logique de REDIRECTION pour le menu 'commande'
            if (menuCible === 'commande') {
                window.location.href = 'commande.php';
                return; // Arrêter l'exécution pour que la redirection soit immédiate.
            }

            // Animation de transition des menus
            document.querySelectorAll('.menu-btn').forEach(m => {
                m.classList.remove('active');
                m.style.transform = 'scale(1)';
            });
            this.classList.add('active');
            this.style.transform = 'scale(1.05)';
            
            // Afficher le contenu du menu sélectionné (pour 'vip' uniquement)
            document.querySelectorAll('.contenu-menu').forEach(contenu => {
                contenu.classList.remove('active');
            });
            document.getElementById(menuCible + '-content').classList.add('active');
            
            // Mettre à jour l'URL (uniquement pour 'vip', car 'commande' redirige)
            const url = new URL(window.location);
            url.searchParams.set('menu', menuCible);
            window.history.replaceState({}, '', url);
        });
    });
    
    // Gestion des investissements
    let planActuel = null;
    
    document.querySelectorAll('.btn-investir').forEach(bouton => {
        bouton.addEventListener('click', function() {
            planActuel = {
                id: this.dataset.planId,
                nom: this.dataset.nom,
                prix: parseFloat(this.dataset.prix),
                gainJournalier: parseFloat(this.dataset.gainJournalier),
                duree: parseInt(this.dataset.duree),
                gainTotal: parseFloat(this.dataset.gainTotal)
            };
            
            // Mettre à jour le popup
            document.getElementById('popup-titre').textContent = planActuel.nom;
            document.getElementById('popup-prix').textContent = numberFormat(planActuel.prix) + ' FCFA';
            document.getElementById('popup-gain-journalier').textContent = '+' + numberFormat(planActuel.gainJournalier) + ' FCFA/jour';
            document.getElementById('popup-duree').textContent = planActuel.duree + ' jours';
            document.getElementById('popup-gain-total').textContent = numberFormat(planActuel.gainTotal) + ' FCFA';
            
            // Afficher le popup
            document.getElementById('investPopup').style.display = 'flex';
        });
    });
    
    // Confirmation d'investissement
    document.getElementById('btn-confirmer-investissement').addEventListener('click', function() {
        // IMPORTANT: La variable PHP $solde est convertie en JavaScript ici.
        if (planActuel && <?= $solde ?> >= planActuel.prix) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'acheter_action.php';
            
            const planIdField = document.createElement('input');
            planIdField.type = 'hidden';
            planIdField.name = 'plan_id';
            planIdField.value = planActuel.id;
            form.appendChild(planIdField);
            
            const prixField = document.createElement('input');
            prixField.type = 'hidden';
            prixField.name = 'prix';
            prixField.value = planActuel.prix;
            form.appendChild(prixField);
            
            document.body.appendChild(form);
            form.submit();
        } else {
            // Afficher le floater au lieu du message simple
            fermerPopup();
            showInsufficientBalanceFloater();
        }
    });
    
    function fermerPopup() {
        document.getElementById('investPopup').style.display = 'none';
    }
    
    // Gestion du floater solde insuffisant
    function showInsufficientBalanceFloater() {
        document.getElementById('insufficientBalanceFloater').style.display = 'flex';
    }
    
    function closeFloater() {
        document.getElementById('insufficientBalanceFloater').style.display = 'none';
    }
    
    function redirectToDepot() {
        window.location.href = 'depot.php';
    }
    
    // Formatage des nombres
    function numberFormat(number) {
        return Number(number).toLocaleString('fr-FR');
    }
    
    // Afficher une alerte
    function showAlert(message, type) {
        const alert = document.getElementById('alertMessage');
        alert.textContent = message;
        alert.className = 'alert-message alert-' + type;
        alert.style.display = 'block';
        
        setTimeout(() => {
            alert.style.display = 'none';
        }, 5000);
    }
    
    // Fermer le popup en cliquant à l'extérieur
    document.getElementById('investPopup').addEventListener('click', function(event) {
        if (event.target === this) {
            fermerPopup();
        }
    });
    
    // Fermer le floater en cliquant à l'extérieur
    document.getElementById('insufficientBalanceFloater').addEventListener('click', function(event) {
        if (event.target === this) {
            closeFloater();
        }
    });
    
    // Initialisation au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Page investissement chargée - Boutons fonctionnels');
        
        // Simuler le clic sur 'commande' si c'est le menu actif au chargement
        const menuActif = "<?= $menu_actif ?>";
        if (menuActif === 'commande') {
            // Optionnel: Déclencher manuellement la logique de redirection
            // Cela ne sera pas fait ici pour éviter une redirection double si PHP le gère aussi.
            // On laisse le JS se concentrer sur les clics.
        }
    });
    </script>
</body>
</html>