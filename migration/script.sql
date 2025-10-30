-- ============================================================
-- SCRIPT SQL FINAL - GESTION TERRAINS DE FOOTBALL
-- Version: 1.0
-- Date: 2025
-- ============================================================

-- Suppression de la base de données si elle existe (attention: perte de données!)
DROP DATABASE IF EXISTS gestion_terrains_foot;

-- Création de la base de données
CREATE DATABASE gestion_terrains_foot
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE gestion_terrains_foot;

-- ============================================================
-- TABLE: Utilisateur
-- Description: Gestion des utilisateurs (clients, gérants, admins)
-- ============================================================
CREATE TABLE Utilisateur (
    id_utilisateur INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telephone VARCHAR(20),
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('Utilisateur', 'Administrateur', 'Gérant') DEFAULT 'Utilisateur',
    verification_token VARCHAR(64) NULL,
    token_expiry DATETIME NULL,
    email_verifie TINYINT(1) DEFAULT 0,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_derniere_connexion TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_verification_token (verification_token),
    INDEX idx_email_verifie (email_verifie),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Terrain
-- Description: Informations sur les terrains de football
-- ============================================================
CREATE TABLE Terrain (
    id_terrain INT PRIMARY KEY AUTO_INCREMENT,
    nom_terrain VARCHAR(100) NOT NULL,
    adresse VARCHAR(255) NOT NULL,
    ville VARCHAR(100) NOT NULL,
    taille ENUM('Mini foot', 'Terrain moyen', 'Grand terrain') NOT NULL,
    type ENUM('Gazon naturel', 'Gazon artificiel', 'Terrain dur') NOT NULL,
    prix_heure DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    id_utilisateur INT NULL COMMENT 'Gérant du terrain',
    actif TINYINT(1) DEFAULT 1,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ville (ville),
    INDEX idx_taille (taille),
    INDEX idx_type (type),
    INDEX idx_actif (actif),
    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: OptionSupplementaire
-- Description: Options supplémentaires pour les réservations
-- ============================================================
CREATE TABLE OptionSupplementaire (
    id_option INT PRIMARY KEY AUTO_INCREMENT,
    nom_option VARCHAR(100) NOT NULL,
    description TEXT,
    prix DECIMAL(10,2) DEFAULT 0.00,
    actif TINYINT(1) DEFAULT 1,
    INDEX idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Reservation
-- Description: Réservations des terrains
-- ============================================================
CREATE TABLE Reservation (
    id_reservation INT PRIMARY KEY AUTO_INCREMENT,
    date_reservation DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    id_utilisateur INT NOT NULL,
    id_terrain INT NOT NULL,
    commentaires TEXT,
    statut ENUM('En attente', 'Confirmée', 'Annulée', 'Modifiée') DEFAULT 'En attente',
    statut_paiement ENUM('en_attente', 'paye', 'annule') DEFAULT 'en_attente',
    prix_total DECIMAL(10,2) DEFAULT 0.00,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date_reservation (date_reservation),
    INDEX idx_statut (statut),
    INDEX idx_statut_paiement (statut_paiement),
    INDEX idx_utilisateur (id_utilisateur),
    INDEX idx_terrain (id_terrain),
    INDEX idx_terrain_date (id_terrain, date_reservation, heure_debut),
    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur) ON DELETE CASCADE,
    FOREIGN KEY (id_terrain) REFERENCES Terrain(id_terrain) ON DELETE CASCADE,
    UNIQUE KEY unique_reservation_slot (id_terrain, date_reservation, heure_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Reservation_Option
-- Description: Liaison entre réservations et options
-- ============================================================
CREATE TABLE Reservation_Option (
    id_reservation INT,
    id_option INT,
    PRIMARY KEY (id_reservation, id_option),
    FOREIGN KEY (id_reservation) REFERENCES Reservation(id_reservation) ON DELETE CASCADE,
    FOREIGN KEY (id_option) REFERENCES OptionSupplementaire(id_option) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Gerant_Terrain
-- Description: Association gérants et terrains (relation N:M)
-- ============================================================
CREATE TABLE Gerant_Terrain (
    id_utilisateur INT,
    id_terrain INT,
    date_assignation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_utilisateur, id_terrain),
    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur) ON DELETE CASCADE,
    FOREIGN KEY (id_terrain) REFERENCES Terrain(id_terrain) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Promotion
-- Description: Promotions sur les terrains
-- ============================================================
CREATE TABLE Promotion (
    id_promo INT PRIMARY KEY AUTO_INCREMENT,
    id_terrain INT NOT NULL,
    description VARCHAR(255),
    pourcentage_remise DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    actif TINYINT(1) DEFAULT 1,
    INDEX idx_dates (date_debut, date_fin),
    INDEX idx_actif (actif),
    FOREIGN KEY (id_terrain) REFERENCES Terrain(id_terrain) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Facture
-- Description: Factures des réservations
-- ============================================================
CREATE TABLE Facture (
    id_facture INT PRIMARY KEY AUTO_INCREMENT,
    id_reservation INT UNIQUE,
    id_promo INT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    tva DECIMAL(5,2) DEFAULT 0.00,
    remise DECIMAL(10,2) DEFAULT 0.00,
    date_facture DATE DEFAULT (CURRENT_DATE),
    statut_facture ENUM('en_attente', 'payee', 'annulee') DEFAULT 'en_attente',
    INDEX idx_date_facture (date_facture),
    INDEX idx_statut (statut_facture),
    FOREIGN KEY (id_reservation) REFERENCES Reservation(id_reservation) ON DELETE CASCADE,
    FOREIGN KEY (id_promo) REFERENCES Promotion(id_promo) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Tournoi
-- Description: Gestion des tournois
-- ============================================================
CREATE TABLE Tournoi (
    id_tournoi INT PRIMARY KEY AUTO_INCREMENT,
    nom_tournoi VARCHAR(100) NOT NULL,
    description TEXT,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    id_terrain INT NOT NULL,
    id_gerant INT NOT NULL,
    statut ENUM('En préparation', 'En cours', 'Terminé', 'Annulé') DEFAULT 'En préparation',
    nombre_max_equipes INT DEFAULT 8,
    prix_inscription DECIMAL(10,2) DEFAULT 0.00,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_statut (statut),
    INDEX idx_dates (date_debut, date_fin),
    FOREIGN KEY (id_terrain) REFERENCES Terrain(id_terrain) ON DELETE CASCADE,
    FOREIGN KEY (id_gerant) REFERENCES Utilisateur(id_utilisateur) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: Equipe
-- Description: Équipes participant aux tournois
-- ============================================================
CREATE TABLE Equipe (
    id_equipe INT PRIMARY KEY AUTO_INCREMENT,
    nom_equipe VARCHAR(100) NOT NULL,
    id_responsable INT NOT NULL,
    id_tournoi INT NOT NULL,
    nombre_joueurs INT DEFAULT 0,
    date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tournoi (id_tournoi),
    FOREIGN KEY (id_responsable) REFERENCES Utilisateur(id_utilisateur) ON DELETE CASCADE,
    FOREIGN KEY (id_tournoi) REFERENCES Tournoi(id_tournoi) ON DELETE CASCADE,
    UNIQUE KEY unique_equipe_tournoi (nom_equipe, id_tournoi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRIGGER: Empêcher les doubles réservations
-- ============================================================
DELIMITER $$

DROP TRIGGER IF EXISTS prevent_double_booking$$

CREATE TRIGGER prevent_double_booking
BEFORE INSERT ON Reservation
FOR EACH ROW
BEGIN
    DECLARE existing_count INT;
    
    SELECT COUNT(*) INTO existing_count
    FROM Reservation
    WHERE id_terrain = NEW.id_terrain
    AND date_reservation = NEW.date_reservation
    AND heure_debut = NEW.heure_debut
    AND statut != 'Annulée';
    
    IF existing_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ce créneau est déjà réservé';
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- DONNÉES DE TEST
-- ============================================================

-- Insertion d'un administrateur par défaut
-- Mot de passe: admin123
INSERT INTO Utilisateur (nom, prenom, email, telephone, mot_de_passe, role, email_verifie) VALUES
('Admin', 'Super', 'admin@footbooking.com', '0612345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrateur', 1);

-- Insertion de gérants
-- Mot de passe: gerant123
INSERT INTO Utilisateur (nom, prenom, email, telephone, mot_de_passe, role, email_verifie) VALUES
('Benali', 'Ahmed', 'ahmed.benali@footbooking.com', '0623456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Gérant', 1),
('El Fassi', 'Karim', 'karim.elfassi@footbooking.com', '0634567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Gérant', 1),
('Alaoui', 'Said', 'said.alaoui@footbooking.com', '0645678901', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Gérant', 1);

-- Insertion de clients
-- Mot de passe: client123
INSERT INTO Utilisateur (nom, prenom, email, telephone, mot_de_passe, role, email_verifie) VALUES
('Tazi', 'Mohammed', 'mohammed.tazi@example.com', '0656789012', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Utilisateur', 1),
('Amrani', 'Fatima', 'fatima.amrani@example.com', '0667890123', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Utilisateur', 1),
('Kadiri', 'Youssef', 'youssef.kadiri@example.com', '0678901234', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Utilisateur', 1);

-- Insertion des terrains
INSERT INTO Terrain (nom_terrain, adresse, ville, taille, type, prix_heure, id_utilisateur) VALUES
('Stade Martil', 'Avenue Hassan II', 'Tétouan', 'Grand terrain', 'Gazon naturel', 220.00, 2),
('Complex Sportif Tamouda Bay', 'Route de Ceuta', 'Tétouan', 'Terrain moyen', 'Gazon artificiel', 170.00, 2),
('Terrain Ksar Rimal', 'Plage Ksar Rimal', 'Tétouan', 'Mini foot', 'Gazon artificiel', 110.00, 3),
('Stade Saniat Rmel', 'Quartier Saniat Rmel', 'Tétouan', 'Grand terrain', 'Gazon naturel', 250.00, 3),
('Terrain Mhannech', 'Boulevard Mhannech', 'Tétouan', 'Terrain moyen', 'Terrain dur', 140.00, 4),
('Complex Oued Laou', 'Route Oued Laou', 'Tétouan', 'Mini foot', 'Gazon artificiel', 100.00, 4),
('Stade El Ouad', 'Quartier El Ouad', 'Tétouan', 'Grand terrain', 'Gazon artificiel', 210.00, 2),
('Terrain Boussafou', 'Quartier Boussafou', 'Tétouan', 'Terrain moyen', 'Gazon naturel', 160.00, 3),
('Complex Moulay El Mehdi', 'Avenue Moulay El Mehdi', 'Tétouan', 'Mini foot', 'Terrain dur', 90.00, 4),
('Stade Wilaya', 'Route de Martil', 'Tétouan', 'Grand terrain', 'Gazon naturel', 240.00, 2),
('Terrain Medina', 'Ancienne Médina', 'Tétouan', 'Terrain moyen', 'Gazon artificiel', 150.00, 3),
('Complex Cabo Negro', 'Cabo Negro', 'Tétouan', 'Mini foot', 'Gazon artificiel', 120.00, 4);

-- Association gérants-terrains
INSERT INTO Gerant_Terrain (id_utilisateur, id_terrain) VALUES
(2, 1), (2, 2), (2, 7), (2, 10),
(3, 3), (3, 4), (3, 8), (3, 11),
(4, 5), (4, 6), (4, 9), (4, 12);

-- Insertion des options supplémentaires
INSERT INTO OptionSupplementaire (nom_option, description, prix) VALUES
('Ballons de football', 'Set de 3 ballons professionnels', 30.00),
('Maillots', 'Location de maillots (2 équipes)', 50.00),
('Arbitre', 'Service d\'arbitrage professionnel', 80.00),
('Éclairage nocturne', 'Éclairage pour matchs de nuit', 40.00),
('Vestiaires premium', 'Accès aux vestiaires avec douches', 25.00),
('Matériel d\'entraînement', 'Plots, cônes, chasubles', 35.00),
('Boissons énergétiques', 'Pack de boissons pour les joueurs', 45.00),
('Photographe', 'Service photo du match', 100.00);

-- Insertion de réservations de test
INSERT INTO Reservation (date_reservation, heure_debut, heure_fin, id_utilisateur, id_terrain, statut, statut_paiement, prix_total) VALUES
('2025-11-05', '10:00:00', '11:00:00', 5, 1, 'Confirmée', 'paye', 220.00),
('2025-11-05', '14:00:00', '15:00:00', 6, 3, 'Confirmée', 'paye', 110.00),
('2025-11-06', '16:00:00', '17:00:00', 7, 5, 'En attente', 'en_attente', 140.00),
('2025-11-07', '18:00:00', '19:00:00', 5, 7, 'Confirmée', 'paye', 210.00);

-- Insertion de promotions
INSERT INTO Promotion (id_terrain, description, pourcentage_remise, date_debut, date_fin) VALUES
(1, 'Promotion Ramadan', 15.00, '2025-12-01', '2025-12-31'),
(3, 'Offre Week-end', 10.00, '2025-11-01', '2025-11-30'),
(5, 'Happy Hours 14h-16h', 20.00, '2025-11-01', '2025-12-31');

-- ============================================================
-- VUES UTILES
-- ============================================================

-- Vue: Réservations avec détails
CREATE OR REPLACE VIEW v_reservations_details AS
SELECT 
    r.id_reservation,
    r.date_reservation,
    r.heure_debut,
    r.heure_fin,
    r.statut,
    r.statut_paiement,
    r.prix_total,
    u.nom AS client_nom,
    u.prenom AS client_prenom,
    u.email AS client_email,
    u.telephone AS client_telephone,
    t.nom_terrain,
    t.ville,
    t.taille,
    t.type,
    g.nom AS gerant_nom,
    g.prenom AS gerant_prenom
FROM Reservation r
JOIN Utilisateur u ON r.id_utilisateur = u.id_utilisateur
JOIN Terrain t ON r.id_terrain = t.id_terrain
LEFT JOIN Utilisateur g ON t.id_utilisateur = g.id_utilisateur;

-- Vue: Statistiques par terrain
CREATE OR REPLACE VIEW v_stats_terrains AS
SELECT 
    t.id_terrain,
    t.nom_terrain,
    t.ville,
    t.type,
    t.taille,
    t.prix_heure,
    COUNT(r.id_reservation) AS nombre_reservations,
    SUM(CASE WHEN r.statut = 'Confirmée' THEN 1 ELSE 0 END) AS reservations_confirmees,
    SUM(r.prix_total) AS revenus_total,
    AVG(r.prix_total) AS prix_moyen_reservation
FROM Terrain t
LEFT JOIN Reservation r ON t.id_terrain = r.id_terrain
GROUP BY t.id_terrain;

-- ============================================================
-- PROCÉDURES STOCKÉES
-- ============================================================

-- Procédure: Obtenir les créneaux disponibles
DELIMITER $$

DROP PROCEDURE IF EXISTS GetAvailableSlots$$

CREATE PROCEDURE GetAvailableSlots(
    IN p_id_terrain INT,
    IN p_date DATE
)
BEGIN
    SELECT 
        TIME_FORMAT(h.slot_time, '%H:%i:%s') AS heure_debut,
        CASE 
            WHEN r.id_reservation IS NOT NULL THEN 0
            ELSE 1
        END AS disponible
    FROM (
        SELECT '08:00:00' AS slot_time UNION ALL
        SELECT '09:00:00' UNION ALL SELECT '10:00:00' UNION ALL
        SELECT '11:00:00' UNION ALL SELECT '12:00:00' UNION ALL
        SELECT '13:00:00' UNION ALL SELECT '14:00:00' UNION ALL
        SELECT '15:00:00' UNION ALL SELECT '16:00:00' UNION ALL
        SELECT '17:00:00' UNION ALL SELECT '18:00:00' UNION ALL
        SELECT '19:00:00' UNION ALL SELECT '20:00:00' UNION ALL
        SELECT '21:00:00'
    ) h
    LEFT JOIN Reservation r ON 
        r.id_terrain = p_id_terrain 
        AND r.date_reservation = p_date 
        AND r.heure_debut = h.slot_time
        AND r.statut != 'Annulée'
    ORDER BY h.slot_time;
END$$

DELIMITER ;

-- ============================================================
-- INDEX SUPPLÉMENTAIRES POUR OPTIMISATION
-- ============================================================

ALTER TABLE Reservation ADD INDEX idx_date_statut (date_reservation, statut);
ALTER TABLE Terrain ADD INDEX idx_ville_actif (ville, actif);
ALTER TABLE Utilisateur ADD INDEX idx_role_verifie (role, email_verifie);

-- ============================================================
-- FIN DU SCRIPT
-- ============================================================

-- Afficher un résumé
SELECT 'Base de données créée avec succès!' AS Message;
SELECT COUNT(*) AS Nombre_Utilisateurs FROM Utilisateur;
SELECT COUNT(*) AS Nombre_Terrains FROM Terrain;
SELECT COUNT(*) AS Nombre_Options FROM OptionSupplementaire;
SELECT COUNT(*) AS Nombre_Reservations FROM Reservation;