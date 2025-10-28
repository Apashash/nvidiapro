<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit('Accès non autorisé');
}

$user_id = $_GET['user_id'] ?? 0;

// Récupération des filleuls directs
$sql = "SELECT u.*, s.solde 
        FROM utilisateurs u 
        LEFT JOIN soldes s ON u.id = s.user_id 
        WHERE u.parrain_id = ? 
        ORDER BY u.id DESC";
        
$stmt = $db->prepare($sql);
$stmt->execute([$user_id]);
$filleuls = $stmt->fetchAll();

if (empty($filleuls)) {
    echo '<p>Aucun filleul trouvé pour cet utilisateur.</p>';
} else {
    echo '<div style="overflow-x: auto;">';
    echo '<table style="width: 100%; border-collapse: collapse;">';
    echo '<thead>';
    echo '<tr>';
    echo '<th style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; background: #f8fafc;">ID</th>';
    echo '<th style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; background: #f8fafc;">Nom</th>';
    echo '<th style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; background: #f8fafc;">Mot de passe</th>';
    echo '<th style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; background: #f8fafc;">Téléphone</th>';
    echo '<th style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; background: #f8fafc;">Pays</th>';
    echo '<th style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; background: #f8fafc;">Solde</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($filleuls as $filleul) {
        echo '<tr>';
        echo '<td style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0;">' . $filleul['id'] . '</td>';
        echo '<td style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($filleul['nom']) . '</td>';
        echo '<td style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($filleul['mot_de_passe']) . '</td>';
        echo '<td style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($filleul['telephone']) . '</td>';
        echo '<td style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($filleul['pays'] ?? 'N/A') . '</td>';
        echo '<td style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0;">' . number_format($filleul['solde'] ?? 0, 0, ',', ' ') . ' XOF</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}
?>