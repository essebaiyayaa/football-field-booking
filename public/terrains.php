<?php
session_start();
require_once '../config/database.php';

// recuperer les terrains
try {
    $stmt = $pdo->query("SELECT * FROM Terrain ORDER BY nom_terrain");
    $terrains = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $terrains = [];
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nos Terrains - FootBooking</title>
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

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 4rem 5%;
            text-align: center;
        }

        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .hero p {
            font-size: 1.2rem;
            opacity: 0.95;
        }

        /* Filters Section */
        .filters {
            background: white;
            padding: 2rem 5%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 3rem;
        }

        .filters-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .filter-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #16a34a;
        }

        /* Terrains Grid */
        .terrains-section {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 5% 5rem;
        }

        .terrains-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .terrain-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: all 0.3s;
        }

        .terrain-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }

        .terrain-image {
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            position: relative;
        }

        .terrain-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.95);
            color: #16a34a;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .terrain-content {
            padding: 1.5rem;
        }

        .terrain-header {
            margin-bottom: 1rem;
        }

        .terrain-name {
            font-size: 1.4rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .terrain-location {
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .terrain-details {
            margin: 1.5rem 0;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-icon {
            width: 40px;
            height: 40px;
            background: #f0fdf4;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #16a34a;
            font-size: 1.2rem;
        }

        .detail-text {
            flex: 1;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #9ca3af;
            display: block;
        }

        .detail-value {
            font-weight: 600;
            color: #374151;
        }

        .terrain-price {
            background: #f0fdf4;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            margin: 1.5rem 0;
        }

        .price-label {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }

        .price-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #16a34a;
        }

        .terrain-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-reserve {
            flex: 1;
            background: #16a34a;
            color: white;
            padding: 0.9rem;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }

        .btn-reserve:hover {
            background: #15803d;
            transform: translateY(-2px);
        }

        .btn-details {
            background: white;
            color: #16a34a;
            border: 2px solid #16a34a;
            padding: 0.9rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s;
            cursor: pointer;
        }

        .btn-details:hover {
            background: #f0fdf4;
        }

        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }

        .no-results i {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }

        /* Footer */
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

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .hero h1 {
                font-size: 2rem;
            }

            .terrains-grid {
                grid-template-columns: 1fr;
            }

            .filters-container {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }
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
            
            <ul class="nav-links">
                <li><a href="home.php">Accueil</a></li>
                <li><a href="terrains.php" style="color: #16a34a;">Nos Terrains</a></li>
                <li><a href="reserver_terrain.php">Réserver</a></li>
            </ul>

            <div class="auth-buttons">
                <a href="login.php" class="btn btn-outline">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Se connecter
                </a>
                <a href="register.php" class="btn btn-primary">
                    <i class="fa-solid fa-user-plus"></i>
                    S'inscrire
                </a>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <h1><i class="fas fa-futbol"></i> Nos Terrains</h1>
        <p>Découvrez nos terrains de football disponibles à Casablanca</p>
    </section>

    <!-- Filters Section -->
    <section class="filters">
        <div class="filters-container">
            <div class="filter-group">
                <label for="taille"><i class="fas fa-ruler-combined"></i> Taille</label>
                <select id="taille" onchange="filterTerrains()">
                    <option value="">Toutes les tailles</option>
                    <option value="Mini foot">Mini foot</option>
                    <option value="Terrain moyen">Terrain moyen</option>
                    <option value="Grand terrain">Grand terrain</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="type"><i class="fas fa-layer-group"></i> Type de gazon</label>
                <select id="type" onchange="filterTerrains()">
                    <option value="">Tous les types</option>
                    <option value="Gazon naturel">Gazon naturel</option>
                    <option value="Gazon artificiel">Gazon artificiel</option>
                    <option value="Terrain dur">Terrain dur</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="ville"><i class="fas fa-city"></i> Ville</label>
                <select id="ville" onchange="filterTerrains()">
                    <option value="">Toutes les villes</option>
                    <option value="Casablanca">Casablanca</option>
                </select>
            </div>
        </div>
    </section>

    <!-- Terrains Grid -->
    <section class="terrains-section">
        <div class="terrains-grid" id="terrainsGrid">
            <?php if (empty($terrains)): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h2>Aucun terrain disponible</h2>
                    <p>Veuillez réessayer plus tard</p>
                </div>
            <?php else: ?>
                <?php foreach ($terrains as $terrain): ?>
                    <div class="terrain-card" 
                         data-taille="<?php echo htmlspecialchars($terrain['taille']); ?>"
                         data-type="<?php echo htmlspecialchars($terrain['type']); ?>"
                         data-ville="<?php echo htmlspecialchars($terrain['ville']); ?>">
                        
                        <div class="terrain-image">
                            <i class="fas fa-futbol"></i>
                            <span class="terrain-badge"><?php echo htmlspecialchars($terrain['taille']); ?></span>
                        </div>

                        <div class="terrain-content">
                            <div class="terrain-header">
                                <h3 class="terrain-name"><?php echo htmlspecialchars($terrain['nom_terrain']); ?></h3>
                                <p class="terrain-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($terrain['adresse'] . ', ' . $terrain['ville']); ?>
                                </p>
                            </div>

                            <div class="terrain-details">
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <i class="fas fa-ruler-combined"></i>
                                    </div>
                                    <div class="detail-text">
                                        <span class="detail-label">Taille</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($terrain['taille']); ?></span>
                                    </div>
                                </div>

                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <i class="fas fa-layer-group"></i>
                                    </div>
                                    <div class="detail-text">
                                        <span class="detail-label">Type</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($terrain['type']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="terrain-price">
                                <p class="price-label">Prix par heure</p>
                                <p class="price-value"><?php echo number_format($terrain['prix_heure'], 2); ?> DH</p>
                            </div>

                            <div class="terrain-actions">
                          <a href="reserver_terrain.php?id=<?php echo $terrain['id_terrain']; ?>" class="btn-reserve">
                                    <i class="fas fa-calendar-check"></i> Réserver
                                </a>
                                <button class="btn-details" onclick="showDetails(<?php echo $terrain['id_terrain']; ?>)">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <footer>
        <p>&copy; 2024 FootBooking. Tous droits réservés.</p>
    </footer>

    <script>
        function filterTerrains() {
            const taille = document.getElementById('taille').value;
            const type = document.getElementById('type').value;
            const ville = document.getElementById('ville').value;
            
            const cards = document.querySelectorAll('.terrain-card');
            
            cards.forEach(card => {
                const cardTaille = card.getAttribute('data-taille');
                const cardType = card.getAttribute('data-type');
                const cardVille = card.getAttribute('data-ville');
                
                const matchTaille = !taille || cardTaille === taille;
                const matchType = !type || cardType === type;
                const matchVille = !ville || cardVille === ville;
                
                if (matchTaille && matchType && matchVille) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function showDetails(terrainId) {
            alert('Détails du terrain ID: ' + terrainId + '\n\nhta nzidouha');
       
        }
    </script>
</body>
</html>