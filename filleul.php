<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fonction pour récupérer les filleuls par niveau
function getFilleulsByLevel($db, $user_id, $level) {
    $filleuls = [];
    
    if (!is_numeric($user_id) || !is_numeric($level) || $level < 1) {
        return $filleuls;
    }

    $current_level = [$user_id];
    
    for ($i = 1; $i <= $level; $i++) {
        if (empty($current_level)) {
            break;
        }
        
        try {
            $placeholders = implode(',', array_fill(0, count($current_level), '?'));
            $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE parrain_id IN ($placeholders)");
            $stmt->execute($current_level);
            $current_level = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            
            if ($i == $level) {
                $filleuls = $current_level;
            }
        } catch (PDOException $e) {
            error_log("Erreur getFilleulsByLevel: ".$e->getMessage());
            break;
        }
    }
    
    return $filleuls;
}

// Fonction pour calculer l'investissement actif
function getInvestissementActif($db, $user_ids) {
    $total = 0;
    
    if (!is_array($user_ids) || empty($user_ids)) {
        return $total;
    }

    $valid_ids = array_filter($user_ids, 'is_numeric');
    if (empty($valid_ids)) {
        return $total;
    }

    try {
        $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(montant), 0) 
            FROM commandes 
            WHERE user_id IN ($placeholders) 
            AND statut = 'actif'
            AND date_fin >= CURDATE()
        ");
        $stmt->execute($valid_ids);
        $total = (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur getInvestissementActif: ".$e->getMessage());
    }
    
    return $total;
}

// Calcul des données
$filleuls_niveau1 = getFilleulsByLevel($db, $user_id, 1);
$filleuls_niveau2 = getFilleulsByLevel($db, $user_id, 2);
$filleuls_niveau3 = getFilleulsByLevel($db, $user_id, 3);

$count_niveau1 = count($filleuls_niveau1);
$count_niveau2 = count($filleuls_niveau2);
$count_niveau3 = count($filleuls_niveau3);

$invest_niveau1 = getInvestissementActif($db, $filleuls_niveau1);
$invest_niveau2 = getInvestissementActif($db, $filleuls_niveau2);
$invest_niveau3 = getInvestissementActif($db, $filleuls_niveau3);

$gains_niveau1 = $invest_niveau1 * 0.15;
$gains_niveau2 = $invest_niveau2 * 0.05;
$gains_niveau3 = $invest_niveau3 * 0.02;
$gains_totaux = $gains_niveau1 + $gains_niveau2 + $gains_niveau3;
$total_filleuls = $count_niveau1 + $count_niveau2 + $count_niveau3;

// Mise à jour des données avec vérification d'existence
try {
    // Vérifier si l'utilisateur existe déjà dans la table
    $check = $db->prepare("SELECT COUNT(*) FROM filleuls WHERE user_id = ?");
    $check->execute([$user_id]);
    $exists = $check->fetchColumn() > 0;

    if ($exists) {
        // Mise à jour de l'entrée existante
        $stmt = $db->prepare("
            UPDATE filleuls SET
                niveau1 = ?,
                niveau2 = ?,
                niveau3 = ?,
                investissement_niveau1 = ?,
                investissement_niveau2 = ?,
                investissement_niveau3 = ?,
                gains_niveau1 = ?,
                gains_niveau2 = ?,
                gains_niveau3 = ?,
                gains_totaux = ?,
                total_filleuls = ?,
                date_maj = ".NOWX()."
            WHERE user_id = ?
        ");
        $stmt->execute([
            $count_niveau1, $count_niveau2, $count_niveau3,
            $invest_niveau1, $invest_niveau2, $invest_niveau3,
            $gains_niveau1, $gains_niveau2, $gains_niveau3,
            $gains_totaux, $total_filleuls,
            $user_id
        ]);
    } else {
        // Insertion d'une nouvelle entrée
        $stmt = $db->prepare("
            INSERT INTO filleuls (
                user_id, niveau1, niveau2, niveau3,
                investissement_niveau1, investissement_niveau2, investissement_niveau3,
                gains_niveau1, gains_niveau2, gains_niveau3, gains_totaux, total_filleuls
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $count_niveau1, $count_niveau2, $count_niveau3,
            $invest_niveau1, $invest_niveau2, $invest_niveau3,
            $gains_niveau1, $gains_niveau2, $gains_niveau3,
            $gains_totaux, $total_filleuls
        ]);
    }
} catch (PDOException $e) {
    error_log("Erreur mise à jour filleuls: ".$e->getMessage());
}

// Récupération des données pour affichage
try {
    $stmt = $db->prepare("SELECT * FROM filleuls WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $filleuls_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $filleuls_data = [];
    error_log("Erreur récupération filleuls_data: ".$e->getMessage());
}

// Récupération des filleuls directs
try {
    $stmt = $db->prepare("
        SELECT u.id, u.nom, u.email, u.date_inscription,
               COALESCE(SUM(c.montant), 0) as investissement_total
        FROM utilisateurs u
        LEFT JOIN commandes c ON u.id = c.user_id AND c.statut = 'actif' AND c.date_fin >= CURDATE()
        WHERE u.parrain_id = ?
        GROUP BY u.id
        ORDER BY u.date_inscription DESC
    ");
    $stmt->execute([$user_id]);
    $filleuls_directs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $filleuls_directs = [];
    error_log("Erreur récupération filleuls directs: ".$e->getMessage());
}

// Récupération du code de parrainage
try {
    $stmt = $db->prepare("SELECT code_parrainage, lien_parrainage FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $code_parrainage = $user_data['code_parrainage'] ?? '';
    $lien_parrainage = $user_data['lien_parrainage'] ?? '';
} catch (PDOException $e) {
    $code_parrainage = '';
    $lien_parrainage = '';
    error_log("Erreur récupération code parrainage: ".$e->getMessage());
}