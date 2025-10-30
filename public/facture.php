<?php
session_start();
require_once '../config/database.php';
require_once '../vendor/autoload.php'; 

// verifier session
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// id reservation from l url
$id_reservation = $_GET['id'] ?? null;

if (!$id_reservation) {
    header('Location: mes-reservations.php');
    exit;
}

// recuperation des details de la reservation
try {
    $stmt = $pdo->prepare("
        SELECT r.*, t.nom_terrain, t.adresse, t.ville, t.prix_heure, 
               u.nom, u.prenom, u.email, u.telephone
        FROM Reservation r
        JOIN Terrain t ON r.id_terrain = t.id_terrain
        JOIN Utilisateur u ON r.id_utilisateur = u.id_utilisateur
        WHERE r.id_reservation = ? AND r.id_utilisateur = ?
    ");
    $stmt->execute([$id_reservation, $_SESSION['user_id']]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reservation) {
        header('Location: mes-reservations.php');
        exit;
    }
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}

// les options de la reservation
try {
    $stmt = $pdo->prepare("
        SELECT os.nom_option, os.prix 
        FROM Reservation_Option ro
        JOIN OptionSupplementaire os ON ro.id_option = os.id_option
        WHERE ro.id_reservation = ?
    ");
    $stmt->execute([$id_reservation]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $options = [];
}

// calculer le totla
$total_options = 0;
foreach ($options as $option) {
    $total_options += $option['prix'];
}
$total_general = $reservation['prix_heure'] + $total_options;

// generer le pdf
if (isset($_POST['download_pdf'])) {
    generateInvoicePDF($reservation, $options, $total_options, $total_general);
    exit;
}

// fonction de generation
function generateInvoicePDF($reservation, $options, $total_options, $total_general) {
    // nouvelel instance tcpdf
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // juste un prototype, mn baed nstyliw facture

    $pdf->SetCreator('FootBooking');
    $pdf->SetAuthor('FootBooking');
    $pdf->SetTitle('Facture Réservation ' . $reservation['id_reservation']);
    $pdf->SetSubject('Facture');
    
    
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // contenu
    $html = generateInvoiceHTML($reservation, $options, $total_options, $total_general, true);
    
    // html contenu
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // telecharger
    $pdf->Output('facture_reservation_' . $reservation['id_reservation'] . '.pdf', 'D');
}

// genenrer le html
function generateInvoiceHTML($reservation, $options, $total_options, $total_general, $forPDF = false) {
    $date_reservation = date('d/m/Y', strtotime($reservation['date_reservation']));
    $heure_debut = date('H:i', strtotime($reservation['heure_debut']));
    $heure_fin = date('H:i', strtotime($reservation['heure_fin']));
    
    if ($forPDF) {
        $styles = "
            <style>
                body { font-family: helvetica; font-size: 12px; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #16a34a; padding-bottom: 10px; }
                .invoice-title { color: #16a34a; font-size: 24px; font-weight: bold; }
                .section { margin-bottom: 15px; }
                .section-title { background-color: #f0f0f0; padding: 8px; font-weight: bold; border-left: 4px solid #16a34a; }
                .info-grid { display: table; width: 100%; }
                .info-row { display: table-row; }
                .info-label { display: table-cell; font-weight: bold; width: 30%; padding: 4px; }
                .info-value { display: table-cell; padding: 4px; }
                .table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                .table th { background-color: #16a34a; color: white; padding: 8px; text-align: left; }
                .table td { padding: 8px; border-bottom: 1px solid #ddd; }
                .total-section { background-color: #f9f9f9; padding: 10px; margin-top: 20px; border: 1px solid #ddd; }
                .total-row { display: flex; justify-content: space-between; margin: 5px 0; }
                .grand-total { font-size: 16px; font-weight: bold; color: #16a34a; border-top: 2px solid #16a34a; padding-top: 5px; }
                .footer { text-align: center; margin-top: 30px; font-size: 10px; color: #666; }
            </style>
        ";
    } else {
        $styles = "";
    }
    
    $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Facture Réservation {$reservation['id_reservation']}</title>
            $styles
        </head>
        <body>
            <div class='header'>
                <h1 class='invoice-title'>FOOTBOOKING</h1>
                <h2>FACTURE</h2>
                <p>Réservation #{$reservation['id_reservation']}</p>
            </div>
            
            <div class='section'>
                <div class='section-title'>Informations de la réservation</div>
                <div class='info-grid'>
                    <div class='info-row'>
                        <div class='info-label'>Numéro de réservation:</div>
                        <div class='info-value'>#{$reservation['id_reservation']}</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Date de réservation:</div>
                        <div class='info-value'>$date_reservation</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Créneau horaire:</div>
                        <div class='info-value'>$heure_debut - $heure_fin</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Statut:</div>
                        <div class='info-value'>{$reservation['statut']}</div>
                    </div>
                </div>
            </div>
            
            <div class='section'>
                <div class='section-title'>Informations du terrain</div>
                <div class='info-grid'>
                    <div class='info-row'>
                        <div class='info-label'>Terrain:</div>
                        <div class='info-value'>{$reservation['nom_terrain']}</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Adresse:</div>
                        <div class='info-value'>{$reservation['adresse']}, {$reservation['ville']}</div>
                    </div>
                </div>
            </div>
            
            <div class='section'>
                <div class='section-title'>Informations client</div>
                <div class='info-grid'>
                    <div class='info-row'>
                        <div class='info-label'>Nom complet:</div>
                        <div class='info-value'>{$reservation['prenom']} {$reservation['nom']}</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Email:</div>
                        <div class='info-value'>{$reservation['email']}</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Téléphone:</div>
                        <div class='info-value'>{$reservation['telephone']}</div>
                    </div>
                </div>
            </div>
            
            <div class='section'>
                <div class='section-title'>Détails de la facturation</div>
                <table class='table'>
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Prix unitaire</th>
                            <th>Quantité</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Location du terrain {$reservation['nom_terrain']}</td>
                            <td>" . number_format($reservation['prix_heure'], 2) . " DH</td>
                            <td>1 heure</td>
                            <td>" . number_format($reservation['prix_heure'], 2) . " DH</td>
                        </tr>";
    
    // Ajouter les options
    foreach ($options as $option) {
        $html .= "
                        <tr>
                            <td>{$option['nom_option']}</td>
                            <td>" . number_format($option['prix'], 2) . " DH</td>
                            <td>1</td>
                            <td>" . number_format($option['prix'], 2) . " DH</td>
                        </tr>";
    }
    
    $html .= "
                    </tbody>
                </table>
                
                <div class='total-section'>
                    <div class='total-row'>
                        <span>Sous-total terrain:</span>
                        <span>" . number_format($reservation['prix_heure'], 2) . " DH</span>
                    </div>";
    
    if ($total_options > 0) {
        $html .= "
                    <div class='total-row'>
                        <span>Options supplémentaires:</span>
                        <span>" . number_format($total_options, 2) . " DH</span>
                    </div>";
    }
    
    $html .= "
                    <div class='total-row grand-total'>
                        <span>TOTAL:</span>
                        <span>" . number_format($total_general, 2) . " DH</span>
                    </div>
                </div>
            </div>";
    
    if (!$forPDF) {
        $html .= "
            <div class='section'>
                <div class='section-title'>Commentaires</div>
                <p>" . nl2br(htmlspecialchars($reservation['commentaires'] ?? 'Aucun commentaire')) . "</p>
            </div>";
    }
    
    $html .= "
            <div class='footer'>
                <p>Merci pour votre réservation !</p>
                <p>FootBooking - Votre partenaire de football préféré</p>
                <p>Facture générée le " . date('d/m/Y à H:i') . "</p>
            </div>
        </body>
        </html>
    ";
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture - Réservation #<?php echo $reservation['id_reservation']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f9fafb;
            color: #333;
            line-height: 1.6;
        }

        /* Header */
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

        /* Container */
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 5%;
        }

        /* Facture */
        .invoice {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 2rem;
        }

        .invoice-header {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 3px solid #16a34a;
            padding-bottom: 1rem;
        }

        .invoice-title {
            color: #16a34a;
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .invoice-subtitle {
            color: #6b7280;
            font-size: 1.2rem;
        }

        .invoice-number {
            background: #16a34a;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
            margin-top: 0.5rem;
            font-weight: bold;
        }

        .section {
            margin-bottom: 2rem;
        }

        .section-title {
            background: #f0fdf4;
            padding: 1rem;
            border-left: 4px solid #16a34a;
            margin-bottom: 1rem;
            font-weight: 600;
            color: #065f46;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-item {
            margin-bottom: 0.75rem;
        }

        .info-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: #6b7280;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }

        .table th {
            background: #16a34a;
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .total-section {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 2rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .total-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .grand-total {
            font-size: 1.25rem;
            font-weight: 700;
            color: #16a34a;
            border-top: 2px solid #16a34a;
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #374151;
            transform: translateY(-2px);
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-confirmed {
            background: #d1fae5;
            color: #065f46;
        }

        .comment-section {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .comment-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .comment-content {
            color: #6b7280;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="home.php" class="logo">
                <i class="fas fa-futbol"></i>
                FootBooking
            </a>
            <a href="mes-reservations.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Mes réservations
            </a>
        </nav>
    </header>

    <div class="container">
        <div class="invoice">
            <!-- Entte de la facture -->
            <div class="invoice-header">
                <h1 class="invoice-title">FOOTBOOKING</h1>
                <p class="invoice-subtitle">FACTURE</p>
                <div class="invoice-number">
                    Réservation #<?php echo $reservation['id_reservation']; ?>
                </div>
            </div>

            <!-- Informations de la reservation -->
            <div class="section">
                <h3 class="section-title">
                    <i class="fas fa-calendar-check"></i>
                    Informations de la réservation
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Numéro de réservation</div>
                        <div class="info-value">#<?php echo $reservation['id_reservation']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date de réservation</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($reservation['date_reservation'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Créneau horaire</div>
                        <div class="info-value">
                            <?php echo date('H:i', strtotime($reservation['heure_debut'])); ?> - 
                            <?php echo date('H:i', strtotime($reservation['heure_fin'])); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Statut</div>
                        <div class="info-value">
                            <span class="status-badge status-confirmed">
                                <?php echo $reservation['statut']; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations du terrain -->
            <div class="section">
                <h3 class="section-title">
                    <i class="fas fa-futbol"></i>
                    Informations du terrain
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Terrain</div>
                        <div class="info-value"><?php echo htmlspecialchars($reservation['nom_terrain']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Adresse</div>
                        <div class="info-value"><?php echo htmlspecialchars($reservation['adresse'] . ', ' . $reservation['ville']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Prix horaire</div>
                        <div class="info-value"><?php echo number_format($reservation['prix_heure'], 2); ?> DH</div>
                    </div>
                </div>
            </div>

            <!-- Informations client -->
            <div class="section">
                <h3 class="section-title">
                    <i class="fas fa-user"></i>
                    Informations client
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nom complet</div>
                        <div class="info-value"><?php echo htmlspecialchars($reservation['prenom'] . ' ' . $reservation['nom']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($reservation['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Téléphone</div>
                        <div class="info-value"><?php echo htmlspecialchars($reservation['telephone'] ?? 'Non renseigné'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Details de la facturation -->
            <div class="section">
                <h3 class="section-title">
                    <i class="fas fa-receipt"></i>
                    Détails de la facturation
                </h3>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Prix unitaire</th>
                            <th>Quantité</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Location du terrain <?php echo htmlspecialchars($reservation['nom_terrain']); ?></td>
                            <td><?php echo number_format($reservation['prix_heure'], 2); ?> DH</td>
                            <td>1 heure</td>
                            <td><?php echo number_format($reservation['prix_heure'], 2); ?> DH</td>
                        </tr>
                        
                        <?php foreach ($options as $option): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($option['nom_option']); ?></td>
                            <td><?php echo number_format($option['prix'], 2); ?> DH</td>
                            <td>1</td>
                            <td><?php echo number_format($option['prix'], 2); ?> DH</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="total-section">
                    <div class="total-row">
                        <span>Sous-total terrain:</span>
                        <span><?php echo number_format($reservation['prix_heure'], 2); ?> DH</span>
                    </div>
                    
                    <?php if ($total_options > 0): ?>
                    <div class="total-row">
                        <span>Options supplémentaires:</span>
                        <span><?php echo number_format($total_options, 2); ?> DH</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="total-row grand-total">
                        <span>TOTAL:</span>
                        <span><?php echo number_format($total_general, 2); ?> DH</span>
                    </div>
                </div>
            </div>

            <!-- Commentaires -->
            <?php if (!empty($reservation['commentaires'])): ?>
            <div class="section">
                <h3 class="section-title">
                    <i class="fas fa-comment"></i>
                    Commentaires
                </h3>
                <div class="comment-section">
                    <div class="comment-content">
                        <?php echo nl2br(htmlspecialchars($reservation['commentaires'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="actions">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="download_pdf" class="btn btn-primary">
                        <i class="fas fa-download"></i>
                        Télécharger la facture (PDF)
                    </button>
                </form>
                <a href="mes-reservations.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i>
                    Mes réservations
                </a>
            </div>
        </div>
    </div>
</body>
</html>