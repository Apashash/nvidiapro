<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Non authentifié.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$cooldown_seconds = 48 * 3600; // 48 heures

// --- 1. Vérification du délai de 48h ---
$stmt = $db->prepare("SELECT last_spin_time FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
$last_spin_time = $user_data['last_spin_time'];

if ($last_spin_time) {
    $last_spin_timestamp = strtotime($last_spin_time);
    $time_since_last_spin = time() - $last_spin_timestamp;

    if ($time_since_last_spin < $cooldown_seconds) {
        $remaining_time = $cooldown_seconds - $time_since_last_spin;
        echo json_encode(['status' => 'cooldown', 'remaining_time' => $remaining_time, 'message' => 'Veuillez attendre 48h entre chaque spin.']);
        exit;
    }
}

// --- 2. Détermination du gain basée sur les probabilités ---
/* * NOUVELLES CHANCES (Total 100%) :
 * 25 F: 90.0 %
 * 300 F: 3.0 %
 * 500 F: 3.0 %
 * 700 F: 2.0 % (Nouveau prix)
 * 1500 F: 1.5 % (Ancien 1000 F)
 * 2000 F: 0.3 % (Ancien 10000 F)
 * 0 F: 0.2 % (Chance de perdre si le dé tire une valeur très basse)
 */
$prizes = [
    // Gain => Chance (en pourcentage * 10, pour travailler avec des entiers)
    25 => 900,  
    300 => 30,
    500 => 30,
    700 => 20,
    1500 => 15,
    2000 => 3,
    10000 => 0, // 0.0% de chance comme demandé
    0 => 2, // 0.2 % (pour compléter à 1000/100%)
];


function choosePrize(array $prizes): int {
    $totalWeight = array_sum($prizes);
    $rand = mt_rand(1, $totalWeight);

    foreach ($prizes as $prize => $weight) {
        $rand -= $weight;
        if ($rand <= 0) {
            return $prize;
        }
    }

    // Fallback: si le calcul échoue (ne devrait pas arriver), retourne 0 ou le plus petit gain
    return 0; 
}

$gains = choosePrize($prizes);

// --- 3. Mise à jour de la base de données (Transaction) ---
try {
    $db->beginTransaction();

    // 3.1 Mettre à jour le solde principal
    $update_solde = $db->prepare("UPDATE soldes SET solde = solde + ? WHERE user_id = ?");
    $update_solde->execute([$gains, $user_id]);

    // 3.2 Enregistrer le spin
    $update_time = $db->prepare("UPDATE utilisateurs SET last_spin_time = NOW() WHERE id = ?");
    $update_time->execute([$user_id]);

    // 3.3 Enregistrer l'historique de gain (uniquement si le gain est positif)
    if ($gains > 0) {
        $insert_historique = $db->prepare("INSERT INTO historique_revenus (user_id, montant, type) VALUES (?, ?, 'roue')");
        $insert_historique->execute([$user_id, $gains]);
    }


    $db->commit();
    
    // 3.4 Récupérer le nouveau solde pour le retour
    $solde_stmt = $db->prepare("SELECT solde FROM soldes WHERE user_id = ?");
    $solde_stmt->execute([$user_id]);
    $new_solde = $solde_stmt->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'gains' => $gains,
        'new_solde' => $new_solde,
    ]);

} catch (Exception $e) {
    $db->rollBack();
    error_log("Transaction failed: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la mise à jour du solde.']);
}
?>