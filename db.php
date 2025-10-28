<?php
// Configuration de la base de données
$host = 'sql104.iceiy.com';
$dbname = 'icei_40255736_2026';
$username = 'icei_40255736';
$password = 'Apashash28';

try {
    // Création de la connexion PDO
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // Définition du timezone pour Abidjan (GMT+0)
    $db->exec("SET time_zone = '+00:00'");
    
    // Configuration des options PDO
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch (PDOException $e) {
    // En cas d'erreur de connexion
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Définition du timezone PHP pour Abidjan
date_default_timezone_set('Africa/Abidjan');

// Fonction simple pour remplacer NOW() par NOWX() dans vos requêtes
function NOWX() {
    return "DATE_ADD(NOW(), INTERVAL 7 HOUR)";
}
?>