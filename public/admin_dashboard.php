<?php
/**
 * Tableau de bord administrateur
 * 
 * Ce fichier affiche le tableau de bord principal pour les administrateurs
 * avec les statistiques globales et les actions rapides disponibles.
 * 
 * @author Jihane Chouhe
 * @version 1.0.0
 * @date 2024-10-30
 * 
 * @changelog
 * Version 1.0.0 (2024-10-30)
 * - Création initiale du tableau de bord admin
 * - Ajout de l'affichage des statistiques globales
 * - Ajout des cartes d'actions rapides
 * - Implémentation du design responsive
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
    $stmt = $pdo->prepare("SELECT nom, prenom, email FROM Utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}

/**
 * Calcul des statistiques du tableau de bord
 * - Nombre total de réservations
 * - Nombre total de terrains
 * - Nombre total de gérants
 * - Chiffre d'affaires total du mois
 */
try {
    // Nombre total de réservations
    $stmt = $pdo->query("SELECT COUNT(*) as total_reservations FROM Reservation");
    $stats['total_reservations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_reservations'];

    // Nombre total de terrains
    $stmt = $pdo->query("SELECT COUNT(*) as total_terrains FROM Terrain");
    $stats['total_terrains'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_terrains'];

    // Nombre total de gérants
    $stmt = $pdo->query("SELECT COUNT(*) as total_gerants FROM Utilisateur WHERE role = 'gerant_terrain'");
    $stats['total_gerants'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_gerants'];

    // Chiffre d'affaires du mois en cours
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(prix_total), 0) as ca_mois 
        FROM Reservation 
        WHERE MONTH(date_reservation) = MONTH(CURDATE()) 
        AND YEAR(date_reservation) = YEAR(CURDATE())
    ");
    $stats['ca_mois'] = $stmt->fetch(PDO::FETCH_ASSOC)['ca_mois'];

} catch (PDOException $e) {
    // Initialisation des statistiques à zéro en cas d'erreur
    $stats = [
        'total_reservations' => 0,
        'total_terrains' => 0,
        'total_gerants' => 0,
        'ca_mois' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Administrateur - FootBooking</title>
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

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: #1e293b;
        }

        .user-role {
            font-size: 0.875rem;
            color: #64748b;
        }

        .logout-btn {
            background: #ef4444;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .logout-btn:hover {
            background: #dc2626;
        }

        /* === CONTAINER === */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 5%;
        }

        /* === SECTION BIENVENUE === */
        .welcome-section {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
        }

        .welcome-title {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            opacity: 0.9;
        }

        /* === GRILLE STATISTIQUES === */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-left: 4px solid #dc2626;
        }

        .stat-icon {
            font-size: 2rem;
            color: #dc2626;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* === GRILLE ACTIONS === */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .action-card:hover {
            border-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .action-icon {
            font-size: 3rem;
            color: #dc2626;
            margin-bottom: 1rem;
        }

        .action-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }

        .action-description {
            color: #64748b;
            line-height: 1.5;
        }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .nav {
                flex-direction: column;
                gap: 1rem;
            }

            .user-info {
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: 1fr;
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
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']); ?></div>
                    <div class="user-role">Administrateur</div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Section de bienvenue personnalisée -->
        <div class="welcome-section">
            <h1 class="welcome-title">Bonjour, <?php echo htmlspecialchars($admin['prenom']); ?> !</h1>
            <p class="welcome-subtitle">Bienvenue dans votre espace d'administration</p>
        </div>

        <!-- Grille des statistiques clés -->
        <div class="stats-grid">
            <!-- Réservations totales -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_reservations']; ?></div>
                <div class="stat-label">Réservations totales</div>
            </div>

            <!-- Terrains totaux -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-futbol"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_terrains']; ?></div>
                <div class="stat-label">Terrains enregistrés</div>
            </div>

            <!-- Gérants totaux -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_gerants']; ?></div>
                <div class="stat-label">Gérants actifs</div>
            </div>

            <!-- Chiffre d'affaires mensuel -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['ca_mois'], 2); ?> DH</div>
                <div class="stat-label">Chiffre d'affaires ce mois</div>
            </div>
        </div>

        <!-- Grille des actions rapides -->
        <div class="actions-grid">
            <!-- Action: Gérer les réservations -->
            <a href="admin_reservations.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-list"></i>
                </div>
                <h3 class="action-title">Toutes les réservations</h3>
                <p class="action-description">Consultez et gérez toutes les réservations de tous les terrains</p>
            </a>

            <!-- Action: Gérer les terrains -->
            <a href="admin_terrains.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h3 class="action-title">Gérer les terrains</h3>
                <p class="action-description">Consultez tous les terrains et ajoutez-en de nouveaux</p>
            </a>

            <!-- Action: Ajouter un terrain -->
            <a href="admin_ajouter_terrain.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h3 class="action-title">Ajouter un terrain</h3>
                <p class="action-description">Créez un nouveau terrain et affectez-le à un gérant</p>
            </a>

            <!-- Action: Gérer les gérants -->
            <a href="admin_gerants.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h3 class="action-title">Gérer les gérants</h3>
                <p class="action-description">Consultez la liste des gérants et créez-en de nouveaux</p>
            </a>
        </div>
    </div>
</body>
</html>