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
    $stmt = $pdo->prepare("SELECT nom, prenom FROM Utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $gerant = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}

// Traitement du changement de statut de paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changer_statut_paiement'])) {
    $reservation_id = $_POST['reservation_id'];
    $nouveau_statut = $_POST['statut_paiement'];
    
    try {
        $stmt = $pdo->prepare("UPDATE Reservation SET statut_paiement = ? WHERE id_reservation = ?");
        $stmt->execute([$nouveau_statut, $reservation_id]);
        
        $success = "Statut de paiement mis à jour avec succès";
    } catch (PDOException $e) {
        $error = "Erreur lors de la mise à jour: " . $e->getMessage();
    }
}

// Récupérer les réservations avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres
$statut_filter = $_GET['statut'] ?? '';
$date_filter = $_GET['date'] ?? '';

try {
    $sql = "
        SELECT r.*, t.nom_terrain, t.adresse, u.prenom, u.nom, u.telephone
        FROM Reservation r
        JOIN Terrain t ON r.id_terrain = t.id_terrain
        JOIN Utilisateur u ON r.id_utilisateur = u.id_utilisateur
        WHERE t.id_utilisateur = ?
    ";
    
    $params = [$_SESSION['user_id']];
    
    if (!empty($statut_filter)) {
        $sql .= " AND r.statut_paiement = ?";
        $params[] = $statut_filter;
    }
    
    if (!empty($date_filter)) {
        $sql .= " AND r.date_reservation = ?";
        $params[] = $date_filter;
    }
    
    $sql .= " ORDER BY r.date_reservation DESC, r.heure_debut DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Compter le total pour la pagination
    $count_sql = "
        SELECT COUNT(*) 
        FROM Reservation r
        JOIN Terrain t ON r.id_terrain = t.id_terrain
        WHERE t.id_utilisateur = ?
    ";
    $count_params = [$_SESSION['user_id']];
    
    if (!empty($statut_filter)) {
        $count_sql .= " AND r.statut_paiement = ?";
        $count_params[] = $statut_filter;
    }
    
    if (!empty($date_filter)) {
        $count_sql .= " AND r.date_reservation = ?";
        $count_params[] = $date_filter;
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
    <title>Gestion des Réservations - FootBooking</title>
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
            color: #16a34a;
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 5%;
        }

        .page-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            color: #1e293b;
        }

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

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #16a34a;
            color: white;
        }

        .btn-primary:hover {
            background: #15803d;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

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
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
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
        }

        .statut-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
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

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

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
    <div class="header">
        <div class="nav">
            <a href="gerant_dashboard.php" class="logo">
                <i class="fas fa-futbol"></i>
                FootBooking - Espace Gérant
            </a>
            <div class="user-menu">
                <a href="gerant_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Retour au tableau de bord
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Gestion des Réservations</h1>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="filters">
            <form method="GET" class="filters-form">
                <div class="form-group">
                    <label class="form-label">Statut de paiement</label>
                    <select name="statut" class="form-select">
                        <option value="">Tous les statuts</option>
                        <option value="paye" <?php echo $statut_filter === 'paye' ? 'selected' : ''; ?>>Payé</option>
                        <option value="en_attente" <?php echo $statut_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                        <option value="annule" <?php echo $statut_filter === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" class="form-input">
                </div>
                
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
            </div>
            
            <?php if (empty($reservations)): ?>
                <div style="padding: 3rem; text-align: center; color: #6b7280;">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p>Aucune réservation trouvée</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Terrain</th>
                            <th>Date</th>
                            <th>Créneau</th>
                            <th>Prix</th>
                            <th>Statut Paiement</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td>#<?php echo $reservation['id_reservation']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($reservation['prenom'] . ' ' . $reservation['nom']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($reservation['telephone']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($reservation['nom_terrain']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($reservation['adresse']); ?></small>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($reservation['date_reservation'])); ?></td>
                                <td><?php echo substr($reservation['heure_debut'], 0, 5) . ' - ' . substr($reservation['heure_fin'], 0, 5); ?></td>
                                <td><?php echo number_format($reservation['prix_total'], 2); ?> DH</td>
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
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="reservation_id" value="<?php echo $reservation['id_reservation']; ?>">
                                        <select name="statut_paiement" onchange="this.form.submit()" class="form-select btn-sm">
                                            <option value="en_attente" <?php echo $reservation['statut_paiement'] === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                            <option value="paye" <?php echo $reservation['statut_paiement'] === 'paye' ? 'selected' : ''; ?>>Payé</option>
                                            <option value="annule" <?php echo $reservation['statut_paiement'] === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                                        </select>
                                        <input type="hidden" name="changer_statut_paiement" value="1">
                                    </form>
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
                <a href="?page=<?php echo $page - 1; ?>&statut=<?php echo $statut_filter; ?>&date=<?php echo $date_filter; ?>" 
                   class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <i class="fas fa-chevron-left"></i>
                    Précédent
                </a>
                
                <span>Page <?php echo $page; ?> sur <?php echo $total_pages; ?></span>
                
                <a href="?page=<?php echo $page + 1; ?>&statut=<?php echo $statut_filter; ?>&date=<?php echo $date_filter; ?>" 
                   class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    Suivant
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>