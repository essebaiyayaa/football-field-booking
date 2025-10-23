CREATE DATABASE IF NOT EXISTS gestion_terrains_foot
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE gestion_terrains_foot;

CREATE TABLE Utilisateur (
    id_utilisateur INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telephone VARCHAR(20),
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('client', 'admin', 'gerant_terrain') DEFAULT 'client'
);

CREATE TABLE Terrain (
    id_terrain INT PRIMARY KEY AUTO_INCREMENT,
    nom_terrain VARCHAR(100) NOT NULL,
    adresse VARCHAR(255) NOT NULL,
    ville VARCHAR(100) NOT NULL,
    taille ENUM('Mini foot', 'Terrain moyen', 'Grand terrain') NOT NULL,
    type ENUM('Gazon naturel', 'Gazon artificiel', 'Terrain dur') NOT NULL,
    prix_heure DECIMAL(10,2) NOT NULL DEFAULT 0.00
);
CREATE TABLE OptionSupplementaire (
    id_option INT PRIMARY KEY AUTO_INCREMENT,
    nom_option VARCHAR(100) NOT NULL,
    prix DECIMAL(10,2) DEFAULT 0.00
);

CREATE TABLE Reservation (
    id_reservation INT PRIMARY KEY AUTO_INCREMENT,
    date_reservation DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    id_utilisateur INT NOT NULL,
    id_terrain INT NOT NULL,
    commentaires TEXT,
    statut ENUM('Confirmée', 'Annulée', 'Modifiée') DEFAULT 'Confirmée',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP NULL,
    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur) ON DELETE CASCADE,
    FOREIGN KEY (id_terrain) REFERENCES Terrain(id_terrain) ON DELETE CASCADE
);

CREATE TABLE Reservation_Option (
    id_reservation INT,
    id_option INT,
    PRIMARY KEY (id_reservation, id_option),
    FOREIGN KEY (id_reservation) REFERENCES Reservation(id_reservation) ON DELETE CASCADE,
    FOREIGN KEY (id_option) REFERENCES OptionSupplementaire(id_option) ON DELETE CASCADE
);

CREATE TABLE Promotion (
    id_promo INT PRIMARY KEY AUTO_INCREMENT,
    id_terrain INT NOT NULL,
    description VARCHAR(255),
    pourcentage_remise DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    FOREIGN KEY (id_terrain) REFERENCES Terrain(id_terrain) ON DELETE CASCADE
);

CREATE TABLE Facture (
    id_facture INT PRIMARY KEY AUTO_INCREMENT,
    id_reservation INT UNIQUE,
    id_promo INT NULL,
    montant_total DECIMAL(10,2) NOT NULL,
    tva DECIMAL(5,2) DEFAULT 0.00,
    remise DECIMAL(10,2) DEFAULT 0.00,
    date_facture DATE DEFAULT (CURRENT_DATE),
    FOREIGN KEY (id_reservation) REFERENCES Reservation(id_reservation) ON DELETE CASCADE,
    FOREIGN KEY (id_promo) REFERENCES Promotion(id_promo) ON DELETE SET NULL
);

CREATE TABLE Tournoi (
    id_tournoi INT PRIMARY KEY AUTO_INCREMENT,
    nom_tournoi VARCHAR(100) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    id_terrain INT NOT NULL,
    id_gerant INT NOT NULL,
    statut ENUM('En préparation', 'En cours', 'Terminé') DEFAULT 'En préparation',
    FOREIGN KEY (id_terrain) REFERENCES Terrain(id_terrain) ON DELETE CASCADE,
    FOREIGN KEY (id_gerant) REFERENCES Utilisateur(id_utilisateur) ON DELETE CASCADE
);

CREATE TABLE Equipe (
    id_equipe INT PRIMARY KEY AUTO_INCREMENT,
    nom_equipe VARCHAR(100) NOT NULL,
    id_responsable INT NOT NULL,
    id_tournoi INT NOT NULL,
    FOREIGN KEY (id_responsable) REFERENCES Utilisateur(id_utilisateur) ON DELETE CASCADE,
    FOREIGN KEY (id_tournoi) REFERENCES Tournoi(id_tournoi) ON DELETE CASCADE
);

