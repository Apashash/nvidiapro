<?php
session_start();
require_once 'db.php';

// Désactiver l'affichage des erreurs
error_reporting(0);

// Configuration de la session de sécurité
$security_timeout = 1800; // 30 minutes en secondes
$security_code = "Apashash28";

// Vérifier si l'utilisateur doit saisir le code de sécurité
$require_security_code = false;

if (isset($_SESSION['security_authenticated'])) {
    // Vérifier si la session de sécurité est expirée
    if (time() - $_SESSION['security_last_access'] > $security_timeout) {
        $require_security_code = true;
        unset($_SESSION['security_authenticated']);
    } else {
        // Mettre à jour le dernier accès
        $_SESSION['security_last_access'] = time();
    }
} else {
    $require_security_code = true;
}

// Traitement du formulaire de sécurité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['security_code'])) {
    $entered_code = trim($_POST['security_code']);
    
    if ($entered_code === $security_code) {
        $_SESSION['security_authenticated'] = true;
        $_SESSION['security_last_access'] = time();
        $require_security_code = false;
    } else {
        $security_error = "Code de sécurité incorrect. Veuillez réessayer.";
    }
}

// Si le code de sécurité est requis, afficher le formulaire de sécurité
if ($require_security_code) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sécurité Admin - BlackRock Investments</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
            
            :root {
                --primary: #3b82f6;
                --primary-dark: #1d4ed8;
                --accent: #8b5cf6;
                --danger: #ef4444;
                --light: #f8fafc;
                --dark: #1e293b;
                --text: #334155;
                --border: #e2e8f0;
                --radius: 8px;
                --shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, var(--primary), var(--accent));
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .security-container {
                max-width: 400px;
                width: 100%;
            }
            
            .security-card {
                background: white;
                border-radius: var(--radius);
                box-shadow: var(--shadow);
                overflow: hidden;
                text-align: center;
            }
            
            .security-header {
                background: linear-gradient(135deg, var(--primary), var(--accent));
                color: white;
                padding: 2rem;
            }
            
            .security-icon {
                font-size: 3rem;
                margin-bottom: 1rem;
            }
            
            .security-title {
                font-size: 1.5rem;
                font-weight: 700;
                margin-bottom: 0.5rem;
            }
            
            .security-subtitle {
                font-size: 0.9rem;
                opacity: 0.9;
            }
            
            .security-body {
                padding: 2rem;
            }
            
            .security-message {
                color: var(--text);
                margin-bottom: 1.5rem;
                font-size: 0.9rem;
                line-height: 1.5;
            }
            
            .security-form {
                margin-bottom: 1.5rem;
            }
            
            .security-input {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid var(--border);
                border-radius: var(--radius);
                font-size: 1rem;
                font-family: inherit;
                transition: all 0.3s ease;
                text-align: center;
                letter-spacing: 1px;
            }
            
            .security-input:focus {
                outline: none;
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            
            .security-btn {
                width: 100%;
                background: linear-gradient(135deg, var(--primary), var(--accent));
                color: white;
                border: none;
                padding: 12px 16px;
                border-radius: var(--radius);
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                margin-top: 1rem;
            }
            
            .security-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
            }
            
            .security-error {
                background: #fee2e2;
                color: #991b1b;
                padding: 12px;
                border-radius: var(--radius);
                margin-bottom: 1rem;
                font-size: 0.9rem;
                border: 1px solid #fecaca;
            }
            
            .security-info {
                background: var(--light);
                padding: 1rem;
                border-radius: var(--radius);
                font-size: 0.8rem;
                color: var(--text);
                border: 1px solid var(--border);
            }
            
            .security-info i {
                color: var(--primary);
                margin-right: 0.5rem;
            }
        </style>
    </head>
    <body>
        <div class="security-container">
            <div class="security-card">
                <div class="security-header">
                    <div class="security-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="security-title">Vérification de Sécurité</div>
                    <div class="security-subtitle">Accès Administrateur Protégé</div>
                </div>
                
                <div class="security-body">
                    <?php if (isset($security_error)): ?>
                        <div class="security-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?= $security_error ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="security-message">
                        Pour accéder au panel d'administration, veuillez saisir le code de sécurité.
                    </div>
                    
                    <form method="POST" class="security-form">
                        <input type="password" name="security_code" class="security-input" 
                               placeholder="Entrez le code de sécurité" required autofocus>
                        <button type="submit" class="security-btn">
                            <i class="fas fa-unlock-alt"></i>
                            Accéder au Panel
                        </button>
                    </form>
                    
                    <div class="security-info">
                        <i class="fas fa-info-circle"></i>
                        Cette session de sécurité expire après 30 minutes d'inactivité.
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Si on arrive ici, l'utilisateur est authentifié
// Mettre à jour le dernier accès
$_SESSION['security_last_access'] = time();

// === RESTE DU CODE ADMIN EXISTANT ===

// Fonction pour récupérer les statistiques générales
function getGeneralStats($db) {
    $stats = [];
    
    // Nombre total d'utilisateurs
    $stmt = $db->query("SELECT COUNT(*) as total FROM utilisateurs");
    $stats['total_users'] = $stmt->fetch()['total'];
    
    // Utilisateurs avec commandes actives
    $stmt = $db->query("SELECT COUNT(DISTINCT user_id) as total FROM commandes WHERE statut = 'actif'");
    $stats['users_with_orders'] = $stmt->fetch()['total'];
    
    // Total des dépôts validés
    $stmt = $db->query("SELECT SUM(montant) as total FROM depots WHERE statut = 'valide'");
    $stats['total_deposits'] = $stmt->fetch()['total'] ?? 0;
    
    // Total des retraits validés
    $stmt = $db->query("SELECT SUM(montant) as total FROM retraits WHERE statut = 'valide'");
    $stats['total_withdrawals'] = $stmt->fetch()['total'] ?? 0;
    
    // Total des retraits en attente
    $stmt = $db->query("SELECT SUM(montant) as total FROM retraits WHERE statut = 'en_attente'");
    $stats['pending_withdrawals'] = $stmt->fetch()['total'] ?? 0;
    
    // Total des soldes utilisateurs
    $stmt = $db->query("SELECT SUM(solde) as total FROM soldes");
    $stats['total_balances'] = $stmt->fetch()['total'] ?? 0;
    
    return $stats;
}

// Fonction pour récupérer les statistiques journalières
function getDailyStats($db) {
    $stmt = $db->query("
        SELECT 
            DATE(date_depot) as date,
            SUM(CASE WHEN statut = 'valide' THEN montant ELSE 0 END) as deposits,
            (SELECT SUM(montant) FROM retraits WHERE statut = 'valide' AND DATE(date_demande) = DATE(depots.date_depot)) as withdrawals
        FROM depots 
        WHERE date_depot >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(date_depot) 
        ORDER BY date DESC
        LIMIT 30
    ");
    return $stmt->fetchAll();
}

// Fonction pour récupérer les utilisateurs avec recherche
function getUsers($db, $search = '') {
    $sql = "SELECT u.*, s.solde, f.niveau1, f.niveau2, f.niveau3, f.gains_totaux 
            FROM utilisateurs u 
            LEFT JOIN soldes s ON u.id = s.user_id 
            LEFT JOIN filleuls f ON u.id = f.user_id";
    
    if (!empty($search)) {
        $sql .= " WHERE u.nom LIKE :search OR u.telephone LIKE :search";
    }
    
    $sql .= " ORDER BY u.id DESC";
    
    $stmt = $db->prepare($sql);
    if (!empty($search)) {
        $stmt->bindValue(':search', "%$search%");
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

// Fonction pour récupérer les filleuls d'un utilisateur
function getUserFilleuls($db, $user_id) {
    $sql = "SELECT u.*, s.solde 
            FROM utilisateurs u 
            LEFT JOIN soldes s ON u.id = s.user_id 
            WHERE u.parrain_id = ? 
            ORDER BY u.id DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Fonction pour récupérer les posts en attente
function getPendingPosts($db) {
    $stmt = $db->query("
        SELECT p.*, u.nom 
        FROM posts p 
        JOIN utilisateurs u ON p.user_id = u.id 
        WHERE p.statut = 'en_attente' 
        ORDER BY p.date_creation DESC
    ");
    return $stmt->fetchAll();
}

// Fonction pour récupérer les tops par génération
function getTopByGeneration($db, $generation) {
    $column = "niveau$generation";
    $stmt = $db->query("
        SELECT u.id, u.nom, f.$column as count 
        FROM filleuls f 
        JOIN utilisateurs u ON f.user_id = u.id 
        ORDER BY f.$column DESC 
        LIMIT 30
    ");
    return $stmt->fetchAll();
}

// Fonction pour récupérer les tops par gains
function getTopByEarnings($db) {
    $stmt = $db->query("
        SELECT u.id, u.nom, f.gains_totaux 
        FROM filleuls f 
        JOIN utilisateurs u ON f.user_id = u.id 
        ORDER BY f.gains_totaux DESC 
        LIMIT 30
    ");
    return $stmt->fetchAll();
}

// Fonction pour récupérer les pays avec le plus d'utilisateurs
function getTopCountries($db) {
    $stmt = $db->query("
        SELECT pays, COUNT(*) as count 
        FROM utilisateurs 
        WHERE pays IS NOT NULL AND pays != ''
        GROUP BY pays 
        ORDER BY count DESC
        LIMIT 20
    ");
    return $stmt->fetchAll();
}

// Fonction pour mettre à jour le solde d'un utilisateur
function updateUserBalance($db, $user_id, $amount, $action) {
    if ($action === 'add') {
        $sql = "UPDATE soldes SET solde = solde + ? WHERE user_id = ?";
        $sql2 = "UPDATE utilisateurs SET solde = solde + ? WHERE id = ?";
    } else {
        $sql = "UPDATE soldes SET solde = GREATEST(0, solde - ?) WHERE user_id = ?";
        $sql2 = "UPDATE utilisateurs SET solde = GREATEST(0, solde - ?) WHERE id = ?";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$amount, $user_id]);
    
    $stmt2 = $db->prepare($sql2);
    $stmt2->execute([$amount, $user_id]);
    
    return $stmt->rowCount() > 0;
}

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'validate_post':
            $stmt = $db->prepare("UPDATE posts SET statut = 'valide' WHERE id = ?");
            $success = $stmt->execute([$_POST['post_id']]);
            echo json_encode(['success' => $success]);
            break;
            
        case 'reject_post':
            $stmt = $db->prepare("UPDATE posts SET statut = 'refuse' WHERE id = ?");
            $success = $stmt->execute([$_POST['post_id']]);
            echo json_encode(['success' => $success]);
            break;
            
        case 'update_balance':
            $user_id = $_POST['user_id'];
            $amount = floatval($_POST['amount']);
            $action = $_POST['balance_action'];
            
            if ($amount > 0) {
                $success = updateUserBalance($db, $user_id, $amount, $action);
                echo json_encode(['success' => $success]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Montant invalide']);
            }
            break;
            
        case 'validate_deposit':
            $deposit_id = intval($_POST['deposit_id']);
            $user_id = intval($_POST['user_id']);
            $montant = floatval($_POST['montant']);
            
            try {
                $db->beginTransaction();
                
                // Vérifier que le dépôt existe et est en attente
                $check_deposit = $db->prepare("SELECT * FROM depots WHERE id = ? AND statut = 'en_attente'");
                $check_deposit->execute([$deposit_id]);
                $deposit = $check_deposit->fetch();
                
                if (!$deposit) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Dépôt introuvable ou déjà traité']);
                    break;
                }
                
                // Vérifier si l'utilisateur a déjà un solde
                $check_solde = $db->prepare("SELECT * FROM soldes WHERE user_id = ?");
                $check_solde->execute([$user_id]);
                
                if ($check_solde->rowCount() > 0) {
                    // Mise à jour du solde existant
                    $update_solde = $db->prepare("UPDATE soldes SET solde = solde + ? WHERE user_id = ?");
                    $update_solde->execute([$montant, $user_id]);
                } else {
                    // Création d'un nouveau solde
                    $insert_solde = $db->prepare("INSERT INTO soldes (user_id, solde) VALUES (?, ?)");
                    $insert_solde->execute([$user_id, $montant]);
                }
                
                // Mise à jour du statut du dépôt
                $update_depot = $db->prepare("UPDATE depots SET statut = 'valide', date_validation = NOW() WHERE id = ?");
                $update_depot->execute([$deposit_id]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Dépôt validé avec succès']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
            }
            break;
            
        case 'reject_deposit':
            $deposit_id = intval($_POST['deposit_id']);
            
            try {
                // Vérifier que le dépôt existe et est en attente
                $check_deposit = $db->prepare("SELECT * FROM depots WHERE id = ? AND statut = 'en_attente'");
                $check_deposit->execute([$deposit_id]);
                $deposit = $check_deposit->fetch();
                
                if (!$deposit) {
                    echo json_encode(['success' => false, 'error' => 'Dépôt introuvable ou déjà traité']);
                    break;
                }
                
                // Mise à jour du statut du dépôt
                $update_depot = $db->prepare("UPDATE depots SET statut = 'rejete' WHERE id = ?");
                $update_depot->execute([$deposit_id]);
                
                echo json_encode(['success' => true, 'message' => 'Dépôt rejeté']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
            }
            break;
            
        case 'validate_withdrawal':
            $withdrawal_id = intval($_POST['withdrawal_id']);
            $user_id = intval($_POST['user_id']);
            $montant = floatval($_POST['montant']);
            
            try {
                $db->beginTransaction();
                
                // Vérifier que le retrait existe et est en attente
                $check_withdrawal = $db->prepare("SELECT * FROM retraits WHERE id = ? AND statut = 'en_attente'");
                $check_withdrawal->execute([$withdrawal_id]);
                $withdrawal = $check_withdrawal->fetch();
                
                if (!$withdrawal) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Retrait introuvable ou déjà traité']);
                    break;
                }
                
                // Mise à jour du statut du retrait
                $update_withdrawal = $db->prepare("UPDATE retraits SET statut = 'valide', date_validation = NOW() WHERE id = ?");
                $update_withdrawal->execute([$withdrawal_id]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Retrait validé avec succès. Le paiement a été effectué.']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
            }
            break;
            
        case 'reject_withdrawal':
            $withdrawal_id = intval($_POST['withdrawal_id']);
            $user_id = intval($_POST['user_id']);
            $montant = floatval($_POST['montant']);
            
            try {
                $db->beginTransaction();
                
                // Vérifier que le retrait existe et est en attente
                $check_withdrawal = $db->prepare("SELECT * FROM retraits WHERE id = ? AND statut = 'en_attente'");
                $check_withdrawal->execute([$withdrawal_id]);
                $withdrawal = $check_withdrawal->fetch();
                
                if (!$withdrawal) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Retrait introuvable ou déjà traité']);
                    break;
                }
                
                // Rembourser le montant dans le solde de l'utilisateur
                // (car lors de la demande de retrait, le montant a été déduit)
                $check_solde = $db->prepare("SELECT * FROM soldes WHERE user_id = ?");
                $check_solde->execute([$user_id]);
                
                if ($check_solde->rowCount() > 0) {
                    // Mise à jour du solde existant (remboursement)
                    $update_solde = $db->prepare("UPDATE soldes SET solde = solde + ? WHERE user_id = ?");
                    $update_solde->execute([$montant, $user_id]);
                } else {
                    // Création d'un nouveau solde (cas rare)
                    $insert_solde = $db->prepare("INSERT INTO soldes (user_id, solde) VALUES (?, ?)");
                    $insert_solde->execute([$user_id, $montant]);
                }
                
                // Mettre à jour aussi le solde dans la table utilisateurs pour cohérence
                $update_user = $db->prepare("UPDATE utilisateurs SET solde = solde + ? WHERE id = ?");
                $update_user->execute([$montant, $user_id]);
                
                // Mise à jour du statut du retrait
                $update_withdrawal = $db->prepare("UPDATE retraits SET statut = 'rejete' WHERE id = ?");
                $update_withdrawal->execute([$withdrawal_id]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Retrait rejeté et montant remboursé']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
            }
            break;
            
        case 'search_users':
            $search = $_POST['search'] ?? '';
            $users = getUsers($db, $search);
            ob_start();
            if (empty($users)) {
                echo '<tr><td colspan="9" style="text-align: center;">Aucun utilisateur trouvé</td></tr>';
            } else {
                foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['nom']) ?></td>
                        <td>
                            <span class="copy-container">
                                <?= htmlspecialchars($user['mot_de_passe']) ?>
                                <button class="copy-btn" data-text="<?= htmlspecialchars($user['mot_de_passe']) ?>" title="Copier le mot de passe">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </span>
                        </td>
                        <td>
                            <span class="copy-container">
                                <?= htmlspecialchars($user['telephone']) ?>
                                <button class="copy-btn" data-text="<?= htmlspecialchars($user['telephone']) ?>" title="Copier le téléphone">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button class="whatsapp-btn" data-phone="<?= htmlspecialchars($user['telephone']) ?>" title="Ouvrir WhatsApp">
                                    <i class="fab fa-whatsapp"></i>
                                </button>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($user['pays'] ?? 'N/A') ?></td>
                        <td><?= number_format($user['solde'] ?? 0, 0, ',', ' ') ?> XOF</td>
                        <td><?= ($user['niveau1'] ?? 0) + ($user['niveau2'] ?? 0) + ($user['niveau3'] ?? 0) ?></td>
                        <td><?= number_format($user['gains_totaux'] ?? 0, 0, ',', ' ') ?> XOF</td>
                        <td>
                            <button class="btn btn-info btn-sm view-filleuls" data-user-id="<?= $user['id'] ?>">
                                <i class="fas fa-users"></i> Filleuls
                            </button>
                        </td>
                    </tr>
                <?php endforeach;
            }
            $html = ob_get_clean();
            echo json_encode(['html' => $html, 'count' => count($users)]);
            break;
            
        case 'get_filleuls':
            $user_id = $_POST['user_id'];
            $filleuls = getUserFilleuls($db, $user_id);
            ob_start();
            if (!empty($filleuls)): ?>
                <div class="filleuls-modal">
                    <div class="modal-header">
                        <h3>Filleuls de l'utilisateur ID: <?= $user_id ?></h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="filleuls-grid">
                            <?php foreach ($filleuls as $filleul): ?>
                                <div class="filleul-card">
                                    <div><strong>ID:</strong> <?= $filleul['id'] ?></div>
                                    <div><strong>Nom:</strong> <?= htmlspecialchars($filleul['nom']) ?></div>
                                    <div><strong>Téléphone:</strong> 
                                        <span class="copy-container">
                                            <?= htmlspecialchars($filleul['telephone']) ?>
                                            <button class="copy-btn" data-text="<?= htmlspecialchars($filleul['telephone']) ?>">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                            <button class="whatsapp-btn" data-phone="<?= htmlspecialchars($filleul['telephone']) ?>">
                                                <i class="fab fa-whatsapp"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <div><strong>Mot de passe:</strong> 
                                        <span class="copy-container">
                                            <?= htmlspecialchars($filleul['mot_de_passe']) ?>
                                            <button class="copy-btn" data-text="<?= htmlspecialchars($filleul['mot_de_passe']) ?>">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <div><strong>Solde:</strong> <?= number_format($filleul['solde'] ?? 0, 0, ',', ' ') ?> XOF</div>
                                    <div><strong>Pays:</strong> <?= htmlspecialchars($filleul['pays'] ?? 'N/A') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="filleuls-modal">
                    <div class="modal-header">
                        <h3>Filleuls de l'utilisateur ID: <?= $user_id ?></h3>
                        <button class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p>Aucun filleul trouvé pour cet utilisateur.</p>
                    </div>
                </div>
            <?php endif;
            $html = ob_get_clean();
            echo json_encode(['html' => $html, 'count' => count($filleuls)]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
    }
    exit;
}

// Récupération des données initiales
$stats = getGeneralStats($db);
$dailyStats = getDailyStats($db);
$users = getUsers($db, $_GET['search'] ?? '');
$pendingPosts = getPendingPosts($db);
$topLevel1 = getTopByGeneration($db, 1);
$topLevel2 = getTopByGeneration($db, 2);
$topLevel3 = getTopByGeneration($db, 3);
$topEarnings = getTopByEarnings($db);
$topCountries = getTopCountries($db);

// Récupération des commandes
$orders = [];
try {
    $stmt = $db->query("
        SELECT c.*, u.nom, p.nom as plan_nom 
        FROM commandes c 
        JOIN utilisateurs u ON c.user_id = u.id 
        JOIN planinvestissement p ON c.plan_id = p.id 
        ORDER BY c.date_creation DESC
    ");
    $orders = $stmt->fetchAll();
} catch (Exception $e) {
    $orders = [];
}

// Récupération des transactions
$deposits = [];
$withdrawals = [];
try {
    $stmt = $db->query("
        SELECT d.*, u.nom 
        FROM depots d 
        JOIN utilisateurs u ON d.user_id = u.id 
        ORDER BY d.date_depot DESC
    ");
    $deposits = $stmt->fetchAll();
    
    $stmt = $db->query("
        SELECT r.*, u.nom, u.pays, p.methode_paiement as operateur, p.numero_telephone, p.pays as wallet_pays
        FROM retraits r 
        JOIN utilisateurs u ON r.user_id = u.id 
        LEFT JOIN portefeuilles p ON r.user_id = p.user_id
        ORDER BY r.date_demande DESC
    ");
    $withdrawals = $stmt->fetchAll();
} catch (Exception $e) {
    // Ignorer les erreurs
}

// Déterminer la section active
$section = $_GET['section'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - BlackRock Investments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #1d4ed8;
            --primary-light: #dbeafe;
            --accent: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --dark: #1e293b;
            --light: #f8fafc;
            --text: #334155;
            --text-light: #64748b;
            --border: #e2e8f0;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --radius: 6px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px;
        }
        
        .admin-header {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 1rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .admin-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .admin-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .admin-nav {
            display: flex;
            overflow-x: auto;
            background: white;
            padding: 0.75rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
            gap: 0.5rem;
            -webkit-overflow-scrolling: touch;
        }
        
        .admin-nav::-webkit-scrollbar {
            display: none;
        }
        
        .nav-item {
            flex: 0 0 auto;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            background: var(--light);
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        
        .nav-item:hover {
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .nav-item.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary-dark);
        }
        
        .section {
            display: none;
        }
        
        .section.active {
            display: block;
            animation: fadeIn 0.2s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .card-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            background: var(--light);
            font-size: 0.95rem;
        }
        
        .card-body {
            padding: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 1rem;
            box-shadow: var(--shadow);
            border-left: 3px solid var(--primary);
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        th, td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        th {
            background: var(--light);
            font-weight: 600;
            color: var(--dark);
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        
        tr:hover {
            background: #f8fafc;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.375rem 0.75rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.7rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-info {
            background: var(--info);
            color: white;
        }
        
        .search-form {
            display: flex;
            margin-bottom: 1rem;
            gap: 0.5rem;
        }
        
        .search-input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-family: inherit;
            font-size: 0.8rem;
        }
        
        .search-btn {
            padding: 0.5rem 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.8rem;
        }
        
        .badge {
            display: inline-block;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .balance-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            background: var(--light);
            padding: 0.75rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }
        
        .balance-input {
            padding: 0.375rem;
            border: 1px solid var(--border);
            border-radius: 3px;
            width: 100px;
            font-size: 0.8rem;
        }
        
        .balance-select {
            padding: 0.375rem;
            border: 1px solid var(--border);
            border-radius: 3px;
            font-size: 0.8rem;
        }
        
        .copy-btn {
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 3px;
            padding: 0.2rem 0.4rem;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.7rem;
        }
        
        .copy-btn:hover {
            background: var(--primary-light);
            border-color: var(--primary);
        }
        
        .whatsapp-btn {
            background: #25D366;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 0.2rem 0.4rem;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.7rem;
        }
        
        .whatsapp-btn:hover {
            background: #128C7E;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }
        
        .user-details {
            background: var(--light);
            padding: 0.75rem;
            border-radius: var(--radius);
            margin-bottom: 0.75rem;
            font-size: 0.8rem;
        }
        
        .filleuls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 0.75rem;
        }
        
        .filleul-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.75rem;
            font-size: 0.8rem;
        }
        
        .filleuls-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: var(--radius);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            z-index: 1000;
            max-width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--light);
        }
        
        .modal-body {
            padding: 1rem;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            color: var(--text-light);
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .daily-stats-table {
            font-size: 0.8rem;
        }
        
        .daily-stats-table th,
        .daily-stats-table td {
            padding: 0.375rem;
        }
        
        @media (max-width: 900px) {
            .transactions-grid {
                grid-template-columns: 1fr !important;
            }
        }
        
        @media (max-width: 768px) {
            .admin-container {
                padding: 10px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filleuls-grid {
                grid-template-columns: 1fr;
            }
            
            .transactions-grid {
                grid-template-columns: 1fr !important;
            }
            
            table {
                font-size: 0.7rem;
            }
            
            th, td {
                padding: 0.375rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div class="admin-title">Administration BlackRock</div>
            <div class="admin-subtitle">Panel de gestion administrateur</div>
        </div>
        
        <div class="admin-nav">
            <a href="?section=dashboard" class="nav-item <?= $section === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i> Dashboard
            </a>
            <a href="?section=users" class="nav-item <?= $section === 'users' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Utilisateurs
            </a>
            <a href="?section=orders" class="nav-item <?= $section === 'orders' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i> Commandes
            </a>
            <a href="?section=transactions" class="nav-item <?= $section === 'transactions' ? 'active' : '' ?>">
                <i class="fas fa-exchange-alt"></i> Transactions
            </a>
            <a href="?section=balance" class="nav-item <?= $section === 'balance' ? 'active' : '' ?>">
                <i class="fas fa-wallet"></i> Soldes
            </a>
            <a href="?section=moderation" class="nav-item <?= $section === 'moderation' ? 'active' : '' ?>">
                <i class="fas fa-shield-alt"></i> Modération
            </a>
            <a href="?section=rankings" class="nav-item <?= $section === 'rankings' ? 'active' : '' ?>">
                <i class="fas fa-trophy"></i> Classements
            </a>
        </div>
        
        <div class="admin-content">
            <!-- Section Dashboard -->
            <div class="section <?= $section === 'dashboard' ? 'active' : '' ?>" id="dashboard">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['total_users'] ?></div>
                        <div class="stat-label">Utilisateurs Totaux</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['users_with_orders'] ?></div>
                        <div class="stat-label">Utilisateurs Actifs</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($stats['total_deposits'], 0, ',', ' ') ?></div>
                        <div class="stat-label">Dépôts Totaux</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($stats['total_withdrawals'], 0, ',', ' ') ?></div>
                        <div class="stat-label">Retraits Totaux</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($stats['pending_withdrawals'], 0, ',', ' ') ?></div>
                        <div class="stat-label">Retraits en Attente</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($stats['total_balances'], 0, ',', ' ') ?></div>
                        <div class="stat-label">Soldes Totaux</div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i> Statistiques Journalières (30 derniers jours)
                    </div>
                    <div class="card-body">
                        <table class="daily-stats-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Dépôts</th>
                                    <th>Retraits</th>
                                    <th>Différence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dailyStats as $day): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($day['date'])) ?></td>
                                        <td><?= number_format($day['deposits'], 0, ',', ' ') ?> XOF</td>
                                        <td><?= number_format($day['withdrawals'] ?? 0, 0, ',', ' ') ?> XOF</td>
                                        <td><?= number_format(($day['deposits'] - ($day['withdrawals'] ?? 0)), 0, ',', ' ') ?> XOF</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Section Utilisateurs -->
            <div class="section <?= $section === 'users' ? 'active' : '' ?>" id="users">
                <form id="searchUsersForm" class="search-form">
                    <input type="text" name="search" class="search-input" placeholder="Rechercher par nom ou téléphone..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                </form>
                
                <div class="card">
                    <div class="card-header">
                        Liste des Utilisateurs (<span id="usersCount"><?= count($users) ?></span>)
                    </div>
                    <div class="card-body">
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Mot de Passe</th>
                                        <th>Téléphone</th>
                                        <th>Pays</th>
                                        <th>Solde</th>
                                        <th>Filleuls</th>
                                        <th>Gains</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="usersTableBody">
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= $user['id'] ?></td>
                                            <td><?= htmlspecialchars($user['nom']) ?></td>
                                            <td>
                                                <span class="copy-container">
                                                    <?= htmlspecialchars($user['mot_de_passe']) ?>
                                                    <button class="copy-btn" data-text="<?= htmlspecialchars($user['mot_de_passe']) ?>" title="Copier le mot de passe">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="copy-container">
                                                    <?= htmlspecialchars($user['telephone']) ?>
                                                    <button class="copy-btn" data-text="<?= htmlspecialchars($user['telephone']) ?>" title="Copier le téléphone">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                    <button class="whatsapp-btn" data-phone="<?= htmlspecialchars($user['telephone']) ?>" title="Ouvrir WhatsApp">
                                                        <i class="fab fa-whatsapp"></i>
                                                    </button>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($user['pays'] ?? 'N/A') ?></td>
                                            <td><?= number_format($user['solde'] ?? 0, 0, ',', ' ') ?> XOF</td>
                                            <td><?= ($user['niveau1'] ?? 0) + ($user['niveau2'] ?? 0) + ($user['niveau3'] ?? 0) ?></td>
                                            <td><?= number_format($user['gains_totaux'] ?? 0, 0, ',', ' ') ?> XOF</td>
                                            <td>
                                                <button class="btn btn-info btn-sm view-filleuls" data-user-id="<?= $user['id'] ?>">
                                                    <i class="fas fa-users"></i> Filleuls
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section Commandes -->
            <div class="section <?= $section === 'orders' ? 'active' : '' ?>" id="orders">
                <div class="card">
                    <div class="card-header">
                        Commandes des Utilisateurs (<?= count($orders) ?>)
                    </div>
                    <div class="card-body">
                        <?php if (!empty($orders)): ?>
                            <div style="overflow-x: auto;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Utilisateur</th>
                                            <th>Plan</th>
                                            <th>Montant</th>
                                            <th>Gain/Jour</th>
                                            <th>Début</th>
                                            <th>Fin</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td><?= $order['id'] ?></td>
                                                <td><?= htmlspecialchars($order['nom']) ?></td>
                                                <td><?= htmlspecialchars($order['plan_nom']) ?></td>
                                                <td><?= number_format($order['montant'], 0, ',', ' ') ?> XOF</td>
                                                <td><?= number_format($order['gain_journalier'], 0, ',', ' ') ?> XOF</td>
                                                <td><?= date('d/m/Y', strtotime($order['date_debut'])) ?></td>
                                                <td><?= date('d/m/Y', strtotime($order['date_fin'])) ?></td>
                                                <td>
                                                    <?php if ($order['statut'] === 'actif'): ?>
                                                        <span class="badge badge-success">Actif</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Inactif</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>Aucune commande trouvée.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Section Transactions -->
            <div class="section <?= $section === 'transactions' ? 'active' : '' ?>" id="transactions">
                <div class="transactions-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <!-- Retraits à gauche -->
                    <div class="card">
                        <div class="card-header">
                            Retraits (<?= count($withdrawals) ?>)
                        </div>
                        <div class="card-body">
                            <?php if (!empty($withdrawals)): ?>
                                <div style="overflow-x: auto;">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Utilisateur</th>
                                                <th>Pays</th>
                                                <th>Opérateur</th>
                                                <th>Montant</th>
                                                <th>Compte</th>
                                                <th>Date</th>
                                                <th>Statut</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($withdrawals as $withdrawal): ?>
                                                <tr id="withdrawal-row-<?= $withdrawal['id'] ?>">
                                                    <td><?= $withdrawal['id'] ?></td>
                                                    <td><?= htmlspecialchars($withdrawal['nom']) ?> (ID: <?= $withdrawal['user_id'] ?>)</td>
                                                    <td><?= htmlspecialchars($withdrawal['wallet_pays'] ?: $withdrawal['pays']) ?></td>
                                                    <td><?= htmlspecialchars($withdrawal['operateur'] ?: $withdrawal['methode']) ?></td>
                                                    <td><?= number_format($withdrawal['montant'], 0, ',', ' ') ?> XOF</td>
                                                    <td><?= htmlspecialchars($withdrawal['numero_compte']) ?></td>
                                                    <td><?= date('d/m/Y H:i', strtotime($withdrawal['date_demande'])) ?></td>
                                                    <td>
                                                        <?php if ($withdrawal['statut'] === 'valide'): ?>
                                                            <span class="badge badge-success">Validé</span>
                                                        <?php elseif ($withdrawal['statut'] === 'en_attente'): ?>
                                                            <span class="badge badge-warning">En attente</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-danger">Rejeté</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($withdrawal['statut'] === 'en_attente'): ?>
                                                            <button class="btn btn-success btn-sm validate-withdrawal" 
                                                                    data-withdrawal-id="<?= $withdrawal['id'] ?>" 
                                                                    data-user-id="<?= $withdrawal['user_id'] ?>"
                                                                    data-montant="<?= $withdrawal['montant'] ?>"
                                                                    title="Valider et effectuer le retrait">
                                                                <i class="fas fa-check"></i> Valider
                                                            </button>
                                                            <button class="btn btn-danger btn-sm reject-withdrawal" 
                                                                    data-withdrawal-id="<?= $withdrawal['id'] ?>"
                                                                    data-user-id="<?= $withdrawal['user_id'] ?>"
                                                                    data-montant="<?= $withdrawal['montant'] ?>"
                                                                    title="Rejeter et rembourser">
                                                                <i class="fas fa-times"></i> Rejeter
                                                            </button>
                                                        <?php else: ?>
                                                            <span style="color: #94a3b8;">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p>Aucun retrait trouvé.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Dépôts à droite -->
                    <div class="card">
                        <div class="card-header">
                            Dépôts (<?= count($deposits) ?>)
                        </div>
                    <div class="card-body">
                        <?php if (!empty($deposits)): ?>
                            <div style="overflow-x: auto;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Utilisateur</th>
                                            <th>Montant</th>
                                            <th>Méthode</th>
                                            <th>Transaction</th>
                                            <th>Date</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deposits as $deposit): ?>
                                            <tr id="deposit-row-<?= $deposit['id'] ?>">
                                                <td><?= $deposit['id'] ?></td>
                                                <td><?= htmlspecialchars($deposit['nom']) ?> (ID: <?= $deposit['user_id'] ?>)</td>
                                                <td><?= number_format($deposit['montant'], 0, ',', ' ') ?> XOF</td>
                                                <td><?= htmlspecialchars($deposit['methode']) ?></td>
                                                <td><?= htmlspecialchars($deposit['numero_transaction'] ?? 'N/A') ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($deposit['date_depot'])) ?></td>
                                                <td>
                                                    <?php if ($deposit['statut'] === 'valide'): ?>
                                                        <span class="badge badge-success">Validé</span>
                                                    <?php elseif ($deposit['statut'] === 'en_attente'): ?>
                                                        <span class="badge badge-warning">En attente</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Rejeté</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($deposit['statut'] === 'en_attente'): ?>
                                                        <button class="btn btn-success btn-sm validate-deposit" 
                                                                data-deposit-id="<?= $deposit['id'] ?>" 
                                                                data-user-id="<?= $deposit['user_id'] ?>"
                                                                data-montant="<?= $deposit['montant'] ?>"
                                                                title="Valider et créditer le compte">
                                                            <i class="fas fa-check"></i> Valider
                                                        </button>
                                                        <button class="btn btn-danger btn-sm reject-deposit" 
                                                                data-deposit-id="<?= $deposit['id'] ?>"
                                                                title="Rejeter le dépôt">
                                                            <i class="fas fa-times"></i> Rejeter
                                                        </button>
                                                    <?php else: ?>
                                                        <span style="color: #94a3b8;">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>Aucun dépôt trouvé.</p>
                        <?php endif; ?>
                    </div>
                </div>
                </div>
            </div>
            
            <!-- Section Gestion Soldes -->
            <div class="section <?= $section === 'balance' ? 'active' : '' ?>" id="balance">
                <div class="card">
                    <div class="card-header">
                        Gestion des Soldes Utilisateurs
                    </div>
                    <div class="card-body">
                        <form id="searchBalanceForm" class="search-form">
                            <input type="text" name="search_user" class="search-input" placeholder="Rechercher par User ID..." value="<?= htmlspecialchars($_GET['search_user'] ?? '') ?>">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i> Rechercher
                            </button>
                        </form>
                        
                        <div id="balanceResult">
                            <?php if (isset($_GET['search_user']) && !empty($_GET['search_user'])): ?>
                                <?php
                                $user_id = $_GET['search_user'];
                                $stmt = $db->prepare("
                                    SELECT u.id, u.nom, s.solde 
                                    FROM utilisateurs u 
                                    LEFT JOIN soldes s ON u.id = s.user_id 
                                    WHERE u.id = ?
                                ");
                                $stmt->execute([$user_id]);
                                $user = $stmt->fetch();
                                ?>
                                
                                <?php if ($user): ?>
                                    <div class="user-details">
                                        <h3>Utilisateur Trouvé</h3>
                                        <div><strong>ID:</strong> <?= $user['id'] ?></div>
                                        <div><strong>Nom:</strong> <?= htmlspecialchars($user['nom']) ?></div>
                                        <div><strong>Solde Actuel:</strong> <?= number_format($user['solde'] ?? 0, 0, ',', ' ') ?> XOF</div>
                                        
                                        <form class="balance-form" id="balanceUpdateForm">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="number" name="amount" class="balance-input" placeholder="Montant" min="0" step="100" required>
                                            <select name="balance_action" class="balance-select" required>
                                                <option value="add">Ajouter</option>
                                                <option value="subtract">Déduire</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-sync-alt"></i> Mettre à jour
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="user-details" style="background: #fee2e2; color: #991b1b;">
                                        <i class="fas fa-exclamation-triangle"></i> Aucun utilisateur trouvé avec cet ID.
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div style="overflow-x: auto; margin-top: 1rem;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>Nom</th>
                                        <th>Solde</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $db->query("
                                        SELECT u.id, u.nom, s.solde 
                                        FROM utilisateurs u 
                                        LEFT JOIN soldes s ON u.id = s.user_id 
                                        ORDER BY s.solde DESC 
                                        LIMIT 50
                                    ");
                                    $topBalances = $stmt->fetchAll();
                                    ?>
                                    <?php foreach ($topBalances as $balance): ?>
                                        <tr>
                                            <td><?= $balance['id'] ?></td>
                                            <td><?= htmlspecialchars($balance['nom']) ?></td>
                                            <td><?= number_format($balance['solde'] ?? 0, 0, ',', ' ') ?> XOF</td>
                                            <td>
                                                <button class="btn btn-primary btn-sm edit-balance" data-user-id="<?= $balance['id'] ?>">
                                                    <i class="fas fa-edit"></i> Modifier
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section Modération -->
            <div class="section <?= $section === 'moderation' ? 'active' : '' ?>" id="moderation">
                <div class="card">
                    <div class="card-header">
                        Posts en Attente de Validation (<span id="pendingPostsCount"><?= count($pendingPosts) ?></span>)
                    </div>
                    <div class="card-body" id="pendingPostsContainer">
                        <?php if (empty($pendingPosts)): ?>
                            <p>Aucun post en attente de validation.</p>
                        <?php else: ?>
                            <?php foreach ($pendingPosts as $post): ?>
                                <div class="user-details" id="post-<?= $post['id'] ?>">
                                    <div><strong><?= htmlspecialchars($post['nom']) ?></strong> - <?= date('d/m/Y H:i', strtotime($post['date_creation'])) ?></div>
                                    <div class="user-info">ID: <?= $post['user_id'] ?> | Post ID: <?= $post['id'] ?></div>
                                    <p><?= nl2br(htmlspecialchars($post['message'])) ?></p>
                                    
                                    <?php if (!empty($post['image'])): ?>
                                        <img src="uploads/<?= htmlspecialchars($post['image']) ?>" style="max-width: 200px; border-radius: 4px; margin-top: 0.5rem;" onerror="this.style.display='none'">
                                    <?php endif; ?>
                                    
                                    <div class="action-buttons">
                                        <button class="btn btn-success validate-post" data-post-id="<?= $post['id'] ?>">
                                            <i class="fas fa-check"></i> Valider
                                        </button>
                                        
                                        <button class="btn btn-danger reject-post" data-post-id="<?= $post['id'] ?>">
                                            <i class="fas fa-times"></i> Rejeter
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Section Classements -->
            <div class="section <?= $section === 'rankings' ? 'active' : '' ?>" id="rankings">
                <div class="card">
                    <div class="card-header">
                        Top 30 - 1ère Génération
                    </div>
                    <div class="card-body">
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Rang</th>
                                        <th>Utilisateur</th>
                                        <th>Nombre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; ?>
                                    <?php foreach ($topLevel1 as $user): ?>
                                        <tr>
                                            <td><?= $rank++ ?></td>
                                            <td><?= htmlspecialchars($user['nom']) ?></td>
                                            <td><?= $user['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        Top 30 - 2ème Génération
                    </div>
                    <div class="card-body">
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Rang</th>
                                        <th>Utilisateur</th>
                                        <th>Nombre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; ?>
                                    <?php foreach ($topLevel2 as $user): ?>
                                        <tr>
                                            <td><?= $rank++ ?></td>
                                            <td><?= htmlspecialchars($user['nom']) ?></td>
                                            <td><?= $user['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        Top 30 - 3ème Génération
                    </div>
                    <div class="card-body">
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Rang</th>
                                        <th>Utilisateur</th>
                                        <th>Nombre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; ?>
                                    <?php foreach ($topLevel3 as $user): ?>
                                        <tr>
                                            <td><?= $rank++ ?></td>
                                            <td><?= htmlspecialchars($user['nom']) ?></td>
                                            <td><?= $user['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        Top 30 - Gains Totaux
                    </div>
                    <div class="card-body">
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Rang</th>
                                        <th>Utilisateur</th>
                                        <th>Gains</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; ?>
                                    <?php foreach ($topEarnings as $user): ?>
                                        <tr>
                                            <td><?= $rank++ ?></td>
                                            <td><?= htmlspecialchars($user['nom']) ?></td>
                                            <td><?= number_format($user['gains_totaux'], 0, ',', ' ') ?> XOF</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        Classement par Pays
                    </div>
                    <div class="card-body">
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Rang</th>
                                        <th>Pays</th>
                                        <th>Utilisateurs</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; ?>
                                    <?php foreach ($topCountries as $country): ?>
                                        <tr>
                                            <td><?= $rank++ ?></td>
                                            <td><?= htmlspecialchars($country['pays']) ?></td>
                                            <td><?= $country['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion des onglets avec navigation rapide
            const navItems = document.querySelectorAll('.nav-item');
            const sections = document.querySelectorAll('.section');
            
            navItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Mettre à jour la classe active
                    navItems.forEach(nav => nav.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Afficher la section correspondante
                    const targetId = this.getAttribute('href').split('=')[1];
                    sections.forEach(section => section.classList.remove('active'));
                    document.getElementById(targetId).classList.add('active');
                    
                    // Mettre à jour l'URL sans recharger la page
                    history.pushState(null, null, `?section=${targetId}`);
                });
            });
            
            // Gestion du bouton retour du navigateur
            window.addEventListener('popstate', function() {
                const urlParams = new URLSearchParams(window.location.search);
                const sectionParam = urlParams.get('section') || 'dashboard';
                
                navItems.forEach(nav => nav.classList.remove('active'));
                const activeNav = document.querySelector(`.nav-item[href*="${sectionParam}"]`);
                if (activeNav) activeNav.classList.add('active');
                
                sections.forEach(section => section.classList.remove('active'));
                const activeSection = document.getElementById(sectionParam);
                if (activeSection) activeSection.classList.add('active');
            });
            
            // Gestion des boutons copier
            function initCopyButtons() {
                const copyButtons = document.querySelectorAll('.copy-btn');
                copyButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const textToCopy = this.getAttribute('data-text');
                        
                        // Créer un élément textarea temporaire
                        const textarea = document.createElement('textarea');
                        textarea.value = textToCopy;
                        textarea.setAttribute('readonly', '');
                        textarea.style.position = 'absolute';
                        textarea.style.left = '-9999px';
                        document.body.appendChild(textarea);
                        
                        // Sélectionner et copier le texte
                        textarea.select();
                        document.execCommand('copy');
                        
                        // Supprimer l'élément temporaire
                        document.body.removeChild(textarea);
                        
                        // Changer l'icône temporairement pour confirmer la copie
                        const originalIcon = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-check"></i>';
                        
                        setTimeout(() => {
                            this.innerHTML = originalIcon;
                        }, 1000);
                    });
                });
            }
            
            // Gestion des boutons WhatsApp
            function initWhatsAppButtons() {
                const whatsappButtons = document.querySelectorAll('.whatsapp-btn');
                whatsappButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const phone = this.getAttribute('data-phone');
                        // Nettoyer le numéro de téléphone
                        const cleanPhone = phone.replace(/\D/g, '');
                        // Ouvrir WhatsApp
                        window.open(`https://wa.me/${cleanPhone}`, '_blank');
                    });
                });
            }
            
            // Recherche AJAX des utilisateurs
            const searchUsersForm = document.getElementById('searchUsersForm');
            if (searchUsersForm) {
                searchUsersForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const search = formData.get('search');
                    
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `ajax=1&action=search_users&search=${encodeURIComponent(search)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('usersTableBody').innerHTML = data.html;
                        document.getElementById('usersCount').textContent = data.count;
                        initCopyButtons();
                        initWhatsAppButtons();
                        initFilleulsButtons();
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Erreur lors de la recherche');
                    });
                });
            }
            
            // Gestion des boutons filleuls
            function initFilleulsButtons() {
                const filleulsButtons = document.querySelectorAll('.view-filleuls');
                filleulsButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const userId = this.getAttribute('data-user-id');
                        
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `ajax=1&action=get_filleuls&user_id=${userId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Créer le modal
                            const overlay = document.createElement('div');
                            overlay.className = 'overlay';
                            
                            const modal = document.createElement('div');
                            modal.innerHTML = data.html;
                            
                            document.body.appendChild(overlay);
                            document.body.appendChild(modal);
                            
                            // Gestion de la fermeture
                            const closeBtn = modal.querySelector('.close-modal');
                            closeBtn.addEventListener('click', function() {
                                document.body.removeChild(overlay);
                                document.body.removeChild(modal);
                            });
                            
                            overlay.addEventListener('click', function() {
                                document.body.removeChild(overlay);
                                document.body.removeChild(modal);
                            });
                            
                            // Initialiser les boutons dans le modal
                            initCopyButtons();
                            initWhatsAppButtons();
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            alert('Erreur lors du chargement des filleuls');
                        });
                    });
                });
            }
            
            // Gestion de la modération des posts
            function initPostModeration() {
                const validateButtons = document.querySelectorAll('.validate-post');
                const rejectButtons = document.querySelectorAll('.reject-post');
                
                validateButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const postId = this.getAttribute('data-post-id');
                        moderatePost(postId, 'validate_post');
                    });
                });
                
                rejectButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const postId = this.getAttribute('data-post-id');
                        moderatePost(postId, 'reject_post');
                    });
                });
            }
            
            function moderatePost(postId, action) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax=1&action=${action}&post_id=${postId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Supprimer le post de l'affichage
                        const postElement = document.getElementById(`post-${postId}`);
                        if (postElement) {
                            postElement.remove();
                        }
                        
                        // Mettre à jour le compteur
                        const countElement = document.getElementById('pendingPostsCount');
                        if (countElement) {
                            const currentCount = parseInt(countElement.textContent);
                            countElement.textContent = currentCount - 1;
                        }
                        
                        // Si plus de posts, afficher message
                        const container = document.getElementById('pendingPostsContainer');
                        if (container.querySelectorAll('.user-details').length === 0) {
                            container.innerHTML = '<p>Aucun post en attente de validation.</p>';
                        }
                    } else {
                        alert('Erreur lors de la modération du post');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors de la modération du post');
                });
            }
            
            // Gestion de la recherche de solde
            const searchBalanceForm = document.getElementById('searchBalanceForm');
            if (searchBalanceForm) {
                searchBalanceForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const searchUser = formData.get('search_user');
                    
                    if (searchUser) {
                        // Redirection simple pour l'instant
                        window.location.href = `?section=balance&search_user=${searchUser}`;
                    }
                });
            }
            
            // Gestion du formulaire de mise à jour de solde
            const balanceUpdateForm = document.getElementById('balanceUpdateForm');
            if (balanceUpdateForm) {
                balanceUpdateForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const userId = formData.get('user_id');
                    const amount = formData.get('amount');
                    const action = formData.get('balance_action');
                    
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `ajax=1&action=update_balance&user_id=${userId}&amount=${amount}&balance_action=${action}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Solde mis à jour avec succès!');
                            // Recharger la page pour voir les changements
                            location.reload();
                        } else {
                            alert('Erreur lors de la mise à jour du solde: ' + (data.error || ''));
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Erreur lors de la mise à jour du solde');
                    });
                });
            }
            
            // Gestion des boutons d'édition de solde
            const editBalanceButtons = document.querySelectorAll('.edit-balance');
            editBalanceButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    document.querySelector('input[name="search_user"]').value = userId;
                    document.getElementById('searchBalanceForm').dispatchEvent(new Event('submit'));
                });
            });
            
            // Gestion de la validation des retraits
            function initWithdrawalButtons() {
                const validateButtons = document.querySelectorAll('.validate-withdrawal');
                const rejectButtons = document.querySelectorAll('.reject-withdrawal');
                
                validateButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Êtes-vous sûr de vouloir valider ce retrait? Le paiement sera marqué comme effectué.')) {
                            return;
                        }
                        
                        const withdrawalId = this.getAttribute('data-withdrawal-id');
                        const userId = this.getAttribute('data-user-id');
                        const montant = this.getAttribute('data-montant');
                        
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
                        
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `ajax=1&action=validate_withdrawal&withdrawal_id=${withdrawalId}&user_id=${userId}&montant=${montant}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Retrait validé avec succès! Le paiement a été marqué comme effectué.');
                                // Mettre à jour la ligne du tableau
                                const row = document.getElementById(`withdrawal-row-${withdrawalId}`);
                                if (row) {
                                    const statusCell = row.querySelector('td:nth-child(7)');
                                    const actionCell = row.querySelector('td:nth-child(8)');
                                    
                                    if (statusCell) {
                                        statusCell.innerHTML = '<span class="badge badge-success">Validé</span>';
                                    }
                                    if (actionCell) {
                                        actionCell.innerHTML = '<span style="color: #94a3b8;">-</span>';
                                    }
                                }
                            } else {
                                alert('Erreur: ' + (data.error || 'Impossible de valider le retrait'));
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-check"></i> Valider';
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            alert('Erreur lors de la validation du retrait');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-check"></i> Valider';
                        });
                    });
                });
                
                rejectButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Êtes-vous sûr de vouloir rejeter ce retrait? Le montant sera remboursé sur le compte utilisateur.')) {
                            return;
                        }
                        
                        const withdrawalId = this.getAttribute('data-withdrawal-id');
                        const userId = this.getAttribute('data-user-id');
                        const montant = this.getAttribute('data-montant');
                        
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
                        
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `ajax=1&action=reject_withdrawal&withdrawal_id=${withdrawalId}&user_id=${userId}&montant=${montant}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Retrait rejeté avec succès. Le montant a été remboursé sur le compte utilisateur.');
                                // Mettre à jour la ligne du tableau
                                const row = document.getElementById(`withdrawal-row-${withdrawalId}`);
                                if (row) {
                                    const statusCell = row.querySelector('td:nth-child(7)');
                                    const actionCell = row.querySelector('td:nth-child(8)');
                                    
                                    if (statusCell) {
                                        statusCell.innerHTML = '<span class="badge badge-danger">Rejeté</span>';
                                    }
                                    if (actionCell) {
                                        actionCell.innerHTML = '<span style="color: #94a3b8;">-</span>';
                                    }
                                }
                            } else {
                                alert('Erreur: ' + (data.error || 'Impossible de rejeter le retrait'));
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-times"></i> Rejeter';
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            alert('Erreur lors du rejet du retrait');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-times"></i> Rejeter';
                        });
                    });
                });
            }
            
            // Gestion de la validation des dépôts
            function initDepositButtons() {
                const validateButtons = document.querySelectorAll('.validate-deposit');
                const rejectButtons = document.querySelectorAll('.reject-deposit');
                
                validateButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Êtes-vous sûr de vouloir valider ce dépôt et créditer le compte utilisateur?')) {
                            return;
                        }
                        
                        const depositId = this.getAttribute('data-deposit-id');
                        const userId = this.getAttribute('data-user-id');
                        const montant = this.getAttribute('data-montant');
                        
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
                        
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `ajax=1&action=validate_deposit&deposit_id=${depositId}&user_id=${userId}&montant=${montant}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Dépôt validé avec succès! Le compte utilisateur a été crédité.');
                                // Mettre à jour la ligne du tableau
                                const row = document.getElementById(`deposit-row-${depositId}`);
                                if (row) {
                                    const statusCell = row.querySelector('td:nth-child(7)');
                                    const actionCell = row.querySelector('td:nth-child(8)');
                                    
                                    if (statusCell) {
                                        statusCell.innerHTML = '<span class="badge badge-success">Validé</span>';
                                    }
                                    if (actionCell) {
                                        actionCell.innerHTML = '<span style="color: #94a3b8;">-</span>';
                                    }
                                }
                            } else {
                                alert('Erreur: ' + (data.error || 'Impossible de valider le dépôt'));
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-check"></i> Valider';
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            alert('Erreur lors de la validation du dépôt');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-check"></i> Valider';
                        });
                    });
                });
                
                rejectButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Êtes-vous sûr de vouloir rejeter ce dépôt?')) {
                            return;
                        }
                        
                        const depositId = this.getAttribute('data-deposit-id');
                        
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
                        
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `ajax=1&action=reject_deposit&deposit_id=${depositId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Dépôt rejeté avec succès.');
                                // Mettre à jour la ligne du tableau
                                const row = document.getElementById(`deposit-row-${depositId}`);
                                if (row) {
                                    const statusCell = row.querySelector('td:nth-child(7)');
                                    const actionCell = row.querySelector('td:nth-child(8)');
                                    
                                    if (statusCell) {
                                        statusCell.innerHTML = '<span class="badge badge-danger">Rejeté</span>';
                                    }
                                    if (actionCell) {
                                        actionCell.innerHTML = '<span style="color: #94a3b8;">-</span>';
                                    }
                                }
                            } else {
                                alert('Erreur: ' + (data.error || 'Impossible de rejeter le dépôt'));
                                this.disabled = false;
                                this.innerHTML = '<i class="fas fa-times"></i> Rejeter';
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            alert('Erreur lors du rejet du dépôt');
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-times"></i> Rejeter';
                        });
                    });
                });
            }
            
            // Initialisation
            initCopyButtons();
            initWhatsAppButtons();
            initFilleulsButtons();
            initPostModeration();
            initDepositButtons();
            initWithdrawalButtons();
            
            console.log('Admin panel initialisé avec succès');
        });
    </script>
</body>
</html>