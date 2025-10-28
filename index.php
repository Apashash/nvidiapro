<?php
session_start();
require_once 'db.php';
include 'menu.php';
include 'image.php';
include 'verify.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupération des données utilisateur
$stmt = $db->prepare("SELECT u.*, s.solde 
                      FROM utilisateurs u
                      LEFT JOIN soldes s ON u.id = s.user_id
                      WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Récupération des revenus totaux
$revenus = $db->prepare("SELECT SUM(montant) as total FROM historique_revenus WHERE user_id = ?");
$revenus->execute([$user_id]);
$revenus = $revenus->fetch();

$devise = ($user['pays'] == 'Cameroun') ? 'XAF' : 'XOF';

// Récupération des posts validés
$posts = $db->prepare("SELECT * FROM posts WHERE statut = 'valide' ORDER BY date_creation DESC LIMIT 10");
$posts->execute();
$posts_data = $posts->fetchAll();

// Traitement du formulaire de post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && isset($_FILES['image'])) {
    $message = trim($_POST['message']);
    $image = $_FILES['image'];
    
    if (!empty($message) && $image['error'] === UPLOAD_ERR_OK) {
        // Vérifier le type de fichier
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $file_type = mime_content_type($image['tmp_name']);
        
        if (in_array($file_type, $allowed_types)) {
            // Vérifier la taille (max 5MB)
            if ($image['size'] <= 5 * 1024 * 1024) {
                // Générer un nom unique pour l'image
                $file_extension = pathinfo($image['name'], PATHINFO_EXTENSION);
                $file_name = uniqid() . '.' . $file_extension;
                $upload_path = 'uploads/' . $file_name;
                
                // Déplacer le fichier
                if (move_uploaded_file($image['tmp_name'], $upload_path)) {
                    // Insérer dans la base de données
                    $insert_post = $db->prepare("INSERT INTO posts (user_id, message, image, statut) VALUES (?, ?, ?, 'en_attente')");
                    if ($insert_post->execute([$user_id, $message, $file_name])) {
                        $success_message = "Votre post a été soumis et sera vérifié avant publication!";
                    } else {
                        $error_message = "Erreur lors de l'enregistrement du post.";
                    }
                } else {
                    $error_message = "Erreur lors du téléchargement de l'image.";
                }
            } else {
                $error_message = "L'image est trop volumineuse (max 5MB).";
            }
        } else {
            $error_message = "Format d'image non supporté (PNG, JPG, JPEG uniquement).";
        }
    } else {
        $error_message = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Accueil - NVIDIA Technology</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* VARIABLES MODIFIÉES POUR LE THÈME NVIDIA */
    :root {
        --primary-white: #ffffff;
        --soft-white: #f8fafc;
        --warm-white: #fefefe;
        --light-gray: #1a1a1a; /* Fond sombre */
        --accent-green: #76b900; /* Vert NVIDIA */
        --green-light: #90d300;
        --green-dark: #588e00;
        --text-dark: #e0e0e0; /* Texte clair sur fond sombre */
        --text-gray: #a0a0a0;
        --text-light: #cccccc;
        --card-bg: rgba(30, 30, 30, 0.95); /* Fond de carte sombre et semi-transparent */
        --border-color: rgba(255, 255, 255, 0.1);
        --error: #ef4444;
        --success: #10b981;
        --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        --premium-color-a: #1e1e1e; /* Noir profond */
        --premium-color-b: #3a3a3a; /* Gris foncé */
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
    
    /* FOND SOMBRE GÉNÉRAL */
    body {
        font-family: 'Inter', sans-serif;
        background: #0d0d0d; /* Fond très sombre */
        color: var(--text-dark);
        line-height: 1.6;
        position: relative;
        -webkit-text-size-adjust: 100%;
        touch-action: pan-y;
        padding: 15px;
        user-select: none;
        -webkit-user-select: none;
    }
    
    /* Désactivation du zoom (conservation du code utilisateur) */
    body * {
        -webkit-user-select: none;
        -webkit-touch-callout: none;
        -webkit-tap-highlight-color: transparent;
    }

    /* Arrière-plan géométrique élégant - Style sombre et vert */
    .background {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: 
            radial-gradient(circle at 10% 20%, rgba(118, 185, 0, 0.15) 0%, transparent 20%),
            radial-gradient(circle at 90% 80%, rgba(150, 211, 0, 0.15) 0%, transparent 20%),
            radial-gradient(circle at 20% 30%, rgba(118, 185, 0, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%, rgba(150, 211, 0, 0.1) 0%, transparent 50%),
            linear-gradient(135deg, #0d0d0d 0%, #1a1a1a 100%);
        z-index: -3;
    }
    
    .geometric-pattern {
        /* Rendu plus subtil sur fond sombre */
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: 
            linear-gradient(30deg, rgba(118, 185, 0, 0.08) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.08) 87.5%, rgba(118, 185, 0, 0.08) 0),
            linear-gradient(150deg, rgba(150, 211, 0, 0.08) 12%, transparent 12.5%, transparent 87%, rgba(150, 211, 0, 0.08) 87.5%, rgba(150, 211, 0, 0.08) 0),
            linear-gradient(30deg, rgba(118, 185, 0, 0.05) 12%, transparent 12.5%, transparent 87%, rgba(118, 185, 0, 0.05) 87.5%, rgba(118, 185, 0, 0.05) 0),
            linear-gradient(150deg, rgba(150, 211, 0, 0.05) 12%, transparent 12.5%, transparent 87%, rgba(150, 211, 0, 0.05) 87.5%, rgba(150, 211, 0, 0.05) 0),
            linear-gradient(60deg, rgba(118, 185, 0, 0.1) 25%, transparent 25.5%, transparent 75%, rgba(118, 185, 0, 0.1) 75%, rgba(118, 185, 0, 0.1) 0),
            linear-gradient(60deg, rgba(150, 211, 0, 0.1) 25%, transparent 25.5%, transparent 75%, rgba(150, 211, 0, 0.1) 75%, rgba(150, 211, 0, 0.1) 0);
        background-size: 100px 175px; /* Ajout des propriétés de background manquantes */
        background-position: 0 0, 0 0, 50px 87.5px, 50px 87.5px, 0 0, 50px 87.5px;
        z-index: -2;
        animation: patternShift 30s linear infinite;
    }
    
    /* ACCENTS LUMINEUX VERT */
    .blue-accent {
        position: fixed;
        top: 0;
        right: 0;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(118, 185, 0, 0.2) 0%, transparent 70%);
        filter: blur(60px);
        z-index: -1;
    }
    
    .purple-accent {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(150, 211, 0, 0.2) 0%, transparent 70%);
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
            rgba(118, 185, 0, 0.1) 40%,
            rgba(150, 211, 0, 0.1) 50%,
            rgba(118, 185, 0, 0.1) 60%,
            transparent 100%
        );
        opacity: 0.2;
        z-index: -1;
        animation: lightShine 15s infinite linear;
    }
    
    .light-beam { /* Ajout du style light-beam manquant */
        position: fixed;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
        animation: rotateBeam 25s linear infinite;
        z-index: -1;
    }
    
    /* Header compact */
    .header {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        animation: slideDown 0.8s ease-out;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    /* Logo - Utilisation du vert NVIDIA */
    .logo-icon {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, var(--accent-green), var(--green-dark));
        box-shadow: 0 8px 25px rgba(118, 185, 0, 0.4);
        border: none;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-white);
        font-weight: bold;
        font-size: 18px;
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
    
    /* Section principale avec carte premium et barre d'actions */
    .main-section {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        animation: fadeInUp 0.8s ease-out;
    }

    /* Carte premium principale - TAILLE RÉDUITE (60%) */
    .premium-card {
        width: 60%; /* Prend 60% de la largeur du .main-section */
        min-width: 250px; /* Taille minimale pour desktop */
        flex-shrink: 0; /* Empêche de rétrécir */
        
        background: linear-gradient(135deg, var(--premium-color-a), var(--premium-color-b));
        border-radius: 18px;
        padding: 18px; 
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.1);
        position: relative;
        overflow: hidden; 
        min-height: 160px; 
        display: flex; 
        flex-direction: column; 
        justify-content: space-between; 
    }
    
    .premium-card::before { /* Modification de before en after pour correspondre au style NVIDIA */
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(45deg, transparent, rgba(255,255,255,0.15), transparent);
        transform: translateX(-100%);
        animation: shimmer 3s infinite;
        z-index: 1; /* Assurer que shimmer est au-dessus du background de la carte */
    }
    
    .premium-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle at 100% 0%, rgba(118, 185, 0, 0.2) 0%, transparent 40%);
        pointer-events: none;
        z-index: 0;
    }
    
    .card-header { /* Ajout des styles manquants */
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        z-index: 2;
    }

    .card-logo {
        width: 32px;
        height: 32px;
        background: rgba(118, 185, 0, 0.2);
        border: 1.5px solid rgba(118, 185, 0, 0.3);
        color: var(--accent-green);
        box-shadow: 0 0 10px rgba(118, 185, 0, 0.3);
        border-radius: 8px; /* Ajout de border-radius manquant */
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 13px;
    }
    
    .balance-container {
        background: rgba(118, 185, 0, 0.1);
        border: 1px solid rgba(118, 185, 0, 0.2);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        border-radius: 12px; /* Ajout de border-radius manquant */
        padding: 12px; /* Ajout de padding manquant */
        margin-bottom: 12px; /* Ajout de margin-bottom manquant */
        z-index: 2;
    }
    
    .balance-label {
        font-size: 13px; /* Ajout de font-size manquant */
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 6px; /* Ajout de margin-bottom manquant */
        display: flex; /* Ajout de display flex manquant */
        align-items: center; /* Ajout de align-items manquant */
        gap: 6px; /* Ajout de gap manquant */
    }
    
    .balance-amount {
        font-size: 24px;
        font-weight: 700;
        color: var(--accent-green);
        text-shadow: 0 2px 8px rgba(118, 185, 0, 0.3);
        letter-spacing: 0.5px; /* Ajout de letter-spacing manquant */
    }
    
    .revenue-info {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 10px 12px; /* Ajout de padding manquant */
        border-radius: 10px; /* Ajout de border-radius manquant */
        display: flex; /* Ajout de display flex manquant */
        justify-content: space-between; /* Ajout de justify-content manquant */
        align-items: center; /* Ajout de align-items manquant */
        z-index: 2;
    }
    
    .revenue-label {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.7);
    }
    
    .revenue-amount {
        font-size: 14px;
        font-weight: 600;
        color: var(--accent-green);
    }
    
    /* Barre d'actions HORIZONTALE - Remplacant la barre verticale */
    .action-bar {
        flex: 1; /* Prend l'espace restant (40% de la largeur du .main-section) */
        background: var(--card-bg);
        border-radius: 14px;
        padding: 12px;
        display: flex;
        flex-direction: row; /* AFFICHAGE HORIZONTAL */
        gap: 10px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(118, 185, 0, 0.1);
    }
    
    .action-item { 
        flex: 1; /* Distribue les 3 boutons de manière égale */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center; /* Centrer verticalement */
        gap: 5px;
        cursor: pointer;
        transition: var(--transition);
        padding: 6px 0;
        border-radius: 8px;
        min-height: 100%; /* S'assurer qu'il prend toute la hauteur disponible */
    }

    .action-item:hover {
        background: rgba(118, 185, 0, 0.1);
        transform: translateY(-2px); /* Ajout de transform manquant */
    }
    
    .action-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        color: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }
    
    .action-retrait .action-icon {
        background: linear-gradient(135deg, var(--accent-green), var(--green-dark));
    }
    
    .action-roue .action-icon {
        background: linear-gradient(135deg, #008000, #006400); /* Vert foncé différent */
    }
    
    .action-cadeau .action-icon {
        background: linear-gradient(135deg, #10b981, #059669); /* Vert existant */
    }
    
    .action-label {
        font-size: 10px;
        color: var(--text-dark);
        font-weight: 600;
        text-align: center;
    }
    
    /* Barre de notifications */
    .notification-bar {
        background: var(--card-bg);
        border: 1px solid rgba(118, 185, 0, 0.1);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        border-radius: 14px; /* Ajout de border-radius manquant */
        padding: 16px; /* Ajout de padding manquant */
        margin-bottom: 20px; /* Ajout de margin-bottom manquant */
        position: relative; /* Ajout de position relative manquant */
        overflow: hidden; /* Ajout d'overflow hidden manquant */
        height: 70px; /* Ajout de height manquant */
        animation: fadeInUp 1s ease-out; /* Ajout d'animation manquant */
    }

    .notification-container {
        position: relative;
        height: 100%;
        overflow: hidden;
    }
    
    .notification-item {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 700;
        color: var(--accent-green);
        text-align: center;
        padding: 0 20px;
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        background: rgba(118, 185, 0, 0.1);
        border: 1px solid rgba(118, 185, 0, 0.2);
        border-radius: 10px;
    }
    
    .notification-item.active {
        opacity: 1;
        transform: translateY(0);
    }
    
    .phone-mask {
        color: var(--text-dark);
        font-weight: 600;
        letter-spacing: 1px;
        font-family: 'Courier New', monospace;
    }
    
    .amount {
        color: var(--accent-green);
        font-weight: 700; /* Ajout de font-weight manquant */
        margin-left: 4px; /* Ajout de margin-left manquant */
    }
    
    /* Boutons d'action horizontaux */
    .horizontal-actions {
        display: flex; /* Ajout de display flex manquant */
        justify-content: space-between; /* Ajout de justify-content manquant */
        gap: 10px; /* Ajout de gap manquant */
        margin-bottom: 20px; /* Ajout de margin-bottom manquant */
        animation: fadeInUp 1.2s ease-out; /* Ajout d'animation manquant */
    }
    
    .h-action-btn {
        flex: 1; /* Ajout de flex manquant */
        background: var(--card-bg);
        border: 1px solid rgba(118, 185, 0, 0.1);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        color: var(--text-dark);
        border-radius: 12px; /* Ajout de border-radius manquant */
        padding: 12px 8px; /* Ajout de padding manquant */
        display: flex; /* Ajout de display flex manquant */
        flex-direction: column; /* Ajout de flex-direction manquant */
        align-items: center; /* Ajout de align-items manquant */
        gap: 6px; /* Ajout de gap manquant */
        text-decoration: none; /* Ajout de text-decoration manquant */
        transition: var(--transition); /* Ajout de transition manquant */
        cursor: pointer; /* Ajout de cursor manquant */
    }
    
    .h-action-btn:hover {
        background: rgba(118, 185, 0, 0.1);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4);
        transform: translateY(-3px); /* Ajout de transform manquant */
    }
    
    .h-action-icon {
        font-size: 18px; /* Ajout de font-size manquant */
        color: var(--accent-green);
    }
    
    .h-action-text {
        font-size: 11px; /* Ajout de font-size manquant */
        font-weight: 600; /* Ajout de font-weight manquant */
        text-align: center; /* Ajout de text-align manquant */
    }
    
    /* Section Photos Slider */
    .photos-section { /* Ajout des styles manquants */
        margin-bottom: 20px;
        animation: fadeInUp 1.4s ease-out;
    }

    .photos-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--accent-green);
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .photo-slider {
        background: var(--card-bg);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(118, 185, 0, 0.1);
        border-radius: 16px;
        overflow: hidden;
        position: relative;
        height: 220px;
    }

    .photo-slides {
        position: relative;
        width: 100%;
        height: 100%;
        overflow: hidden;
    }

    .photo-slide {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        transform: scale(1.1);
        transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.95);
    }

    .photo-slide.active {
        opacity: 1;
        transform: scale(1);
    }

    .photo-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .photo-overlay {
        position: absolute; /* Ajout de position absolute manquant */
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
        padding: 20px;
        color: white;
        text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    }
    
    .photo-title { /* Ajout des styles manquants */
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .photo-subtitle { /* Ajout des styles manquants */
        font-size: 14px;
        opacity: 0.9;
    }

    .photo-indicators {
        display: flex;
        justify-content: center;
        gap: 8px;
        padding: 12px;
        background: rgba(118, 185, 0, 0.05);
    }
    
    .photo-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: rgba(118, 185, 0, 0.3);
        transition: var(--transition);
        cursor: pointer;
    }
    
    .photo-indicator.active {
        background: var(--accent-green);
        transform: scale(1.2); /* Ajout de transform manquant */
    }
    
    /* Section Posts */
    .posts-section { /* Ajout des styles manquants */
        margin-bottom: 80px;
        animation: fadeInUp 1.6s ease-out;
    }
    
    .posts-header { /* Ajout des styles manquants */
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .posts-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--accent-green);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .post-btn {
        background: linear-gradient(135deg, var(--accent-green), var(--green-light));
        box-shadow: 0 4px 12px rgba(118, 185, 0, 0.3);
        color: var(--primary-white);
        border: none;
        padding: 8px 15px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .post-btn:hover {
        box-shadow: 0 6px 15px rgba(118, 185, 0, 0.4);
        transform: translateY(-2px); /* Ajout de transform manquant */
    }
    
    /* Styles pour le slider des posts */
    .post-slider-container { /* Ajout des styles manquants */
        margin-top: 15px;
    }

    .post-slider {
        background: var(--card-bg);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(118, 185, 0, 0.1);
        border-radius: 16px;
        overflow: hidden;
        position: relative;
        height: 320px;
    }

    .post-slides {
        position: relative;
        width: 100%;
        height: 100%;
        overflow: hidden;
    }

    .post-slide {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.95);
    }

    .post-slide.active {
        opacity: 1;
        transform: translateX(0);
    }

    .post-slide.prev {
        transform: translateX(-100%);
    }

    .post-image-container {
        width: 100%;
        height: 250px;
        position: relative;
        overflow: hidden;
    }

    .post-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .post-content {
        padding: 15px;
        background: rgba(118, 185, 0, 0.05);
    }
    
    .post-text {
        font-size: 14px;
        color: var(--text-dark);
        line-height: 1.5;
        text-align: center;
    }
    
    .post-indicators {
        display: flex;
        justify-content: center;
        gap: 8px;
        padding: 12px;
        background: rgba(118, 185, 0, 0.05);
    }
    
    .post-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: rgba(118, 185, 0, 0.3);
        transition: var(--transition);
        cursor: pointer;
    }
    
    .post-indicator.active {
        background: var(--accent-green);
        transform: scale(1.2); /* Ajout de transform manquant */
    }
    
    /* Popups améliorés */
    .popup {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
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
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        border: 2px solid var(--accent-green);
        max-height: 85vh;
        overflow-y: auto;
    }
    
    .popup-header {
        padding: 20px;
        background: linear-gradient(135deg, var(--accent-green), var(--green-dark));
        color: var(--primary-white);
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
        color: var(--primary-white);
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
        transform: scale(1.1); /* Ajout de transform manquant */
    }
    
    .popup-body {
        padding: 25px 20px 20px 20px;
    }
    
    .form-group { /* Ajout des styles manquants */
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--text-dark);
        font-size: 14px;
    }
    
    .form-input, .form-textarea {
        width: 100%;
        padding: 12px;
        background: rgba(118, 185, 0, 0.05);
        border: 1px solid rgba(118, 185, 0, 0.2);
        border-radius: 8px;
        font-size: 14px;
        color: var(--text-dark);
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    
    .form-textarea { /* Ajout des styles manquants */
        resize: vertical;
        min-height: 100px;
    }

    .form-input:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--accent-green);
        box-shadow: 0 0 0 2px rgba(118, 185, 0, 0.2);
    }
    
    .file-upload { /* Ajout des styles manquants */
        position: relative;
        display: inline-block;
        width: 100%;
    }

    .file-upload-btn {
        background: rgba(118, 185, 0, 0.05);
        border: 1px dashed rgba(118, 185, 0, 0.3);
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        width: 100%;
        position: relative;
    }
    
    .file-upload-btn:hover {
        background: rgba(118, 185, 0, 0.1);
        border-color: var(--accent-green);
    }
    
    .file-upload-input { /* Ajout des styles manquants */
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    .image-preview-small {
        width: 80px;
        height: 80px;
        border-radius: 8px;
        object-fit: cover;
        border: 2px solid var(--accent-green);
        position: absolute;
        top: 50%;
        right: 15px;
        transform: translateY(-50%);
        display: none;
    }
    
    .submit-btn {
        background: linear-gradient(135deg, var(--accent-green), var(--green-light));
        box-shadow: 0 4px 12px rgba(118, 185, 0, 0.3);
        color: var(--primary-white);
        border: none;
        padding: 15px;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        width: 100%;
        margin-top: 10px;
    }
    
    .submit-btn:hover {
        box-shadow: 0 6px 15px rgba(118, 185, 0, 0.4);
        transform: translateY(-2px); /* Ajout de transform manquant */
    }
    
    .community-buttons { /* Ajout des styles manquants */
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .community-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px;
        border-radius: 12px;
        background: rgba(118, 185, 0, 0.05);
        transition: var(--transition);
        cursor: pointer;
        text-decoration: none;
        color: var(--text-dark);
    }
    
    .community-btn:hover {
        background: rgba(118, 185, 0, 0.1);
        transform: translateX(5px);
    }
    
    .community-btn i { /* Ajout des styles manquants */
        font-size: 24px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        flex-shrink: 0;
    }
    
    .community-btn-telegram i {
        background: linear-gradient(135deg, #0088cc, #005580); /* Telegram Blue - Conservé pour l'identité de l'app */
    }
    
    .community-btn-text { /* Ajout des styles manquants */
        flex: 1;
    }

    .community-btn-text h4 {
        margin-bottom: 4px;
        font-size: 15px;
        color: var(--text-dark);
        font-weight: 700;
    }
    
    .community-btn-text p { /* Ajout des styles manquants */
        font-size: 13px;
        color: var(--text-gray);
    }
    
    /* Welcome Popup */
    .welcome-popup { /* Ajout des styles manquants */
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(10px);
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: fadeIn 0.3s ease;
    }

    .welcome-content {
        background: var(--card-bg);
        width: 100%;
        max-width: 350px;
        border-radius: 20px;
        overflow: hidden;
        animation: popupIn 0.5s ease;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        border: 2px solid var(--accent-green);
        text-align: center;
        position: relative;
    }
    
    .welcome-header {
        padding: 25px 20px 15px 20px;
        background: linear-gradient(135deg, var(--accent-green), var(--green-light));
        color: var(--primary-white);
        position: relative;
    }
    
    .welcome-close-top {
        position: absolute;
        top: 15px;
        right: 15px;
        background: none;
        border: none;
        color: var(--primary-white);
        font-size: 22px;
        cursor: pointer;
        transition: var(--transition);
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: bold;
    }
    
    .welcome-close-top:hover {
        background: rgba(0, 0, 0, 0.2);
        transform: scale(1.1); /* Ajout de transform manquant */
    }
    
    .welcome-header h3 { /* Ajout des styles manquants */
        font-weight: 700;
        font-size: 18px;
        margin-bottom: 5px;
    }
    
    .welcome-message { /* Ajout des styles manquants */
        font-size: 14px;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 10px;
        line-height: 1.4;
    }

    .welcome-body { /* Ajout des styles manquants */
        padding: 20px 20px 20px 20px;
    }
    
    .bonus-section {
        background: linear-gradient(135deg, var(--accent-green), var(--green-dark));
        box-shadow: 0 4px 12px rgba(118, 185, 0, 0.3);
        color: white;
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 15px;
        text-align: center;
    }
    
    .bonus-title { /* Ajout des styles manquants */
        font-weight: 700;
        font-size: 16px;
        margin-bottom: 8px;
    }

    .bonus-text { /* Ajout des styles manquants */
        font-size: 13px;
        line-height: 1.4;
        opacity: 0.9;
    }
    
    .welcome-buttons { /* Ajout des styles manquants */
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 15px;
    }

    .welcome-btn { /* Ajout des styles manquants */
        padding: 14px;
        border-radius: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-size: 15px;
        border: none;
    }
    
    .welcome-btn-join {
        background: linear-gradient(135deg, var(--accent-green), var(--green-dark));
        color: #1e1e1e; /* Texte noir sur bouton vert vif */
        font-size: 16px;
        padding: 16px;
    }
    
    .welcome-btn-close {
        background: rgba(118, 185, 0, 0.05);
        color: var(--text-dark);
        border: 1px solid rgba(118, 185, 0, 0.2);
    }
    
    .welcome-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }
    
    .button-row { /* Ajout des styles manquants */
        display: flex;
        gap: 10px;
    }

    .button-row .welcome-btn { /* Ajout des styles manquants */
        flex: 1;
        padding: 12px;
        font-size: 14px;
    }
    
    /* Bouton Service Client Flottant */
    .floating-service {
        position: fixed;
        bottom: 100px;
        right: 20px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--accent-green), var(--green-light));
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
        animation: pulse-green 0.4s ease;
    }
    
    .floating-service:active i {
        transform: scaleX(-1) rotate(15deg) scale(1.1);
    }

    @keyframes pulse-green {
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
    
    /* Popup Service Client */
    .service-popup {
        position: fixed;
        bottom: 170px;
        right: 20px;
        background: var(--card-bg);
        border-radius: 16px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
        z-index: 100;
        padding: 0;
        display: none;
        flex-direction: column;
        animation: slideUp 0.3s ease;
        border: 1px solid var(--accent-green);
        width: 200px;
        overflow: hidden;
    }
    
    .service-header {
        padding: 15px;
        background: linear-gradient(135deg, var(--accent-green), var(--green-light));
        color: var(--primary-white);
        text-align: center;
        font-weight: 700;
        font-size: 14px;
    }
    
    .service-option {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px;
        cursor: pointer;
        transition: var(--transition);
        background: rgba(118, 185, 0, 0.05);
        border: none;
        width: 100%;
        color: var(--text-dark);
        font-size: 14px;
        font-weight: 600;
    }
    
    .service-option:hover {
        background: rgba(118, 185, 0, 0.1);
    }
    
    .service-option i {
        font-size: 18px;
        width: 24px;
        text-align: center;
        color: var(--accent-green);
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
    
    @keyframes slideInUp {
        from { 
            opacity: 0;
            transform: translateY(30px);
        }
        to { 
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideUp {
        from { 
            opacity: 0;
            transform: translateY(20px);
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
    
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    
    @keyframes popupIn {
        from { transform: scale(0.9); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        /* Stacker le main-section sur tablette/mobile */
        .main-section {
            flex-direction: column; 
            gap: 15px;
        }

        /* La carte et la barre d'action reprennent toute la largeur sur mobile */
        .premium-card {
            width: 100%; 
            min-width: 0;
        }
        
        .action-bar {
            width: 100%; 
            flex-direction: row; 
            padding: 12px;
            min-height: auto;
        }

        .action-item {
             min-height: auto;
        }
    }

    @media (max-width: 480px) {
        body {
            padding: 12px;
        }
        
        .premium-card {
            padding: 15px;
            min-height: 150px;
        }
        
        .balance-amount {
            font-size: 22px;
        }
        
        .action-icon {
            width: 32px;
            height: 32px;
            font-size: 14px;
        }
        
        .action-label {
            font-size: 9px;
        }
        
        .horizontal-actions {
            gap: 8px;
        }
        
        .h-action-btn {
            padding: 10px 6px;
        }
        
        .h-action-icon {
            font-size: 16px;
        }
        
        .h-action-text {
            font-size: 10px;
        }
        
        .notification-bar {
            height: 65px;
            padding: 14px;
        }
        
        .notification-item {
            font-size: 15px;
        }
        
        .photo-slider {
            height: 200px;
        }
        
        .post-slider {
            height: 300px;
        }
        
        .post-image-container {
            height: 220px;
        }
        
        .welcome-content {
            max-width: 300px;
        }
        
        .floating-service {
            width: 55px;
            height: 55px;
            bottom: 90px;
            right: 15px;
        }
        
        .service-popup {
            width: 180px;
            bottom: 155px;
            right: 15px;
        }
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

        <div class="photos-section">
            <div class="photos-title">
                <i class="fas fa-images"></i>
                NVIDIA Technologie
            </div>
            
            <div class="photo-slider" id="photoSlider">
                <div class="photo-slides">
                    <div class="photo-slide active">
                        <img src="photo1.jpg" class="photo-image" alt="NVIDIA Technology">
                        <div class="photo-overlay">
                            <div class="photo-title">Investissez dans l'Innovation</div>
                            <div class="photo-subtitle">Leader mondial de la technologie</div>
                        </div>
                    </div>
                    
                    <div class="photo-slide">
                        <img src="photo2.jpg" class="photo-image" alt="Croissance Rapide">
                        <div class="photo-overlay">
                            <div class="photo-title">Croissance Rapide</div>
                            <div class="photo-subtitle">Maximisez vos rendements</div>
                        </div>
                    </div>
                    
                    <div class="photo-slide">
                        <img src="photo3.jpg" class="photo-image" alt="Communauté Active">
                        <div class="photo-overlay">
                            <div class="photo-title">Communauté Active</div>
                            <div class="photo-subtitle">Rejoignez des milliers d'utilisateurs</div>
                        </div>
                    </div>
                    
                    <div class="photo-slide">
                        <img src="photo4.jpg" class="photo-image" alt="Actions VIP">
                        <div class="photo-overlay">
                            <div class="photo-title">Actions VIP NVIDIA</div>
                            <div class="photo-subtitle">Investissements exclusifs dès le 14 octobre</div>
                        </div>
                    </div>
                    
                    <div class="photo-slide">
                        <img src="photo5.jpg" class="photo-image" alt="Innovation Technologique">
                        <div class="photo-overlay">
                            <div class="photo-title">Innovation Technologique</div>
                            <div class="photo-subtitle">Expertise mondiale à votre service</div>
                        </div>
                    </div>
                    
                    <div class="photo-slide">
                        <img src="photo6.jpg" class="photo-image" alt="Opportunités NVIDIA">
                        <div class="photo-overlay">
                            <div class="photo-title">Opportunités NVIDIA</div>
                            <div class="photo-subtitle">Diversifiez votre portefeuille</div>
                        </div>
                    </div>
                </div>
                <div class="photo-indicators">
                    <div class="photo-indicator active" onclick="showPhoto(0)"></div>
                    <div class="photo-indicator" onclick="showPhoto(1)"></div>
                    <div class="photo-indicator" onclick="showPhoto(2)"></div>
                    <div class="photo-indicator" onclick="showPhoto(3)"></div>
                    <div class="photo-indicator" onclick="showPhoto(4)"></div>
                    <div class="photo-indicator" onclick="showPhoto(5)"></div>
                </div>
            </div>
        </div>
        
        <div class="main-section">
            <div class="premium-card">
                <div class="card-header">
                    <div class="balance-label">
                        <i class="fas fa-wallet"></i> Solde Principal
                    </div>
                    <div class="card-logo">NV</div>
                </div>
                
                <div class="balance-container">
                    <div class="balance-amount"><?= number_format($user['solde'], 0, ',', ' ') ?> <?= $devise ?></div>
                </div>
                
                <div class="revenue-info">
                    <div class="revenue-label">Revenus Totaux</div>
                    <div class="revenue-amount"><?= number_format($revenus['total'] ?? 0, 0, ',', ' ') ?> <?= $devise ?></div>
                </div>
            </div>
            
            <div class="action-bar">
                <div class="action-item action-retrait" onclick="window.location.href='retrait.php'">
                    <div class="action-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="action-label">Retrait</div>
                </div>
                
                <div class="action-item action-roue" onclick="window.location.href='roue.php'">
                    <div class="action-icon">
                        <i class="fas fa-dice"></i>
                    </div>
                    <div class="action-label">Roue</div>
                </div>
                
                <div class="action-item action-cadeau" onclick="window.location.href='cadeau.php'">
                    <div class="action-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div class="action-label">Cadeau</div>
                </div>
            </div>
        </div>
        
        <div class="horizontal-actions">
            <a href="depot.php" class="h-action-btn">
                <i class="fas fa-arrow-down h-action-icon"></i>
                <span class="h-action-text">Dépôt</span>
            </a>
            
            <a href="faq.php" class="h-action-btn">
                <i class="fas fa-play-circle h-action-icon"></i>
                <span class="h-action-text">Tuto</span>
            </a>
            
            <a href="commande.php" class="h-action-btn">
                <i class="fas fa-crown h-action-icon"></i>
                <span class="h-action-text">VIP</span>
            </a>
            
            <button class="h-action-btn" onclick="openTelegramPopup()">
                <i class="fab fa-telegram h-action-icon"></i>
                <span class="h-action-text">Rejoindre</span>
            </button>
        </div>
        
        <div class="notification-bar">
            <div class="notification-container" id="notificationContainer">
                </div>
        </div>
        
        <div class="posts-section">
            <div class="posts-header">
                <div class="posts-title">
                    <i class="fas fa-newspaper"></i>
                    Actualités Communauté
                </div>
                <button class="post-btn" onclick="openPostPopup()">
                    <i class="fas fa-plus"></i>
                    Poster
                </button>
            </div>
            
            <div class="post-slider-container">
                <div class="post-slider" id="postSlider">
                    <div class="post-slides">
                        <?php if (count($posts_data) > 0): ?>
                            <?php foreach ($posts_data as $index => $post): ?>
                                <div class="post-slide <?= $index === 0 ? 'active' : '' ?>">
                                    <div class="post-image-container">
                                        <img src="uploads/<?= htmlspecialchars($post['image']) ?>" class="post-image" alt="Post image" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iIzIyMiIvPjx0ZXh0IHg9IjEwMCIgeT0iMTAwIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTgiIGZpbGw9IiM2NjYiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5JbWFnZTwvdGV4dD48L2ZzZz4='"/>
                                    </div>
                                    <div class="post-content">
                                        <div class="post-text"><?= htmlspecialchars($post['message']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="post-slide active">
                                <div class="post-image-container" style="display: flex; align-items: center; justify-content: center; color: var(--text-gray); background: var(--light-gray);">
                                    <div style="text-align: center;">
                                        <i class="fas fa-images" style="font-size: 40px; margin-bottom: 10px;"></i>
                                        <p>Aucun post pour le moment</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($posts_data) > 1): ?>
                    <div class="post-indicators">
                        <?php foreach ($posts_data as $index => $post): ?>
                            <div class="post-indicator <?= $index === 0 ? 'active' : '' ?>" onclick="showPost(<?= $index ?>)"></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <button class="floating-service" onclick="toggleServicePopup()">
        <i class="fas fa-phone-alt"></i>
    </button>
    
    <div class="service-popup" id="servicePopup">
        <div class="service-header">
            Service Client
        </div>
        <button class="service-option" onclick="contactCustomerService()">
            <i class="fas fa-comment-dots"></i>
            <span>Nous contacter</span>
        </button>
    </div>
    
    <div class="welcome-popup" id="welcomePopup">
        <div class="welcome-content">
            <div class="welcome-header">
                <h3>NVIDIA Technologie</h3>
                <div class="welcome-message">
                    NVIDIA est le leader mondial de la technologie de l'informatique accélérée et de l'intelligence artificielle. Nous proposons désormais d'investir dans nos actions VIP dès le 14 octobre.
                </div>
                <button class="welcome-close-top" onclick="closeWelcomePopup()">&times;</button>
            </div>
            <div class="welcome-body">
                <div class="bonus-section">
                    <div class="bonus-title">🎁 Bonus d'inscription : 250 FCFA</div>
                    <div class="bonus-text">Invitez vos amis et gagnez 20% de leur investissement</div>
                </div>
                
                <div class="welcome-buttons">
                    <div class="button-row">
                        <a href="https://t.me/officielcanalBlackRock" class="welcome-btn welcome-btn-join">
                            <i class="fab fa-telegram"></i>
                            Canal
                        </a>
                        <a href="https://t.me/groupeofficielblackrock" class="welcome-btn welcome-btn-join">
                            <i class="fab fa-telegram"></i>
                            Groupe
                        </a>
                    </div>
                    <button class="welcome-btn welcome-btn-close" onclick="closeWelcomePopup()">
                        <i class="fas fa-times"></i>
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="popup" id="telegramPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h3>Rejoignez notre Communauté NVIDIA</h3>
                <button class="popup-close" onclick="closePopup('telegramPopup')">&times;</button>
            </div>
            <div class="popup-body">
                <p class="popup-text">
                    Restez connecté avec la communauté NVIDIA et ne manquez aucune opportunité d'investissement!
                </p>
                <div class="community-buttons">
                    <a href="https://t.me/officielcanalBlackRock" class="community-btn community-btn-telegram">
                        <i class="fab fa-telegram"></i>
                        <div class="community-btn-text">
                            <h4>Canal Officiel</h4>
                            <p>Annonces et actualités</p>
                        </div>
                    </a>
                    <a href="https://t.me/groupeofficielblackrock" class="community-btn community-btn-telegram">
                        <i class="fab fa-telegram"></i>
                        <div class="community-btn-text">
                            <h4>Groupe Discussion</h4>
                            <p>Échangez avec la communauté</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="popup" id="postPopup">
        <div class="popup-content">
            <div class="popup-header">
                <h3>Créer un post</h3>
                <button class="popup-close" onclick="closePopup('postPopup')">&times;</button>
            </div>
            <div class="popup-body">
                <form id="postForm" enctype="multipart/form-data" method="POST">
                    <div class="form-group">
                        <label class="form-label" for="postMessage">Message</label>
                        <textarea id="postMessage" name="message" class="form-textarea" placeholder="Partagez votre expérience NVIDIA..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="postImage">Image</label>
                        <div class="file-upload">
                            <div class="file-upload-btn">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 24px; margin-bottom: 8px;"></i>
                                <div>Cliquez pour télécharger une image</div>
                                <div style="font-size: 12px; color: var(--text-gray); margin-top: 4px;">PNG, JPG, JPEG max 5MB</div>
                                <img id="imagePreview" class="image-preview-small" alt="Aperçu">
                            </div>
                            <input type="file" id="postImage" name="image" class="file-upload-input" accept="image/*" required onchange="previewImage(this)">
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> Publier le post
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // FONCTIONS DE GESTION DES POPUPS
        function closePopup(id) {
            const popup = document.getElementById(id);
            if (popup) {
                popup.style.display = 'none';
            }
        }

        function closeWelcomePopup() {
            closePopup('welcomePopup');
        }

        function openTelegramPopup() {
            document.getElementById('telegramPopup').style.display = 'flex';
        }

        function openPostPopup() {
            document.getElementById('postPopup').style.display = 'flex';
        }

        // BOUTON SERVICE CLIENT FLOTTANT
        function toggleServicePopup() {
            const popup = document.getElementById('servicePopup');
            popup.style.display = popup.style.display === 'flex' ? 'none' : 'flex';
        }

        function contactCustomerService() {
            window.open('https://t.me/Blackrockserviceclient', '_blank');
            closePopup('servicePopup');
        }

        // GÉNÉRATION DES NOTIFICATIONS
        function generateNotifications() {
            const container = document.getElementById('notificationContainer');
            const notifications = [];
            
            for (let i = 0; i < 15; i++) {
                const type = Math.random();
                let notification;
                
                if (type < 0.5) {
                    const phone = generatePhoneNumber();
                    notification = { text: `2******${phone.slice(-3)} nous a rejoint` };
                } else if (type < 0.9) {
                    const phone = generatePhoneNumber();
                    const amount = generateDepositAmount();
                    notification = { text: `2******${phone.slice(-3)} a déposé <span class="amount">${amount.toLocaleString()} <?= $devise ?></span>` };
                } else {
                    const phone = generatePhoneNumber();
                    const amount = generateWithdrawalAmount();
                    notification = { text: `2******${phone.slice(-3)} a retiré <span class="amount">${amount.toLocaleString()} <?= $devise ?></span>` };
                }
                
                notifications.push(notification);
            }
            
            notifications.forEach((notif) => {
                const item = document.createElement('div');
                item.className = 'notification-item';
                item.innerHTML = notif.text;
                container.appendChild(item);
            });
            
            let currentIndex = 0;
            const items = container.querySelectorAll('.notification-item');
            
            function showNextNotification() {
                items.forEach(item => item.classList.remove('active'));
                if (items[currentIndex]) items[currentIndex].classList.add('active');
                currentIndex = (currentIndex + 1) % items.length;
            }
            
            if (items.length > 0) items[0].classList.add('active');
            setInterval(showNextNotification, 3000);
        }
        
        function generatePhoneNumber() {
            const prefixes = ['65', '66', '67', '68', '69', '70', '76', '77', '78', '79'];
            const prefix = prefixes[Math.floor(Math.random() * prefixes.length)];
            const suffix = Math.floor(Math.random() * 1000000).toString().padStart(6, '0');
            return prefix + suffix;
        }
        
        function generateDepositAmount() {
            const rand = Math.random();
            let amount = rand < 0.5 ? Math.floor(Math.random() * 22000 + 3000) : 
                         rand < 0.9 ? Math.floor(Math.random() * 45000 + 25000) : 
                         Math.floor(Math.random() * 180000 + 70000);
            
            const multipleType = Math.random();
            if (multipleType < 0.7) amount = Math.round(amount / 500) * 500;
            else if (multipleType < 0.9) amount = Math.round(amount / 100) * 100;
            else amount = Math.round(amount / 50) * 50;
            
            return amount;
        }
        
        function generateWithdrawalAmount() {
            const rand = Math.random();
            let amount = rand < 0.7 ? Math.floor(Math.random() * 9000 + 1000) : Math.floor(Math.random() * 20000 + 10000);
            
            const multipleType = Math.random();
            if (multipleType < 0.5) amount = Math.round(amount / 500) * 500;
            else if (multipleType < 0.8) amount = Math.round(amount / 100) * 100;
            else amount = Math.round(amount / 50) * 50;
            
            return amount;
        }

        // GESTION DU SLIDER DE PHOTOS - Animation ZOOM - MAINTENANT 6 IMAGES
        let currentPhotoIndex = 0;
        const photoSlides = document.querySelectorAll('.photo-slide');
        const photoIndicators = document.querySelectorAll('.photo-indicator');
        let photoInterval;
        
        function showPhoto(index) {
            photoSlides.forEach(slide => slide.classList.remove('active'));
            photoIndicators.forEach(indicator => indicator.classList.remove('active'));
            
            photoSlides[index].classList.add('active');
            photoIndicators[index].classList.add('active');
            currentPhotoIndex = index;
        }
        
        function nextPhoto() {
            const nextIndex = (currentPhotoIndex + 1) % photoSlides.length;
            showPhoto(nextIndex);
        }
        
        function startPhotoSlider() {
            photoInterval = setInterval(nextPhoto, 4000);
        }

        // GESTION DU SLIDER DES POSTS - Défilement automatique
        let currentPostIndex = 0;
        const postSlides = document.querySelectorAll('.post-slide');
        const postIndicators = document.querySelectorAll('.post-indicator');
        let postInterval;
        
        function showPost(index) {
            postSlides.forEach(slide => {
                slide.classList.remove('active', 'prev');
            });
            postIndicators.forEach(indicator => indicator.classList.remove('active'));
            
            // Animation de transition
            if (postSlides[currentPostIndex]) {
                postSlides[currentPostIndex].classList.add('prev');
            }
            
            postSlides[index].classList.add('active');
            postIndicators[index].classList.add('active');
            currentPostIndex = index;
        }
        
        function nextPost() {
            const nextIndex = (currentPostIndex + 1) % postSlides.length;
            showPost(nextIndex);
        }
        
        function startPostSlider() {
            if (postSlides.length > 1) {
                postInterval = setInterval(nextPost, 4000); // Défilement toutes les 4 secondes
            }
        }

        // PRÉVISUALISATION D'IMAGE AMÉLIORÉE
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }

        // GESTION DU FORMULAIRE DE POST
        document.getElementById('postForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                alert('Votre post a été soumis et sera vérifié avant publication!');
                closePopup('postPopup');
                this.reset();
                document.getElementById('imagePreview').style.display = 'none';
                setTimeout(() => location.reload(), 2000);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors de la publication du post.');
            });
        });

        // FERMETURE DES POPUPS EN CLIQUANT À L'EXTÉRIEUR
        window.onclick = function(event) {
            if (event.target.classList.contains('popup')) {
                event.target.style.display = 'none';
            }
            if (event.target.classList.contains('welcome-popup')) {
                event.target.style.display = 'none';
            }
            if (!event.target.closest('.service-popup') && !event.target.closest('.floating-service')) {
                document.getElementById('servicePopup').style.display = 'none';
            }
        }

        // EMPÊCHER LE ZOOM SUR MOBILE
        document.addEventListener('wheel', function(e) {
            if (e.ctrlKey) e.preventDefault();
        }, { passive: false });
        
        document.addEventListener('gesturestart', function(e) {
            e.preventDefault();
        });
        
        // INITIALISATION
        document.addEventListener('DOMContentLoaded', function() {
            generateNotifications();
            startPhotoSlider();
            startPostSlider();
            
            // Afficher le popup de bienvenue après 1 seconde
            setTimeout(() => {
                document.getElementById('welcomePopup').style.display = 'flex';
            }, 1000);
            
            // Gestion du swipe pour le slider de photos
            const photoSlider = document.getElementById('photoSlider');
            let startX = 0;
            
            photoSlider.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                clearInterval(photoInterval);
            });
            
            photoSlider.addEventListener('touchend', (e) => {
                const endX = e.changedTouches[0].clientX;
                const diff = startX - endX;
                
                if (Math.abs(diff) > 50) {
                    if (diff > 0) {
                        nextPhoto();
                    } else {
                        const prevIndex = (currentPhotoIndex - 1 + photoSlides.length) % photoSlides.length;
                        showPhoto(prevIndex);
                    }
                }
                startPhotoSlider();
            });
            
            // Gestion du swipe pour le slider des posts
            const postSlider = document.getElementById('postSlider');
            if (postSlider) {
                let postStartX = 0;
                
                postSlider.addEventListener('touchstart', (e) => {
                    postStartX = e.touches[0].clientX;
                    clearInterval(postInterval);
                });
                
                postSlider.addEventListener('touchend', (e) => {
                    const endX = e.changedTouches[0].clientX;
                    const diff = postStartX - endX;
                    
                    if (Math.abs(diff) > 50) {
                        if (diff > 0) {
                            nextPost();
                        } else {
                            const prevIndex = (currentPostIndex - 1 + postSlides.length) % postSlides.length;
                            showPost(prevIndex);
                        }
                    }
                    startPostSlider();
                });
            }
        });
    </script>
</body>
</html>