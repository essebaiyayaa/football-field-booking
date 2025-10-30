<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

// Récupérer les informations actuelles de l'utilisateur
try {
    $stmt = $pdo->prepare("SELECT nom, prenom, email, telephone FROM Utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Erreur lors de la récupération des données.";
    error_log($e->getMessage());
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        // Mise à jour des informations personnelles
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');

        // Validation
        if (empty($nom)) {
            $errors[] = "Le nom est requis.";
        }
        if (empty($prenom)) {
            $errors[] = "Le prénom est requis.";
        }
        if (!empty($telephone) && !preg_match('/^[0-9+\s\-()]{10,20}$/', $telephone)) {
            $errors[] = "Le numéro de téléphone n'est pas valide.";
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE Utilisateur SET nom = ?, prenom = ?, telephone = ? WHERE id_utilisateur = ?");
                $stmt->execute([$nom, $prenom, $telephone, $_SESSION['user_id']]);
                
                // Mettre à jour la session
                $_SESSION['nom'] = $nom;
                $_SESSION['prenom'] = $prenom;
                
                $success = "Vos informations ont été mises à jour avec succès.";
                
                // Recharger les données
                $user['nom'] = $nom;
                $user['prenom'] = $prenom;
                $user['telephone'] = $telephone;
            } catch (PDOException $e) {
                $errors[] = "Erreur lors de la mise à jour. Veuillez réessayer.";
                error_log($e->getMessage());
            }
        }
    } elseif ($action === 'change_password') {
        // Changement de mot de passe
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($current_password)) {
            $errors[] = "Le mot de passe actuel est requis.";
        }
        if (empty($new_password)) {
            $errors[] = "Le nouveau mot de passe est requis.";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        }
        if ($new_password !== $confirm_password) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }

        if (empty($errors)) {
            try {
                // Vérifier le mot de passe actuel
                $stmt = $pdo->prepare("SELECT mot_de_passe FROM Utilisateur WHERE id_utilisateur = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user_password = $stmt->fetchColumn();

                if (password_verify($current_password, $user_password)) {
                    // Mettre à jour le mot de passe
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE Utilisateur SET mot_de_passe = ? WHERE id_utilisateur = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    
                    $success = "Votre mot de passe a été modifié avec succès.";
                } else {
                    $errors[] = "Le mot de passe actuel est incorrect.";
                }
            } catch (PDOException $e) {
                $errors[] = "Erreur lors de la modification du mot de passe.";
                error_log($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - FootBooking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
        }

        /* Navigation */
        nav {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #16a34a;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-links a {
            text-decoration: none;
            color: #374151;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #16a34a;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-outline {
            border: 2px solid #16a34a;
            color: #16a34a;
            background: transparent;
        }

        .btn-outline:hover {
            background: #16a34a;
            color: white;
        }

        .btn-primary {
            background: #16a34a;
            color: white;
            border: 2px solid #16a34a;
        }

        .btn-primary:hover {
            background: #15803d;
            border-color: #15803d;
        }

        /* Menu utilisateur */
        .user-menu {
            position: relative;
        }

        .user-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .user-button:hover {
            background: #15803d;
        }

        .user-button i.fa-user-circle {
            font-size: 1.5rem;
        }

        .user-button i.fa-chevron-down {
            font-size: 0.8rem;
            transition: transform 0.3s;
        }

        .user-button:hover i.fa-chevron-down {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 220px;
            display: none;
            z-index: 1000;
        }

        .dropdown-menu.show {
            display: block;
            animation: fadeIn 0.2s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            color: #374151;
            text-decoration: none;
            transition: background 0.2s;
        }

        .dropdown-menu a:hover {
            background: #f3f4f6;
        }

        .dropdown-menu a.logout {
            color: #dc2626;
        }

        .dropdown-menu a.logout:hover {
            background: #fee2e2;
        }

        .dropdown-menu hr {
            margin: 0.5rem 0;
            border: none;
            border-top: 1px solid #e5e7eb;
        }

        /* Contenu principal */
        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: #1f2937;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #6b7280;
        }

        .profile-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: #1f2937;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: #16a34a;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert ul {
            margin-left: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #16a34a;
        }

        input:disabled {
            background: #f9fafb;
            cursor: not-allowed;
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 0.5rem;
        }

        .toggle-password:hover {
            color: #16a34a;
        }

        .btn-submit {
            padding: 0.75rem 1.5rem;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            background: #15803d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        .password-hint {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        @media (max-width: 768px) {
            nav {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }

            .container {
                padding: 0 1rem;
            }

            .profile-section {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <nav>
        <a href="home.php" class="logo">
            <i class="fas fa-futbol"></i> FootBooking
        </a>
        
        <ul class="nav-links">
            <li><a href="home.php">Accueil</a></li>
            <li><a href="terrains.php">Liste des terrains</a></li>
            <li><a href="reservation.php">Réserver un terrain</a></li>
        </ul>

        <div class="auth-buttons">
            <div class="user-menu">
                <button class="user-button" onclick="toggleUserMenu()">
                    <i class="fa-solid fa-user-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?>
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
                <div class="dropdown-menu" id="userDropdown">
                    <a href="profile.php">
                        <i class="fa-solid fa-user"></i> Mon profil
                    </a>
                    <a href="mes_reservations.php">
                        <i class="fa-solid fa-calendar-check"></i> Mes réservations
                    </a>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="dashboard_admin.php">
                            <i class="fa-solid fa-gauge"></i> Dashboard Admin
                        </a>
                    <?php elseif ($_SESSION['role'] === 'gerant_terrain'): ?>
                        <a href="dashboard_gerant.php">
                            <i class="fa-solid fa-gauge"></i> Dashboard Gérant
                        </a>
                    <?php endif; ?>
                    <hr>
                    <a href="logout.php" class="logout">
                        <i class="fa-solid fa-right-from-bracket"></i> Se déconnecter
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1><i class="fa-solid fa-user"></i> Mon Profil</h1>
            <p>Gérez vos informations personnelles et votre sécurité</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Informations personnelles -->
        <div class="profile-section">
            <h2 class="section-title">
                <i class="fa-solid fa-id-card"></i>
                Informations personnelles
            </h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_info">
                
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($user['nom'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" id="prenom" name="prenom" required value="<?php echo htmlspecialchars($user['prenom'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" disabled value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                    <p class="password-hint">L'email ne peut pas être modifié</p>
                </div>

                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>">
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-save"></i> Enregistrer les modifications
                </button>
            </form>
        </div>

        <!-- Changement de mot de passe -->
        <div class="profile-section">
            <h2 class="section-title">
                <i class="fa-solid fa-lock"></i>
                Sécurité
            </h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">Mot de passe actuel</label>
                    <div class="password-wrapper">
                        <input type="password" id="current_password" name="current_password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('current_password', 'eye1')">
                            <i class="fas fa-eye" id="eye1"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_password" name="new_password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('new_password', 'eye2')">
                            <i class="fas fa-eye" id="eye2"></i>
                        </button>
                    </div>
                    <p class="password-hint">Minimum 8 caractères</p>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', 'eye3')">
                            <i class="fas fa-eye" id="eye3"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-key"></i> Changer le mot de passe
                </button>
            </form>
        </div>
    </div>

    <script>
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        // Fermer le menu si on clique en dehors
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            if (userMenu && !userMenu.contains(event.target)) {
                const dropdown = document.getElementById('userDropdown');
                if (dropdown) {
                    dropdown.classList.remove('show');
                }
            }
        });

        function togglePassword(fieldId, iconId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(iconId);
            
            if (field.type === 'password') {
                field.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>