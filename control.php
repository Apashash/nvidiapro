<?php
require_once 'db.php';

try {
    // 1. Créer la table pour les filleuls actifs si elle n'existe pas
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS filleuls_actifs (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            niveau1_actifs INT(11) DEFAULT 0,
            niveau2_actifs INT(11) DEFAULT 0,
            niveau3_actifs INT(11) DEFAULT 0,
            date_maj TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $db->exec($createTableQuery);

    // 2. Mise à jour des soldes des utilisateurs depuis la table soldes
    $updateSoldesQuery = "
        UPDATE utilisateurs u
        JOIN soldes s ON u.id = s.user_id
        SET u.solde = s.solde
    ";
    $db->exec($updateSoldesQuery);

    // 3. Calcul du nombre réel de filleuls ayant investi (avec commandes actives)
    // et mise à jour de la table filleuls_actifs
    $updateFilleulsActifsQuery = "
        INSERT INTO filleuls_actifs (user_id, niveau1_actifs, niveau2_actifs, niveau3_actifs)
        SELECT 
            u.id as user_id,
            (SELECT COUNT(DISTINCT u1.id)
             FROM utilisateurs u1
             JOIN commandes c1 ON u1.id = c1.user_id
             WHERE u1.parrain_id = u.id
             AND c1.date_fin >= CURDATE()
             AND c1.statut = 'actif') as niveau1_actifs,
             
            (SELECT COUNT(DISTINCT u2.id)
             FROM utilisateurs u1
             JOIN utilisateurs u2 ON u2.parrain_id = u1.id
             JOIN commandes c2 ON u2.id = c2.user_id
             WHERE u1.parrain_id = u.id
             AND c2.date_fin >= CURDATE()
             AND c2.statut = 'actif') as niveau2_actifs,
             
            (SELECT COUNT(DISTINCT u3.id)
             FROM utilisateurs u1
             JOIN utilisateurs u2 ON u2.parrain_id = u1.id
             JOIN utilisateurs u3 ON u3.parrain_id = u2.id
             JOIN commandes c3 ON u3.id = c3.user_id
             WHERE u1.parrain_id = u.id
             AND c3.date_fin >= CURDATE()
             AND c3.statut = 'actif') as niveau3_actifs
        
        FROM utilisateurs u
        ON DUPLICATE KEY UPDATE 
            niveau1_actifs = VALUES(niveau1_actifs),
            niveau2_actifs = VALUES(niveau2_actifs),
            niveau3_actifs = VALUES(niveau3_actifs),
            date_maj = CURRENT_TIMESTAMP
    ";
    $db->exec($updateFilleulsActifsQuery);

    // 4. Mise à jour du nombre de filleuls dans la table utilisateurs
    $updateFilleulsQuery = "
        UPDATE utilisateurs u
        JOIN filleuls_actifs fa ON u.id = fa.user_id
        SET u.nombre_filleuls = fa.niveau1_actifs
    ";
    $db->exec($updateFilleulsQuery);

    // 5. Mise à jour des niveaux VIP en fonction des filleuls ACTIFS (ayant investi)
    $updateVipQuery = "
        UPDATE vip v
        JOIN filleuls_actifs fa ON v.user_id = fa.user_id
        SET 
            v.invitations_actuelles = fa.niveau1_actifs,
            v.niveau = CASE 
                WHEN fa.niveau1_actifs < 3 THEN 0
                WHEN fa.niveau1_actifs >= 3 AND fa.niveau1_actifs < 7 THEN 1
                WHEN fa.niveau1_actifs >= 7 AND fa.niveau1_actifs < 12 THEN 2
                WHEN fa.niveau1_actifs >= 12 AND fa.niveau1_actifs < 20 THEN 3
                WHEN fa.niveau1_actifs >= 20 AND fa.niveau1_actifs < 40 THEN 4
                WHEN fa.niveau1_actifs >= 40 THEN 5
                ELSE 0
            END,
            v.invitations_requises = CASE 
                WHEN fa.niveau1_actifs < 3 THEN 3 - fa.niveau1_actifs
                WHEN fa.niveau1_actifs >= 3 AND fa.niveau1_actifs < 7 THEN 7 - fa.niveau1_actifs
                WHEN fa.niveau1_actifs >= 7 AND fa.niveau1_actifs < 12 THEN 12 - fa.niveau1_actifs
                WHEN fa.niveau1_actifs >= 12 AND fa.niveau1_actifs < 20 THEN 20 - fa.niveau1_actifs
                WHEN fa.niveau1_actifs >= 20 AND fa.niveau1_actifs < 40 THEN 40 - fa.niveau1_actifs
                WHEN fa.niveau1_actifs >= 40 THEN 0
                ELSE 0
            END,
            v.pourcentage = CASE 
                WHEN fa.niveau1_actifs < 3 THEN (fa.niveau1_actifs / 3) * 100
                WHEN fa.niveau1_actifs >= 3 AND fa.niveau1_actifs < 7 THEN ((fa.niveau1_actifs - 3) / (7 - 3)) * 100
                WHEN fa.niveau1_actifs >= 7 AND fa.niveau1_actifs < 12 THEN ((fa.niveau1_actifs - 7) / (12 - 7)) * 100
                WHEN fa.niveau1_actifs >= 12 AND fa.niveau1_actifs < 20 THEN ((fa.niveau1_actifs - 12) / (20 - 12)) * 100
                WHEN fa.niveau1_actifs >= 20 AND fa.niveau1_actifs < 40 THEN ((fa.niveau1_actifs - 20) / (40 - 20)) * 100
                WHEN fa.niveau1_actifs >= 40 THEN 100
                ELSE 0
            END
    ";
    $db->exec($updateVipQuery);

    // 6. Vérification des tâches en attente depuis plus de 30 minutes
    $tasks_to_validate = ['whatsapp', 'telegram_canal', 'telegram_groupe', 'statut', 'story', 'groupes'];
    foreach ($tasks_to_validate as $task) {
        $time_column = $task . '_time';
        $status_column = $task . '_status';
        
        $query = "UPDATE taches_utilisateurs 
                  SET $status_column = CASE 
                      WHEN '$task' = 'groupes' THEN 'non_fait' 
                      WHEN $status_column = 'en_attente' AND TIMESTAMPDIFF(MINUTE, $time_column, ".NOWX().") >= 30 THEN 'valide'
                      ELSE $status_column
                  END
                  WHERE $status_column = 'en_attente'";
        $db->exec($query);
    }

    // 7. Mise à jour des soldes en fonction des tâches validées et non encore payées
    $updateSoldesFromTasksQuery = "
        UPDATE soldes s
        JOIN taches_utilisateurs t ON s.user_id = t.user_id
        LEFT JOIN taches_paiements p ON s.user_id = p.user_id
        SET 
            s.solde = s.solde + 
                CASE WHEN t.whatsapp_status = 'valide' AND (p.whatsapp_paye IS NULL OR p.whatsapp_paye = FALSE) THEN 40 ELSE 0 END +
                CASE WHEN t.telegram_canal_status = 'valide' AND (p.telegram_canal_paye IS NULL OR p.telegram_canal_paye = FALSE) THEN 35 ELSE 0 END +
                CASE WHEN t.telegram_groupe_status = 'valide' AND (p.telegram_groupe_paye IS NULL OR p.telegram_groupe_paye = FALSE) THEN 50 ELSE 0 END +
                CASE WHEN t.statut_status = 'valide' AND (p.statut_paye IS NULL OR p.statut_paye = FALSE) THEN 100 ELSE 0 END +
                CASE WHEN t.story_status = 'valide' AND (p.story_paye IS NULL OR p.story_paye = FALSE) THEN 60 ELSE 0 END,
            s.date_maj = CURRENT_TIMESTAMP
        WHERE 
            (t.whatsapp_status = 'valide' AND (p.whatsapp_paye IS NULL OR p.whatsapp_paye = FALSE)) OR
            (t.telegram_canal_status = 'valide' AND (p.telegram_canal_paye IS NULL OR p.telegram_canal_paye = FALSE)) OR
            (t.telegram_groupe_status = 'valide' AND (p.telegram_groupe_paye IS NULL OR p.telegram_groupe_paye = FALSE)) OR
            (t.statut_status = 'valide' AND (p.statut_paye IS NULL OR p.statut_paye = FALSE)) OR
            (t.story_status = 'valide' AND (p.story_paye IS NULL OR p.story_paye = FALSE))
    ";
    $db->exec($updateSoldesFromTasksQuery);

    // 8. Mise à jour de la table taches_paiements pour marquer les tâches comme payées
    $tasksToUpdate = $db->prepare("
        SELECT 
            t.user_id,
            t.whatsapp_status = 'valide' AND (p.whatsapp_paye IS NULL OR p.whatsapp_paye = FALSE) AS update_whatsapp,
            t.telegram_canal_status = 'valide' AND (p.telegram_canal_paye IS NULL OR p.telegram_canal_paye = FALSE) AS update_telegram_canal,
            t.telegram_groupe_status = 'valide' AND (p.telegram_groupe_paye IS NULL OR p.telegram_groupe_paye = FALSE) AS update_telegram_groupe,
            t.statut_status = 'valide' AND (p.statut_paye IS NULL OR p.statut_paye = FALSE) AS update_statut,
            t.story_status = 'valide' AND (p.story_paye IS NULL OR p.story_paye = FALSE) AS update_story
        FROM taches_utilisateurs t
        LEFT JOIN taches_paiements p ON t.user_id = p.user_id
        WHERE 
            (t.whatsapp_status = 'valide' AND (p.whatsapp_paye IS NULL OR p.whatsapp_paye = FALSE)) OR
            (t.telegram_canal_status = 'valide' AND (p.telegram_canal_paye IS NULL OR p.telegram_canal_paye = FALSE)) OR
            (t.telegram_groupe_status = 'valide' AND (p.telegram_groupe_paye IS NULL OR p.telegram_groupe_paye = FALSE)) OR
            (t.statut_status = 'valide' AND (p.statut_paye IS NULL OR p.statut_paye = FALSE)) OR
            (t.story_status = 'valide' AND (p.story_paye IS NULL OR p.story_paye = FALSE))
    ");
    $tasksToUpdate->execute();
    $tasks = $tasksToUpdate->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tasks as $task) {
        $checkExists = $db->prepare("SELECT id FROM taches_paiements WHERE user_id = ?");
        $checkExists->execute([$task['user_id']]);
        
        if ($checkExists->rowCount() > 0) {
            $updateQuery = "
                UPDATE taches_paiements SET
                    whatsapp_paye = CASE WHEN ? THEN TRUE ELSE whatsapp_paye END,
                    telegram_canal_paye = CASE WHEN ? THEN TRUE ELSE telegram_canal_paye END,
                    telegram_groupe_paye = CASE WHEN ? THEN TRUE ELSE telegram_groupe_paye END,
                    statut_paye = CASE WHEN ? THEN TRUE ELSE statut_paye END,
                    story_paye = CASE WHEN ? THEN TRUE ELSE story_paye END,
                    date_maj = CURRENT_TIMESTAMP
                WHERE user_id = ?
            ";
            $stmt = $db->prepare($updateQuery);
            $stmt->execute([
                $task['update_whatsapp'],
                $task['update_telegram_canal'],
                $task['update_telegram_groupe'],
                $task['update_statut'],
                $task['update_story'],
                $task['user_id']
            ]);
        } else {
            $insertQuery = "
                INSERT INTO taches_paiements (user_id, whatsapp_paye, telegram_canal_paye, telegram_groupe_paye, statut_paye, story_paye)
                VALUES (?, ?, ?, ?, ?, ?)
            ";
            $stmt = $db->prepare($insertQuery);
            $stmt->execute([
                $task['user_id'],
                $task['update_whatsapp'] ? TRUE : FALSE,
                $task['update_telegram_canal'] ? TRUE : FALSE,
                $task['update_telegram_groupe'] ? TRUE : FALSE,
                $task['update_statut'] ? TRUE : FALSE,
                $task['update_story'] ? TRUE : FALSE
            ]);
        }
    }

    

} catch (PDOException $e) {
    error_log("Erreur lors des mises à jour: " . $e->getMessage());
    echo "Erreur: " . $e->getMessage();
}