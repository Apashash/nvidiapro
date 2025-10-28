-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Hôte : sql303.hstn.me
-- Généré le :  lun. 20 oct. 2025 à 18:55
-- Version du serveur :  11.4.7-MariaDB
-- Version de PHP :  7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `mseet_40127968_digi`
--

-- --------------------------------------------------------

--
-- Structure de la table `blog_comments`
--

CREATE TABLE `blog_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `status` enum('en_attente','valide','rejete') DEFAULT 'en_attente',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `images` text DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('en_attente','valide','rejete') DEFAULT 'en_attente',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `codes_utilises`
--

CREATE TABLE `codes_utilises` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_utilisation` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

CREATE TABLE `commandes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `gain_journalier` decimal(15,2) NOT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `statut` varchar(20) DEFAULT 'actif',
  `date_creation` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `comptes_reseaux`
--

CREATE TABLE `comptes_reseaux` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plateforme` enum('tiktok','instagram','facebook') NOT NULL,
  `nom_utilisateur` varchar(255) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `followers` int(11) DEFAULT 0,
  `statut` enum('en_attente','valide','rejete') DEFAULT 'en_attente',
  `niveau` int(11) DEFAULT 0,
  `date_liaison` timestamp NULL DEFAULT current_timestamp(),
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `connexions_journalieres`
--

CREATE TABLE `connexions_journalieres` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `derniere_connexion` timestamp NULL DEFAULT current_timestamp(),
  `prochain_paiement` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `demandes_packs`
--

CREATE TABLE `demandes_packs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `compte_reseau_id` int(11) NOT NULL,
  `pack_id` int(11) NOT NULL,
  `type_pack` enum('bonus','achat') NOT NULL,
  `statut` enum('en_attente','valide','livre','annule') DEFAULT 'en_attente',
  `date_demande` timestamp NULL DEFAULT current_timestamp(),
  `date_livraison` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `depots`
--

CREATE TABLE `depots` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `methode` varchar(50) NOT NULL,
  `numero_transaction` varchar(100) DEFAULT NULL,
  `pays` varchar(50) DEFAULT NULL,
  `statut` enum('en_attente','valide','rejete') DEFAULT 'en_attente',
  `date_depot` timestamp NULL DEFAULT current_timestamp(),
  `date_validation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `depot_tokens`
--

CREATE TABLE `depot_tokens` (
  `id` int(11) NOT NULL,
  `depot_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `action` enum('valide','rejete') NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `filleuls`
--

CREATE TABLE `filleuls` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `niveau1` int(11) DEFAULT 0,
  `niveau2` int(11) DEFAULT 0,
  `niveau3` int(11) DEFAULT 0,
  `investissement_niveau1` decimal(15,2) DEFAULT 0.00,
  `investissement_niveau2` decimal(15,2) DEFAULT 0.00,
  `investissement_niveau3` decimal(15,2) DEFAULT 0.00,
  `gains_niveau1` decimal(15,2) DEFAULT 0.00,
  `gains_niveau2` decimal(15,2) DEFAULT 0.00,
  `gains_niveau3` decimal(15,2) DEFAULT 0.00,
  `gains_totaux` decimal(15,2) DEFAULT 0.00,
  `total_filleuls` int(11) DEFAULT 0,
  `date_maj` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `filleuls_actifs`
--

CREATE TABLE `filleuls_actifs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `niveau1_actifs` int(11) DEFAULT 0,
  `niveau2_actifs` int(11) DEFAULT 0,
  `niveau3_actifs` int(11) DEFAULT 0,
  `date_maj` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `historique_revenus`
--

CREATE TABLE `historique_revenus` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `commande_id` int(11) DEFAULT NULL,
  `montant` decimal(15,2) NOT NULL,
  `type` enum('paiement_journalier','parrainage','bonus') NOT NULL,
  `date_paiement` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `leader`
--

CREATE TABLE `leader` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `reseau` varchar(255) NOT NULL,
  `position` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `packs_achat`
--

CREATE TABLE `packs_achat` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prix_pieces` int(11) NOT NULL,
  `followers` int(11) NOT NULL,
  `likes` int(11) NOT NULL,
  `description` text NOT NULL,
  `actif` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `packs_bonus`
--

CREATE TABLE `packs_bonus` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `niveau_requis` int(11) NOT NULL,
  `followers_gratuits` int(11) NOT NULL,
  `likes_gratuits` int(11) NOT NULL,
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `partenaires`
--

CREATE TABLE `partenaires` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `age` int(11) NOT NULL,
  `ville` varchar(100) NOT NULL,
  `pays` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `cni_type` enum('CNI','CNI_temp','passeport','carte_residence') NOT NULL,
  `cni_avant_path` varchar(255) NOT NULL,
  `cni_arriere_path` varchar(255) NOT NULL,
  `selfie_cni_path` varchar(255) NOT NULL,
  `date_demande` timestamp NULL DEFAULT current_timestamp(),
  `date_traitement` datetime DEFAULT NULL,
  `statut` enum('en_attente','approuve','rejete') DEFAULT 'en_attente',
  `commentaire` text DEFAULT NULL,
  `niveau_attribue` int(11) DEFAULT 1,
  `salaire_hebdomadaire` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `photos_profil`
--

CREATE TABLE `photos_profil` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nom_fichier` varchar(255) NOT NULL,
  `date_upload` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `pieces`
--

CREATE TABLE `pieces` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `solde` decimal(15,2) DEFAULT 0.00,
  `solde_precedent` decimal(15,2) DEFAULT 0.00,
  `date_maj` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `planinvestissement`
--

CREATE TABLE `planinvestissement` (
  `id` int(11) NOT NULL,
  `serie` enum('X','B') NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `rendement_journalier` decimal(5,2) NOT NULL,
  `duree_jours` int(11) NOT NULL,
  `description` text NOT NULL,
  `image_url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `planinvestissement`
--

INSERT INTO `planinvestissement` (`id`, `serie`, `nom`, `prix`, `rendement_journalier`, `duree_jours`, `description`, `image_url`) VALUES
(1, 'X', 'Action VIP 1', '3000.00', '10.50', 125, 'Plan Action VIP 1 - Investissement premium avec rendement élevé', 'vip.jpg'),
(2, 'X', 'Action VIP 2', '7000.00', '11.00', 125, 'Plan Action VIP 2 - Investissement premium avec rendement élevé', 'vip.jpg'),
(3, 'X', 'Action VIP 3', '15000.00', '12.00', 125, 'Plan Action VIP 3 - Investissement premium avec rendement élevé', 'vip.jpg'),
(4, 'X', 'Action VIP 4', '25000.00', '12.50', 125, 'Plan Action VIP 4 - Investissement premium avec rendement élevé', 'vip.jpg'),
(5, 'X', 'Action VIP 5', '45000.00', '13.00', 125, 'Plan Action VIP 5 - Investissement premium avec rendement élevé', 'vip.jpg'),
(6, 'X', 'Action VIP 6', '70000.00', '13.50', 125, 'Plan Action VIP 6 - Investissement premium avec rendement élevé', 'vip.jpg'),
(7, 'X', 'Action VIP 7', '115000.00', '14.00', 125, 'Plan Action VIP 7 - Investissement premium avec rendement élevé', 'vip.jpg'),
(8, 'X', 'Action VIP 8', '170000.00', '14.50', 125, 'Plan Action VIP 8 - Investissement premium avec rendement élevé', 'vip.jpg'),
(9, 'X', 'Action VIP 9', '250000.00', '19.50', 125, 'Plan Action VIP 9 - Investissement premium avec rendement élevé', 'vip.jpg'),
(10, 'X', 'Action VIP 10', '400000.00', '19.50', 125, 'Plan Action VIP 10 - Investissement premium avec rendement élevé', 'vip.jpg'),
(11, 'X', 'Action VIP 11', '600000.00', '19.50', 125, 'Plan Action VIP 11 - Investissement premium avec rendement élevé', 'vip.jpg');

-- --------------------------------------------------------

--
-- Structure de la table `portefeuilles`
--

CREATE TABLE `portefeuilles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nom_portefeuille` varchar(255) NOT NULL,
  `pays` varchar(100) NOT NULL,
  `methode_paiement` varchar(100) NOT NULL,
  `numero_telephone` varchar(20) NOT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `date_modification` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `image` varchar(255) NOT NULL,
  `likes` int(11) DEFAULT 0,
  `statut` enum('en_attente','valide','refuse') DEFAULT 'en_attente',
  `date_creation` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `problemes_depots`
--

CREATE TABLE `problemes_depots` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `depot_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `photos` text DEFAULT NULL,
  `statut` enum('en_attente','traite','resolu') DEFAULT 'en_attente',
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `date_traitement` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `retraits`
--

CREATE TABLE `retraits` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `methode` varchar(50) NOT NULL,
  `numero_compte` varchar(100) NOT NULL,
  `statut` enum('en_attente','valide','rejete') DEFAULT 'en_attente',
  `date_demande` timestamp NULL DEFAULT current_timestamp(),
  `date_traitement` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `retrait_tokens`
--

CREATE TABLE `retrait_tokens` (
  `id` int(11) NOT NULL,
  `retrait_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `action` enum('valide','rejete') NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `roue`
--

CREATE TABLE `roue` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nombre_tours` int(11) DEFAULT 0,
  `dernier_gain` int(11) DEFAULT 0,
  `date_dernier_tour` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `soldes`
--

CREATE TABLE `soldes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `solde` decimal(15,2) DEFAULT 0.00,
  `solde_precedent` decimal(15,2) DEFAULT 0.00,
  `date_maj` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `taches_paiements`
--

CREATE TABLE `taches_paiements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `whatsapp_paye` tinyint(1) DEFAULT 0,
  `telegram_canal_paye` tinyint(1) DEFAULT 0,
  `telegram_groupe_paye` tinyint(1) DEFAULT 0,
  `statut_paye` tinyint(1) DEFAULT 0,
  `story_paye` tinyint(1) DEFAULT 0,
  `date_maj` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `taches_utilisateurs`
--

CREATE TABLE `taches_utilisateurs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `whatsapp_status` enum('non_fait','en_attente','valide') DEFAULT 'non_fait',
  `whatsapp_image` varchar(255) DEFAULT NULL,
  `whatsapp_time` datetime DEFAULT NULL,
  `telegram_canal_status` enum('non_fait','en_attente','valide') DEFAULT 'non_fait',
  `telegram_canal_image` varchar(255) DEFAULT NULL,
  `telegram_canal_time` datetime DEFAULT NULL,
  `telegram_groupe_status` enum('non_fait','en_attente','valide') DEFAULT 'non_fait',
  `telegram_groupe_image` varchar(255) DEFAULT NULL,
  `telegram_groupe_time` datetime DEFAULT NULL,
  `statut_status` enum('non_fait','en_attente','valide') DEFAULT 'non_fait',
  `statut_image` varchar(255) DEFAULT NULL,
  `statut_time` datetime DEFAULT NULL,
  `statut_views` int(11) DEFAULT 0,
  `story_status` enum('non_fait','en_attente','valide') DEFAULT 'non_fait',
  `story_image` varchar(255) DEFAULT NULL,
  `story_time` datetime DEFAULT NULL,
  `groupes_status` enum('non_fait','en_attente','valide') DEFAULT 'non_fait',
  `groupes_image` text DEFAULT NULL,
  `groupes_time` datetime DEFAULT NULL,
  `date_maj` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transaction_passwords`
--

CREATE TABLE `transaction_passwords` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `password` varchar(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'admin',
  `email_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `code` varchar(6) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_instagram`
--

CREATE TABLE `user_instagram` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_tiktok`
--

CREATE TABLE `user_tiktok` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `pays` varchar(50) DEFAULT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `solde` decimal(15,2) DEFAULT 0.00,
  `revenus_totaux` decimal(15,2) DEFAULT 0.00,
  `nombre_filleuls` int(11) DEFAULT 0,
  `code_parrainage` varchar(20) DEFAULT NULL,
  `parrain_id` int(11) DEFAULT NULL,
  `lien_parrainage` varchar(255) DEFAULT NULL,
  `date_inscription` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `vip`
--

CREATE TABLE `vip` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `niveau` int(11) DEFAULT 0,
  `pourcentage` int(11) DEFAULT 0,
  `invitations_requises` int(11) DEFAULT 3,
  `invitations_actuelles` int(11) DEFAULT 0,
  `date_maj` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `blog_comments`
--
ALTER TABLE `blog_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `codes_utilises`
--
ALTER TABLE `codes_utilises`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_code_user` (`user_id`,`code`);

--
-- Index pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Index pour la table `comptes_reseaux`
--
ALTER TABLE `comptes_reseaux`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `connexions_journalieres`
--
ALTER TABLE `connexions_journalieres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Index pour la table `demandes_packs`
--
ALTER TABLE `demandes_packs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `compte_reseau_id` (`compte_reseau_id`),
  ADD KEY `pack_id` (`pack_id`);

--
-- Index pour la table `depots`
--
ALTER TABLE `depots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `depot_tokens`
--
ALTER TABLE `depot_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `depot_id` (`depot_id`);

--
-- Index pour la table `filleuls`
--
ALTER TABLE `filleuls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `filleuls_actifs`
--
ALTER TABLE `filleuls_actifs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Index pour la table `historique_revenus`
--
ALTER TABLE `historique_revenus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `commande_id` (`commande_id`);

--
-- Index pour la table `leader`
--
ALTER TABLE `leader`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `packs_achat`
--
ALTER TABLE `packs_achat`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `packs_bonus`
--
ALTER TABLE `packs_bonus`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `partenaires`
--
ALTER TABLE `partenaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date_demande` (`date_demande`);

--
-- Index pour la table `photos_profil`
--
ALTER TABLE `photos_profil`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `pieces`
--
ALTER TABLE `pieces`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Index pour la table `planinvestissement`
--
ALTER TABLE `planinvestissement`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `portefeuilles`
--
ALTER TABLE `portefeuilles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Index pour la table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `problemes_depots`
--
ALTER TABLE `problemes_depots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `depot_id` (`depot_id`);

--
-- Index pour la table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `retraits`
--
ALTER TABLE `retraits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `retrait_tokens`
--
ALTER TABLE `retrait_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `retrait_id` (`retrait_id`);

--
-- Index pour la table `roue`
--
ALTER TABLE `roue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `soldes`
--
ALTER TABLE `soldes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `taches_paiements`
--
ALTER TABLE `taches_paiements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `taches_utilisateurs`
--
ALTER TABLE `taches_utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `transaction_passwords`
--
ALTER TABLE `transaction_passwords`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `user_instagram`
--
ALTER TABLE `user_instagram`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username` (`username`);

--
-- Index pour la table `user_tiktok`
--
ALTER TABLE `user_tiktok`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telephone` (`telephone`),
  ADD UNIQUE KEY `code_parrainage` (`code_parrainage`),
  ADD KEY `parrain_id` (`parrain_id`),
  ADD KEY `idx_code_parrainage` (`code_parrainage`);

--
-- Index pour la table `vip`
--
ALTER TABLE `vip`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `blog_comments`
--
ALTER TABLE `blog_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `codes_utilises`
--
ALTER TABLE `codes_utilises`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `comptes_reseaux`
--
ALTER TABLE `comptes_reseaux`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `connexions_journalieres`
--
ALTER TABLE `connexions_journalieres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `demandes_packs`
--
ALTER TABLE `demandes_packs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `depots`
--
ALTER TABLE `depots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `depot_tokens`
--
ALTER TABLE `depot_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `filleuls`
--
ALTER TABLE `filleuls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `filleuls_actifs`
--
ALTER TABLE `filleuls_actifs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `historique_revenus`
--
ALTER TABLE `historique_revenus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `leader`
--
ALTER TABLE `leader`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `packs_achat`
--
ALTER TABLE `packs_achat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `packs_bonus`
--
ALTER TABLE `packs_bonus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `partenaires`
--
ALTER TABLE `partenaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `photos_profil`
--
ALTER TABLE `photos_profil`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `pieces`
--
ALTER TABLE `pieces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `planinvestissement`
--
ALTER TABLE `planinvestissement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `portefeuilles`
--
ALTER TABLE `portefeuilles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `problemes_depots`
--
ALTER TABLE `problemes_depots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `retraits`
--
ALTER TABLE `retraits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `retrait_tokens`
--
ALTER TABLE `retrait_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `roue`
--
ALTER TABLE `roue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `soldes`
--
ALTER TABLE `soldes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `taches_paiements`
--
ALTER TABLE `taches_paiements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `taches_utilisateurs`
--
ALTER TABLE `taches_utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transaction_passwords`
--
ALTER TABLE `transaction_passwords`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_instagram`
--
ALTER TABLE `user_instagram`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user_tiktok`
--
ALTER TABLE `user_tiktok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `vip`
--
ALTER TABLE `vip`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `blog_comments`
--
ALTER TABLE `blog_comments`
  ADD CONSTRAINT `blog_comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`),
  ADD CONSTRAINT `blog_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD CONSTRAINT `blog_posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `codes_utilises`
--
ALTER TABLE `codes_utilises`
  ADD CONSTRAINT `codes_utilises_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `commandes_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `planinvestissement` (`id`);

--
-- Contraintes pour la table `connexions_journalieres`
--
ALTER TABLE `connexions_journalieres`
  ADD CONSTRAINT `connexions_journalieres_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `portefeuilles`
--
ALTER TABLE `portefeuilles`
  ADD CONSTRAINT `portefeuilles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
