<?php
session_start();
require_once 'db.php';
include 'verify.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$query = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$query->execute([$user_id]);
$user = $query->fetch();

// Récupérer le solde depuis la table soldes
$solde_query = $db->prepare("SELECT solde FROM soldes WHERE user_id = ?");
$solde_query->execute([$user_id]);
$solde_data = $solde_query->fetch();
$solde_utilisateur = $solde_data ? $solde_data['solde'] : 0;

// Vérifier si l'utilisateur a un portefeuille
$has_wallet = false;
$wallet_query = $db->prepare("SELECT * FROM portefeuilles WHERE user_id = ?");
$wallet_query->execute([$user_id]);
if ($wallet_query->rowCount() > 0) {
    $has_wallet = true;
    $wallet_data = $wallet_query->fetch();
}

// Vérifier si l'utilisateur a un mot de passe de transaction
$has_transaction_password = false;
$transaction_password_query = $db->prepare("SELECT * FROM transaction_passwords WHERE user_id = ?");
$transaction_password_query->execute([$user_id]);
if ($transaction_password_query->rowCount() > 0) {
    $has_transaction_password = true;
}

// Vérifier si les retraits sont disponibles (Lundi à Samedi, 9h-19h GMT)
$heure_gmt = gmdate('H');
$jour_semaine = gmdate('N'); // 1 (lundi) à 7 (dimanche)
$retraits_disponibles = ($jour_semaine >= 1 && $jour_semaine <= 6) && ($heure_gmt >= 9 && $heure_gmt < 19);

// Initialiser les variables
$message = "";
$peut_retirer = true;
$message_erreur = "";

// Vérifier les conditions pour le retrait (ne pas afficher les messages d'erreur au chargement)
if (!$has_wallet) {
    $peut_retirer = false;
} elseif (!$has_transaction_password) {
    $peut_retirer = false;
} elseif (!$retraits_disponibles) {
    $peut_retirer = false;
}

// Traitement du formulaire de retrait
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['demander_retrait'])) {
    
    // Vérifications lors de la soumission du formulaire
    $erreurs = [];
    
    if (!$has_wallet) {
        $erreurs[] = "wallet";
    } elseif (!$has_transaction_password) {
        $erreurs[] = "password";
    } elseif (!$retraits_disponibles) {
        $erreurs[] = "Les retraits sont disponibles du lundi au samedi de 9h à 19h GMT. Veuillez revenir pendant ces horaires.";
    } else {
        // 1. Vérifier s'il a un plan en cours (date_fin non dépassée)
        $query_commande = $db->prepare("SELECT COUNT(*) as nb_commandes FROM commandes WHERE user_id = ? AND date_fin >= CURDATE()");
        $query_commande->execute([$user_id]);
        $commandes = $query_commande->fetch();

        if ($commandes['nb_commandes'] == 0) {
            $erreurs[] = "Vous devez avoir au moins un plan d'investissement en cours pour effectuer un retrait.";
        }

        // 2. Vérifier s'il a déjà un dépôt valide
        $query_depot = $db->prepare("SELECT COUNT(*) as nb_depots FROM depots WHERE user_id = ? AND statut = 'valide'");
        $query_depot->execute([$user_id]);
        $depots = $query_depot->fetch();

        if ($depots['nb_depots'] == 0) {
            $erreurs[] = "Vous devez avoir effectué au moins un dépôt validé pour effectuer un retrait.";
        }

        // 3. Vérifier s'il n'y a pas de retrait dans les dernières 24h (en attente ou validé)
        $query_retrait_recent = $db->prepare("SELECT COUNT(*) as nb_retraits FROM retraits WHERE user_id = ? AND statut IN ('en_attente', 'valide') AND date_demande >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $query_retrait_recent->execute([$user_id]);
        $retraits_recents = $query_retrait_recent->fetch();

        if ($retraits_recents['nb_retraits'] > 0) {
            $erreurs[] = "Vous ne pouvez effectuer qu'un seul retrait toutes les 24 heures. Veuillez patienter.";
        }
    }
    
    if (!empty($erreurs)) {
        // Afficher seulement la première erreur
        if ($erreurs[0] == "wallet") {
            $message = "<div class='notification error'>Vous devez d'abord configurer votre portefeuille.</div>";
        } elseif ($erreurs[0] == "password") {
            $message = "<div class='notification error'>Vous devez d'abord créer un mot de passe de transaction.</div>";
        } else {
            $message = "<div class='notification error'>" . $erreurs[0] . "</div>";
        }
    } else {
        $montant = floatval($_POST['montant']);
        $transaction_password = $_POST['transaction_password'] ?? '';
        
        // Validation des données
        if (empty($montant) || empty($transaction_password)) {
            $message = "<div class='notification error'>Veuillez remplir tous les champs.</div>";
        } elseif ($montant > $solde_utilisateur) {
            $message = "<div class='notification error'>Solde insuffisant.</div>";
        } elseif ($montant < 1200) {
            $message = "<div class='notification error'>Le montant minimum de retrait est de 1 200 XOF.</div>";
        } else {
            // Vérifier le mot de passe de transaction
            $password_check = $db->prepare("SELECT * FROM transaction_passwords WHERE user_id = ? AND password = ?");
            $password_check->execute([$user_id, $transaction_password]);
            
            if ($password_check->rowCount() === 0) {
                $message = "<div class='notification error'>Mot de passe de transaction incorrect.</div>";
            } else {
                // Calculer le montant net après frais (15%)
                $frais = ($montant * 15) / 100;
                $montant_net = $montant - $frais;
                
                try {
                    // Récupérer l'ancien solde avant la modification
                    $ancien_solde = $solde_utilisateur;
                    
                    // Enregistrement dans la table retraits avec +7 heures (montant SANS frais)
                    $insert = $db->prepare("INSERT INTO retraits (user_id, montant, methode, numero_compte, statut, date_demande) 
                                           VALUES (?, ?, ?, ?, 'en_attente', DATE_ADD(NOW(), INTERVAL 7 HOUR))");
                    $insert->execute([$user_id, $montant, $wallet_data['methode_paiement'], $wallet_data['numero_telephone']]);
                    
                    $retrait_id = $db->lastInsertId();
                    
                    // Mettre à jour le solde dans la table soldes (retirer le montant SANS frais)
                    $nouveau_solde = $solde_utilisateur - $montant;
                    $update_solde = $db->prepare("UPDATE soldes SET solde = ?, solde_precedent = ? WHERE user_id = ?");
                    $update_solde->execute([$nouveau_solde, $solde_utilisateur, $user_id]);
                    
                    // Mettre à jour aussi le solde dans la table utilisateurs pour cohérence
                    $update_user = $db->prepare("UPDATE utilisateurs SET solde = ? WHERE id = ?");
                    $update_user->execute([$nouveau_solde, $user_id]);
                    
                    // Générer et stocker les tokens dans la base de données
                    $token_valide = bin2hex(random_bytes(32));
                    $token_rejete = bin2hex(random_bytes(32));
                    
                    // Insérer les tokens dans la base de données avec une expiration de 24h
                    $insert_token_valide = $db->prepare("INSERT INTO retrait_tokens (retrait_id, token, action, expires_at) VALUES (?, ?, 'valide', DATE_ADD(NOW(), INTERVAL 24 HOUR))");
                    $insert_token_valide->execute([$retrait_id, $token_valide]);
                    
                    $insert_token_rejete = $db->prepare("INSERT INTO retrait_tokens (retrait_id, token, action, expires_at) VALUES (?, ?, 'rejete', DATE_ADD(NOW(), INTERVAL 24 HOUR))");
                    $insert_token_rejete->execute([$retrait_id, $token_rejete]);
                    
                    // Envoyer l'email de notification
                    require_once 'vendor/autoload.php';
                    
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    
                    // Configuration SMTP
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'sonyxperiainvestment@gmail.com';
                    $mail->Password = 'rfdw ihyw apvt qenc';
                    $mail->SMTPSecure = 'tls';
                    $mail->Port = 587;
                    $mail->CharSet = 'UTF-8';
                    
                    // Destinataires
                    $mail->setFrom('sonyxperiainvestment@gmail.com', 'NVIDIA Technologie');
                    $mail->addAddress('sonyxperiainvestment@gmail.com'); // Votre email admin
                    
                    // Contenu de l'email
                    $mail->isHTML(true);
                    $mail->Subject = '💰 NOUVEAU RETRAIT - ' . $user['nom'];
                    
                    $mail->Body = '
                    <!DOCTYPE html>
                    <html lang="fr">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Demande de Retrait</title>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #76B900, #4A7500); color: white; padding: 20px; text-align: center; }
                            .content { background: #f9f9f9; padding: 20px; border-radius: 5px; }
                            .info-box { background: white; padding: 15px; border-radius: 5px; margin-bottom: 15px; border-left: 4px solid #76B900; }
                            .button { display: inline-block; padding: 12px 24px; margin: 10px 5px; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
                            .button-validate { background: #38B000; }
                            .button-reject { background: #D00000; }
                            .footer { text-align: center; margin-top: 20px; color: #777; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="header">
                                <h1>💰 NOUVELLE DEMANDE DE RETRAIT</h1>
                            </div>
                            
                            <div class="content">
                                <div class="info-box">
                                    <h3>👤 Informations Utilisateur</h3>
                                    <p><strong>ID Utilisateur:</strong> ' . $user['id'] . '</p>
                                    <p><strong>Nom:</strong> ' . $user['nom'] . '</p>
                                    <p><strong>Téléphone:</strong> ' . $user['telephone'] . '</p>
                                    <p><strong>Pays:</strong> ' . $wallet_data['pays'] . '</p>
                                </div>
                                
                                <div class="info-box">
                                    <h3>👛 Portefeuille de Retrait</h3>
                                    <p><strong>Nom du portefeuille:</strong> ' . htmlspecialchars($wallet_data['nom_portefeuille']) . '</p>
                                    <p><strong>Méthode de paiement:</strong> ' . htmlspecialchars($wallet_data['methode_paiement']) . '</p>
                                    <p><strong>Numéro de compte:</strong> ' . htmlspecialchars($wallet_data['numero_telephone']) . '</p>
                                    <p><strong>Pays:</strong> ' . htmlspecialchars($wallet_data['pays']) . '</p>
                                </div>
                                
                                <div class="info-box">
                                    <h3>💳 Détails du Retrait</h3>
                                    <p><strong>Référence:</strong> RET' . str_pad($retrait_id, 6, '0', STR_PAD_LEFT) . '</p>
                                    <p><strong>Montant demandé:</strong> ' . number_format($montant, 0, ',', ' ') . ' XOF</p>
                                    <p><strong>Frais (15%):</strong> ' . number_format($frais, 0, ',', ' ') . ' XOF</p>
                                    <p><strong>Montant net à recevoir:</strong> ' . number_format($montant_net, 0, ',', ' ') . ' XOF</p>
                                    <p><strong>Date demande:</strong> ' . date('d/m/Y H:i') . '</p>
                                </div>

                                <div class="info-box">
                                    <h3>💰 Informations de Solde</h3>
                                    <p><strong>Ancien solde:</strong> ' . number_format($ancien_solde, 0, ',', ' ') . ' XOF</p>
                                    <p><strong>Montant retiré:</strong> ' . number_format($montant, 0, ',', ' ') . ' XOF</p>
                                    <p><strong>Nouveau solde:</strong> ' . number_format($nouveau_solde, 0, ',', ' ') . ' XOF</p>
                                </div>
                                
                                <div style="text-align: center; margin: 25px 0;">
                                    <button onclick="copyAccountNumber(\'' . htmlspecialchars($wallet_data['numero_telephone']) . '\')" class="button" style="background: #2E86AB;">📋 COPIER LE NUMÉRO</button>
                                    <a href="https://blackrock.hstn.me/process_retrait.php?action=valide&retrait_id=' . $retrait_id . '&token=' . $token_valide . '" class="button button-validate">✅ VALIDER LE RETRAIT</a>
                                    <a href="https://blackrock.hstn.me/process_retrait.php?action=rejete&retrait_id=' . $retrait_id . '&token=' . $token_rejete . '" class="button button-reject">❌ REJETER LE RETRAIT</a>
                                </div>
                            </div>
                            
                            <div class="footer">
                                <p>© ' . date('Y') . ' NVIDIA Technologie. Tous droits réservés.</p>
                            </div>
                        </div>
                        
                        <script>
                        function copyAccountNumber(number) {
                            navigator.clipboard.writeText(number).then(function() {
                                alert("Numéro de compte copié: " + number);
                            }, function() {
                                alert("Échec de la copie");
                            });
                        }
                        </script>
                    </body>
                    </html>';
                    
                    $mail->send();
                    
                    // Stocker le message de succès dans la session et rediriger
                    $_SESSION['success_retrait'] = "Votre retrait de " . number_format($montant_net, 0, ',', ' ') . " XOF a été demandé avec succès ! Il sera traité dans moins de 30 minutes. En cas de perturbation du réseau, le traitement peut prendre entre 1h et 24h.";
                    header('Location: retrait.php');
                    exit();
                    
                } catch (Exception $e) {
                    $message = "<div class='notification error'>❌ Erreur lors de la demande de retrait: " . $e->getMessage() . "</div>";
                }
            }
        }
    }
}

// Inclure le menu après le traitement POST pour éviter "headers already sent"
include 'menu.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retrait - NVIDIA Technologie</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary-white: #ffffff;
        --soft-white: #f8fafc;
        --warm-white: #fefefe;
        --light-gray: #f1f5f9;
        --accent-green: #76B900; /* NVIDIA Green */
        --green-light: #9BC930;
        --green-dark: #4A7500;
        --accent-gray: #303030; /* Darker accent for contrast */
        --text-dark: #1e293b;
        --text-gray: #64748b;
        --text-light: #475569;
        --card-bg: rgba(255, 255, 255, 0.95);
        --border-color: rgba(255, 255, 255, 0.3);
        --error: #ef4444;
        --success: #10b981;
        --warning: #f59e0b;
        --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
    }
    
    body {
        /* Background updated for a sleek, dark-greenish look */
        background: #111827; /* Darker background for tech theme */
        color: var(--soft-white);
        min-height: 100vh;
        overflow-x: hidden;
    }

    .container {
        max-width: 430px;
        margin: 0 auto;
        background: transparent;
    }
    
    /* Arrière-plan géométrique / accents */
    .background, .geometric-pattern, .blue-accent, .purple-accent {
        display: none; /* Simplification du fond pour le thème NVIDIA */
    }

    /* Nouveau fond sombre avec accent vert */
    .dark-background-effect {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #111827 0%, #030712 100%);
        z-index: -3;
    }
    
    .green-accent-blurry {
        position: fixed;
        top: 50%;
        left: 50%;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(118, 185, 0, 0.15) 0%, transparent 70%);
        filter: blur(100px);
        transform: translate(-50%, -50%);
        z-index: -1;
    }

    /* En-tête avec image de fond */
    .header {
        height: 180px;
        background: url('assets/head.jpg') center/cover; /* Garde l'image de fond si elle existe, sinon utilise le gradient */
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 25px 20px 50px 20px;
        animation: headerSlide 1s ease-out;
        border-radius: 0 0 20px 20px;
        /* Ombre modifiée */
        box-shadow: 0 8px 25px rgba(118, 185, 0, 0.3);
    }
    
    .header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6); /* Surcouche plus foncée */
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
        background: var(--accent-green); /* Vert NVIDIA */
        border: none;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #000000; /* Texte noir sur vert */
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
        background: linear-gradient(45deg, transparent, rgba(0, 0, 0, 0.2), transparent); /* Reflet sombre */
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
        background: #1f2937; /* Dark card background */
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(118, 185, 0, 0.2);
        margin-bottom: 20px;
        transition: var(--transition);
        color: var(--soft-white);
    }
    
    .info-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 25px rgba(0, 0, 0, 0.4);
    }
    
    .info-card h2 {
        font-size: 18px;
        font-weight: 700;
        color: var(--accent-green); /* NVIDIA Green */
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .info-card p {
        color: var(--light-gray);
        font-size: 14px;
        line-height: 1.5;
    }
    
    /* Formulaire */
    .wallet-form {
        background: #1f2937; /* Dark card background */
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(118, 185, 0, 0.2);
        margin-bottom: 20px;
        animation: fadeInUp 1.2s ease-out;
        color: var(--soft-white);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--soft-white);
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .form-input {
        width: 100%;
        padding: 15px;
        border: 1px solid rgba(118, 185, 0, 0.3);
        border-radius: 10px;
        font-size: 15px;
        transition: var(--transition);
        background: rgba(118, 185, 0, 0.1); /* Slight green tint */
        color: var(--primary-white);
    }
    
    .form-input:focus {
        outline: none;
        border-color: var(--accent-green);
        box-shadow: 0 0 0 2px rgba(118, 185, 0, 0.3);
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
        background: rgba(118, 185, 0, 0.1);
        border: 1px solid rgba(118, 185, 0, 0.3);
        border-radius: 10px;
        transition: var(--transition);
        color: var(--primary-white);
    }
    
    .pin-input:focus {
        outline: none;
        border-color: var(--accent-green);
        box-shadow: 0 0 0 2px rgba(118, 185, 0, 0.3);
    }
    
    .submit-btn {
        background: linear-gradient(135deg, var(--accent-green), var(--green-dark)); /* Green gradient */
        color: #000000; /* Black text on green */
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
    
    .submit-btn:disabled {
        background: var(--text-gray);
        color: var(--primary-white);
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    /* Section portefeuille existant */
    .wallet-display {
        background: #1f2937; /* Dark card background */
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(118, 185, 0, 0.2);
        margin-bottom: 20px;
        animation: fadeInUp 1.2s ease-out;
        color: var(--soft-white);
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
        color: var(--green-light);
        font-size: 14px;
    }
    
    .wallet-value {
        color: var(--light-gray);
        font-size: 14px;
    }
    
    /* Messages d'alerte */
    .notification {
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-weight: 600;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: fadeIn 0.5s ease;
        color: var(--primary-white);
    }

    .error {
        background: rgba(239, 71, 111, 0.1);
        border-left: 4px solid var(--error);
        color: var(--error);
    }

    .success {
        background: rgba(6, 214, 160, 0.1);
        border-left: 4px solid var(--success);
        color: var(--success);
    }

    .warning {
        background: rgba(255, 189, 74, 0.1);
        border-left: 4px solid var(--warning);
        color: var(--warning);
    }
    
    /* Popups */
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
        background: #1f2937; /* Dark popup background */
        width: 100%;
        max-width: 380px;
        border-radius: 20px;
        overflow: hidden;
        animation: popupIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 25px 50px rgba(118, 185, 0, 0.3);
        border: 2px solid var(--accent-green);
        max-height: 85vh;
        overflow-y: auto;
        color: var(--soft-white);
    }
    
    .popup-header {
        padding: 20px;
        background: linear-gradient(135deg, var(--accent-green), var(--green-dark));
        color: #000000; /* Black text */
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
    }
    
    .popup-header h3 {
        font-weight: 700;
        font-size: 18px;
        margin: 0;
        color: var(--primary-white);
    }
    
    .popup-close {
        background: rgba(0, 0, 0, 0.2);
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
        background: rgba(0, 0, 0, 0.3);
        transform: scale(1.1);
    }
    
    .popup-body {
        padding: 25px 20px 20px 20px;
    }
    
    /* Animations (inchangé) */
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
    
    /* Responsive (légèrement ajusté) */
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
    <div class="dark-background-effect"></div>
    <div class="green-accent-blurry"></div>
    
    <div class="container">
        <div class="header">
            <div class="header-logo">
                <div class="logo-icon">NV</div>
                <div>
                    <div class="logo-text">NVIDIA</div>
                    <div class="logo-subtext">Technologie</div>
                </div>
            </div>
            
            <button class="back-btn" onclick="window.location.href='compte.php'">
                <i class="fas fa-arrow-left"></i>
                Retour
            </button>
            
            <div class="header-content">
                <h1>Demande de Retrait</h1>
                <p>Retirez vos gains sur votre compte de fonds</p>
            </div>
        </div>
        
        <div class="main-section">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="notification success">
                    <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="notification error">
                    <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success_retrait'])): ?>
                <div class="notification success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <h3>✅ Retrait demandé avec succès !</h3>
                        <p><?= $_SESSION['success_retrait'] ?></p>
                        <p><strong>📱 N'oubliez pas:</strong> Partagez votre réussite dans nos canaux de diffusion !</p>
                    </div>
                </div>
                <?php unset($_SESSION['success_retrait']); ?>
            <?php endif; ?>
            
            <?php echo $message; ?>

            <?php if (!$has_wallet): ?>
                <div class="popup" id="walletPopup" style="display: flex;">
                    <div class="popup-content">
                        <div class="popup-header">
                            <h3>Portefeuille manquant</h3>
                            <button class="popup-close" onclick="closePopup('walletPopup')">&times;</button>
                        </div>
                        <div class="popup-body">
                            <p style="margin-bottom: 20px; color: var(--light-gray);">
                                Vous devez configurer votre portefeuille de retrait avant de pouvoir effectuer un retrait.
                            </p>
                            <button class="submit-btn" onclick="window.location.href='portefeuille.php'">
                                <i class="fas fa-wallet"></i>
                                Configurer le portefeuille
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$has_transaction_password && $has_wallet): ?>
                <div class="popup" id="passwordPopup" style="display: flex;">
                    <div class="popup-content">
                        <div class="popup-header">
                            <h3>Mot de passe manquant</h3>
                            <button class="popup-close" onclick="closePopup('passwordPopup')">&times;</button>
                        </div>
                        <div class="popup-body">
                            <p style="margin-bottom: 20px; color: var(--light-gray);">
                                Vous devez créer un mot de passe de transaction avant de pouvoir effectuer un retrait.
                            </p>
                            <button class="submit-btn" onclick="window.location.href='compte.php'">
                                <i class="fas fa-lock"></i>
                                Créer le mot de passe
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="info-card">
                <h2>
                    <i class="fas fa-info-circle"></i>
                    Informations de retrait
                </h2>
                <p>
                    • Frais de retrait: 15%<br>
                    • Montant minimum: 1 200 XOF<br>
                    • Retraits disponibles: Lundi à Samedi, 9h-19h GMT
                </p>
            </div>

            <?php if ($has_wallet): ?>
                <div class="wallet-display">
                    <h2>
                        <i class="fas fa-wallet"></i>
                        Votre portefeuille de retrait
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
                        <div class="wallet-value"><?= htmlspecialchars($wallet_data['methode_paiement']) ?></div>
                    </div>
                    
                    <div class="wallet-item">
                        <div class="wallet-label">Numéro de compte</div>
                        <div class="wallet-value"><?= htmlspecialchars($wallet_data['numero_telephone']) ?></div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($has_wallet && $has_transaction_password): ?>
                <form class="wallet-form" method="POST" action="" id="retraitForm">
                    <h2>
                        <i class="fas fa-money-bill-wave"></i>
                        Demande de retrait
                    </h2>
                    
                    <div class="form-group">
                        <label class="form-label" for="montant">
                            <i class="fas fa-coins"></i> Montant à retirer (XOF)
                        </label>
                        <input type="number" class="form-input" id="montant" name="montant" 
                               min="1200" step="100" required placeholder="Minimum 1200 XOF"
                               value="<?= isset($_POST['montant']) ? $_POST['montant'] : '' ?>">
                        <small style="color: var(--light-gray); margin-top: 8px; display: block;">
                            Frais: 15% - Montant net: <span id="montantNet">0</span> XOF
                        </small>
                    </div>
                    
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
                    
                    <button type="submit" class="submit-btn" name="demander_retrait" <?= !$retraits_disponibles ? 'disabled' : '' ?>>
                        <i class="fas fa-paper-plane"></i>
                        <?= $retraits_disponibles ? 'Demander le retrait' : 'Retraits indisponibles' ?>
                    </button>

                    <?php if (!$retraits_disponibles): ?>
                        <div class="notification warning" style="margin-top: 15px;">
                            <i class="fas fa-clock"></i>
                            Les retraits sont disponibles du lundi au samedi de 9h à 19h GMT.
                        </div>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
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
        
        // Calculer le montant net en temps réel (15% de frais)
        function calculerMontantNet() {
            const montant = parseFloat(document.getElementById('montant').value) || 0;
            const frais = (montant * 15) / 100;
            const montantNet = montant - frais;
            
            document.getElementById('montantNet').textContent = montantNet.toLocaleString('fr-FR');
        }
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Calcul du montant net en temps réel
            const montantInput = document.getElementById('montant');
            if (montantInput) {
                montantInput.addEventListener('input', calculerMontantNet);
                calculerMontantNet(); // Calcul initial
            }
            
            // Empêcher la soumission du formulaire si les conditions ne sont pas remplies
            const retraitForm = document.getElementById('retraitForm');
            if (retraitForm) {
                retraitForm.addEventListener('submit', function(e) {
                    const montant = parseFloat(document.getElementById('montant').value) || 0;
                    const transactionPassword = document.getElementById('transactionPassword').value || '';
                    
                    if (montant < 1200) {
                        e.preventDefault();
                        alert("Le montant minimum de retrait est de 1 200 XOF.");
                        return;
                    }
                    
                    if (transactionPassword.length !== 4) {
                        e.preventDefault();
                        alert("Veuillez saisir votre mot de passe de transaction (4 chiffres).");
                        return;
                    }
                });
            }
            
            // Afficher automatiquement les popups si nécessaire
            <?php if (!$has_wallet): ?>
                document.getElementById('walletPopup').style.display = 'flex';
            <?php elseif (!$has_transaction_password): ?>
                document.getElementById('passwordPopup').style.display = 'flex';
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