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

// --- 1. Récupération des données du dernier spin ---
$stmt = $db->prepare("SELECT last_spin_time FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
$last_spin_time = $user_data['last_spin_time'];

$can_spin = true;
$remaining_time = 0;
$cooldown_seconds = 48 * 3600; // 48 heures en secondes

if ($last_spin_time) {
    $last_spin_timestamp = strtotime($last_spin_time);
    $time_since_last_spin = time() - $last_spin_timestamp;

    if ($time_since_last_spin < $cooldown_seconds) {
        $can_spin = false;
        $remaining_time = $cooldown_seconds - $time_since_last_spin;
    }
}

// Récupération du solde pour l'affichage
$solde_stmt = $db->prepare("SELECT solde FROM soldes WHERE user_id = ?");
$solde_stmt->execute([$user_id]);
$solde_data = $solde_stmt->fetch();
$solde_principal = $solde_data['solde'] ?? 0;
$devise = ($_SESSION['pays'] ?? 'Cameroun') == 'Cameroun' ? 'XAF' : 'XOF';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Machine à Sous Gratuite</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- NOUVEAU THÈME NVIDIA --- */
        :root {
            --primary-white: #ffffff;
            --accent-green: #76b900; /* Vert NVIDIA */
            --background-dark: #0f1c1f; /* Noir très foncé/anthracite */
            --button-red: #ff0000;
            --error: #ef4444; /* Rouge pour le temps restant */
            --card-bg: rgba(10, 20, 20, 0.95);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--background-dark);
            color: #e0e0e0;
            padding: 15px;
            text-align: center;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding-top: 20px;
        }

        /* --- STYLES MACHINE À SOUS --- */
        .slot-container {
            position: relative;
            width: 90%;
            max-width: 450px;
            margin: 30px auto;
            padding: 20px;
            background: linear-gradient(to bottom, #444, #222);
            border-radius: 15px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.5), inset 0 0 10px rgba(255, 255, 255, 0.2);
        }

        .slot-reels {
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            border: 5px solid var(--accent-green); /* Cadre vert NVIDIA */
            border-radius: 8px;
            background: #111;
        }

        .reel {
            width: 30%;
            height: 120px;
            overflow: hidden;
            position: relative;
            border-radius: 5px;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.7);
        }

        .reel-inner {
            position: absolute;
            width: 100%;
            top: 0;
            transition: transform 0.1s linear;
        }

        .symbol {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 120px;
            font-size: 50px;
            font-weight: bold;
            color: var(--primary-white);
            background: #222;
            border-bottom: 1px solid #333;
        }
        
        .symbol i {
            font-size: 50px;
            color: var(--primary-white); /* Symboles en blanc pour contraste */
        }
        
        .payline {
            position: absolute;
            top: 50%;
            left: 0;
            width: 100%;
            height: 4px;
            background-color: var(--button-red);
            transform: translateY(-50%);
            box-shadow: 0 0 10px var(--button-red);
            z-index: 10;
            pointer-events: none;
        }
        
        /* Bouton Spin - Vert NVIDIA */
        .spin-button {
            width: 100%;
            max-width: 200px;
            background: var(--accent-green); /* Vert NVIDIA */
            color: var(--primary-white);
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 8px 15px rgba(118, 185, 0, 0.5);
            margin-top: 25px;
            text-transform: uppercase;
        }

        .spin-button:disabled {
            background: #446600; /* Vert foncé désactivé */
            box-shadow: none;
            cursor: not-allowed;
        }

        .solde-display {
            font-size: 16px;
            font-weight: 600;
            color: var(--accent-green); /* Solde en vert NVIDIA */
            margin-bottom: 20px;
        }
        
        /* Zone d'action pour le bouton ou le compte à rebours */
        .action-zone {
            margin-top: 25px;
            min-height: 80px; 
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        /* Compte à rebours direct en rouge */
        #timer-display {
            text-align: center;
            color: var(--error); /* En rouge comme demandé */
            font-size: 28px;
            font-weight: 700;
        }
        
        /* POPUP de Résultat */
        .result-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .popup-content {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(118, 185, 0, 0.6); /* Ombre verte */
            border: 2px solid var(--accent-green);
            max-width: 350px;
            width: 90%;
            transform: scale(0.8);
            opacity: 0;
            animation: popupIn 0.4s forwards;
            text-align: center;
        }
        
        @keyframes popupIn {
            to { transform: scale(1); opacity: 1; }
        }
        
        .result-icon {
            font-size: 50px;
            color: var(--accent-green); /* Icône en vert NVIDIA */
            margin-bottom: 15px;
            animation: bounce 0.8s infinite alternate;
        }
        
        @keyframes bounce {
            from { transform: translateY(0); }
            to { transform: translateY(-10px); }
        }
        
        .result-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--accent-green); /* Titre en vert NVIDIA */
            margin-bottom: 10px;
        }
        
        .result-message {
            font-size: 16px;
            color: #e0e0e0;
            margin-bottom: 20px;
        }
        
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.9.1/gsap.min.js"></script>
    <script>
        // Définition des symboles
        const SYMBOLS = [
            '<i class="fas fa-gem"></i>',   
            '<i class="fas fa-lemon"></i>',  
            '<i class="fas fa-cherry"></i>', // Petit gain (300/500/700 F)
            '<i class="fas fa-star"></i>',  
            '<i class="fas fa-bell"></i>',   
            '<i class="fas fa-dollar-sign"></i>', 
            '<span>BAR</span>',             
            '<span style="color:#ef4444;">7</span>', // Gros gain (1500/2000 F)             
        ];
        
        // Hauteur d'un symbole en pixels (doit correspondre au CSS)
        const SYMBOL_HEIGHT = 120;
        
        /**
         * Construit un rouleau (reel) avec une séquence de symboles aléatoires.
         */
        function buildReel(reelElement) {
            const inner = reelElement.querySelector('.reel-inner');
            inner.innerHTML = '';
            
            const totalSymbols = 20; 
            
            for (let i = 0; i < totalSymbols; i++) {
                const randomIndex = Math.floor(Math.random() * SYMBOLS.length);
                const symbolDiv = document.createElement('div');
                symbolDiv.className = 'symbol';
                symbolDiv.innerHTML = SYMBOLS[randomIndex];
                inner.appendChild(symbolDiv);
            }
        }
        
        /**
         * Anime l'arrêt d'un rouleau à une position spécifique.
         */
        function spinReel(reelIndex, symbolIndex, spinDuration, delay) {
            const reel = document.querySelectorAll('.reel-inner')[reelIndex];
            
            // Le 17ème symbole (index 16) est un bon point d'arrêt
            const stopSymbolIndex = 16; 
            const finalPosition = stopSymbolIndex * SYMBOL_HEIGHT;
            
            return new Promise(resolve => {
                const randomOffset = (Math.random() * (SYMBOL_HEIGHT/2)) - (SYMBOL_HEIGHT/4);
                
                gsap.to(reel, {
                    y: -finalPosition + randomOffset,
                    duration: spinDuration,
                    ease: `power${reelIndex + 2}.out`, 
                    delay: delay,
                    onComplete: resolve
                });
            });
        }
        
        // --- LOGIQUE DE SPIN ET COMMUNICATION BACKEND ---
        async function startSpin() {
            const spinButton = document.getElementById('spinButton');
            spinButton.disabled = true;
            spinButton.textContent = 'SPINNING...';
            
            const reels = document.querySelectorAll('.reel-inner');
            reels.forEach(reel => {
                gsap.to(reel, {
                    y: -19 * SYMBOL_HEIGHT, 
                    duration: 0.5,
                    ease: "linear",
                    repeat: -1, 
                });
            });
            
            try {
                const response = await fetch('spin_roue.php', { method: 'POST' });
                const data = await response.json();

                if (data.status === 'success') {
                    const winnings = data.gains;
                    
                    let comboSymbolIndex;
                    if (winnings >= 1500) {
                        // Très gros gain (1500 F, 2000 F): Afficher 7-7-7
                        comboSymbolIndex = 7; 
                    } else if (winnings > 25) {
                        // Gain moyen (300 F, 500 F, 700 F): Afficher 3 Cerises
                        comboSymbolIndex = 2; 
                    } else if (winnings === 25) {
                        // Micro gain: Afficher 3 Cloches
                        comboSymbolIndex = 4;
                    } else {
                        // Perdu (0 F): Combinaison perdante
                        comboSymbolIndex = -1; 
                    }

                    let promises = [];
                    let baseDuration = 3.0;
                    
                    for (let i = 0; i < reels.length; i++) {
                        let finalSymbolIndex;
                        
                        if (comboSymbolIndex !== -1) {
                            finalSymbolIndex = comboSymbolIndex; 
                        } else {
                            finalSymbolIndex = Math.floor(Math.random() * SYMBOLS.length); 
                            // Assure une combinaison non gagnante visuellement
                            if (i > 0 && finalSymbolIndex === promises[0].finalSymbolIndex) {
                                finalSymbolIndex = (finalSymbolIndex + 1) % SYMBOLS.length;
                            }
                        }
                        
                        promises.push(
                            spinReel(i, finalSymbolIndex, baseDuration + i * 0.5, 0)
                            .then(() => {
                                // Reconstruire le rouleau pour que l'index "gagnant" soit correct au prochain spin
                                buildReel(document.querySelectorAll('.reel')[i]);
                            })
                        );
                    }

                    await Promise.all(promises);
                    
                    showResultPopup(winnings);
                    document.getElementById('soldePrincipal').textContent = data.new_solde.toLocaleString('fr') + ' <?= $devise ?>';
                    
                    setTimeout(() => window.location.reload(), 3000); 

                } else if (data.status === 'cooldown') {
                    alert(`Vous devez attendre 48h entre chaque spin. Temps restant: ${formatTime(data.remaining_time)}`);
                    reels.forEach(reel => gsap.killTweensOf(reel));
                    spinButton.disabled = false;
                    spinButton.textContent = 'SPIN';
                } else {
                    alert(data.message || 'Une erreur est survenue.');
                    reels.forEach(reel => gsap.killTweensOf(reel));
                    spinButton.disabled = false;
                    spinButton.textContent = 'SPIN';
                }
            } catch (error) {
                console.error('Erreur de réseau:', error);
                alert('Erreur de connexion. Veuillez réessayer.');
                reels.forEach(reel => gsap.killTweensOf(reel));
                spinButton.disabled = false;
                spinButton.textContent = 'SPIN';
            }
        }
        
        // --- COMPTE À REBOURS ---
        let remainingSeconds = <?= $remaining_time ?>;
        const timerDisplay = document.getElementById('timer-display');
        const spinButton = document.getElementById('spinButton');

        function formatTime(seconds) {
            const d = Math.floor(seconds / (3600 * 24));
            seconds %= 3600 * 24;
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            
            let parts = [];
            
            parts.push(`${d}j`);
            parts.push(`${h.toString().padStart(2, '0')}h`);
            parts.push(`${m.toString().padStart(2, '0')}m`);
            parts.push(`${s.toString().padStart(2, '0')}s`);
            
            return parts.join(' : ');
        }

        function updateTimer() {
            if (remainingSeconds <= 0) {
                // Temps écoulé : afficher le bouton SPIN
                spinButton.style.display = 'block';
                spinButton.disabled = false;
                timerDisplay.style.display = 'none';
            } else {
                // Temps restant : masquer le bouton SPIN et afficher le compte à rebours
                spinButton.style.display = 'none';
                timerDisplay.style.display = 'block';
                timerDisplay.textContent = formatTime(remainingSeconds);
                remainingSeconds--;
                setTimeout(updateTimer, 1000);
            }
        }
        
        // --- POPUP DE RÉSULTAT ---
        function showResultPopup(gains) {
            const popup = document.getElementById('resultPopup');
            const title = document.getElementById('resultTitle');
            const message = document.getElementById('resultMessage');
            
            if (gains > 0) {
                title.textContent = "Félicitations !";
                message.innerHTML = `Vous avez gagné <span style="color:var(--accent-green);">${gains.toLocaleString('fr')} <?= $devise ?></span>. Le montant a été ajouté à votre solde principal.`;
                document.getElementById('resultIcon').className = 'fas fa-trophy result-icon';
            } else {
                title.textContent = "Pas de chance...";
                message.innerHTML = "Aucun gain cette fois. Revenez dans 48 heures pour tenter à nouveau votre chance !";
                document.getElementById('resultIcon').className = 'fas fa-sad-cry result-icon';
            }
            
            popup.style.display = 'flex';
        }
        
        // --- INITIALISATION ---
        document.addEventListener('DOMContentLoaded', () => {
            const reels = document.querySelectorAll('.reel');
            reels.forEach(buildReel); 
            updateTimer();
            if (!<?= json_encode($can_spin) ?>) {
                spinButton.style.display = 'none';
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-gem"></i> Machine à Sous Gratuite</h1>
        
        <p class="solde-display">Solde Principal: <span id="soldePrincipal"><?= number_format($solde_principal, 0, ',', ' ') ?> <?= $devise ?></span></p>

        <div class="slot-container">
            <div class="payline"></div>
            <div class="slot-reels">
                <div class="reel"><div class="reel-inner"></div></div>
                <div class="reel"><div class="reel-inner"></div></div>
                <div class="reel"><div class="reel-inner"></div></div>
            </div>
        </div>

        <div class="action-zone">
            <button id="spinButton" class="spin-button" onclick="startSpin()">
                SPIN
            </button>
            <div id="timer-display" style="display:none;"></div>
        </div>
        
        <div class="result-popup" id="resultPopup">
            <div class="popup-content">
                <div id="resultIcon"></div>
                <div id="resultTitle" class="result-title"></div>
                <div id="resultMessage" class="result-message"></div>
            </div>
        </div>

    </div>
</body>
</html>