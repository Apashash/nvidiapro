-- Script SQL pour corriger la base de données
-- À copier-coller dans votre panneau phpMyAdmin ou console MySQL

-- 1. Ajouter la colonne date_validation manquante dans la table retraits
ALTER TABLE `retraits` ADD COLUMN `date_validation` DATETIME DEFAULT NULL AFTER `date_demande`;

-- 2. Mettre à jour les enregistrements existants validés pour avoir une date_validation
UPDATE `retraits` 
SET `date_validation` = `date_traitement` 
WHERE `statut` = 'valide' AND `date_validation` IS NULL AND `date_traitement` IS NOT NULL;

-- 3. Vérifier la structure de la table depots (doit déjà avoir date_validation)
-- Si date_validation n'existe pas dans depots, décommentez la ligne suivante:
-- ALTER TABLE `depots` ADD COLUMN `date_validation` DATETIME DEFAULT NULL AFTER `date_depot`;

-- Note: Ces modifications sont nécessaires pour que les validations de retrait fonctionnent correctement
