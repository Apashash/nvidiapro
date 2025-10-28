<?php
session_start();
include('db.php');
include('menu.php');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$query = $db->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$query->execute([$user_id]);
$user = $query->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificat Technologique - NVIDIA Technologie</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Définition des couleurs NVIDIA */
        :root {
            --nvidia-green: #76B900; /* Le vert emblématique de NVIDIA */
            --dark-charcoal: #1a1c20; /* Noir/Gris très foncé pour les fonds */
            --charcoal: #2a2d30; /* Gris foncé pour les éléments secondaires */
            --light-grey: #f0f2f5;
            --white: #ffffff;
            --text-dark: #e0e0e0; /* Texte clair sur fond sombre */
            --text-light: #444; /* Texte plus sombre */
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            /* Dégradé de fond pour le header */
            --gradient: linear-gradient(135deg, var(--dark-charcoal) 0%, #101214 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Arrière-plan mis à jour */
        body {
            font-family: 'Poppins', sans-serif;
            /* Fond sombre, inspiré du thème sombre technologique */
            background: var(--dark-charcoal); 
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            padding-bottom: 100px;
        }

        .container {
            max-width: 430px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header adapté au thème NVIDIA */
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 30px 20px;
            background: var(--gradient);
            border-radius: 20px;
            color: var(--white);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            /* Bordure d'accentuation verte */
            border-bottom: 5px solid var(--nvidia-green); 
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            /* Effet subtil avec la couleur verte */
            background: radial-gradient(circle, rgba(118, 185, 0, 0.1) 0%, transparent 70%); 
            animation: float 6s ease-in-out infinite;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 10px;
            position: relative;
            text-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }

        .header p {
            font-size: 16px;
            opacity: 0.9;
            font-weight: 400;
            position: relative;
        }

        /* Conteneur de certificat en thème sombre */
        .certificate-container {
            background: var(--charcoal); 
            border-radius: 20px;
            padding: 0;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 2px solid var(--nvidia-green); /* Bordure verte */
            position: relative;
        }

        .certificate-image {
            width: 100%;
            height: auto;
            display: block;
            border-bottom: 3px solid var(--nvidia-green);
        }

        .certificate-content {
            padding: 25px;
            text-align: center;
        }

        /* Titre en vert NVIDIA */
        .certificate-title {
            color: var(--nvidia-green); 
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .certificate-description {
            color: var(--text-dark);
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 20px;
            text-align: left;
        }

        .benefits-list {
            text-align: left;
            margin: 25px 0;
        }

        /* Items des avantages */
        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 15px;
            padding: 12px;
            /* Fond légèrement plus clair que le conteneur */
            background: rgba(118, 185, 0, 0.05); 
            border-radius: 10px;
            border-left: 4px solid var(--nvidia-green); /* Barre latérale verte */
        }

        .benefit-icon {
            color: var(--nvidia-green);
            font-size: 18px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .benefit-text {
            flex: 1;
            color: var(--text-dark);
            font-size: 14px;
            font-weight: 500;
        }

        /* Boîte d'importance en couleur d'avertissement technologique */
        .importance-box {
            background: linear-gradient(135deg, #2c3e50, #34495e); /* Nuance de gris-bleu foncé */
            border: 2px solid #f39c12; /* Bordure orange/ambre pour l'alerte */
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
        }

        .importance-title {
            color: #f39c12;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .importance-text {
            color: var(--light-grey);
            font-size: 14px;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        /* Bouton principal en vert NVIDIA */
        .btn-primary {
            flex: 1;
            background: linear-gradient(135deg, var(--nvidia-green), #5a8d00);
            color: var(--dark-charcoal); /* Texte sombre sur fond clair (vert) */
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 6px 20px rgba(118, 185, 0, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(118, 185, 0, 0.5);
            background: var(--nvidia-green);
        }

        /* Bouton secondaire adapté au thème sombre */
        .btn-secondary {
            flex: 1;
            background: transparent;
            color: var(--nvidia-green);
            border: 2px solid var(--nvidia-green);
            padding: 16px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-secondary:hover {
            background: var(--nvidia-green);
            color: var(--dark-charcoal); /* Texte sombre sur hover */
            transform: translateY(-3px);
        }

        /* Informations utilisateur en thème technologique */
        .user-info {
            background: linear-gradient(135deg, #37474f, #263238); /* Gris-bleu foncé */
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
            border: 2px solid var(--nvidia-green);
        }

        .user-name {
            color: var(--white);
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .user-status {
            color: var(--nvidia-green);
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .badge {
            background: var(--nvidia-green);
            color: var(--dark-charcoal);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            margin-left: 10px;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .certificate-title {
                font-size: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-primary, .btn-secondary {
                width: 100%;
            }
        }
        
        /* Note supplémentaire en couleur claire */
        div[style*="text-align: center; color: #666;"] {
            color: var(--text-dark) !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-microchip"></i> NVIDIA Technologie</h1>
            <p>Certificat de Partenariat Technologique</p>
        </div>

        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($user['nom']); ?></div>
            <div class="user-status">
                <i class="fas fa-check-circle"></i> Partenaire Validé
                <span class="badge">PRO</span>
            </div>
        </div>

        <div class="certificate-container">
            <img src="canva.jpg" alt="Certificat Technologique NVIDIA" class="certificate-image">
            
            <div class="certificate-content">
                <div class="certificate-title">
                    <i class="fas fa-certificate"></i>
                    Certificat de Partenariat NVIDIA
                </div>
                
                <p class="certificate-description">
                    Obtenez votre certificat de partenariat officiel qui atteste de votre statut de partenaire privilégié 
                    chez **NVIDIA Technologie**. Ce document exclusif valide votre engagement et vous offre 
                    des avantages uniques au sein de notre écosystème.
                </p>

                <div class="benefits-list">
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-server"></i> </div>
                        <div class="benefit-text">
                            <strong>Accès prioritaire aux plateformes de calcul</strong><br>
                            Sécurise l'accès à nos ressources GPU de pointe.
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-code"></i> </div>
                        <div class="benefit-text">
                            <strong>Statut de développeur certifié</strong><br>
                            Atteste de votre appartenance à la communauté de développement NVIDIA.
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-project-diagram"></i> </div>
                        <div class="benefit-text">
                            <strong>Participation aux bêtas privées</strong><br>
                            Accédez en avant-première aux nouvelles architectures et logiciels.
                        </div>
                    </div>
                </div>

                <div class="importance-box">
                    <div class="importance-title">
                        <i class="fas fa-exclamation-circle"></i> INFORMATION CLÉ
                    </div>
                    <p class="importance-text">
                        Ce certificat sera essentiel pour les futurs événements de partenaires, l'accès au support technique 
                        avancé et pour bénéficier des dernières innovations de NVIDIA.
                    </p>
                </div>

                <div class="action-buttons">
                    <button class="btn-primary pulse" onclick="requestCertificate()">
                        <i class="fas fa-cloud-download-alt"></i> Générer mon certificat
                    </button>
                    <button class="btn-secondary" onclick="shareCertificate()">
                        <i class="fas fa-broadcast-tower"></i> Partager le statut
                    </button>
                </div>
            </div>
        </div>

        <div style="text-align: center; color: #666; font-size: 13px; margin-top: 20px;">
            <p>
                <i class="fas fa-info-circle"></i>
                Le certificat est généré sous format numérique dans les 24h après validation de la demande.
            </p>
        </div>
    </div>

    <script>
        function requestCertificate() {
            // Simulation de la demande de certificat
            const userConfirmed = confirm('Voulez-vous vraiment générer votre certificat de Partenariat NVIDIA ?\n\nLe certificat sera disponible sous 24 heures.');
            
            if (userConfirmed) {
                alert('✅ Votre demande de certificat a été envoyée avec succès !\n\nVous recevrez votre certificat de Partenariat NVIDIA sous 24 heures.');
                
                // Ici vous pouvez ajouter un appel AJAX pour enregistrer la demande en base de données
                // fetch('request_certificate.php', { method: 'POST' })
            }
        }

        function shareCertificate() {
            const shareText = 'Je viens d\'obtenir mon certificat de Partenariat NVIDIA ! 🚀 Fier de faire partie de la technologie de pointe.';
            const shareUrl = window.location.href;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Mon Certificat NVIDIA',
                    text: shareText,
                    url: shareUrl
                });
            } else {
                // Fallback pour les navigateurs qui ne supportent pas l'API Web Share
                navigator.clipboard.writeText(shareText + ' ' + shareUrl).then(() => {
                    alert('Lien de partage copié dans le presse-papier ! 📋');
                });
            }
        }

        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.certificate-container, .user-info');
            elements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    element.style.transition = 'all 0.6s ease';
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html>