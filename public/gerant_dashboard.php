<?php
session_start();
require_once '../config/database.php';

// Vérification que l'utilisateur est un gérant
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gerant_terrain') {
    header('Location: login.php');
    exit;
}

// Récupérer les informations du gérant
try {
    $stmt = $pdo->prepare("SELECT nom, prenom, email FROM Utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $gerant = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}

// Récupérer les statistiques
try {
    // Nombre total de réservations
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_reservations 
        FROM Reservation r 
        JOIN Terrain t ON r.id_terrain = t.id_terrain 
        WHERE t.id_utilisateur = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['total_reservations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_reservations'];

    // Réservations du jour
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as reservations_aujourdhui 
        FROM Reservation r 
        JOIN Terrain t ON r.id_terrain = t.id_terrain 
        WHERE t.id_utilisateur = ? AND r.date_reservation = CURDATE()
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['reservations_aujourdhui'] = $stmt->fetch(PDO::FETCH_ASSOC)['reservations_aujourdhui'];

    // Chiffre d'affaires du mois
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(t.prix_heure), 0) as ca_mois 
        FROM Reservation r 
        JOIN Terrain t ON r.id_terrain = t.id_terrain 
        WHERE t.id_utilisateur = ? 
        AND MONTH(r.date_reservation) = MONTH(CURDATE()) 
        AND YEAR(r.date_reservation) = YEAR(CURDATE())
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['ca_mois'] = $stmt->fetch(PDO::FETCH_ASSOC)['ca_mois'];

} catch (PDOException $e) {
    $stats = [
        'total_reservations' => 0,
        'reservations_aujourdhui' => 0,
        'ca_mois' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Gérant - FootBooking</title>
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
            color: #16a34a;
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

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 5%;
        }

        .welcome-section {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
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
            border-left: 4px solid #16a34a;
        }

        .stat-icon {
            font-size: 2rem;
            color: #16a34a;
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
            border-color: #16a34a;
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .action-icon {
            font-size: 3rem;
            color: #16a34a;
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
    <div class="header">
        <div class="nav">
            <a href="gerant_dashboard.php" class="logo">
                <i class="fas fa-futbol"></i>
                FootBooking - Espace Gérant
            </a>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($gerant['prenom'] . ' ' . $gerant['nom']); ?></div>
                    <div class="user-role">Gérant</div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="welcome-section">
            <h1 class="welcome-title">Bonjour, <?php echo htmlspecialchars($gerant['prenom']); ?> !</h1>
            <p class="welcome-subtitle">Bienvenue dans votre espace gérant</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_reservations']; ?></div>
                <div class="stat-label">Réservations totales</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-value"><?php echo $stats['reservations_aujourdhui']; ?></div>
                <div class="stat-label">Réservations aujourd'hui</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['ca_mois'], 2); ?> DH</div>
                <div class="stat-label">Chiffre d'affaires ce mois</div>
            </div>
        </div>

        <div class="actions-grid">
            <a href="gerant_reservations.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-list"></i>
                </div>
                <h3 class="action-title">Gérer les réservations</h3>
                <p class="action-description">Consultez et gérez toutes les réservations de vos terrains</p>
            </a>

            <a href="gerant_terrains.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-futbol"></i>
                </div>
                <h3 class="action-title">Mes terrains</h3>
                <p class="action-description">Consultez et gérez vos terrains</p>
            </a>

            <a href="gerant_rapports.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3 class="action-title">Rapports et statistiques</h3>
                <p class="action-description">Analyser les performances de vos terrains</p>
            </a>
        </div>
    </div>
</body>
</html>