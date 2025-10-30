<?php
/**
 * Formulaire d'ajout de terrain pour les administrateurs
 * 
 * Ce fichier permet aux administrateurs d'ajouter un nouveau terrain
 * et de l'assigner à un gérant existant.
 * 
 * @author Jihane Chouhe
 * @version 1.0.0
 * @date 2024-10-30
 * 
 * @changelog
 * Version 1.0.0 (2024-10-30)
 * - Création du formulaire d'ajout de terrain
 * - Ajout de la sélection du gérant assigné
 * - Validation des données
 * - Gestion des erreurs
 */

session_start();
require_once '../config/database.php';

// Vérification que l'utilisateur est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

/**
 * Récupération de tous les gérants pour la liste déroulante
 */
try {
    $stmt = $pdo->query("
        SELECT id_utilisateur, prenom, nom, email 
        FROM Utilisateur 
        WHERE role = 'gerant_terrain' 
        ORDER BY prenom, nom
    ");
    $gerants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $gerants = [];
}

/**
 * Traitement du formulaire d'ajout de terrain
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_terrain = trim($_POST['nom_terrain']);
    $adresse = trim($_POST['adresse']);
    $ville = trim($_POST['ville']);
    $taille = $_POST['taille'];
    $type = $_POST['type'];
    $prix_heure = floatval($_POST['prix_heure']);
    $id_gerant = !empty($_POST['id_gerant']) ? intval($_POST['id_gerant']) : null;
    
    // Validation des données
    $errors = [];
    
    if (empty($nom_terrain)) {
        $errors[] = "Le nom du terrain est obligatoire";
    }
    
    if (empty($adresse)) {
        $errors[] = "L'adresse est obligatoire";
    }
    
    if (empty($ville)) {
        $errors[] = "La ville est obligatoire";
    }
    
    if ($prix_heure <= 0) {
        $errors[] = "Le prix par heure doit être supérieur à 0";
    }
    
    // Si pas d'erreurs, insertion dans la base de données
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO Terrain (nom_terrain, adresse, ville, taille, type, prix_heure, id_utilisateur) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nom_terrain, $adresse, $ville, $taille, $type, $prix_heure, $id_gerant]);
            
            $success = "Terrain ajouté avec succès !";
            
            // Réinitialisation du formulaire
            $nom_terrain = $adresse = $ville = '';
            $prix_heure = 0;
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de l'ajout du terrain: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Terrain - Admin FootBooking</title>
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
            color: #dc2626;
        }

        /* === CONTAINER === */
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 5%;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #64748b;
        }

        /* === FORMULAIRE === */
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            padding: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .required {
            color: #dc2626;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #dc2626;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-help {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        /* === ALERTES === */
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

        .alert ul {
            margin-left: 1.5rem;
            margin-top: 0.5rem;
        }

        /* === BOUTONS === */
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 1rem;
            border-top: 2px solid #e5e7eb;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #dc2626;
            color: white;
        }

        .btn-primary:hover {
            background: #b91c1c;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
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
                <a href="admin_terrains.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux terrains
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- En-tête de page -->
        <div class="page-header">
            <h1 class="page-title">Ajouter un nouveau terrain</h1>
            <p class="page-subtitle">Remplissez les informations du terrain et assignez-le à un gérant</p>
        </div>

        <!-- Messages de succès/erreur -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Erreur(s) détectée(s):</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Formulaire d'ajout de terrain -->
        <div class="form-container">
            <form method="POST" action="">
                <!-- Section: Informations générales -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Informations générales
                    </h2>

                    <!-- Nom du terrain -->
                    <div class="form-group">
                        <label class="form-label">
                            Nom du terrain <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="nom_terrain" 
                            class="form-input" 
                            placeholder="Ex: Terrain Central"
                            value="<?php echo htmlspecialchars($nom_terrain ?? ''); ?>"
                            required
                        >
                    </div>

                    <!-- Adresse -->
                    <div class="form-group">
                        <label class="form-label">
                            Adresse complète <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="adresse" 
                            class="form-input" 
                            placeholder="Ex: 123 Rue du Stade"
                            value="<?php echo htmlspecialchars($adresse ?? ''); ?>"
                            required
                        >
                    </div>

                    <!-- Ville -->
                    <div class="form-group">
                        <label class="form-label">
                            Ville <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="ville" 
                            class="form-input" 
                            placeholder="Ex: Casablanca"
                            value="<?php echo htmlspecialchars($ville ?? ''); ?>"
                            required
                        >
                    </div>
                </div>

                <!-- Section: Caractéristiques -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-sliders-h"></i>
                        Caractéristiques du terrain
                    </h2>

                    <!-- Taille -->
                    <div class="form-group">
                        <label class="form-label">
                            Taille du terrain <span class="required">*</span>
                        </label>
                        <select name="taille" class="form-select" required>
                            <option value="">Sélectionnez la taille</option>
                            <option value="Mini foot" <?php echo (isset($taille) && $taille === 'Mini foot') ? 'selected' : ''; ?>>Mini foot</option>
                            <option value="Terrain moyen" <?php echo (isset($taille) && $taille === 'Terrain moyen') ? 'selected' : ''; ?>>Terrain moyen</option>
                            <option value="Grand terrain" <?php echo (isset($taille) && $taille === 'Grand terrain') ? 'selected' : ''; ?>>Grand terrain</option>
                        </select>
                    </div>

                    <!-- Type -->
                    <div class="form-group">
                        <label class="form-label">
                            Type de surface <span class="required">*</span>
                        </label>
                        <select name="type" class="form-select" required>
                            <option value="">Sélectionnez le type</option>
                            <option value="Gazon naturel" <?php echo (isset($type) && $type === 'Gazon naturel') ? 'selected' : ''; ?>>Gazon naturel</option>
                            <option value="Gazon artificiel" <?php echo (isset($type) && $type === 'Gazon artificiel') ? 'selected' : ''; ?>>Gazon artificiel</option>
                            <option value="Terrain dur" <?php echo (isset($type) && $type === 'Terrain dur') ? 'selected' : ''; ?>>Terrain dur</option>
                        </select>
                    </div>

                    <!-- Prix par heure -->
                    <div class="form-group">
                        <label class="form-label">
                            Prix par heure (DH) <span class="required">*</span>
                        </label>
                        <input 
                            type="number" 
                            name="prix_heure" 
                            class="form-input" 
                            placeholder="Ex: 150"
                            value="<?php echo htmlspecialchars($prix_heure ?? ''); ?>"
                            min="0"
                            step="0.01"
                            required
                        >
                        <p class="form-help">Le prix de location du terrain par heure</p>
                    </div>
                </div>

                <!-- Section: Assignation -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-user-tie"></i>
                        Gérant assigné
                    </h2>

                    <!-- Sélection du gérant -->
                    <div class="form-group">
                        <label class="form-label">
                            Assigner à un gérant
                        </label>
                        <select name="id_gerant" class="form-select">
                            <option value="">Aucun gérant (à assigner plus tard)</option>
                            <?php foreach ($gerants as $gerant): ?>
                                <option value="<?php echo $gerant['id_utilisateur']; ?>" 
                                        <?php echo (isset($id_gerant) && $id_gerant == $gerant['id_utilisateur']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($gerant['prenom'] . ' ' . $gerant['nom'] . ' (' . $gerant['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-help">
                            <?php if (empty($gerants)): ?>
                                <span style="color: #f59e0b;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Aucun gérant disponible. Créez d'abord un gérant.
                                </span>
                            <?php else: ?>
                                Le gérant sera responsable de la gestion de ce terrain
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Actions du formulaire -->
                <div class="form-actions">
                    <a href="admin_terrains.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Annuler
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Ajouter le terrain
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>