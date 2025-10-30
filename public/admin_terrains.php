<?php
/**
 * Interface de gestion des terrains pour les administrateurs
 * 
 * Ce fichier permet aux administrateurs de visualiser tous les terrains
 * avec leurs gérants assignés et de gérer les affectations.
 * 
 * @author Jihane Chouhe
 * @version 1.0.0
 * @date 2024-10-30
 * 
 * @changelog
 * Version 1.0.0 (2024-10-30)
 * - Création de l'interface de gestion des terrains admin
 * - Ajout de l'affichage de tous les terrains
 * - Affichage des gérants assignés
 * - Filtrage par ville et type
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
$limit = 12;
$offset = ($page - 1) * $limit;

/**
 * Récupération des filtres de recherche
 */
$ville_filter = $_GET['ville'] ?? '';
$type_filter = $_GET['type'] ?? '';

/**
 * Récupération de toutes les villes pour le filtre
 */
try {
    $stmt = $pdo->query("SELECT DISTINCT ville FROM Terrain ORDER BY ville");
    $villes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $villes = [];
}

/**
 * Récupération des terrains avec filtres et pagination
 */
try {
    // Construction de la requête SQL avec filtres dynamiques
    $sql = "
        SELECT 
            t.*,
            u.prenom as gerant_prenom,
            u.nom as gerant_nom,
            u.email as gerant_email,
            (SELECT COUNT(*) FROM Reservation r WHERE r.id_terrain = t.id_terrain) as nb_reservations
        FROM Terrain t
        LEFT JOIN Utilisateur u ON t.id_utilisateur = u.id_utilisateur
        WHERE 1=1
    ";
    
    $params = [];
    
    // Ajout du filtre de ville si spécifié
    if (!empty($ville_filter)) {
        $sql .= " AND t.ville = ?";
        $params[] = $ville_filter;
    }
    
    // Ajout du filtre de type si spécifié
    if (!empty($type_filter)) {
        $sql .= " AND t.type = ?";
        $params[] = $type_filter;
    }
    
    // Tri et limitation pour la pagination
    $sql .= " ORDER BY t.nom_terrain ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $terrains = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    /**
     * Comptage du nombre total de terrains pour la pagination
     */
    $count_sql = "SELECT COUNT(*) FROM Terrain WHERE 1=1";
    $count_params = [];
    
    if (!empty($ville_filter)) {
        $count_sql .= " AND ville = ?";
        $count_params[] = $ville_filter;
    }
    
    if (!empty($type_filter)) {
        $count_sql .= " AND type = ?";
        $count_params[] = $type_filter;
    }
    
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($count_params);
    $total_terrains = $stmt->fetchColumn();
    $total_pages = ceil($total_terrains / $limit);
    
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Terrains - Admin FootBooking</title>
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
            max-width: 1400px;
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

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #dc2626;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-add:hover {
            background: #b91c1c;
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

        .form-select {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
        }

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

        /* === GRILLE TERRAINS === */
        .terrains-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .terrain-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: all 0.3s;
        }

        .terrain-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .terrain-header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 1.5rem;
        }

        .terrain-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .terrain-location {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .terrain-body {
            padding: 1.5rem;
        }

        .terrain-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .info-icon {
            color: #dc2626;
        }

        .terrain-stats {
            display: flex;
            justify-content: space-between;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
        }

        .gerant-info {
            padding: 1rem;
            background: #f0fdf4;
            border-left: 3px solid #16a34a;
            border-radius: 4px;
        }

        .gerant-info.no-gerant {
            background: #fef3c7;
            border-left-color: #f59e0b;
        }

        .gerant-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }

        .gerant-name {
            font-weight: 600;
            color: #1e293b;
        }

        .gerant-email {
            font-size: 0.875rem;
            color: #64748b;
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
            
            .terrains-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
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
            <h1 class="page-title">Gestion des Terrains</h1>
            <a href="admin_ajouter_terrain.php" class="btn-add">
                <i class="fas fa-plus"></i>
                Ajouter un terrain
            </a>
        </div>

        <!-- Formulaire de filtres -->
        <div class="filters">
            <form method="GET" class="filters-form">
                <!-- Filtre par ville -->
                <div class="form-group">
                    <label class="form-label">Ville</label>
                    <select name="ville" class="form-select">
                        <option value="">Toutes les villes</option>
                        <?php foreach ($villes as $ville): ?>
                            <option value="<?php echo htmlspecialchars($ville); ?>" 
                                    <?php echo $ville_filter === $ville ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ville); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtre par type -->
                <div class="form-group">
                    <label class="form-label">Type de terrain</label>
                    <select name="type" class="form-select">
                        <option value="">Tous les types</option>
                        <option value="Gazon naturel" <?php echo $type_filter === 'Gazon naturel' ? 'selected' : ''; ?>>Gazon naturel</option>
                        <option value="Gazon artificiel" <?php echo $type_filter === 'Gazon artificiel' ? 'selected' : ''; ?>>Gazon artificiel</option>
                        <option value="Terrain dur" <?php echo $type_filter === 'Terrain dur' ? 'selected' : ''; ?>>Terrain dur</option>
                    </select>
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

        <!-- Grille des terrains -->
        <?php if (empty($terrains)): ?>
            <div style="padding: 3rem; text-align: center; background: white; border-radius: 12px; color: #6b7280;">
                <i class="fas fa-futbol" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <p>Aucun terrain trouvé</p>
            </div>
        <?php else: ?>
            <div class="terrains-grid">
                <?php foreach ($terrains as $terrain): ?>
                    <div class="terrain-card">
                        <!-- En-tête du terrain -->
                        <div class="terrain-header">
                            <div class="terrain-name"><?php echo htmlspecialchars($terrain['nom_terrain']); ?></div>
                            <div class="terrain-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($terrain['ville']); ?>
                            </div>
                        </div>

                        <!-- Corps du terrain -->
                        <div class="terrain-body">
                            <!-- Informations du terrain -->
                            <div class="terrain-info">
                                <div class="info-item">
                                    <i class="fas fa-ruler-combined info-icon"></i>
                                    <span><?php echo htmlspecialchars($terrain['taille']); ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-leaf info-icon"></i>
                                    <span><?php echo htmlspecialchars($terrain['type']); ?></span>
                                </div>
                            </div>

                            <!-- Statistiques -->
                            <div class="terrain-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($terrain['prix_heure'], 0); ?> DH</div>
                                    <div class="stat-label">Prix/heure</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $terrain['nb_reservations']; ?></div>
                                    <div class="stat-label">Réservations</div>
                                </div>
                            </div>

                            <!-- Informations du gérant -->
                            <div class="gerant-info <?php echo !$terrain['gerant_prenom'] ? 'no-gerant' : ''; ?>">
                                <div class="gerant-label">Gérant assigné</div>
                                <?php if ($terrain['gerant_prenom']): ?>
                                    <div class="gerant-name">
                                        <i class="fas fa-user-tie"></i>
                                        <?php echo htmlspecialchars($terrain['gerant_prenom'] . ' ' . $terrain['gerant_nom']); ?>
                                    </div>
                                    <div class="gerant-email">
                                        <i class="fas fa-envelope"></i>
                                        <?php echo htmlspecialchars($terrain['gerant_email']); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="gerant-name">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Aucun gérant assigné
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <!-- Bouton page précédente -->
                <a href="?page=<?php echo $page - 1; ?>&ville=<?php echo $ville_filter; ?>&type=<?php echo $type_filter; ?>" 
                   class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <i class="fas fa-chevron-left"></i>
                    Précédent
                </a>
                
                <!-- Indicateur de page -->
                <span>Page <?php echo $page; ?> sur <?php echo $total_pages; ?></span>
                
                <!-- Bouton page suivante -->
                <a href="?page=<?php echo $page + 1; ?>&ville=<?php echo $ville_filter; ?>&type=<?php echo $type_filter; ?>" 
                   class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    Suivant
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>