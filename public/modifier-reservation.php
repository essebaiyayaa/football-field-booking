<?php
session_start();
require_once '../config/database.php';
require_once '../config/config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Vérifier si l'ID de réservation est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "ID de réservation invalide.";
    $_SESSION['message_type'] = "error";
    header('Location: mes-reservations.php');
    exit();
}

$id_reservation = (int)$_GET['id'];
$errors = [];

try {
    // Récupérer la réservation avec les détails du terrain
    $stmt = $pdo->prepare("
        SELECT r.*, t.nom_terrain, t.ville, t.taille, t.type, t.prix_heure, t.id_terrain
        FROM Reservation r
        JOIN Terrain t ON r.id_terrain = t.id_terrain
        WHERE r.id_reservation = ? AND r.id_utilisateur = ?
    ");
    $stmt->execute([$id_reservation, $user_id]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reservation) {
        $_SESSION['message'] = "Réservation introuvable ou vous n'avez pas les droits pour la modifier.";
        $_SESSION['message_type'] = "error";
        header('Location: mes-reservations.php');
        exit();
    }
    
    // Vérifier si la réservation est annulée
    if ($reservation['statut'] === 'Annulée') {
        $_SESSION['message'] = "Impossible de modifier une réservation annulée.";
        $_SESSION['message_type'] = "error";
        header('Location: mes-reservations.php');
        exit();
    }
    
    // Vérifier le délai de 48 heures
    $reservation_datetime = new DateTime($reservation['date_reservation'] . ' ' . $reservation['heure_debut']);
    $now = new DateTime();
    $diff = $now->diff($reservation_datetime);
    $hours_until = ($diff->days * 24) + $diff->h;
    
    if ($reservation_datetime < $now || $hours_until < 48) {
        $_SESSION['message'] = "La modification n'est possible que jusqu'à 48 heures avant le début du match.";
        $_SESSION['message_type'] = "error";
        header('Location: mes-reservations.php');
        exit();
    }
    
    // Récupérer les options actuelles de la réservation
    $stmt = $pdo->prepare("
        SELECT id_option 
        FROM Reservation_Option 
        WHERE id_reservation = ?
    ");
    $stmt->execute([$id_reservation]);
    $current_options = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $_SESSION['message'] = "Erreur lors de la récupération : " . $e->getMessage();
    $_SESSION['message_type'] = "error";
    header('Location: mes-reservations.php');
    exit();
}

// Récupérer tous les terrains pour le formulaire
try {
    $stmt = $pdo->query("SELECT * FROM Terrain ORDER BY ville, nom_terrain");
    $terrains = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $terrains = [];
}

// Récupérer toutes les options supplémentaires
try {
    $stmt = $pdo->query("SELECT * FROM OptionSupplementaire ORDER BY nom_option");
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $options = [];
}

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_reservation'])) {
    $date_reservation = $_POST['date_reservation'] ?? '';
    $heure_debut = $_POST['heure_debut'] ?? '';
    $heure_fin = $_POST['heure_fin'] ?? '';
    $id_terrain = (int)($_POST['id_terrain'] ?? 0);
    $commentaires = trim($_POST['commentaires'] ?? '');
    $selected_options = $_POST['options'] ?? [];
    
    // Validation
    if (empty($date_reservation)) {
        $errors[] = "La date de réservation est obligatoire.";
    }
    
    if (empty($heure_debut) || empty($heure_fin)) {
        $errors[] = "L'horaire est obligatoire.";
    }
    
    if ($id_terrain <= 0) {
        $errors[] = "Veuillez sélectionner un terrain.";
    }
    
    // Vérifier que la nouvelle date respecte le délai de 48h
    if (!empty($date_reservation) && !empty($heure_debut)) {
        $new_datetime = new DateTime($date_reservation . ' ' . $heure_debut);
        $now = new DateTime();
        $diff = $now->diff($new_datetime);
        $hours_until = ($diff->days * 24) + $diff->h;
        
        if ($new_datetime < $now) {
            $errors[] = "Impossible de réserver dans le passé.";
        } elseif ($hours_until < 48) {
            $errors[] = "La réservation doit être effectuée au moins 48 heures à l'avance.";
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Vérifier la disponibilité du terrain (sauf pour la réservation en cours)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM Reservation 
                WHERE id_terrain = ? 
                AND date_reservation = ? 
                AND statut IN ('Confirmée', 'Modifiée')
                AND id_reservation != ?
                AND (
                    (heure_debut < ? AND heure_fin > ?) OR
                    (heure_debut < ? AND heure_fin > ?) OR
                    (heure_debut >= ? AND heure_fin <= ?)
                )
            ");
            $stmt->execute([
                $id_terrain, 
                $date_reservation, 
                $id_reservation,
                $heure_fin, $heure_debut,
                $heure_fin, $heure_debut,
                $heure_debut, $heure_fin
            ]);
            $conflict = $stmt->fetchColumn();
            
            if ($conflict > 0) {
                $errors[] = "Le terrain n'est pas disponible pour ce créneau horaire.";
                $pdo->rollBack();
            } else {
                // Mettre à jour la réservation
                $stmt = $pdo->prepare("
                    UPDATE Reservation 
                    SET date_reservation = ?,
                        heure_debut = ?,
                        heure_fin = ?,
                        id_terrain = ?,
                        commentaires = ?,
                        statut = CASE 
                            WHEN statut = 'Confirmée' THEN 'Modifiée'
                            ELSE statut
                        END,
                        date_modification = NOW()
                    WHERE id_reservation = ? AND id_utilisateur = ?
                ");
                $stmt->execute([
                    $date_reservation,
                    $heure_debut,
                    $heure_fin,
                    $id_terrain,
                    $commentaires,
                    $id_reservation,
                    $user_id
                ]);
                
                // Supprimer les anciennes options
                $stmt = $pdo->prepare("DELETE FROM Reservation_Option WHERE id_reservation = ?");
                $stmt->execute([$id_reservation]);
                
                // Ajouter les nouvelles options
                if (!empty($selected_options)) {
                    $stmt = $pdo->prepare("INSERT INTO Reservation_Option (id_reservation, id_option) VALUES (?, ?)");
                    foreach ($selected_options as $option_id) {
                        $stmt->execute([$id_reservation, $option_id]);
                    }
                }
                
                $pdo->commit();
                
                $_SESSION['message'] = "Réservation modifiée avec succès.";
                $_SESSION['message_type'] = "success";
                header('Location: mes-reservations.php');
                exit();
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier la Réservation - FootBooking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
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

        .logo svg {
            width: 32px;
            height: 32px;
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

        .btn-outline {
            background: white;
            color: #16a34a;
            border: 2px solid #16a34a;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-outline:hover {
            background: #f0fdf4;
        }

        .btn-primary {
            background: #16a34a;
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary:hover {
            background: #15803d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        .page-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 3rem 5%;
            text-align: center;
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .container {
            max-width: 900px;
            margin: 3rem auto;
            padding: 0 5%;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2.5rem;
        }

        .info-box {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .info-box p {
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .errors {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .errors ul {
            color: #991b1b;
            list-style: none;
        }

        .errors li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h3 {
            font-size: 1.3rem;
            color: #1f2937;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .option-item:hover {
            background: #f3f4f6;
        }

        .option-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .option-item label {
            cursor: pointer;
            margin: 0;
            flex: 1;
        }

        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }

        .btn {
            flex: 1;
            padding: 1rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 1rem;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #1f2937;
            flex: 1;
            padding: 1rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 1rem;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .actions .btn-primary {
            flex: 1;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        footer {
            background: #1f2937;
            color: white;
            padding: 3rem 5%;
            text-align: center;
            margin-top: 4rem;
        }

        footer p {
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .form-card {
                padding: 1.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <section class="page-header">
        <h1><i class="fas fa-edit"></i> Modifier la Réservation</h1>
        <p>Mettez à jour les détails de votre réservation</p>
    </section>

    <div class="container">
        <div class="form-card">
            <div class="info-box">
                <p>
                    <i class="fas fa-info-circle"></i>
                    <strong>Rappel :</strong> La modification doit être effectuée au moins 48 heures avant le début du match, sous réserve de disponibilité du terrain.
                </p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li>
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-section">
                    <h3><i class="fas fa-futbol"></i> Terrain</h3>
                    <div class="form-group">
                        <label for="id_terrain">
                            <i class="fas fa-map-marker-alt"></i> Sélectionner un terrain
                        </label>
                        <select name="id_terrain" id="id_terrain" required>
                            <option value="">-- Choisir un terrain --</option>
                            <?php foreach ($terrains as $terrain): ?>
                                <option value="<?php echo $terrain['id_terrain']; ?>" 
                                        <?php echo $terrain['id_terrain'] == $reservation['id_terrain'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($terrain['nom_terrain'] . ' - ' . $terrain['ville'] . ' (' . $terrain['taille'] . ', ' . $terrain['type'] . ') - ' . $terrain['prix_heure'] . '€/h'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-calendar-alt"></i> Date et Horaire</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="date_reservation">
                                <i class="fas fa-calendar"></i> Date de réservation
                            </label>
                            <input type="date" 
                                   name="date_reservation" 
                                   id="date_reservation" 
                                   value="<?php echo htmlspecialchars($reservation['date_reservation']); ?>"
                                   min="<?php echo date('Y-m-d', strtotime('+2 days')); ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="heure_debut">
                                <i class="fas fa-clock"></i> Heure de début
                            </label>
                            <input type="time" 
                                   name="heure_debut" 
                                   id="heure_debut"
                                   value="<?php echo substr($reservation['heure_debut'], 0, 5); ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="heure_fin">
                                <i class="fas fa-clock"></i> Heure de fin
                            </label>
                            <input type="time" 
                                   name="heure_fin" 
                                   id="heure_fin"
                                   value="<?php echo substr($reservation['heure_fin'], 0, 5); ?>"
                                   required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-plus-circle"></i> Options Supplémentaires</h3>
                    <div class="options-grid">
                        <?php foreach ($options as $option): ?>
                            <div class="option-item">
                                <input type="checkbox" 
                                       name="options[]" 
                                       id="option_<?php echo $option['id_option']; ?>"
                                       value="<?php echo $option['id_option']; ?>"
                                       <?php echo in_array($option['id_option'], $current_options) ? 'checked' : ''; ?>>
                                <label for="option_<?php echo $option['id_option']; ?>">
                                    <?php echo htmlspecialchars($option['nom_option']); ?>
                                    (<?php echo number_format($option['prix'], 2); ?>€)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-comment"></i> Commentaires</h3>
                    <div class="form-group">
                        <label for="commentaires">Demandes spécifiques (facultatif)</label>
                        <textarea name="commentaires" 
                                  id="commentaires" 
                                  placeholder="Ajoutez vos commentaires ou demandes spécifiques..."><?php echo htmlspecialchars($reservation['commentaires']); ?></textarea>
                    </div>
                </div>

                <div class="actions">
                    <a href="mes-reservations.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Annuler
                    </a>
                    <button type="submit" name="modifier_reservation" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>