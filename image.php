<?php
/**
 * Fonction pour générer automatiquement l'URL d'une image
 * avec un paramètre de version basé sur la dernière modification.
 * Cela force le navigateur à recharger l'image si elle change.
 */

function imageUrl($path) {
    // Vérifie si le fichier existe physiquement
    if (file_exists($path)) {
        $version = filemtime($path); // date de dernière modification
    } else {
        $version = time(); // pour forcer une vérification quand l'image n'existait pas avant
    }

    // Retourne l'URL avec un paramètre de version
    return $path . '?v=' . $version;
}
?>