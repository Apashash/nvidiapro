<?php
session_start();
require_once 'db.php';
include 'menu.php';
include 'verify.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupération des données utilisateur
$user = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$user->execute([$user_id]);
$user = $user->fetch();

// Récupération du solde normal
$solde = $db->prepare("SELECT solde FROM soldes WHERE user_id = ?");
$solde->execute([$user_id]);
$solde = $solde->fetch();

// Récupération du solde en pièces
$pieces = $db->prepare("SELECT solde FROM pieces WHERE user_id = ?");
$pieces->execute([$user_id]);
$pieces = $pieces->fetch();

// Récupération des revenus totaux
$revenus = $db->prepare("SELECT SUM(montant) as total FROM historique_revenus WHERE user_id = ?");
$revenus->execute([$user_id]);
$revenus = $revenus->fetch();

// Récupération du niveau VIP
$vip = $db->prepare("SELECT * FROM vip WHERE user_id = ?");
$vip->execute([$user_id]);
$vip = $vip->fetch();

// Récupération des transactions
$transactions = $db->prepare("
    (SELECT 'depot' as type, montant, date_depot as date, statut FROM depots WHERE user_id = ?)
    UNION ALL
    (SELECT 'retrait' as type, montant, date_demande as date, statut FROM retraits WHERE user_id = ?)
    UNION ALL
    (SELECT 'revenu' as type, montant, date_paiement as date, 'valide' as statut FROM historique_revenus WHERE user_id = ?)
    ORDER BY date DESC LIMIT 20
");
$transactions->execute([$user_id, $user_id, $user_id]);
$transactions = $transactions->fetchAll();

// Vérification si l'utilisateur a un mot de passe de transaction
$has_transaction_password = false;
$transaction_password_query = $db->prepare("SELECT * FROM transaction_passwords WHERE user_id = ?");
$transaction_password_query->execute([$user_id]);
if ($transaction_password_query->rowCount() > 0) {
    $has_transaction_password = true;
}

// Commandes actives
$commandes = $db->prepare("SELECT * FROM commandes WHERE user_id = ? AND date_fin >= CURDATE()");
$commandes->execute([$user_id]);
$commandes = $commandes->fetchAll();
$has_active_command = count($commandes) > 0;

// Vérification des paiements pour chaque commande
$can_collect = false;
$next_payment_info = "Aucun paiement prévu";
$earliest_payment_time = null;

if ($has_active_command) {
    foreach ($commandes as $commande) {
        $order_creation_time = strtotime($commande['date_creation']);
        $current_time = time();
        $hours_since_creation = ($current_time - $order_creation_time) / 3600;
        
        if ($hours_since_creation < 24) {
            $next_payment_time = $order_creation_time + 24 * 3600;
            if (!$earliest_payment_time || $next_payment_time < $earliest_payment_time) {
                $earliest_payment_time = $next_payment_time;
                $next_payment_info = "Prochain paiement: " . date('d/m H:i', $next_payment_time);
            }
            continue;
        }
        
        $last_payment = $db->prepare("
            SELECT MAX(date_paiement) as last_payment 
            FROM historique_revenus 
            WHERE user_id = ? AND commande_id = ? AND type = 'paiement_journalier'
        ");
        $last_payment->execute([$user_id, $commande['id']]);
        $last_payment = $last_payment->fetch();
        
        if ($last_payment['last_payment']) {
            $last_payment_time = strtotime($last_payment['last_payment']);
            $diff_hours = ($current_time - $last_payment_time) / 3600;
            
            if ($diff_hours >= 24) {
                $can_collect = true;
                break;
            } else {
                $next_payment_time = $last_payment_time + 24 * 3600;
                if (!$earliest_payment_time || $next_payment_time < $earliest_payment_time) {
                    $earliest_payment_time = $next_payment_time;
                    $next_payment_info = "Prochain paiement: " . date('d/m H:i', $next_payment_time);
                }
            }
        } else {
            $can_collect = true;
            break;
        }
    }
}

// Utilisation du code de parrainage comme ID utilisateur
$custom_id = $user['code_parrainage'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte - NVIDIA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary-black: #0a0a0a; /* Fond principal */
        --soft-black: #121212; /* Fond du corps */
        --dark-gray: #1f2937; /* Couleur secondaire foncée */
        --accent-green-primary: #76b900; /* NVIDIA Green */
        --accent-green-secondary: #00e676; /* Neon Green pour les gradients/succès */
        --text-light: #ffffff; /* Couleur de texte principale */
        --text-muted: #94a3b8; /* Couleur de texte secondaire */
        --card-bg: rgba(18, 18, 18, 0.85); /* Fond de carte foncé */
        --border-color: rgba(118, 185, 0, 0.15); /* Bordure subtile verte */
        --error: #ef4444;
        --success: var(--accent-green-secondary);
        --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
    }
    
    body {
        background: var(--soft-black);
        color: var(--text-light);
        min-height: 100vh;
        overflow-x: hidden;
    }
    
    .container {
        max-width: 430px;
        margin: 0 auto;
        background: transparent;
    }
    
    /* Arrière-plan géométrique: Thème noir/vert */
    .background {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: var(--soft-black);
        z-index: -3;
    }
    
    .geometric-pattern {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        /* Motif géométrique avec l'accent vert */
        background-image: 
            linear-gradient(30deg, rgba(118, 185, 0, 0.08) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.08) 87.5%, rgba(118, 185, 0, 0.08) 0),
            linear-gradient(150deg, rgba(0, 230, 118, 0.08) 12%, transparent 12.5%, transparent 87%, rgba(0, 230, 118, 0.08) 87.5%, rgba(0, 230, 118, 0.08) 0),
            linear-gradient(60deg, rgba(118, 185, 0, 0.1) 25%, transparent 25.5%, transparent 75%, rgba(118, 185, 0, 0.1) 75%, rgba(118, 185, 0, 0.1) 0);
        background-size: 100px 175px;
        background-position: 0 0, 50px 87.5px, 0 0;
        z-index: -2;
        animation: patternShift 30s linear infinite;
        opacity: 0.3;
    }
    
    .blue-accent {
        position: fixed;
        top: 0;
        right: 0;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(118, 185, 0, 0.2) 0%, transparent 70%);
        filter: blur(80px);
        z-index: -1;
    }
    
    .purple-accent {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(0, 230, 118, 0.2) 0%, transparent 70%);
        filter: blur(80px);
        z-index: -1;
    }
    
    /* En-tête avec image de fond */
    .header {
        height: 220px;
        /* Conserver l'image de fond et ajouter un fond noir pour le contraste */
        background: url('assets/head.jpg') center/cover, var(--primary-black); 
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 25px 20px 70px 20px;
        animation: headerSlide 1s ease-out;
        border-radius: 0 0 20px 20px;
        box-shadow: 0 8px 25px rgba(118, 185, 0, 0.4); /* Ombre verte */
    }
    
    .header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7); /* Overlay plus foncé */
        border-radius: 0 0 20px 20px;
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
        position: relative;
    }
    
    .header h1 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--text-light);
        text-shadow: 0 2px 10px rgba(0,0,0,0.5);
    }
    
    .header p {
        font-size: 15px;
        color: rgba(255, 255, 255, 0.9);
        animation: fadeInUp 1s ease-out 0.3s both;
    }
    
    /* Logo en haut à gauche */
    .header-logo {
        position: absolute;
        top: 20px;
        left: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        z-index: 2;
    }
    
    .logo-icon {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, var(--accent-green-primary), var(--accent-green-secondary));
        border: none;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-light);
        font-weight: bold;
        font-size: 18px;
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
    
    .logo-text {
        font-family: 'Playfair Display', serif;
        font-weight: 700;
        font-size: 22px;
        background: linear-gradient(135deg, var(--accent-green-primary), var(--accent-green-secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        letter-spacing: -0.5px;
    }
    
    .logo-subtext {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.9);
        margin-top: -2px;
        letter-spacing: 2px;
        font-weight: 500;
    }
    
    /* Bouton déconnexion en haut à droite */
    .logout-btn {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(0, 0, 0, 0.6); /* Fond sombre */
        border: none;
        border-radius: 12px;
        padding: 8px 15px;
        font-weight: 600;
        font-size: 13px;
        color: var(--text-light);
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        z-index: 2;
    }
    
    .logout-btn:hover {
        background: rgba(0, 0, 0, 0.8);
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.7);
    }
    
    /* Navigation VIP */
    .vip-navigation {
        position: absolute;
        bottom: -25px;
        left: 20px;
        right: 20px;
        display: flex;
        background: var(--card-bg);
        border-radius: 15px;
        padding: 15px;
        box-shadow: 0 8px 25px rgba(118, 185, 0, 0.3); /* Ombre verte */
        border: 2px solid var(--accent-green-primary);
        animation: menuSlideUp 0.8s ease-out 0.5s both;
        align-items: center;
        justify-content: space-between;
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
    
    .vip-level {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .vip-number {
        font-size: 32px;
        font-weight: 900;
        background: linear-gradient(135deg, var(--accent-green-primary), var(--accent-green-secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        line-height: 1;
    }
    
    .vip-label {
        font-size: 14px;
        color: var(--text-muted);
        font-weight: 600;
        margin-top: 2px;
    }
    
    .vip-progress {
        flex: 1;
        margin-left: 20px;
    }
    
    .progress-container {
        width: 100%;
        background: rgba(118, 185, 0, 0.1);
        border-radius: 10px;
        height: 12px;
        overflow: hidden;
        margin-bottom: 8px;
    }
    
    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--accent-green-primary), var(--accent-green-secondary));
        border-radius: 10px;
        transition: width 0.8s ease;
        position: relative;
        overflow: hidden;
    }
    
    .progress-bar::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        animation: shimmer 2s infinite;
    }
    
    .progress-text {
        font-size: 12px;
        color: var(--text-muted);
        font-weight: 600;
        text-align: right;
    }
    
    /* Section soldes */
    .balance-section {
        display: flex;
        gap: 12px;
        margin: 60px 20px 20px 20px;
        animation: fadeInUp 1s ease-out;
    }
    
    .balance-card {
        flex: 1;
        background: var(--card-bg);
        border-radius: 16px;
        padding: 18px;
        box-shadow: 0 8px 20px rgba(118, 185, 0, 0.15);
        border: 1px solid var(--border-color);
        text-align: center;
        transition: var(--transition);
    }
    
    .balance-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 25px rgba(118, 185, 0, 0.3);
    }
    
    .balance-title {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 8px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    
    .balance-amount {
        font-size: 22px;
        font-weight: 900;
        color: var(--text-light);
        letter-spacing: 0.5px;
    }
    
    /* Boutons d'action principaux */
    .action-buttons {
        display: flex;
        gap: 12px;
        margin: 0 20px 15px 20px;
        animation: fadeInUp 1.2s ease-out;
    }
    
    .action-button {
        flex: 1;
        background: linear-gradient(135deg, var(--accent-green-primary), var(--accent-green-secondary));
        color: var(--text-light);
        border: none;
        border-radius: 14px;
        padding: 16px 12px;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: var(--transition);
        box-shadow: 0 6px 20px rgba(118, 185, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .action-button:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(118, 185, 0, 0.6);
    }
    
    .action-button:active {
        transform: translateY(-1px);
    }
    
    .action-button.secondary {
        /* Garder le vert pour l'uniformité du thème */
        background: linear-gradient(135deg, var(--accent-green-secondary), var(--accent-green-primary));
        box-shadow: 0 6px 20px rgba(0, 230, 118, 0.4);
    }
    
    .payment-info {
        text-align: center;
        margin: 0 20px 20px 20px;
        font-size: 14px;
        color: var(--text-muted);
        animation: fadeInUp 1.3s ease-out;
        display: none;
    }
    
    .payment-info.show {
        display: block;
    }
    
    /* Menu vertical */
    .menu-section {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 0;
        margin: 0 20px 20px 20px;
        box-shadow: 0 8px 20px rgba(118, 185, 0, 0.15);
        border: 1px solid var(--border-color);
        overflow: hidden;
        animation: fadeInUp 1.4s ease-out;
    }
    
    .menu-item {
        display: flex;
        align-items: center;
        padding: 18px 20px;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        color: var(--text-light);
    }
    
    .menu-item:last-child {
        border-bottom: none;
    }
    
    .menu-item:hover {
        background: rgba(118, 185, 0, 0.1);
        padding-left: 25px;
    }
    
    .menu-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: rgba(118, 185, 0, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        color: var(--accent-green-primary);
        font-size: 18px;
    }
    
    .menu-text {
        flex: 1;
        font-weight: 600;
        font-size: 15px;
    }
    
    .menu-arrow {
        color: var(--text-muted);
        font-size: 14px;
    }
    
    /* Styles pour la section de transactions dans le popup */
    .transactions-container {
        max-height: 50vh; /* Ajusté pour le popup */
        overflow-y: auto;
    }
    
    .transaction-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .transaction-item:last-child {
        border-bottom: none;
    }
    
    .transaction-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .transaction-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        color: white;
    }
    
    .transaction-details {
        display: flex;
        flex-direction: column;
    }
    
    .transaction-type {
        font-weight: 600;
        font-size: 14px;
        color: var(--text-light);
    }
    
    .transaction-date {
        font-size: 12px;
        color: var(--text-muted);
    }
    
    .transaction-amount {
        font-weight: 700;
        font-size: 15px;
    }
    
    .positive {
        color: var(--success); /* Neon Green */
    }
    
    .negative {
        color: var(--error);
    }
    
    .transaction-status {
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 8px;
        font-weight: 600;
    }
    
    /* Couleurs de statut adaptées au thème foncé */
    .status-en_attente {
        background: #3c3000;
        color: #ffc107;
    }
    
    .status-valide {
        background: #0d301b;
        color: #00e676;
    }
    
    .status-rejete {
        background: #58151c;
        color: #ef4444;
    }
    
    .empty-transactions {
        text-align: center;
        padding: 30px;
        color: var(--text-muted);
        font-style: italic;
    }
    
    /* NOUVEAUX BOUTONS DE FILTRAGE */
    .filter-buttons {
        display: flex;
        gap: 8px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        flex: 1;
        min-width: 100px;
        background: rgba(118, 185, 0, 0.1);
        border: 1px solid rgba(118, 185, 0, 0.2);
        border-radius: 10px;
        padding: 10px 12px;
        font-weight: 600;
        font-size: 13px;
        color: var(--accent-green-primary);
        cursor: pointer;
        transition: var(--transition);
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    
    .filter-btn:hover {
        background: rgba(118, 185, 0, 0.2);
        transform: translateY(-2px);
    }
    
    .filter-btn.active {
        background: linear-gradient(135deg, var(--accent-green-primary), var(--accent-green-secondary));
        color: var(--text-light);
        border-color: var(--accent-green-primary);
        box-shadow: 0 4px 12px rgba(118, 185, 0, 0.3);
    }

    /* ID utilisateur */
    .user-id-section {
        position: absolute;
        top: 80px;
        right: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(0, 0, 0, 0.6);
        padding: 8px 15px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        z-index: 2;
    }
    
    .user-id-label {
        font-size: 13px;
        color: var(--text-muted);
        font-weight: 600;
    }
    
    .user-id-value {
        font-size: 14px;
        font-weight: 700;
        color: var(--text-light);
        font-family: 'Courier New', monospace;
    }
    
    .copy-btn {
        background: rgba(118, 185, 0, 0.1);
        border: none;
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 12px;
        color: var(--accent-green-primary);
        cursor: pointer;
        transition: var(--transition);
        font-weight: 600;
    }
    
    .copy-btn:hover {
        background: rgba(118, 185, 0, 0.3);
    }
    
    /* Bouton Service Client Flottant */
    .floating-service {
        position: fixed;
        bottom: 100px;
        right: 20px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--accent-green-primary), var(--accent-green-secondary));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 25px rgba(118, 185, 0, 0.4);
        cursor: pointer;
        z-index: 99;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: none;
        overflow: hidden;
    }
    
    .floating-service i {
        font-size: 24px;
        font-weight: bold;
        transition: transform 0.3s ease;
        transform: scaleX(-1);
    }
    
    .floating-service:active {
        transform: scale(0.95);
        animation: pulse 0.4s ease;
    }
    
    .floating-service:active i {
        transform: scaleX(-1) rotate(15deg) scale(1.1);
    }
    
    @keyframes pulse {
        0% {
            box-shadow: 0 8px 25px rgba(118, 185, 0, 0.4);
        }
        50% {
            box-shadow: 0 0 0 15px rgba(118, 185, 0, 0.2);
        }
        100% {
            box-shadow: 0 8px 25px rgba(118, 185, 0, 0.4);
        }
    }
    
    .floating-service:hover {
        transform: scale(1.05);
        box-shadow: 0 12px 30px rgba(118, 185, 0, 0.6);
    }
    
    .floating-service:hover i {
        transform: scaleX(-1) rotate(-5deg);
    }
    
    /* Popups */
    .popup {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(10px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 15px;
        animation: fadeIn 0.3s ease;
    }
    
    .popup-content {
        background: var(--card-bg);
        width: 100%;
        max-width: 380px;
        border-radius: 20px;
        overflow: hidden;
        animation: popupIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 25px 50px rgba(118, 185, 0, 0.3);
        border: 2px solid var(--accent-green-primary);
        max-height: 85vh;
        overflow-y: auto;
    }
    
    .popup-header {
        padding: 20px;
        background: linear-gradient(135deg, var(--accent-green-primary), var(--accent-green-secondary));
        color: var(--text-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
    }
    
    .popup-header h3 {
        font-weight: 700;
        font-size: 18px;
        margin: 0;
    }
    
    .popup-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: var(--text-light);
        font-size: 20px;
        cursor: pointer;
        transition: var(--transition);
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: bold;
    }
    
    .popup-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.1);
    }
    
    .popup-body {
        padding: 25px 20px 20px 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--text-light);
        font-size: 14px;
    }
    
    .pin-inputs {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .pin-input {
        width: 60px;
        height: 60px;
        text-align: center;
        font-size: 24px;
        font-weight: 700;
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(118, 185, 0, 0.3);
        border-radius: 10px;
        transition: var(--transition);
        color: var(--text-light);
    }
    
    .pin-input:focus {
        outline: none;
        border-color: var(--accent-green-primary);
        box-shadow: 0 0 0 2px rgba(118, 185, 0, 0.2);
    }
    
    .submit-btn {
        background: linear-gradient(135deg, var(--accent-green-primary), var(--accent-green-secondary));
        color: var(--text-light);
        border: none;
        padding: 15px;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        width: 100%;
        margin-top: 10px;
        box-shadow: 0 4px 12px rgba(118, 185, 0, 0.3);
    }
    
    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(118, 185, 0, 0.4);
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
    
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    
    @keyframes popupIn {
        from { transform: scale(0.9); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
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
    @media (max-width: 480px) {
        .header {
            height: 200px;
            padding: 20px 15px 60px 15px;
        }
        
        .header h1 {
            font-size: 24px;
        }
        
        .header-logo {
            top: 15px;
            left: 15px;
        }
        
        .logout-btn {
            top: 15px;
            right: 15px;
        }
        
        .vip-navigation {
            padding: 12px;
            bottom: -20px;
            left: 15px;
            right: 15px;
        }
        
        .vip-number {
            font-size: 28px;
        }
        
        .balance-section {
            margin: 50px 15px 15px 15px;
        }
        
        .action-buttons {
            margin: 0 15px 15px 15px;
        }
        
        .menu-section {
            margin: 0 15px 15px 15px;
        }
        
        .transactions-section {
            margin: 0 15px 80px 15px;
        }
        
        .user-id-section {
            top: 70px;
            right: 15px;
        }
        
        .floating-service {
            width: 55px;
            height: 55px;
            bottom: 90px;
            right: 15px;
        }
        
        .filter-buttons {
            gap: 6px;
        }
        
        .filter-btn {
            min-width: 90px;
            padding: 8px 10px;
            font-size: 12px;
        }
    }
    </style>
</head>
<body>
    <div class="background"></div>
    <div class="geometric-pattern"></div>
    <div class="blue-accent"></div>
    <div class="purple-accent"></div>
    
    <div class="container">
        <div class="header">
            <div class="header-logo">
                <div class="logo-icon">NV</div>
                <div>
                    <div class="logo-text">NVIDIA</div>
                    <div class="logo-subtext">TECHNOLOGIE</div>
                </div>
            </div>
            
            <button class="logout-btn" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i>
                Déconnexion
            </button>
            
            <div class="user-id-section">
                <div class="user-id-label">ID:</div>
                <div class="user-id-value" id="userIdValue"><?= $custom_id ?></div>
                <button class="copy-btn" onclick="copyUserId()">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
            
         
            <div class="vip-navigation">
                <div class="vip-level">
                    <div class="vip-number">VIP <?= $vip ? $vip['niveau'] : '0' ?></div>
                    <div class="vip-label">Niveau VIP</div>
                </div>
                <div class="vip-progress">
                    <div class="progress-container">
                        <div class="progress-bar" id="vipProgress" style="width: <?= $vip ? $vip['pourcentage'] : '0' ?>%"></div>
                    </div>
                    <div class="progress-text"><?= $vip ? $vip['pourcentage'] : '0' ?>% complété</div>
                </div>
            </div>
        </div>
        
        <div class="balance-section">
            <div class="balance-card">
                <div class="balance-title">
                    <i class="fas fa-wallet"></i> Solde Principal
                </div>
                <div class="balance-amount"><?= number_format($solde['solde'] ?? 0, 0, ',', ' ') ?></div>
            </div>
            
            <div class="balance-card">
                <div class="balance-title">
                    <i class="fas fa-chart-line"></i> Revenus Totaux
                </div>
                <div class="balance-amount"><?= number_format($revenus['total'] ?? 0, 0, ',', ' ') ?></div>
            </div>
        </div>
        
        <div class="action-buttons">
            <button class="action-button" onclick="window.location.href='salaire.php'">
                <i class="fas fa-money-bill-wave"></i>
                Récupérer Salaire
            </button>
            
            <button class="action-button secondary" id="collectBtn">
                <i class="fas fa-coins"></i>
                Récupérer Gain
            </button>
        </div>
        
        <div class="payment-info" id="paymentInfo">
            <?= $next_payment_info ?>
        </div>
        
        <div class="menu-section">
            <a href="certificat.php" class="menu-item">
                <div class="menu-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="menu-text">Certificat</div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            
            <a href="depot.php" class="menu-item">
                <div class="menu-icon">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="menu-text">Dépôt</div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            
            <a href="retrait.php" class="menu-item">
                <div class="menu-icon">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="menu-text">Retrait</div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            
            <a href="portefeuille.php" class="menu-item">
                <div class="menu-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="menu-text">Portefeuille</div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            
            <a href="https://t.me/groupeofficielblackrock" class="menu-item" target="_blank">
                <div class="menu-icon">
                    <i class="fab fa-telegram"></i>
                </div>
                <div class="menu-text">Telegram</div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            
            <div class="menu-item" onclick="openTransactionsPopup()">
                <div class="menu-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="menu-text">Historique des Transactions</div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
            
            <div class="menu-item" onclick="openTransactionPasswordPopup()">
                <div class="menu-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="menu-text">
                    <?= $has_transaction_password ? 'Modifier le mot de passe de transaction' : 'Créer un mot de passe de transaction' ?>
                </div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
            
            <a href="connexion.php" class="menu-item">
                <div class="menu-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="menu-text">Connexions</div>
                <div class="menu-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
        </div>
        
        </div>
    
    <button class="floating-service" onclick="toggleServicePopup()">
        <i class="fas fa-phone-alt"></i>
    </button>
    
    <div class="popup" id="transactionsPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h3>Historique des Transactions</h3>
                <button class="popup-close" onclick="closePopup('transactionsPopup')">&times;</button>
            </div>
            <div class="popup-body">
                <div class="filter-buttons" style="margin-bottom: 20px;">
                    <button class="filter-btn active" data-filter="all">
                        <i class="fas fa-list"></i>
                        Tous
                    </button>
                    <button class="filter-btn" data-filter="depot">
                        <i class="fas fa-arrow-down"></i>
                        Dépôts
                    </button>
                    <button class="filter-btn" data-filter="retrait">
                        <i class="fas fa-arrow-up"></i>
                        Retraits
                    </button>
                    <button class="filter-btn" data-filter="revenu">
                        <i class="fas fa-coins"></i>
                        Revenus
                    </button>
                </div>
                
                <div class="transactions-container" id="transactionsContainer">
                    <?php if (count($transactions) > 0): ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <div class="transaction-item" data-type="<?= $transaction['type'] ?>">
                                <div class="transaction-info">
                                    <div class="transaction-icon" style="background: 
                                        <?php 
                                        if ($transaction['type'] == 'depot') echo 'linear-gradient(135deg, var(--accent-green-secondary), #0d301b)'; 
                                        elseif ($transaction['type'] == 'retrait') echo 'linear-gradient(135deg, var(--error), #58151c)'; 
                                        else echo 'linear-gradient(135deg, var(--accent-green-primary), var(--accent-green-secondary))'; 
                                        ?>">
                                        <i class="fas 
                                            <?php 
                                            if ($transaction['type'] == 'depot') echo 'fa-arrow-down'; 
                                            elseif ($transaction['type'] == 'retrait') echo 'fa-arrow-up'; 
                                            else echo 'fa-coins'; 
                                            ?>">
                                        </i>
                                    </div>
                                    <div class="transaction-details">
                                        <div class="transaction-type">
                                            <?php 
                                            if ($transaction['type'] == 'depot') echo 'Dépôt';
                                            elseif ($transaction['type'] == 'retrait') echo 'Retrait';
                                            else echo 'Revenu';
                                            ?>
                                        </div>
                                        <div class="transaction-date">
                                            <?php
                                            // MODIFICATION : Soustraire 7 heures pour les dépôts et retraits uniquement
                                            $date = new DateTime($transaction['date']);
                                            if ($transaction['type'] == 'depot' || $transaction['type'] == 'retrait') {
                                                $date->modify('-7 hours');
                                            }
                                            echo $date->format('d/m/Y H:i');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="transaction-amount <?= $transaction['type'] == 'retrait' ? 'negative' : 'positive' ?>">
                                        <?= $transaction['type'] == 'retrait' ? '-' : '+' ?><?= number_format($transaction['montant'], 0, ',', ' ') ?>
                                    </div>
                                    <?php if ($transaction['type'] != 'revenu'): ?>
                                        <div class="transaction-status status-<?= str_replace('é', 'e', strtolower($transaction['statut'])) ?>">
                                            <?= $transaction['statut'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-transactions">
                            Aucune transaction enregistrée
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="popup" id="transactionPasswordPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h3 id="transactionPasswordTitle">
                    <?= $has_transaction_password ? 'Modifier le mot de passe' : 'Créer un mot de passe' ?>
                </h3>
                <button class="popup-close" onclick="closePopup('transactionPasswordPopup')">&times;</button>
            </div>
            <div class="popup-body">
                <form id="transactionPasswordForm">
                    <?php if ($has_transaction_password): ?>
                        <div class="form-group">
                            <label class="form-label" for="oldPin">Ancien code (4 chiffres)</label>
                            <div class="pin-inputs">
                                <input type="text" class="pin-input" maxlength="1" data-index="0" oninput="moveToNext(this, 'oldPin')">
                                <input type="text" class="pin-input" maxlength="1" data-index="1" oninput="moveToNext(this, 'oldPin')">
                                <input type="text" class="pin-input" maxlength="1" data-index="2" oninput="moveToNext(this, 'oldPin')">
                                <input type="text" class="pin-input" maxlength="1" data-index="3" oninput="moveToNext(this, 'oldPin')">
                            </div>
                            <input type="hidden" id="oldPin" name="old_pin">
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label" for="newPin">Nouveau code (4 chiffres)</label>
                        <div class="pin-inputs">
                            <input type="text" class="pin-input" maxlength="1" data-index="0" oninput="moveToNext(this, 'newPin')">
                            <input type="text" class="pin-input" maxlength="1" data-index="1" oninput="moveToNext(this, 'newPin')">
                            <input type="text" class="pin-input" maxlength="1" data-index="2" oninput="moveToNext(this, 'newPin')">
                            <input type="text" class="pin-input" maxlength="1" data-index="3" oninput="moveToNext(this, 'newPin')">
                        </div>
                        <input type="hidden" id="newPin" name="new_pin">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirmPin">Confirmer le code</label>
                        <div class="pin-inputs">
                            <input type="text" class="pin-input" maxlength="1" data-index="0" oninput="moveToNext(this, 'confirmPin')">
                            <input type="text" class="pin-input" maxlength="1" data-index="1" oninput="moveToNext(this, 'confirmPin')">
                            <input type="text" class="pin-input" maxlength="1" data-index="2" oninput="moveToNext(this, 'confirmPin')">
                            <input type="text" class="pin-input" maxlength="1" data-index="3" oninput="moveToNext(this, 'confirmPin')">
                        </div>
                        <input type="hidden" id="confirmPin" name="confirm_pin">
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <?= $has_transaction_password ? 'Modifier le mot de passe' : 'Créer le mot de passe' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // FONCTION DE DÉCONNEXION
        function logout() {
            if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
                window.location.href = 'deconnexion.php';
            }
        }

        // FONCTIONS DE GESTION DES POPUPS
        function closePopup(id) {
            const popup = document.getElementById(id);
            if (popup) {
                popup.style.display = 'none';
            }
        }

        // NOUVELLE FONCTION POUR OUVRIR L'HISTORIQUE DES TRANSACTIONS
        function openTransactionsPopup() {
            document.getElementById('transactionsPopup').style.display = 'flex';
        }

        function openTransactionPasswordPopup() {
            document.getElementById('transactionPasswordPopup').style.display = 'flex';
        }

        // FONCTION POUR COPIER L'ID UTILISATEUR
        function copyUserId() {
            const userId = document.getElementById('userIdValue').textContent;
            navigator.clipboard.writeText(userId).then(function() {
                const btn = document.querySelector('.copy-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                }, 2000);
            });
        }

        // GESTION DU BOUTON RÉCUPÉRER GAIN
        document.getElementById('collectBtn').addEventListener('click', function() {
            <?php if ($can_collect): ?>
                window.location.href = 'paiement.php';
            <?php else: ?>
                // Afficher le message de prochain paiement
                const paymentInfo = document.getElementById('paymentInfo');
                paymentInfo.textContent = "<?= $next_payment_info ?>";
                paymentInfo.classList.add('show');
                
                // Masquer le message après 5 secondes
                setTimeout(() => {
                    paymentInfo.classList.remove('show');
                }, 5000);
            <?php endif; ?>
        });

        // GESTION DU MOT DE PASSE DE TRANSACTION (PIN INPUTS)
        function moveToNext(input, fieldId) {
            const index = parseInt(input.getAttribute('data-index'));
            const value = input.value;
            
            // Vérifier que c'est un chiffre
            if (!/^\d$/.test(value)) {
                input.value = '';
                return;
            }
            
            // Mettre à jour le champ caché
            const hiddenField = document.getElementById(fieldId);
            let currentValue = hiddenField.value || '';
            let newPinValue = '';
            
            // Reconstruire la valeur du pin à partir de tous les inputs du groupe
            const pinInputs = input.parentElement.querySelectorAll('.pin-input');
            pinInputs.forEach(pinInput => {
                newPinValue += pinInput.value;
            });

            hiddenField.value = newPinValue;
            
            // Passer au champ suivant si un chiffre est entré
            if (index < 3 && value !== '') {
                const nextInput = input.parentElement.querySelector(`[data-index="${index + 1}"]`);
                if (nextInput) {
                    nextInput.focus();
                }
            }
        }

        // GESTION DU FORMULAIRE DE MOT DE PASSE
        document.getElementById('transactionPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const oldPin = document.getElementById('oldPin') ? document.getElementById('oldPin').value : '';
            const newPin = document.getElementById('newPin').value;
            const confirmPin = document.getElementById('confirmPin').value;
            
            // Validation
            if (<?= $has_transaction_password ? 'true' : 'false' ?> && oldPin.length !== 4) {
                alert('Veuillez saisir votre ancien code à 4 chiffres.');
                return;
            }
            
            if (newPin.length !== 4) {
                alert('Veuillez saisir un nouveau code à 4 chiffres.');
                return;
            }
            
            if (newPin !== confirmPin) {
                alert('Les codes ne correspondent pas.');
                return;
            }
            
            // Envoyer les données au serveur
            const formData = new FormData();
            formData.append('old_pin', oldPin);
            formData.append('new_pin', newPin);
            formData.append('confirm_pin', confirmPin);
            
            fetch('update_transaction_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Mot de passe de transaction mis à jour avec succès!');
                    closePopup('transactionPasswordPopup');
                    // Recharger la page pour mettre à jour l'interface
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert(data.message || 'Erreur lors de la mise à jour du mot de passe.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors de la mise à jour du mot de passe.');
            });
        });

        // BOUTON SERVICE CLIENT FLOTTANT
        function toggleServicePopup() {
            // Rediriger vers le service client
            window.open('https://t.me/Blackrockserviceclient', '_blank');
        }

        // FONCTION POUR FILTRER LES TRANSACTIONS
        function filterTransactions(filterType) {
            const transactionItems = document.querySelectorAll('.transaction-item');
            let visibleCount = 0;
            
            transactionItems.forEach(item => {
                const itemType = item.getAttribute('data-type');
                
                if (filterType === 'all' || itemType === filterType) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Afficher un message si aucun élément n'est visible
            const emptyMessage = document.querySelector('.empty-transactions');
            const container = document.getElementById('transactionsContainer');
            
            // S'assurer que le message vide est à l'intérieur du container du popup
            if (visibleCount === 0) {
                let currentEmptyMessage = container.querySelector('.empty-transactions');
                if (!currentEmptyMessage) {
                    currentEmptyMessage = document.createElement('div');
                    currentEmptyMessage.className = 'empty-transactions';
                    container.appendChild(currentEmptyMessage);
                }
                currentEmptyMessage.textContent = `Aucune transaction de type ${getFilterLabel(filterType)} trouvée`;
                currentEmptyMessage.style.display = 'block';
            } else {
                if (emptyMessage) {
                    emptyMessage.style.display = 'none';
                }
            }
        }
        
        function getFilterLabel(filterType) {
            switch(filterType) {
                case 'depot': return 'dépôt';
                case 'retrait': return 'retrait';
                case 'revenu': return 'revenu';
                default: return '';
            }
        }

        // INITIALISATION DES BOUTONS DE FILTRAGE
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-buttons .filter-btn');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Retirer la classe active de tous les boutons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Ajouter la classe active au bouton cliqué
                    this.classList.add('active');
                    
                    // Appliquer le filtre
                    const filterType = this.getAttribute('data-filter');
                    filterTransactions(filterType);
                });
            });
            
            // Animation d'entrée des éléments
            const elements = document.querySelectorAll('.balance-section, .action-buttons, .menu-section');
            elements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Afficher le message de prochain paiement si nécessaire
            <?php if (!$can_collect && $has_active_command): ?>
                setTimeout(() => {
                    const paymentInfo = document.getElementById('paymentInfo');
                    paymentInfo.classList.add('show');
                }, 1000);
            <?php endif; ?>
        });

        // EMPÊCHER LE ZOOM SUR MOBILE
        document.addEventListener('wheel', function(e) {
            if (e.ctrlKey) e.preventDefault();
        }, { passive: false });
        
        document.addEventListener('gesturestart', function(e) {
            e.preventDefault();
        });
    </script>
</body>
</html>