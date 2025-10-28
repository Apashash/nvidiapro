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

// Liste des pays et opérateurs selon SoleasPay
$pays_operateurs = [
    'Bénin' => [
        'country_code' => 'BJ',
        'currency' => 'XOF',
        'operators' => [
            '35' => 'MTN Money',
            '36' => 'Moov Money'
        ]
    ],
    'Burkina Faso' => [
        'country_code' => 'BF',
        'currency' => 'XOF',
        'operators' => [
            '33' => 'Moov Money',
            '34' => 'Orange Money'
        ]
    ],
    'Cameroun' => [
        'country_code' => 'CM',
        'currency' => 'XAF',
        'operators' => [
            '1' => 'MTN Mobile Money',
            '2' => 'Orange Money'
        ]
    ],
    
    'Côte d\'Ivoire' => [
        'country_code' => 'CI',
        'currency' => 'XOF',
        'operators' => [
            '30' => 'MTN Money',
            '32' => 'Wave',
            '31' => 'Moov Money',
            '29' => 'Orange Money'
        ]
    ],
    'Mali' => [
        'country_code' => 'ML',
        'currency' => 'XOF',
        'operators' => [
            '39' => 'Orange Money',
            '40' => 'Moov Money'
        ]
    ],
    'Togo' => [
        'country_code' => 'TG',
        'currency' => 'XOF',
        'operators' => [
            '38' => 'Moov Money',
            '37' => 'T-Money'
        ]
    ],
   
    'Sénégal' => [
        'country_code' => 'SN',
        'currency' => 'XOF',
        'operators' => [
            '26' => 'Free Money',
            '25' => 'Wave',
            '27' => 'Expresso',
            '28' => 'Wizall',
            '24' => 'Orange Money'
        ]
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dépôt - NVIDIA Technology</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary-black: #0a0a0a;
        --soft-black: #121212;
        --dark-gray: #1f2937;
        --accent-green-primary: #76b900;
        --accent-green-secondary: #00e676;
        --text-light: #ffffff;
        --text-muted: #94a3b8;
        --card-bg: rgba(18, 18, 18, 0.85);
        --border-color: rgba(118, 185, 0, 0.15);
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
    
    @keyframes patternShift {
        0% { transform: translate(0, 0); }
        100% { transform: translate(100px, 175px); }
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
    
    .container {
        max-width: 430px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .header {
        text-align: center;
        margin-bottom: 30px;
        animation: fadeInDown 0.8s ease-out;
    }
    
    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .logo-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--accent-green-primary), var(--accent-green-secondary));
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        box-shadow: 0 8px 25px rgba(118, 185, 0, 0.4);
        position: relative;
        overflow: hidden;
    }
    
    .logo-icon i {
        font-size: 30px;
        color: white;
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
    
    @keyframes logoShine {
        0%, 100% { transform: translateX(-100%) rotate(45deg); }
        50% { transform: translateX(100%) rotate(45deg); }
    }
    
    h1 {
        font-size: 28px;
        font-weight: 700;
        background: linear-gradient(135deg, var(--accent-green-primary), var(--accent-green-secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 8px;
    }
    
    .subtitle {
        color: var(--text-muted);
        font-size: 14px;
    }
    
    .form-container {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(118, 185, 0, 0.2);
        border: 1px solid var(--border-color);
        animation: fadeInUp 0.8s ease-out;
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
    
    .form-group {
        margin-bottom: 20px;
    }
    
    label {
        display: block;
        color: var(--text-light);
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    label i {
        color: var(--accent-green-primary);
        font-size: 16px;
    }
    
    input, select {
        width: 100%;
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 14px 16px;
        color: var(--text-light);
        font-size: 15px;
        transition: var(--transition);
    }
    
    input:focus, select:focus {
        outline: none;
        border-color: var(--accent-green-primary);
        box-shadow: 0 0 0 3px rgba(118, 185, 0, 0.1);
    }
    
    select {
        cursor: pointer;
    }
    
    option {
        background: var(--soft-black);
        color: var(--text-light);
    }
    
    .input-icon {
        position: relative;
    }
    
    .input-icon i {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
    }
    
    .submit-btn {
        width: 100%;
        background: linear-gradient(135deg, var(--accent-green-primary), var(--accent-green-secondary));
        color: white;
        border: none;
        border-radius: 14px;
        padding: 16px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        box-shadow: 0 6px 20px rgba(118, 185, 0, 0.4);
        margin-top: 10px;
    }
    
    .submit-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(118, 185, 0, 0.6);
    }
    
    .submit-btn:active {
        transform: translateY(-1px);
    }
    
    .submit-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    .alert {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideDown 0.5s ease-out;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .alert-success {
        background: rgba(0, 230, 118, 0.15);
        border: 1px solid var(--success);
        color: var(--success);
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid var(--error);
        color: var(--error);
    }
    
    .alert i {
        font-size: 18px;
    }
    
    .info-box {
        background: rgba(118, 185, 0, 0.1);
        border: 1px solid rgba(118, 185, 0, 0.2);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .info-box h3 {
        font-size: 14px;
        color: var(--accent-green-primary);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .info-box ul {
        list-style: none;
        padding-left: 0;
    }
    
    .info-box li {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 5px;
        padding-left: 20px;
        position: relative;
    }
    
    .info-box li::before {
        content: '✓';
        position: absolute;
        left: 0;
        color: var(--accent-green-secondary);
        font-weight: bold;
    }
    
    .loading {
        display: none;
        text-align: center;
        padding: 20px;
    }
    
    .loading.active {
        display: block;
    }
    
    .spinner {
        border: 3px solid rgba(118, 185, 0, 0.2);
        border-top: 3px solid var(--accent-green-primary);
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto 15px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .loading-text {
        color: var(--text-muted);
        font-size: 14px;
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
            <div class="logo-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <h1>Effectuer un Dépôt</h1>
            <p class="subtitle">Rechargez votre compte en toute sécurité</p>
        </div>
        
        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> Informations importantes</h3>
            <ul>
                <li>Le montant sera crédité instantanément</li>
                <li>Transaction sécurisée </li>
                <li>Aucun frais supplémentaire</li>
            </ul>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form id="depotForm" method="POST" action="process_depot.php">
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Montant du dépôt</label>
                    <input type="number" name="montant" id="montant" min="200" step="100" required placeholder="Entrez le montant">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-globe"></i> Pays</label>
                    <select name="pays" id="pays" required>
                        <option value="">Sélectionnez votre pays</option>
                        <?php foreach ($pays_operateurs as $pays => $data): ?>
                            <option value="<?php echo htmlspecialchars($pays); ?>" 
                                    data-currency="<?php echo $data['currency']; ?>"
                                    data-operators='<?php echo json_encode($data['operators']); ?>'>
                                <?php echo htmlspecialchars($pays); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-mobile-alt"></i> Opérateur</label>
                    <select name="operateur" id="operateur" required disabled>
                        <option value="">Sélectionnez d'abord un pays</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Adresse email</label>
                    <input type="email" name="email" id="email" required placeholder="votre@email.com" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Numéro de téléphone</label>
                    <input type="tel" name="numero" id="numero" required placeholder="Ex: 237690000001" pattern="[0-9]{6,15}">
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-lock"></i> Confirmer le paiement
                </button>
            </form>
            
            <div class="loading" id="loadingDiv">
                <div class="spinner"></div>
                <p class="loading-text">Traitement du paiement en cours...</p>
            </div>
        </div>
    </div>
    
    <script>
    // Gestion dynamique des opérateurs selon le pays
    const paysSelect = document.getElementById('pays');
    const operateurSelect = document.getElementById('operateur');
    
    paysSelect.addEventListener('change', function() {
        operateurSelect.innerHTML = '<option value="">Sélectionnez un opérateur</option>';
        
        if (this.value) {
            const selectedOption = this.options[this.selectedIndex];
            const operators = JSON.parse(selectedOption.dataset.operators);
            
            operateurSelect.disabled = false;
            
            for (const [serviceId, operatorName] of Object.entries(operators)) {
                const option = document.createElement('option');
                option.value = serviceId;
                option.textContent = operatorName;
                operateurSelect.appendChild(option);
            }
        } else {
            operateurSelect.disabled = true;
        }
    });
    
    // Gestion de la soumission du formulaire
    const form = document.getElementById('depotForm');
    const submitBtn = document.getElementById('submitBtn');
    const loadingDiv = document.getElementById('loadingDiv');
    
    form.addEventListener('submit', function(e) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement en cours...';
    });
    </script>
</body>
</html>
