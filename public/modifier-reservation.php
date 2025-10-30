<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$reservation_id = $_GET['id'] ?? null;

if (!$reservation_id) {
    header('Location: mes-reservations.php');
    exit();
}

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

// Fonction pour vérifier la disponibilité d'un terrain
function isTerrainAvailable($pdo, $terrain_id, $date_reservation, $heure_debut, $exclude_reservation_id = null) {
    $sql = "SELECT COUNT(*) as count 
            FROM Reservation 
            WHERE id_terrain = ? 
            AND date_reservation = ? 
            AND heure_debut = ? 
            AND statut = 'Confirmée'";
    
    $params = [$terrain_id, $date_reservation, $heure_debut];
    
    if ($exclude_reservation_id) {
        $sql .= " AND id_reservation != ?";
        $params[] = $exclude_reservation_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    return $result['count'] == 0;
}

// Vérifier que la réservation appartient bien à l'utilisateur et peut être modifiée
$sql = "SELECT r.*, t.nom_terrain, t.prix_heure 
        FROM Reservation r 
        JOIN Terrain t ON r.id_terrain = t.id_terrain 
        WHERE r.id_reservation = ? AND r.id_utilisateur = ? AND r.statut = 'Confirmée'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$reservation_id, $user_id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    header('Location: mes-reservations.php');
    exit();
}

// Vérifier si la modification est encore possible (48h avant)
if (!canModifyReservation($reservation['date_reservation'], $reservation['heure_debut'])) {
    $_SESSION['error_message'] = "La modification n'est plus possible. Elle doit être effectuée au moins 48 heures avant le début du match.";
    header('Location: mes-reservations.php');
    exit();
}

// Récupérer les terrains disponibles
$terrains_sql = "SELECT * FROM Terrain ORDER BY nom_terrain";
$terrains = $pdo->query($terrains_sql)->fetchAll();

// Récupérer les options supplémentaires
$options_sql = "SELECT * FROM OptionSupplementaire ORDER BY nom_option";
$options = $pdo->query($options_sql)->fetchAll();

// Récupérer les options déjà sélectionnées pour cette réservation
$selected_options_sql = "SELECT id_option FROM Reservation_Option WHERE id_reservation = ?";
$selected_options_stmt = $pdo->prepare($selected_options_sql);
$selected_options_stmt->execute([$reservation_id]);
$selected_options = $selected_options_stmt->fetchAll(PDO::FETCH_COLUMN);

// Traitement de la modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_reservation = $_POST['date_reservation'];
    $heure_debut = $_POST['heure_debut'];
    $id_terrain = $_POST['id_terrain'];
    $options_selected = $_POST['options'] ?? [];
    $commentaires = $_POST['commentaires'];
    
    // Vérifier à nouveau que la modification est possible (au cas où l'utilisateur reste longtemps sur la page)
    if (!canModifyReservation($date_reservation, $heure_debut)) {
        $error_message = "La modification n'est plus possible. Elle doit être effectuée au moins 48 heures avant le début du match.";
    }
    // Vérifier la disponibilité du terrain
    elseif (!isTerrainAvailable($pdo, $id_terrain, $date_reservation, $heure_debut, $reservation_id)) {
        $error_message = "Ce terrain n'est pas disponible pour la date et l'heure sélectionnées. Veuillez choisir un autre créneau.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Mettre à jour la réservation
            $update_sql = "UPDATE Reservation 
                          SET date_reservation = ?, heure_debut = ?, id_terrain = ?, 
                              commentaires = ?, statut = 'Modifiée', date_modification = NOW()
                          WHERE id_reservation = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$date_reservation, $heure_debut, $id_terrain, $commentaires, $reservation_id]);
            
            // Supprimer les anciennes options
            $delete_options_sql = "DELETE FROM Reservation_Option WHERE id_reservation = ?";
            $delete_options_stmt = $pdo->prepare($delete_options_sql);
            $delete_options_stmt->execute([$reservation_id]);
            
            // Ajouter les nouvelles options
            if (!empty($options_selected)) {
                $insert_option_sql = "INSERT INTO Reservation_Option (id_reservation, id_option) VALUES (?, ?)";
                $insert_option_stmt = $pdo->prepare($insert_option_sql);
                foreach ($options_selected as $option_id) {
                    $insert_option_stmt->execute([$reservation_id, $option_id]);
                }
            }
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Réservation modifiée avec succès.";
            header('Location: mes-reservations.php');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Une erreur est survenue lors de la modification de la réservation.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Réservation - FootBooking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* [Reprendre tout le CSS précédent] */
        /* ... */

        .availability-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #166534;
        }

        .availability-info i {
            color: #16a34a;
        }

        .unavailable {
            color: #dc2626;
            font-weight: 600;
        }

        .available {
            color: #16a34a;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Modifier la réservation</h1>
            <p class="page-subtitle">Modifiez les détails de votre réservation #<?= $reservation_id ?></p>
        </div>

        <div class="availability-info">
            <i class="fas fa-info-circle"></i>
            <strong>Disponibilité en temps réel :</strong> La modification est soumise à la disponibilité du terrain pour le nouveau créneau sélectionné.
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" id="modificationForm">
                <div class="form-group">
                    <label class="form-label" for="date_reservation">Date de réservation</label>
                    <input type="date" class="form-control" id="date_reservation" name="date_reservation" 
                           value="<?= htmlspecialchars($reservation['date_reservation']) ?>" required
                           min="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="heure_debut">Heure de début</label>
                    <select class="form-control" id="heure_debut" name="heure_debut" required>
                        <?php for ($hour = 16; $hour <= 22; $hour++): 
                            $heure_value = sprintf('%02d:00:00', $hour);
                            $is_available = isTerrainAvailable($pdo, $reservation['id_terrain'], $reservation['date_reservation'], $heure_value, $reservation_id);
                        ?>
                            <option value="<?= $heure_value ?>" 
                                <?= $reservation['heure_debut'] === $heure_value ? 'selected' : '' ?>
                                data-available="<?= $is_available ? 'true' : 'false' ?>">
                                <?= $hour ?>:00 - <?= $hour + 1 ?>:00
                                <?= !$is_available && $reservation['heure_debut'] !== $heure_value ? ' (Indisponible)' : '' ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="id_terrain">Terrain</label>
                    <select class="form-control" id="id_terrain" name="id_terrain" required>
                        <?php foreach ($terrains as $terrain): 
                            $is_available = isTerrainAvailable($pdo, $terrain['id_terrain'], $reservation['date_reservation'], $reservation['heure_debut'], $reservation_id);
                        ?>
                            <option value="<?= $terrain['id_terrain'] ?>" 
                                <?= $reservation['id_terrain'] == $terrain['id_terrain'] ? 'selected' : '' ?>
                                data-available="<?= $is_available ? 'true' : 'false' ?>">
                                <?= htmlspecialchars($terrain['nom_terrain']) ?> - 
                                <?= $terrain['taille'] ?> - 
                                <?= $terrain['type'] ?> - 
                                <?= number_format($terrain['prix_heure'], 2, ',', ' ') ?> €/h
                                <?= !$is_available && $reservation['id_terrain'] != $terrain['id_terrain'] ? ' (Indisponible)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Options supplémentaires</label>
                    <div class="checkbox-group">
                        <?php foreach ($options as $option): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="options[]" value="<?= $option['id_option'] ?>"
                                    <?= in_array($option['id_option'], $selected_options) ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($option['nom_option']) ?> 
                                    (+<?= number_format($option['prix'], 2, ',', ' ') ?> €)
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="commentaires">Commentaires (optionnel)</label>
                    <textarea class="form-control" id="commentaires" name="commentaires" 
                              rows="4" placeholder="Ajoutez des informations supplémentaires..."><?= htmlspecialchars($reservation['commentaires'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <a href="mes-reservations.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        Retour
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        // Vérification en temps réel de la disponibilité
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('date_reservation');
            const heureSelect = document.getElementById('heure_debut');
            const terrainSelect = document.getElementById('id_terrain');
            const form = document.getElementById('modificationForm');
            
            function checkAvailability() {
                // Cette fonction pourrait être étendue avec AJAX pour une vérification en temps réel
                const selectedTerrain = terrainSelect.options[terrainSelect.selectedIndex];
                const selectedHeure = heureSelect.options[heureSelect.selectedIndex];
                
                // Vérifier si le terrain sélectionné est disponible
                if (selectedTerrain.dataset.available === 'false' && 
                    selectedTerrain.value != '<?= $reservation['id_terrain'] ?>') {
                    alert('Attention : Le terrain sélectionné n\'est pas disponible pour ce créneau. Veuillez choisir un autre terrain ou un autre créneau.');
                    return false;
                }
                
                // Vérifier si l'horaire sélectionné est disponible
                if (selectedHeure.dataset.available === 'false' && 
                    selectedHeure.value != '<?= $reservation['heure_debut'] ?>') {
                    alert('Attention : Ce créneau horaire n\'est pas disponible. Veuillez choisir un autre horaire.');
                    return false;
                }
                
                return true;
            }
            
            form.addEventListener('submit', function(e) {
                if (!checkAvailability()) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>