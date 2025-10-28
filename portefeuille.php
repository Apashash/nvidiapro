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

// Vérification si l'utilisateur a un mot de passe de transaction
$has_transaction_password = false;
$transaction_password_query = $db->prepare("SELECT * FROM transaction_passwords WHERE user_id = ?");
$transaction_password_query->execute([$user_id]);
if ($transaction_password_query->rowCount() > 0) {
    $has_transaction_password = true;
}

// Vérification si l'utilisateur a déjà un portefeuille
$has_wallet = false;
$wallet_data = null;
$wallet_query = $db->prepare("SELECT * FROM portefeuilles WHERE user_id = ?");
$wallet_query->execute([$user_id]);
if ($wallet_query->rowCount() > 0) {
    $has_wallet = true;
    $wallet_data = $wallet_query->fetch();
}

// Traitement du formulaire de portefeuille
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_wallet'])) {
    if (!$has_transaction_password) {
        $_SESSION['error'] = "Vous devez d'abord créer un mot de passe de transaction";
    } else {
        // Vérification du mot de passe de transaction
        $transaction_password = $_POST['transaction_password'] ?? '';
        $password_check = $db->prepare("SELECT * FROM transaction_passwords WHERE user_id = ? AND password = ?");
        $password_check->execute([$user_id, $transaction_password]);
        
        if ($password_check->rowCount() === 0) {
            $_SESSION['error'] = "Mot de passe de transaction incorrect";
        } else {
            // Validation des données
            $nom_portefeuille = trim($_POST['nom_portefeuille']);
            $pays = $_POST['pays'];
            $methode_paiement = $_POST['methode_paiement'];
            $numero_telephone = trim($_POST['numero_telephone']);
            $confirm_telephone = trim($_POST['confirm_telephone']);
            
            // Validation
            if (empty($nom_portefeuille) || empty($pays) || empty($methode_paiement) || empty($numero_telephone)) {
                $_SESSION['error'] = "Tous les champs sont obligatoires";
            } elseif ($numero_telephone !== $confirm_telephone) {
                $_SESSION['error'] = "Les numéros de téléphone ne correspondent pas";
            } elseif (!preg_match('/^\d+$/', $numero_telephone)) {
                $_SESSION['error'] = "Le numéro de téléphone ne doit contenir que des chiffres";
            } else {
                try {
                    if ($has_wallet) {
                        // Mise à jour du portefeuille existant
                        $update_query = $db->prepare("UPDATE portefeuilles SET nom_portefeuille = ?, pays = ?, methode_paiement = ?, numero_telephone = ? WHERE user_id = ?");
                        $update_query->execute([$nom_portefeuille, $pays, $methode_paiement, $numero_telephone, $user_id]);
                        $_SESSION['success'] = "Portefeuille mis à jour avec succès";
                    } else {
                        // Création d'un nouveau portefeuille
                        $insert_query = $db->prepare("INSERT INTO portefeuilles (user_id, nom_portefeuille, pays, methode_paiement, numero_telephone) VALUES (?, ?, ?, ?, ?)");
                        $insert_query->execute([$user_id, $nom_portefeuille, $pays, $methode_paiement, $numero_telephone]);
                        $_SESSION['success'] = "Portefeuille créé avec succès";
                        $has_wallet = true;
                    }
                    
                    // Recharger les données du portefeuille
                    $wallet_query->execute([$user_id]);
                    $wallet_data = $wallet_query->fetch();
                    
                } catch (Exception $e) {
                    $_SESSION['error'] = "Erreur lors de la sauvegarde du portefeuille: " . $e->getMessage();
                }
            }
        }
    }
}

// Liste des pays et méthodes de paiement (version finale)
$pays_methodes = [
    'Cameroun' => ['mtn', 'orange'],
    'Togo' => ['tmoney', 'moov'],
    'Sénégal' => ['orange', 'free', 'wave'],
    'Niger' => ['airtel'],
    'Mali' => ['orange', 'moov', 'wave'],
    'Côte d\'Ivoire' => ['orange', 'mtn', 'moov', 'wave'],
    'Gabon' => ['airtel', 'moov'],
    'République démocratique du Congo' => ['m_pesa', 'orange', 'afrimoney', 'airtel'],
    'Congo-Brazzaville' => ['airtel', 'mtn'],
    'Burkina Faso' => ['moov', 'orange'],
    'Bénin' => ['mtn', 'moov']
];

// Noms affichables des méthodes de paiement
$methodes_noms = [
    'mtn' => 'MTN Mobile Money',
    'orange' => 'Orange Money',
    'tmoney' => 'T-Money',
    'moov' => 'Moov Money',
    'free' => 'Free Money',
    'wave' => 'Wave',
    'airtel' => 'Airtel Money',
    'm_pesa' => 'M-PESA',
    'afrimoney' => 'Afrimoney'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portefeuille - NVIDIA Technologie</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        /* Couleurs NVIDIA */
        --primary-white: #ffffff;
        --soft-white: #f8fafc;
        --light-gray: #f1f5f9;
        --accent-green: #76B900; /* Vert NVIDIA */
        --green-light: #90d300;
        --green-dark: #5a8a00;
        --accent-cyan: #00CC7C; /* Cyan secondaire */
        --text-dark: #1e293b;
        --text-gray: #64748b;
        --text-light: #475569;
        --card-bg: rgba(255, 255, 255, 0.95);
        --border-color: rgba(255, 255, 255, 0.3);
        --error: #ef4444;
        --success: #10b981;
        --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        --premium-color: var(--accent-green);
        --deep-color: var(--green-dark);
        --dark-bg: #1a1a1a; /* Fond pour contraste */
        --dark-text: #e0e0e0;
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
    }
    
    body {
        /* Fond légèrement foncé pour un look tech */
        background: linear-gradient(135deg, var(--dark-bg) 0%, #2c2c2c 50%, #3a3a3a 100%);
        color: var(--dark-text);
        min-height: 100vh;
        overflow-x: hidden;
    }
    
    .container {
        max-width: 430px;
        margin: 0 auto;
        background: transparent;
    }
    
    /* Arrière-plan géométrique */
    .background {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: 
            radial-gradient(circle at 10% 20%, rgba(118, 185, 0, 0.15) 0%, transparent 20%),
            radial-gradient(circle at 90% 80%, rgba(0, 204, 124, 0.15) 0%, transparent 20%),
            radial-gradient(circle at 20% 30%, rgba(118, 185, 0, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%, rgba(0, 204, 124, 0.1) 0%, transparent 50%),
            linear-gradient(135deg, var(--dark-bg) 0%, #2c2c2c 100%);
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
            linear-gradient(150deg, rgba(0, 204, 124, 0.08) 12%, transparent 12.5%, transparent 87%, rgba(0, 204, 124, 0.08) 87.5%, rgba(0, 204, 124, 0.08) 0),
            linear-gradient(30deg, rgba(118, 185, 0, 0.05) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.05) 87.5%, rgba(118, 185, 0, 0.05) 0),
            linear-gradient(150deg, rgba(0, 204, 124, 0.05) 12%, transparent 12.5%, transparent 87%, rgba(0, 204, 124, 0.05) 87.5%, rgba(0, 204, 124, 0.05) 0),
            linear-gradient(60deg, rgba(118, 185, 0, 0.1) 25%, transparent 25.5%, transparent 75%, rgba(118, 185, 0, 0.1) 75%, rgba(118, 185, 0, 0.1) 0),
            linear-gradient(60deg, rgba(0, 204, 124, 0.1) 25%, transparent 25.5%, transparent 75%, rgba(0, 204, 124, 0.1) 75%, rgba(0, 204, 124, 0.1) 0);
        background-size: 100px 175px;
        background-position: 0 0, 0 0, 50px 87.5px, 50px 87.5px, 0 0, 50px 87.5px;
        z-index: -2;
        animation: patternShift 30s linear infinite;
    }
    
    .green-accent {
        position: fixed;
        top: 0;
        right: 0;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(118, 185, 0, 0.25) 0%, transparent 70%);
        filter: blur(60px);
        z-index: -1;
    }
    
    .cyan-accent {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(0, 204, 124, 0.25) 0%, transparent 70%);
        filter: blur(60px);
        z-index: -1;
    }
    
    /* En-tête avec image de fond */
    .header {
        height: 180px;
        /* Remplacer 'assets/head.jpg' par une image de fond sombre ou tech si possible, sinon garder l'original */
        background: url('assets/head.jpg') center/cover; 
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 25px 20px 50px 20px;
        animation: headerSlide 1s ease-out;
        border-radius: 0 0 20px 20px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5); /* Ombre plus foncée */
    }
    
    .header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.65); /* Assombrissement plus fort */
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
        color: var(--primary-white);
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
        background: linear-gradient(135deg, var(--accent-green), var(--accent-cyan));
        border: none;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--dark-bg); /* Texte foncé sur fond clair */
        font-weight: bold;
        font-size: 18px;
        box-shadow: 0 8px 25px rgba(118, 185, 0, 0.5);
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
        background: linear-gradient(135deg, var(--accent-green), var(--accent-cyan));
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
    
    /* Bouton retour */
    .back-btn {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255, 255, 255, 0.9);
        border: none;
        border-radius: 12px;
        padding: 8px 15px;
        font-weight: 600;
        font-size: 13px;
        color: var(--text-dark);
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        z-index: 2;
    }
    
    .back-btn:hover {
        background: var(--primary-white);
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
    }
    
    /* Section principale */
    .main-section {
        padding: 20px;
        animation: fadeInUp 1s ease-out;
    }
    
    .info-card {
        background: rgba(255, 255, 255, 0.05); /* Fond transparent/foncé */
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(118, 185, 0, 0.2); /* Bordure accentuée */
        margin-bottom: 20px;
        transition: var(--transition);
        color: var(--dark-text);
    }
    
    .info-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 25px rgba(0, 0, 0, 0.4);
    }
    
    .info-card h2 {
        font-size: 18px;
        font-weight: 700;
        color: var(--accent-green); /* Couleur NVIDIA */
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .info-card p {
        color: var(--dark-text);
        font-size: 14px;
        line-height: 1.5;
    }
    
    /* Formulaire */
    .wallet-form {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(118, 185, 0, 0.2);
        margin-bottom: 20px;
        animation: fadeInUp 1.2s ease-out;
        color: var(--dark-text);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--dark-text);
        font-size: 14px;
    }
    
    .form-input {
        width: 100%;
        padding: 15px;
        border: 1px solid rgba(118, 185, 0, 0.2);
        border-radius: 10px;
        font-size: 15px;
        transition: var(--transition);
        background: #333; /* Fond d'input sombre */
        color: var(--primary-white);
    }
    
    .form-input:focus {
        outline: none;
        border-color: var(--accent-green);
        box-shadow: 0 0 0 2px rgba(118, 185, 0, 0.4);
    }
    
    .form-select {
        width: 100%;
        padding: 15px;
        border: 1px solid rgba(118, 185, 0, 0.2);
        border-radius: 10px;
        font-size: 15px;
        transition: var(--transition);
        background: #333;
        color: var(--primary-white);
        cursor: pointer;
    }

    /* Styles pour les options du select sur fond sombre */
    .form-select option {
        background: #333;
        color: var(--primary-white);
    }
    
    .form-select:focus {
        outline: none;
        border-color: var(--accent-green);
        box-shadow: 0 0 0 2px rgba(118, 185, 0, 0.4);
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
        background: #333;
        color: var(--primary-white);
        border: 1px solid rgba(118, 185, 0, 0.2);
        border-radius: 10px;
        transition: var(--transition);
    }
    
    .pin-input:focus {
        outline: none;
        border-color: var(--accent-green);
        box-shadow: 0 0 0 2px rgba(118, 185, 0, 0.4);
    }
    
    .submit-btn {
        /* Dégradé Vert/Cyan NVIDIA */
        background: linear-gradient(135deg, var(--accent-green), var(--accent-cyan));
        color: var(--dark-bg); /* Texte très foncé sur bouton clair */
        border: none;
        padding: 15px;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        width: 100%;
        margin-top: 10px;
        box-shadow: 0 4px 12px rgba(118, 185, 0, 0.4);
    }
    
    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(118, 185, 0, 0.6);
    }
    
    .submit-btn:disabled {
        background: var(--text-gray);
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    /* Section portefeuille existant */
    .wallet-display {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(118, 185, 0, 0.2);
        margin-bottom: 20px;
        animation: fadeInUp 1.2s ease-out;
        color: var(--dark-text);
    }
    
    .wallet-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid rgba(118, 185, 0, 0.1);
    }
    
    .wallet-item:last-child {
        border-bottom: none;
    }
    
    .wallet-label {
        font-weight: 600;
        color: var(--dark-text);
        font-size: 14px;
    }
    
    .wallet-value {
        color: var(--soft-white);
        font-size: 14px;
    }
    
    .edit-btn {
        /* Couleur secondaire pour bouton d'édition */
        background: linear-gradient(135deg, var(--green-light), var(--accent-green));
        color: var(--dark-bg);
        border: none;
        padding: 12px 20px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        margin-top: 15px;
        box-shadow: 0 4px 12px rgba(118, 185, 0, 0.3);
    }
    
    .edit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(118, 185, 0, 0.4);
    }
    
    /* Messages d'alerte */
    .alert {
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-weight: 600;
        animation: fadeInUp 0.5s ease-out;
    }
    
    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
        border: 1px solid rgba(16, 185, 129, 0.2);
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    
    .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, 0.2);
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
        backdrop-filter: blur(5px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 15px;
        animation: fadeIn 0.3s ease;
    }
    
    .popup-content {
        background: #282828; /* Fond sombre pour la popup */
        color: var(--dark-text);
        width: 100%;
        max-width: 380px;
        border-radius: 20px;
        overflow: hidden;
        animation: popupIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        border: 2px solid var(--accent-green);
        max-height: 85vh;
        overflow-y: auto;
    }
    
    .popup-header {
        padding: 20px;
        background: linear-gradient(135deg, var(--accent-green), var(--accent-cyan));
        color: var(--dark-bg);
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
        background: rgba(0, 0, 0, 0.2);
        border: none;
        color: var(--dark-bg);
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
        background: rgba(0, 0, 0, 0.4);
        transform: scale(1.1);
    }
    
    .popup-body {
        padding: 25px 20px 20px 20px;
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
            height: 160px;
            padding: 20px 15px 40px 15px;
        }
        
        .header h1 {
            font-size: 24px;
        }
        
        .header-logo {
            top: 15px;
            left: 15px;
        }
        
        .back-btn {
            top: 15px;
            right: 15px;
        }
        
        .main-section {
            padding: 15px;
        }
        
        .info-card, .wallet-form, .wallet-display {
            padding: 20px;
        }
        
        .pin-input {
            width: 50px;
            height: 50px;
            font-size: 20px;
        }
    }
    </style>
</head>
<body>
    <div class="background"></div>
    <div class="geometric-pattern"></div>
    <div class="green-accent"></div>
    <div class="cyan-accent"></div>
    
    <div class="container">
        <div class="header">
            <div class="header-logo">
                <div class="logo-icon">NV</div> <div>
                    <div class="logo-text">NVIDIA</div>
                    <div class="logo-subtext">Technologie</div>
                </div>
            </div>
            
            <button class="back-btn" onclick="window.location.href='compte.php'">
                <i class="fas fa-arrow-left"></i>
                Retour
            </button>
            
            <div class="header-content">
                <h1>Mon Portefeuille</h1>
                <p>Gérez votre compte de retrait NVIDIA Technologie</p>
            </div>
        </div>
        
        <div class="main-section">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <div class="info-card">
                <h2>
                    <i class="fas fa-info-circle"></i>
                    Information importante
                </h2>
                <p>
                    Cette page vous permet d'enregistrer votre portefeuille de retrait **NVIDIA Technologie**. 
                    C'est le compte sur lequel vous allez recevoir vos retraits.
                </p>
                <p style="margin-top: 10px; font-size: 13px; color: var(--error);">
                    <strong>Attention :</strong> En cas d'erreur dans le numéro, NVIDIA Technologie ne peut pas être tenu responsable.
                </p>
            </div>
            
            <?php if (!$has_transaction_password): ?>
                <div class="popup" id="transactionPasswordPopup" style="display: flex;">
                    <div class="popup-content">
                        <div class="popup-header">
                            <h3>Créer un mot de passe de transaction</h3>
                            <button class="popup-close" onclick="closePopup('transactionPasswordPopup')">&times;</button>
                        </div>
                        <div class="popup-body">
                            <p style="margin-bottom: 20px; color: var(--dark-text);">
                                Vous devez créer un mot de passe de transaction avant de pouvoir configurer votre portefeuille.
                            </p>
                            <form id="transactionPasswordForm">
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
                                    Créer le mot de passe
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($has_wallet): ?>
                <div class="wallet-display">
                    <h2>
                        <i class="fas fa-wallet"></i>
                        Portefeuille actuel
                    </h2>
                    
                    <div class="wallet-item">
                        <div class="wallet-label">Nom du portefeuille</div>
                        <div class="wallet-value"><?= htmlspecialchars($wallet_data['nom_portefeuille']) ?></div>
                    </div>
                    
                    <div class="wallet-item">
                        <div class="wallet-label">Pays</div>
                        <div class="wallet-value"><?= htmlspecialchars($wallet_data['pays']) ?></div>
                    </div>
                    
                    <div class="wallet-item">
                        <div class="wallet-label">Méthode de paiement</div>
                        <div class="wallet-value">
                            <?= isset($methodes_noms[$wallet_data['methode_paiement']]) ? 
                                $methodes_noms[$wallet_data['methode_paiement']] : 
                                htmlspecialchars($wallet_data['methode_paiement']) ?>
                        </div>
                    </div>
                    
                    <div class="wallet-item">
                        <div class="wallet-label">Numéro de téléphone</div>
                        <div class="wallet-value"><?= htmlspecialchars($wallet_data['numero_telephone']) ?></div>
                    </div>
                    
                    <div class="wallet-item">
                        <div class="wallet-label">Dernière modification</div>
                        <div class="wallet-value"><?= date('d/m/Y H:i', strtotime($wallet_data['date_modification'])) ?></div>
                    </div>
                    
                    <button class="edit-btn" onclick="showEditForm()">
                        <i class="fas fa-edit"></i>
                        Modifier le portefeuille
                    </button>
                </div>
            <?php endif; ?>
            
            <form class="wallet-form" method="POST" action="" id="walletForm" <?= $has_wallet ? 'style="display: none;"' : '' ?>>
                <h2>
                    <i class="fas fa-plus-circle"></i>
                    <?= $has_wallet ? 'Modifier le portefeuille' : 'Créer un portefeuille' ?>
                </h2>
                
                <div class="form-group">
                    <label class="form-label" for="nom_portefeuille">
                        <i class="fas fa-user"></i> Nom du portefeuille
                    </label>
                    <input type="text" class="form-input" id="nom_portefeuille" name="nom_portefeuille" 
                           value="<?= $has_wallet ? htmlspecialchars($wallet_data['nom_portefeuille']) : '' ?>" 
                           placeholder="Ex: Mon portefeuille principal" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="pays">
                        <i class="fas fa-globe"></i> Pays
                    </label>
                    <select class="form-select" id="pays" name="pays" required onchange="updatePaymentMethods()">
                        <option value="">Sélectionnez votre pays</option>
                        <?php foreach ($pays_methodes as $pays => $methodes): ?>
                            <option value="<?= $pays ?>" 
                                <?= ($has_wallet && $wallet_data['pays'] === $pays) ? 'selected' : '' ?>>
                                <?= $pays ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="methode_paiement">
                        <i class="fas fa-money-bill-wave"></i> Méthode de paiement
                    </label>
                    <select class="form-select" id="methode_paiement" name="methode_paiement" required>
                        <option value="">Sélectionnez d'abord votre pays</option>
                        <?php if ($has_wallet): ?>
                            <option value="<?= $wallet_data['methode_paiement'] ?>" selected>
                                <?= isset($methodes_noms[$wallet_data['methode_paiement']]) ? 
                                    $methodes_noms[$wallet_data['methode_paiement']] : 
                                    $wallet_data['methode_paiement'] ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="numero_telephone">
                        <i class="fas fa-phone"></i> Numéro de téléphone (sans indicatif)
                    </label>
                    <input type="text" class="form-input" id="numero_telephone" name="numero_telephone" 
                           value="<?= $has_wallet ? htmlspecialchars($wallet_data['numero_telephone']) : '' ?>" 
                           placeholder="Ex: 612345678" required pattern="\d+" title="Uniquement des chiffres">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="confirm_telephone">
                        <i class="fas fa-redo"></i> Confirmer le numéro de téléphone
                    </label>
                    <input type="text" class="form-input" id="confirm_telephone" name="confirm_telephone" 
                           placeholder="Resaisir le même numéro" required pattern="\d+" title="Uniquement des chiffres">
                </div>
                
                <?php if ($has_transaction_password): ?>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Mot de passe de transaction (4 chiffres)
                        </label>
                        <div class="pin-inputs">
                            <input type="text" class="pin-input" maxlength="1" data-index="0" oninput="moveToNext(this, 'transactionPassword')">
                            <input type="text" class="pin-input" maxlength="1" data-index="1" oninput="moveToNext(this, 'transactionPassword')">
                            <input type="text" class="pin-input" maxlength="1" data-index="2" oninput="moveToNext(this, 'transactionPassword')">
                            <input type="text" class="pin-input" maxlength="1" data-index="3" oninput="moveToNext(this, 'transactionPassword')">
                        </div>
                        <input type="hidden" id="transactionPassword" name="transaction_password">
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="submit-btn" name="save_wallet">
                    <i class="fas fa-save"></i>
                    <?= $has_wallet ? 'Modifier le portefeuille' : 'Créer le portefeuille' ?>
                </button>
                
                <?php if ($has_wallet): ?>
                    <button type="button" class="submit-btn" style="background: #555; margin-top: 10px; color: var(--primary-white);" onclick="hideEditForm()">
                        <i class="fas fa-times"></i>
                        Annuler
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        // Données des méthodes de paiement par pays
        const paysMethodes = <?= json_encode($pays_methodes) ?>;
        const methodesNoms = <?= json_encode($methodes_noms) ?>;
        
        // Fonction pour mettre à jour les méthodes de paiement en fonction du pays sélectionné
        function updatePaymentMethods() {
            const paysSelect = document.getElementById('pays');
            const methodeSelect = document.getElementById('methode_paiement');
            const pays = paysSelect.value;
            
            // Vider les options actuelles
            methodeSelect.innerHTML = '<option value="">Sélectionnez une méthode</option>';
            
            if (pays && paysMethodes[pays]) {
                // Ajouter les méthodes disponibles pour le pays sélectionné
                paysMethodes[pays].forEach(methode => {
                    const option = document.createElement('option');
                    option.value = methode;
                    option.textContent = methodesNoms[methode] || methode;
                    methodeSelect.appendChild(option);
                });
            }
        }
        
        // Fonction pour passer au champ suivant dans les inputs PIN
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
            const newValue = currentValue.substring(0, index) + value + currentValue.substring(index + 1);
            hiddenField.value = newValue;
            
            // Passer au champ suivant
            if (index < 3 && value !== '') {
                const nextInput = input.parentElement.querySelector(`[data-index="${index + 1}"]`);
                if (nextInput) {
                    nextInput.focus();
                }
            }
        }
        
        // Fonction pour fermer les popups
        function closePopup(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        // Fonctions pour afficher/masquer le formulaire d'édition
        function showEditForm() {
            document.getElementById('walletForm').style.display = 'block';
            document.querySelector('.wallet-display').style.display = 'none';
        }
        
        function hideEditForm() {
            document.getElementById('walletForm').style.display = 'none';
            document.querySelector('.wallet-display').style.display = 'block';
        }
        
        // Gestion du formulaire de mot de passe de transaction
        document.getElementById('transactionPasswordForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPin = document.getElementById('newPin').value;
            const confirmPin = document.getElementById('confirmPin').value;
            
            // Validation
            if (newPin.length !== 4) {
                alert('Veuillez saisir un code à 4 chiffres.');
                return;
            }
            
            if (newPin !== confirmPin) {
                alert('Les codes ne correspondent pas.');
                return;
            }
            
            // Envoyer les données au serveur
            const formData = new FormData();
            formData.append('new_pin', newPin);
            formData.append('confirm_pin', confirmPin);
            
            fetch('update_transaction_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Mot de passe de transaction créé avec succès!');
                    location.reload();
                } else {
                    alert(data.message || 'Erreur lors de la création du mot de passe.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors de la création du mot de passe.');
            });
        });
        
        // Validation du formulaire de portefeuille
        document.getElementById('walletForm').addEventListener('submit', function(e) {
            const numero = document.getElementById('numero_telephone').value;
            const confirmNumero = document.getElementById('confirm_telephone').value;
            const transactionPassword = document.getElementById('transactionPassword')?.value || '';
            
            // Vérification de la correspondance des numéros
            if (numero !== confirmNumero) {
                e.preventDefault();
                alert('Les numéros de téléphone ne correspondent pas.');
                return;
            }
            
            // Vérification du mot de passe de transaction
            if (transactionPassword && transactionPassword.length !== 4) {
                e.preventDefault();
                alert('Veuillez saisir votre mot de passe de transaction (4 chiffres).');
                return;
            }
            
            // Vérification que le numéro ne contient que des chiffres
            if (!/^\d+$/.test(numero)) {
                e.preventDefault();
                alert('Le numéro de téléphone ne doit contenir que des chiffres.');
                return;
            }
        });
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Si l'utilisateur a déjà un portefeuille, initialiser les méthodes de paiement
            <?php if ($has_wallet): ?>
                updatePaymentMethods();
            <?php endif; ?>
            
            // Animation d'entrée des éléments
            const elements = document.querySelectorAll('.info-card, .wallet-form, .wallet-display');
            elements.forEach((element, index) => {
                if (element.style.display !== 'none') {
                    element.style.animationDelay = `${index * 0.1}s`;
                }
            });
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