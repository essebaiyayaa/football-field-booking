<?php
/**
 * Interface de consultation des réservations pour les administrateurs
 * 
 * Ce fichier permet aux administrateurs de visualiser toutes les réservations
 * de tous les terrains avec filtrage par statut, date et terrain.
 * 
 * @author Jihane Chouhe
 * @version 1.0.0
 * @date 2024-10-30
 * 
 * @changelog
 * Version 1.0.0 (2024-10-30)
 * - Création de l'interface de consultation des réservations admin
 * - Ajout de l'affichage paginé de toutes les réservations
 * - Implémentation des filtres (statut, date, terrain)
 * - Affichage des informations client, terrain et gérant
 * - Ajout de la pagination
 */

session_start();
require_once '../config/database.php';

// Vérification que l'utilisateur est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

/**
 * Récupération des informations de l'administrateur connecté
 */
try {
    $stmt = $pdo->prepare("SELECT nom, prenom FROM Utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}

/**
 * Configuration de la pagination
 */
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15; // Nombre de réservations par page
$offset = ($page - 1) * $limit;

/**
 * Récupération des filtres de recherche
 */
$statut_filter = $_GET['statut'] ?? '';
$date_filter = $_GET['date'] ?? '';
$terrain_filter = $_GET['terrain'] ?? '';

/**
 * Récupération de tous les terrains pour le filtre
 */
try {
    $stmt = $pdo->query("SELECT id_terrain, nom_terrain FROM Terrain ORDER BY nom_terrain");
    $terrains = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $terrains = [];
}

/**
 * Récupération des réservations avec filtres et pagination
 */
try {
    // Construction de la requête SQL avec filtres dynamiques
    $sql = "
        SELECT 
            r.*, 
            t.nom_terrain, 
            t.adresse,
            t.ville,
            u.prenom as client_prenom, 
            u.nom as client_nom, 
            u.telephone,
            g.prenom as gerant_prenom,
            g.nom as gerant_nom
        FROM Reservation r
        JOIN Terrain t ON r.id_terrain = t.id_terrain
        JOIN Utilisateur u ON r.id_utilisateur = u.id_utilisateur
        LEFT JOIN Utilisateur g ON t.id_utilisateur = g.id_utilisateur
        WHERE 1=1
    ";
    
    $params = [];
    
    // Ajout du filtre de statut si spécifié
    if (!empty($statut_filter)) {
        $sql .= " AND r.statut_paiement = ?";
        $params[] = $statut_filter;
    }
    
    // Ajout du filtre de date si spécifié
    if (!empty($date_filter)) {
        $sql .= " AND r.date_reservation = ?";
        $params[] = $date_filter;
    }
    
    // Ajout du filtre de terrain si spécifié
    if (!empty($terrain_filter)) {
        $sql .= " AND r.id_terrain = ?";
        $params[] = $terrain_filter;
    }
    
    // Tri et limitation pour la pagination
    $sql .= " ORDER BY r.date_reservation DESC, r.heure_debut DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    /**
     * Comptage du nombre total de réservations pour la pagination
     */
    $count_sql = "
        SELECT COUNT(*) 
        FROM Reservation r
        JOIN Terrain t ON r.id_terrain = t.id_terrain
        WHERE 1=1
    ";
    $count_params = [];
    
    // Application des mêmes filtres pour le comptage
    if (!empty($statut_filter)) {
        $count_sql .= " AND r.statut_paiement = ?";
        $count_params[] = $statut_filter;
    }
    
    if (!empty($date_filter)) {
        $count_sql .= " AND r.date_reservation = ?";
        $count_params[] = $date_filter;
    }
    
    if (!empty($terrain_filter)) {
        $count_sql .= " AND r.id_terrain = ?";
        $count_params[] = $terrain_filter;
    }
    
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($count_params);
    $total_reservations = $stmt->fetchColumn();
    $total_pages = ceil($total_reservations / $limit);
    
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toutes les Réservations - Admin FootBooking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            color: #334155;
        }

        /* === HEADER === */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: #dc2626;
            text-decoration: none;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .back-btn:hover {
            color: #dc2626;
        }

        /* === CONTAINER === */
        .container {
            max-width: 1600px;
            margin: 2rem auto;
            padding: 0 5%;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            color: #1e293b;
        }

        /* === FILTRES === */
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-select, .form-input {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
        }

        /* === BOUTONS === */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #dc2626;
            color: white;
        }

        .btn-primary:hover {
            background: #b91c1c;
        }

        /* === TABLEAU === */
        .reservations-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .table-header {
            background: #f8fafc;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        .total-count {
            color: #64748b;
            font-size: 0.875rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        td {
            font-size: 0.875rem;
        }

        /* === BADGES DE STATUT === */
        .statut-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .statut-paye {
            background: #dcfce7;
            color: #166534;
        }

        .statut-en-attente {
            background: #fef3c7;
            color: #92400e;
        }

        .statut-annule {
            background: #fee2e2;
            color: #991b1b;
        }

        /* === PAGINATION === */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-top: 2rem;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            color: #374151;
        }

        .pagination-btn:hover:not(.disabled) {
            background: #f8fafc;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .filters-form {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Header avec navigation -->
    <div class="header">
        <div class="nav">
            <a href="admin_dashboard.php" class="logo">
                <i class="fas fa-shield-alt"></i>
                FootBooking - Administration
            </a>
            <div class="user-menu">
                <a href="admin_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Retour au tableau de bord
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- En-tête de page -->
        <div class="page-header">
            <h1 class="page-title">Toutes les Réservations</h1>
        </div>

        <!-- Formulaire de filtres -->
        <div class="filters">
            <form method="GET" class="filters-form">
                <!-- Filtre par terrain -->
                <div class="form-group">
                    <label class="form-label">Terrain</label>
                    <select name="terrain" class="form-select">
                        <option value="">Tous les terrains</option>
                        <?php foreach ($terrains as $terrain): ?>
                            <option value="<?php echo $terrain['id_terrain']; ?>" 
                                    <?php echo $terrain_filter == $terrain['id_terrain'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($terrain['nom_terrain']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtre par statut de paiement -->
                <div class="form-group">
                    <label class="form-label">Statut de paiement</label>
                    <select name="statut" class="form-select">
                        <option value="">Tous les statuts</option>
                        <option value="paye" <?php echo $statut_filter === 'paye' ? 'selected' : ''; ?>>Payé</option>
                        <option value="en_attente" <?php echo $statut_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                        <option value="annule" <?php echo $statut_filter === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                    </select>
                </div>
                
                <!-- Filtre par date -->
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" class="form-input">
                </div>
                
                <!-- Bouton de filtrage -->
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Filtrer
                    </button>
                </div>
            </form>
        </div>

        <!-- Tableau des réservations -->
        <div class="reservations-table">
            <div class="table-header">
                <h2 class="table-title">Liste des réservations</h2>
                <span class="total-count"><?php echo $total_reservations; ?> réservation(s)</span>
            </div>
            
            <!-- Message si aucune réservation -->
            <?php if (empty($reservations)): ?>
                <div style="padding: 3rem; text-align: center; color: #6b7280;">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p>Aucune réservation trouvée</p>
                </div>
            <?php else: ?>
                <!-- Tableau avec toutes les réservations -->
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Terrain</th>
                            <th>Gérant</th>
                            <th>Date</th>
                            <th>Créneau</th>
                            <th>Prix</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <!-- ID de la réservation -->
                                <td>#<?php echo $reservation['id_reservation']; ?></td>
                                
                                <!-- Informations du client -->
                                <td>
                                    <strong><?php echo htmlspecialchars($reservation['client_prenom'] . ' ' . $reservation['client_nom']); ?></strong><br>
                                    <small style="color: #64748b;"><?php echo htmlspecialchars($reservation['telephone']); ?></small>
                                </td>
                                
                                <!-- Informations du terrain -->
                                <td>
                                    <strong><?php echo htmlspecialchars($reservation['nom_terrain']); ?></strong><br>
                                    <small style="color: #64748b;"><?php echo htmlspecialchars($reservation['ville']); ?></small>
                                </td>
                                
                                <!-- Informations du gérant -->
                                <td>
                                    <?php if ($reservation['gerant_prenom']): ?>
                                        <?php echo htmlspecialchars($reservation['gerant_prenom'] . ' ' . $reservation['gerant_nom']); ?>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Date de la réservation -->
                                <td><?php echo date('d/m/Y', strtotime($reservation['date_reservation'])); ?></td>
                                
                                <!-- Créneau horaire -->
                                <td><?php echo substr($reservation['heure_debut'], 0, 5) . ' - ' . substr($reservation['heure_fin'], 0, 5); ?></td>
                                
                                <!-- Prix total -->
                                <td><strong><?php echo number_format($reservation['prix_total'], 2); ?> DH</strong></td>
                                
                                <!-- Badge de statut de paiement -->
                                <td>
                                    <span class="statut-badge statut-<?php echo $reservation['statut_paiement']; ?>">
                                        <?php 
                                        $statuts = [
                                            'paye' => 'Payé',
                                            'en_attente' => 'En attente',
                                            'annule' => 'Annulé'
                                        ];
                                        echo $statuts[$reservation['statut_paiement']] ?? $reservation['statut_paiement'];
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <!-- Bouton page précédente -->
                <a href="?page=<?php echo $page - 1; ?>&terrain=<?php echo $terrain_filter; ?>&statut=<?php echo $statut_filter; ?>&date=<?php echo $date_filter; ?>" 
                   class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <i class="fas fa-chevron-left"></i>
                    Précédent
                </a>
                
                <!-- Indicateur de page -->
                <span>Page <?php echo $page; ?> sur <?php echo $total_pages; ?></span>
                
                <!-- Bouton page suivante -->
                <a href="?page=<?php echo $page + 1; ?>&terrain=<?php echo $terrain_filter; ?>&statut=<?php echo $statut_filter; ?>&date=<?php echo $date_filter; ?>" 
                   class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    Suivant
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>