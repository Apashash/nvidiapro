<?php
function mettreAJourSolde($conn, $user_id) {
    $query = "SELECT id, user_id, montant FROM depots WHERE statut = 'Valide' AND solde_mis_a_jour = FALSE AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $depot_id = $row['id'];
            $montant = $row['montant']; 

            // Mettre à jour le solde de l'utilisateur
            $update_solde_query = "UPDATE soldes SET solde = solde + ? WHERE user_id = ?";
            $stmt_update = $conn->prepare($update_solde_query);
            $stmt_update->bind_param('di', $montant, $user_id);
            $stmt_update->execute();

            // Marquer le dépôt comme traité pour éviter les mises à jour multiples
            $update_statut_query = "UPDATE depots SET solde_mis_a_jour = TRUE WHERE id = ?";
            $stmt_update_statut = $conn->prepare($update_statut_query);
            $stmt_update_statut->bind_param('i', $depot_id);
            $stmt_update_statut->execute();
        }
    }

    $stmt->close();
}
?>