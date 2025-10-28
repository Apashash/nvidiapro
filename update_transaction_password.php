<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$user_id = $_SESSION['user_id'];
$old_pin = $_POST['old_pin'] ?? '';
$new_pin = $_POST['new_pin'] ?? '';
$confirm_pin = $_POST['confirm_pin'] ?? '';

// Vérifier si l'utilisateur a déjà un mot de passe
$has_password = $db->prepare("SELECT * FROM transaction_passwords WHERE user_id = ?");
$has_password->execute([$user_id]);
$has_password = $has_password->fetch();

if ($has_password) {
    // Vérifier l'ancien mot de passe
    if ($old_pin !== $has_password['password']) {
        echo json_encode(['success' => false, 'message' => 'Ancien code incorrect']);
        exit;
    }
}

// Vérifier que le nouveau code est à 4 chiffres
if (!preg_match('/^\d{4}$/', $new_pin)) {
    echo json_encode(['success' => false, 'message' => 'Le code doit contenir exactement 4 chiffres']);
    exit;
}

// Vérifier que les codes correspondent
if ($new_pin !== $confirm_pin) {
    echo json_encode(['success' => false, 'message' => 'Les codes ne correspondent pas']);
    exit;
}

// Mettre à jour ou insérer le mot de passe
if ($has_password) {
    $update = $db->prepare("UPDATE transaction_passwords SET password = ? WHERE user_id = ?");
    $result = $update->execute([$new_pin, $user_id]);
} else {
    $insert = $db->prepare("INSERT INTO transaction_passwords (user_id, password) VALUES (?, ?)");
    $result = $insert->execute([$user_id, $new_pin]);
}

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Mot de passe mis à jour avec succès']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
}
?>