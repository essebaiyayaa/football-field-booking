<?php
session_start();
require_once '../config/database.php';

// Vérifier la session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer les critères de recherche
$type = isset($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : null;
$taille = isset($_GET['taille']) && $_GET['taille'] !== '' ? $_GET['taille'] : null;

try {
    // Construire la requête SQL dynamiquement
    $sql = "SELECT * FROM Terrain WHERE 1=1";
    $params = [];

    if ($type !== null) {
        $sql .= " AND type = ?";
        $params[] = $type;
    }

    if ($taille !== null) {
        $sql .= " AND taille = ?";
        $params[] = $taille;
    }

    $sql .= " ORDER BY nom_terrain";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $terrains = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Retourner les résultats en JSON
    header('Content-Type: application/json');
    echo json_encode($terrains);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>