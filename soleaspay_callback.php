<?php
/**
 * Webhook SoleasPay
 * Reçoit les notifications de paiement de SoleasPay
 */

require_once 'db.php';

// Log la requête pour débogage
$log_file = 'soleaspay_callback.log';
$log_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'headers' => getallheaders(),
    'body' => file_get_contents('php://input'),
    'get' => $_GET,
    'post' => $_POST
];
file_put_contents($log_file, json_encode($log_data, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Récupérer le corps de la requête
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Vérifier que les données sont valides
if (empty($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Extraire les informations de la transaction
$success = $data['success'] ?? false;
$status = $data['status'] ?? '';
$transaction_data = $data['data'] ?? [];

// Informations de la transaction
$operation = $transaction_data['operation'] ?? '';
$reference = $transaction_data['reference'] ?? ''; // Référence SoleasPay
$order_id = $transaction_data['external_reference'] ?? ''; // Notre order_id
$amount = $transaction_data['amount'] ?? 0;
$currency = $transaction_data['currency'] ?? '';

// Log des données reçues
file_put_contents($log_file, "Traitement: order_id=$order_id, reference=$reference, status=$status, success=" . ($success ? 'true' : 'false') . "\n\n", FILE_APPEND);

if (empty($order_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order_id']);
    exit;
}

try {
    // Rechercher le dépôt dans la base de données
    $stmt = $db->prepare("SELECT * FROM depots WHERE numero_transaction LIKE ? OR numero_transaction = ?");
    $stmt->execute(['%' . $order_id . '%', $order_id]);
    $depot = $stmt->fetch();
    
    if (!$depot) {
        file_put_contents($log_file, "Erreur: Dépôt non trouvé pour order_id=$order_id\n\n", FILE_APPEND);
        http_response_code(404);
        echo json_encode(['error' => 'Deposit not found']);
        exit;
    }
    
    // Vérifier que le montant correspond
    if (abs($depot['montant'] - $amount) > 0.01) {
        file_put_contents($log_file, "Erreur: Montant différent - DB: {$depot['montant']}, Callback: $amount\n\n", FILE_APPEND);
    }
    
    // Vérifier si le dépôt n'a pas déjà été validé (protection contre les doublons)
    if ($depot['statut'] === 'valide') {
        file_put_contents($log_file, "Info: Dépôt déjà validé pour order_id=$order_id\n\n", FILE_APPEND);
        echo json_encode(['success' => true, 'message' => 'Already processed']);
        exit;
    }
    
    // Traiter selon le statut
    if ($success === true && $status === 'SUCCESS') {
        // Paiement réussi - Mise à jour du solde
        $db->beginTransaction();
        
        // Vérifier si l'utilisateur a déjà un solde
        $check_solde = $db->prepare("SELECT * FROM soldes WHERE user_id = ?");
        $check_solde->execute([$depot['user_id']]);
        
        if ($check_solde->rowCount() > 0) {
            // Mise à jour du solde existant
            $update_solde = $db->prepare("UPDATE soldes SET solde = solde + ? WHERE user_id = ?");
            $update_solde->execute([$depot['montant'], $depot['user_id']]);
        } else {
            // Création d'un nouveau solde
            $insert_solde = $db->prepare("INSERT INTO soldes (user_id, solde) VALUES (?, ?)");
            $insert_solde->execute([$depot['user_id'], $depot['montant']]);
        }
        
        // Mise à jour du statut du dépôt
        $update_depot = $db->prepare("UPDATE depots SET statut = 'valide', date_validation = NOW() WHERE id = ?");
        $update_depot->execute([$depot['id']]);
        
        $db->commit();
        
        file_put_contents($log_file, "Succès: Dépôt validé et solde mis à jour pour order_id=$order_id\n\n", FILE_APPEND);
        
        echo json_encode(['success' => true, 'message' => 'Deposit validated']);
        
    } elseif ($status === 'RECEIVED' || $status === 'PROCESSING') {
        // Paiement en cours - ne rien faire, attendre un callback ultérieur
        file_put_contents($log_file, "Info: Paiement en cours pour order_id=$order_id\n\n", FILE_APPEND);
        echo json_encode(['success' => true, 'message' => 'Payment processing']);
        
    } else {
        // Paiement échoué ou refusé - NE PAS dégrader si déjà validé
        if ($depot['statut'] !== 'valide') {
            $update_depot = $db->prepare("UPDATE depots SET statut = 'rejete' WHERE id = ?");
            $update_depot->execute([$depot['id']]);
            file_put_contents($log_file, "Échec: Paiement échoué pour order_id=$order_id, statut mis à rejete\n\n", FILE_APPEND);
        } else {
            file_put_contents($log_file, "Info: Callback d'échec ignoré car dépôt déjà validé pour order_id=$order_id\n\n", FILE_APPEND);
        }
        
        echo json_encode(['success' => true, 'message' => 'Deposit rejected']);
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    file_put_contents($log_file, "Erreur exception: " . $e->getMessage() . "\n\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
