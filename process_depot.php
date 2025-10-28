<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: depot.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupération des données du formulaire
$montant = floatval($_POST['montant'] ?? 0);
$pays = trim($_POST['pays'] ?? '');
$operateur = trim($_POST['operateur'] ?? ''); // Service ID
$email = trim($_POST['email'] ?? '');
$numero = trim($_POST['numero'] ?? '');

// Validation des données
if ($montant < 200) {
    $_SESSION['error'] = "Le montant minimum est de 200";
    header('Location: depot.php');
    exit;
}

if (empty($pays) || empty($operateur) || empty($email) || empty($numero)) {
    $_SESSION['error'] = "Tous les champs sont obligatoires";
    header('Location: depot.php');
    exit;
}

// Validation de l'email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Adresse email invalide";
    header('Location: depot.php');
    exit;
}

// Validation du numéro de téléphone
if (!preg_match('/^[0-9]{9,15}$/', $numero)) {
    $_SESSION['error'] = "Numéro de téléphone invalide";
    header('Location: depot.php');
    exit;
}

// Détermination de la devise selon le pays
$devises = [
    'Bénin' => 'XOF',
    'Burkina Faso' => 'XOF',
    'Cameroun' => 'XAF',
    'Côte d\'Ivoire' => 'XOF',
    'Mali' => 'XOF',
    'Togo' => 'XOF',
    'Sénégal' => 'XOF'
];

$currency = $devises[$pays] ?? 'XOF';

// Clé API SoleasPay
$api_key = 'CnKwsRD0pLYO2etn82SvBUy_TjeqbsJaRpUpNEHDP1s-AP';

// Génération d'un ID de commande unique
$order_id = 'DEP_' . $user_id . '_' . time() . '_' . rand(1000, 9999);

// Récupération des données utilisateur
$user_stmt = $db->prepare("SELECT nom FROM utilisateurs WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_data = $user_stmt->fetch();
$payer_name = $user_data['nom'] ?? 'Client';

// Préparation de la requête SoleasPay
$url = 'https://soleaspay.com/api/agent/bills/v3';

$headers = [
    'x-api-key: ' . $api_key,
    'operation: 2',
    'service: ' . $operateur,
    'Content-Type: application/json'
];

$data = [
    'wallet' => $numero,
    'amount' => $montant,
    'currency' => $currency,
    'orderId' => $order_id,
    'description' => 'Dépôt sur NVIDIA Technology',
    'payer' => $payer_name,
    'payerEmail' => $email,
    'successUrl' => 'https://' . $_SERVER['HTTP_HOST'] . '/depot.php?success=1',
    'failureUrl' => 'https://' . $_SERVER['HTTP_HOST'] . '/depot.php?failed=1'
];

// Enregistrement du dépôt dans la base de données
try {
    $insert_depot = $db->prepare("INSERT INTO depots (user_id, montant, methode, numero_transaction, pays, statut) VALUES (?, ?, ?, ?, ?, 'en_attente')");
    $insert_depot->execute([$user_id, $montant, 'Mobile Money - Service ' . $operateur, $order_id, $pays]);
    $depot_id = $db->lastInsertId();
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur lors de l'enregistrement du dépôt";
    header('Location: depot.php');
    exit;
}

// Envoi de la requête à SoleasPay
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Traitement de la réponse
if ($response === false) {
    $_SESSION['error'] = "Erreur de connexion au serveur de paiement";
    header('Location: depot.php');
    exit;
}

$result = json_decode($response, true);

// Vérification de la réponse
if (isset($result['success']) && $result['success'] === true) {
    // Paiement en cours de traitement
    $reference = $result['data']['reference'] ?? '';
    $status = $result['status'] ?? 'PROCESSING';
    
    // Mise à jour de la référence SoleasPay dans la base
    if (!empty($reference)) {
        $update_ref = $db->prepare("UPDATE depots SET numero_transaction = ? WHERE id = ?");
        $update_ref->execute([$reference . '|' . $order_id, $depot_id]);
    }
    
    // Vérification rapide du paiement (tentative après 3 secondes)
    sleep(3);
    
    // Vérification du statut du paiement
    $verify_url = 'https://soleaspay.com/api/agent/verif-pay?orderId=' . urlencode($order_id) . '&payId=' . urlencode($reference);
    
    $verify_headers = [
        'x-api-key: ' . $api_key,
        'operation: 2',
        'service: ' . $operateur,
        'Content-Type: application/json'
    ];
    
    $ch_verify = curl_init();
    curl_setopt($ch_verify, CURLOPT_URL, $verify_url);
    curl_setopt($ch_verify, CURLOPT_HTTPHEADER, $verify_headers);
    curl_setopt($ch_verify, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_verify, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch_verify, CURLOPT_TIMEOUT, 15);
    
    $verify_response = curl_exec($ch_verify);
    $verify_http_code = curl_getinfo($ch_verify, CURLINFO_HTTP_CODE);
    curl_close($ch_verify);
    
    // Si la vérification réussit immédiatement
    if ($verify_response !== false && $verify_http_code === 200) {
        $verify_result = json_decode($verify_response, true);
        
        if (isset($verify_result['success']) && $verify_result['success'] === true && $verify_result['status'] === 'SUCCESS') {
            // Paiement réussi - Mise à jour du solde immédiatement
            try {
                $db->beginTransaction();
                
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
                $update_depot->execute([$depot_id]);
                
                $db->commit();
                
                $_SESSION['success'] = "Dépôt de $montant $currency effectué avec succès! Votre compte a été crédité.";
                header('Location: compte.php');
                exit;
                
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error'] = "Erreur lors de la mise à jour du solde: " . $e->getMessage();
                header('Location: depot.php');
                exit;
            }
        }
    }
    
    // Si le paiement n'est pas encore validé, informer l'utilisateur
    // Le webhook SoleasPay mettra à jour le statut automatiquement quand le paiement sera confirmé
    $_SESSION['success'] = "Votre demande de dépôt a été enregistrée avec succès! Le paiement est en cours de traitement. Votre compte sera crédité automatiquement dès confirmation du paiement (généralement sous quelques minutes). Vous pouvez aussi vérifier votre solde dans quelques instants.";
    header('Location: compte.php');
    exit;
    
} else {
    // Erreur lors du paiement
    $error_message = $result['message'] ?? 'Erreur lors du traitement du paiement';
    
    // Mise à jour du statut du dépôt
    $update_depot = $db->prepare("UPDATE depots SET statut = 'rejete' WHERE id = ?");
    $update_depot->execute([$depot_id]);
    
    $_SESSION['error'] = $error_message;
    header('Location: depot.php');
    exit;
}
?>
