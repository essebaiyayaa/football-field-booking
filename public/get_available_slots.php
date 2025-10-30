<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$terrain_id = $_GET['terrain_id'] ?? null;
$date = $_GET['date'] ?? null;

if (!$terrain_id || !$date) {
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

try {
    // Récupérer tous les créneaux réservés pour une date donnée
    $stmt = $pdo->prepare("
        SELECT heure_debut 
        FROM Reservation 
        WHERE id_terrain = ? 
        AND date_reservation = ? 
        AND statut != 'Annulée'
    ");
    $stmt->execute([$terrain_id, $date]);
    
    $booked_slots = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $booked_slots[] = $row['heure_debut'];
    }
    
    echo json_encode([
        'success' => true,
        'booked_slots' => $booked_slots,
        'date' => $date,
        'terrain_id' => $terrain_id,
        'timestamp' => time()
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}
?>