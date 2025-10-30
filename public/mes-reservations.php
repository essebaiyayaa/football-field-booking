<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les réservations de l'utilisateur
$sql = "SELECT r.*, t.nom_terrain, t.adresse, t.ville, t.taille, t.type, t.prix_heure,
               GROUP_CONCAT(DISTINCT o.nom_option SEPARATOR ', ') as options,
               GROUP_CONCAT(DISTINCT o.prix SEPARATOR ', ') as prix_options,
               f.montant_total
        FROM Reservation r
        JOIN Terrain t ON r.id_terrain = t.id_terrain
        LEFT JOIN Reservation_Option ro ON r.id_reservation = ro.id_reservation
        LEFT JOIN OptionSupplementaire o ON ro.id_option = o.id_option
        LEFT JOIN Facture f ON r.id_reservation = f.id_reservation
        WHERE r.id_utilisateur = ?
        GROUP BY r.id_reservation
        ORDER BY r.date_reservation DESC, r.heure_debut DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$reservations = $stmt->fetchAll();

// Fonction pour vérifier si la modification est possible (48h avant)
function canModifyReservation($date_reservation, $heure_debut) {
    $reservation_datetime = new DateTime($date_reservation . ' ' . $heure_debut);
    $current_datetime = new DateTime();
    $interval = $current_datetime->diff($reservation_datetime);
    
    // Calculer le nombre total d'heures jusqu'à la réservation
    $total_hours = ($interval->days * 24) + $interval->h;
    
    // Modification possible si plus de 48 heures restantes
    return $total_hours > 48;
}

// Traitement de l'annulation
if (isset($_POST['annuler_reservation'])) {
    $reservation_id = $_POST['reservation_id'];
    
    // Vérifier que la réservation appartient bien à l'utilisateur
    $check_sql = "SELECT id_reservation, date_reservation, heure_debut FROM Reservation WHERE id_reservation = ? AND id_utilisateur = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$reservation_id, $user_id]);
    $reservation_data = $check_stmt->fetch();
    
    if ($reservation_data) {
        // Vérifier si l'annulation est possible (48h avant)
        if (canModifyReservation($reservation_data['date_reservation'], $reservation_data['heure_debut'])) {
            // Annuler la réservation
            $update_sql = "UPDATE Reservation SET statut = 'Annulée', date_modification = NOW() WHERE id_reservation = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$reservation_id]);
            
            $_SESSION['success_message'] = "Réservation annulée avec succès.";
        } else {
            $_SESSION['error_message'] = "Impossible d'annuler la réservation. L'annulation doit être effectuée au moins 48 heures avant le début du match.";
        }
        header('Location: mes-reservations.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Réservations - FootBooking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* [Conserver tout le CSS précédent] */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f9fafb;
        }
* {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f9fafb;
        }

        /* Header & Navigation */
        header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        nav {
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

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #555;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #16a34a;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-outline {
            background: white;
            color: #16a34a;
            border: 2px solid #16a34a;
        }

        .btn-outline:hover {
            background: #f0fdf4;
        }

        .btn-primary {
            background: #16a34a;
            color: white;
        }

        .btn-primary:hover {
            background: #15803d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        /* Main Content */
        .main-content {
            min-height: calc(100vh - 200px);
            padding: 3rem 5%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 2.5rem;
            color: #1f2937;
            margin-bottom: 1rem;
        }

        .page-subtitle {
            color: #6b7280;
            font-size: 1.1rem;
        }

        /* Reservations List */
        .reservations-container {
            display: grid;
            gap: 1.5rem;
        }

        .reservation-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-left: 4px solid #16a34a;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .reservation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .reservation-card.cancelled {
            border-left-color: #dc2626;
            opacity: 0.7;
        }

        .reservation-card.modified {
            border-left-color: #f59e0b;
        }

        .reservation-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .reservation-info {
            flex: 1;
        }

        .reservation-title {
            font-size: 1.4rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .reservation-datetime {
            font-size: 1.1rem;
            color: #16a34a;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .reservation-terrain {
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .reservation-details {
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .reservation-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-confirmed {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-cancelled {
            background: #fef2f2;
            color: #dc2626;
        }

        .status-modified {
            background: #fefce8;
            color: #d97706;
        }

        .reservation-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .reservation-options {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .options-title {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .options-list {
            color: #6b7280;
        }

        .reservation-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1f2937;
            margin-top: 1rem;
        }

        .no-reservations {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }

        .no-reservations i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: #d1d5db;
        }

        .no-reservations h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #374151;
        }

        /* Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #f0fdf4;
            border-color: #16a34a;
            color: #166534;
        }

        .alert-error {
            background: #fef2f2;
            border-color: #dc2626;
            color: #991b1b;
        }

        /* Footer */
        footer {
            background: #1f2937;
            color: white;
            padding: 3rem 5%;
            text-align: center;
        }

        footer p {
            opacity: 0.8;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .page-title {
                font-size: 2rem;
            }

            .reservation-header {
                flex-direction: column;
            }

            .reservation-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .btn {
                flex: 1;
                justify-content: center;
                min-width: 120px;
            }
        }

        .btn-disabled {
            background: #9ca3af;
            color: white;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-disabled:hover {
            background: #9ca3af;
            transform: none;
            box-shadow: none;
        }

        .modification-info {
            background: #fefce8;
            border: 1px solid #fef08a;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #854d0e;
        }

        .modification-info i {
            color: #ca8a04;
        }

    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Mes Réservations</h1>
            <p class="page-subtitle">Consultez et gérez l'ensemble de vos réservations de terrains de football</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $_SESSION['success_message']; ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $_SESSION['error_message']; ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="modification-info">
            <i class="fas fa-info-circle"></i>
            <strong>Information importante :</strong> La modification et l'annulation des réservations sont possibles jusqu'à 48 heures avant le début du match, sous réserve de disponibilité du terrain.
        </div>

        <?php if (empty($reservations)): ?>
            <div class="no-reservations">
                <i class="fas fa-calendar-times"></i>
                <h3>Aucune réservation trouvée</h3>
                <p>Vous n'avez pas encore effectué de réservation.</p>
                <a href="reservation.php" class="btn btn-primary" style="margin-top: 1.5rem;">
                    <i class="fas fa-plus"></i>
                    Faire une réservation
                </a>
            </div>
        <?php else: ?>
            <div class="reservations-container">
                <?php foreach ($reservations as $reservation): 
                    $can_modify = canModifyReservation($reservation['date_reservation'], $reservation['heure_debut']);
                    $is_confirmed = strtolower($reservation['statut']) === 'confirmée';
                ?>
                    <div class="reservation-card <?= strtolower($reservation['statut']) === 'annulée' ? 'cancelled' : '' ?> <?= strtolower($reservation['statut']) === 'modifiée' ? 'modified' : '' ?>">
                        <div class="reservation-header">
                            <div class="reservation-info">
                                <h3 class="reservation-title">Réservation #<?= $reservation['id_reservation'] ?></h3>
                                <div class="reservation-datetime">
                                    <i class="fas fa-calendar-day"></i>
                                    <?= date('d/m/Y', strtotime($reservation['date_reservation'])) ?> 
                                    • 
                                    <i class="fas fa-clock"></i>
                                    <?= date('H:i', strtotime($reservation['heure_debut'])) ?> - <?= date('H:i', strtotime($reservation['heure_fin'])) ?>
                                </div>
                                <div class="reservation-terrain">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($reservation['nom_terrain']) ?> - <?= htmlspecialchars($reservation['ville']) ?>
                                </div>
                                <div class="reservation-details">
                                    <i class="fas fa-futbol"></i>
                                    <?= $reservation['taille'] ?> • <?= $reservation['type'] ?>
                                </div>
                                <?php if (!$can_modify && $is_confirmed): ?>
                                    <div class="modification-info" style="margin-top: 1rem; margin-bottom: 0; padding: 0.75rem;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Modification impossible - Moins de 48h avant le match
                                    </div>
                                <?php endif; ?>
                                <?php if ($reservation['options']): ?>
                                    <div class="reservation-options">
                                        <div class="options-title">
                                            <i class="fas fa-plus-circle"></i>
                                            Options supplémentaires :
                                        </div>
                                        <div class="options-list"><?= htmlspecialchars($reservation['options']) ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($reservation['montant_total']): ?>
                                    <div class="reservation-price">
                                        <i class="fas fa-receipt"></i>
                                        Total : <?= number_format($reservation['montant_total'], 2, ',', ' ') ?> €
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="reservation-status-container">
                                <span class="reservation-status status-<?= strtolower($reservation['statut']) ?>">
                                    <?= $reservation['statut'] ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($is_confirmed): ?>
                            <div class="reservation-actions">
                                <?php if ($can_modify): ?>
                                    <a href="modifier-reservation.php?id=<?= $reservation['id_reservation'] ?>" class="btn btn-warning">
                                        <i class="fas fa-edit"></i>
                                        Modifier
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-warning btn-disabled" disabled title="Modification impossible - Moins de 48h avant le match">
                                        <i class="fas fa-edit"></i>
                                        Modifier
                                    </button>
                                <?php endif; ?>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler cette réservation ?')">
                                    <input type="hidden" name="reservation_id" value="<?= $reservation['id_reservation'] ?>">
                                    <button type="submit" name="annuler_reservation" class="btn btn-danger" <?= !$can_modify ? 'disabled' : '' ?>>
                                        <i class="fas fa-times"></i>
                                        <?= $can_modify ? 'Annuler' : 'Annulation impossible' ?>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>