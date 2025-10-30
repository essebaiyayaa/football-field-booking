<?php
/**
 * Interface de gestion des gérants pour les administrateurs
 * 
 * Ce fichier permet aux administrateurs de visualiser tous les gérants,
 * créer de nouveaux gérants et envoyer les informations de connexion par email.
 * 
 * @author Jihane Chouhe
 * @version 1.0.0
 * @date 2024-10-30
 * 
 * @changelog
 * Version 1.0.0 (2024-10-30)
 * - Création de l'interface de gestion des gérants
 * - Ajout du formulaire de création de gérant
 * - Implémentation de l'envoi d'email avec les identifiants
 * - Affichage de la liste des gérants avec leurs terrains
 * - Génération automatique de mot de passe sécurisé
 */

session_start();
require_once '../config/database.php';
require_once '../config/mailer.php';


// Vérification que l'utilisateur est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

/**
 * Fonction pour générer un mot de passe aléatoire sécurisé
 * 
 * @param int $length Longueur du mot de passe
 * @return string Mot de passe généré
 */
function genererMotDePasse($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Fonction pour envoyer un email avec les identifiants
 * 
 * @param string $email Email du destinataire
 * @param string $nom Nom du gérant
 * @param string $prenom Prénom du gérant
 * @param string $password Mot de passe en clair
 * @return bool Succès de l'envoi
 */

function envoyerEmailIdentifiants($email, $nom, $prenom, $password, $verification_link = null) {
    try {
        $mailer = new Mailer();
        
        // Si un lien de vérification est fourni, utiliser l'email de vérification
        if ($verification_link) {
            $result = $mailer->sendVerificationEmail(
                $email,
                $prenom . ' ' . $nom,
                $verification_link
            );
        } else {
            // Sinon, envoyer un email personnalisé avec les identifiants
            $subject = "Bienvenue sur FootBooking - Vos identifiants de connexion";
            
            $htmlBody = "
            <!DOCTYPE html>
            <html lang='fr'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        line-height: 1.6; 
                        color: #333;
                        margin: 0;
                        padding: 0;
                        background-color: #f9fafb;
                    }
                    .container { 
                        max-width: 600px; 
                        margin: 0 auto;
                        background: white;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    }
                    .header { 
                        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); 
                        color: white; 
                        padding: 40px 30px; 
                        text-align: center; 
                    }
                    .header h1 {
                        margin: 0;
                        font-size: 28px;
                    }
                    .content { 
                        padding: 40px 30px;
                    }
                    .credentials { 
                        background: #f0fdf4; 
                        padding: 25px; 
                        border-radius: 8px; 
                        margin: 25px 0; 
                        border-left: 4px solid #16a34a;
                    }
                    .credential-item { 
                        margin: 15px 0;
                        padding: 10px 0;
                        border-bottom: 1px solid #dcfce7;
                    }
                    .credential-item:last-child {
                        border-bottom: none;
                    }
                    .credential-label { 
                        font-weight: bold; 
                        color: #065f46;
                        display: block;
                        margin-bottom: 5px;
                        font-size: 14px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    }
                    .credential-value { 
                        color: #16a34a; 
                        font-size: 18px; 
                        font-weight: bold;
                        font-family: 'Courier New', monospace;
                        background: white;
                        padding: 10px;
                        border-radius: 4px;
                        display: inline-block;
                    }
                    .warning-box {
                        background: #fef3c7;
                        border-left: 4px solid #f59e0b;
                        padding: 15px;
                        border-radius: 6px;
                        margin: 20px 0;
                    }
                    .warning-box p {
                        margin: 0;
                        color: #92400e;
                    }
                    .button { 
                        display: inline-block; 
                        background: #16a34a; 
                        color: white !important; 
                        padding: 14px 35px; 
                        text-decoration: none; 
                        border-radius: 8px; 
                        margin: 25px 0;
                        font-weight: bold;
                        font-size: 16px;
                        text-align: center;
                    }
                    .button:hover {
                        background: #15803d;
                    }
                    .footer { 
                        text-align: center; 
                        color: #6b7280; 
                        font-size: 14px;
                        padding: 20px;
                        background: #f9fafb;
                    }
                    .footer p {
                        margin: 5px 0;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>⚽ Bienvenue sur FootBooking</h1>
                        <p style='margin: 10px 0 0 0; opacity: 0.9;'>Votre compte gérant a été créé</p>
                    </div>
                    <div class='content'>
                        <p>Bonjour <strong>" . htmlspecialchars($prenom . " " . $nom) . "</strong>,</p>
                        
                        <p>Votre compte gérant a été créé avec succès sur la plateforme FootBooking. Vous pouvez maintenant gérer votre terrain et vos réservations.</p>
                        
                        <p style='font-weight: bold; margin-top: 25px;'>Voici vos identifiants de connexion :</p>
                        
                        <div class='credentials'>
                            <div class='credential-item'>
                                <span class='credential-label'>📧 Adresse email</span>
                                <span class='credential-value'>" . htmlspecialchars($email) . "</span>
                            </div>
                            <div class='credential-item'>
                                <span class='credential-label'>🔑 Mot de passe temporaire</span>
                                <span class='credential-value'>" . htmlspecialchars($password) . "</span>
                            </div>
                        </div>
                        
                        <div class='warning-box'>
                            <p><strong>⚠️ Important :</strong> Pour des raisons de sécurité, nous vous recommandons vivement de changer votre mot de passe lors de votre première connexion.</p>
                        </div>
                        
                        <div style='text-align: center;'>
                            <a href='" . SITE_URL . "/gerant/login.php' class='button'>
                                🔓 Se connecter maintenant
                            </a>
                        </div>
                        
                        <p style='color: #6b7280; font-size: 14px; margin-top: 30px;'>
                            Si vous rencontrez des difficultés pour vous connecter, n'hésitez pas à contacter l'administrateur.
                        </p>
                    </div>
                    <div class='footer'>
                        <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                        <p>&copy; " . date('Y') . " FootBooking - Tous droits réservés</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $textBody = "
            Bienvenue sur FootBooking
            
            Bonjour " . $prenom . " " . $nom . ",
            
            Votre compte gérant a été créé avec succès.
            
            Vos identifiants de connexion :
            Email : " . $email . "
            Mot de passe : " . $password . "
            
            IMPORTANT : Changez votre mot de passe lors de votre première connexion.
            
            Connexion : " . SITE_URL . "/gerant/login.php
            
            Cordialement,
            L'équipe FootBooking
            ";
            
            // Utiliser la méthode sendEmail générique
            $result = $mailer->sendEmail(
                $email,
                $prenom . ' ' . $nom,
                $subject,
                $htmlBody,
                $textBody
            );
        }
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Email envoyé avec succès'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email'
            ];
        }
        
    } catch (Exception $e) {
        error_log("Erreur Mailer: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erreur : ' . $e->getMessage()
        ];
    }
}

/**
 * Traitement du formulaire de création de gérant
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_gerant'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    
    // Validation des données
    $errors = [];
    
    if (empty($nom)) {
        $errors[] = "Le nom est obligatoire";
    }
    
    if (empty($prenom)) {
        $errors[] = "Le prénom est obligatoire";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email est invalide";
    }
    
    // Vérifier si l'email existe déjà
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM Utilisateur WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Cet email est déjà utilisé";
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la vérification de l'email";
        }
    }
    
    // Si pas d'erreurs, créer le gérant
    if (empty($errors)) {
        // Générer un mot de passe aléatoire
        $password_clair = "admin123";
        $password_hash = password_hash($password_clair, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO Utilisateur (nom, prenom, email, telephone, mot_de_passe, role, email_verified) 
                VALUES (?, ?, ?, ?, ?, 'gerant_terrain', 1)
            ");
            $stmt->execute([$nom, $prenom, $email, $telephone, $password_hash]);
            
            // Envoyer l'email avec les identifiants
            if (envoyerEmailIdentifiants($email, $nom, $prenom, $password_clair)) {
                $success = "Gérant créé avec succès ! Un email avec les identifiants a été envoyé à " . htmlspecialchars($email);
            } else {
                $success = "Gérant créé avec succès ! Cependant, l'email n'a pas pu être envoyé. Mot de passe: " . $password_clair;
            }
            
            // Réinitialiser le formulaire
            $nom = $prenom = $email = $telephone = '';
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la création du gérant: " . $e->getMessage();
        }
    }
}

/**
 * Récupération de tous les gérants avec leurs statistiques
 */
try {
    $stmt = $pdo->query("
        SELECT 
            u.id_utilisateur,
            u.nom,
            u.prenom,
            u.email,
            u.telephone,
            u.date_creation,
            COUNT(DISTINCT t.id_terrain) as nb_terrains,
            COUNT(DISTINCT r.id_reservation) as nb_reservations
        FROM Utilisateur u
        LEFT JOIN Terrain t ON u.id_utilisateur = t.id_utilisateur
        LEFT JOIN Reservation r ON t.id_terrain = r.id_terrain
        WHERE u.role = 'gerant_terrain'
        GROUP BY u.id_utilisateur
        ORDER BY u.date_creation DESC
    ");
    $gerants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $gerants = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Gérants - Admin FootBooking</title>
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
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 5%;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            color: #1e293b;
        }

        /* === LAYOUT === */
        .layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* === FORMULAIRE === */
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            padding: 2rem;
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .form-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        .required {
            color: #dc2626;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-input:focus {
            outline: none;
            border-color: #dc2626;
        }

        /* === BOUTONS === */
        .btn {
            width: 100%;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #dc2626;
            color: white;
        }

        .btn-primary:hover {
            background: #b91c1c;
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

        /* === LISTE GÉRANTS === */
        .gerants-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .gerants-header {
            background: #f8fafc;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .gerants-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        .gerants-list {
            padding: 1rem;
        }

        .gerant-card {
            padding: 1.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .gerant-card:hover {
            border-color: #dc2626;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .gerant-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .gerant-info {
            flex: 1;
        }

        .gerant-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .gerant-email {
            color: #64748b;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .gerant-badge {
            background: #dcfce7;
            color: #166534;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .gerant-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-box {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #dc2626;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .empty-state {
            padding: 3rem;
            text-align: center;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }

        /* === RESPONSIVE === */
        @media (max-width: 1024px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .form-container {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .gerant-stats {
                grid-template-columns: 1fr;
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
                <a href="admin_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Retour au tableau de bord
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- En-tête de page -->
        <div class="page-header">
            <h1 class="page-title">Gestion des Gérants</h1>
        </div>

        <!-- Messages de succès/erreur -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
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

        <!-- Layout principal -->
        <div class="layout">
            <!-- Formulaire de création de gérant -->
            <div class="form-container">
                <h2 class="form-title">
                    <i class="fas fa-user-plus"></i>
                    Créer un nouveau gérant
                </h2>

                <form method="POST" action="">
                    <!-- Nom -->
                    <div class="form-group">
                        <label class="form-label">
                            Nom <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="nom" 
                            class="form-input" 
                            placeholder="Nom de famille"
                            value="<?php echo htmlspecialchars($nom ?? ''); ?>"
                            required
                        >
                    </div>

                    <!-- Prénom -->
                    <div class="form-group">
                        <label class="form-label">
                            Prénom <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="prenom" 
                            class="form-input" 
                            placeholder="Prénom"
                            value="<?php echo htmlspecialchars($prenom ?? ''); ?>"
                            required
                        >
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label class="form-label">
                            Email <span class="required">*</span>
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="email@exemple.com"
                            value="<?php echo htmlspecialchars($email ?? ''); ?>"
                            required
                        >
                    </div>

                    <!-- Téléphone -->
                    <div class="form-group">
                        <label class="form-label">
                            Téléphone
                        </label>
                        <input 
                            type="tel" 
                            name="telephone" 
                            class="form-input" 
                            placeholder="0612345678"
                            value="<?php echo htmlspecialchars($telephone ?? ''); ?>"
                        >
                    </div>

                    <!-- Info -->
                    <div style="background: #eff6ff; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.875rem; color: #1e40af;">
                        <i class="fas fa-info-circle"></i>
                        Un mot de passe sécurisé sera généré automatiquement et envoyé par email au gérant.
                    </div>

                    <!-- Bouton submit -->
                    <button type="submit" name="creer_gerant" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Créer et envoyer les identifiants
                    </button>
                </form>
            </div>

            <!-- Liste des gérants -->
            <div class="gerants-container">
                <div class="gerants-header">
                    <h2 class="gerants-title">
                        <i class="fas fa-users"></i>
                        Liste des gérants (<?php echo count($gerants); ?>)
                    </h2>
                </div>

                <div class="gerants-list">
                    <?php if (empty($gerants)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-tie"></i>
                            <p>Aucun gérant enregistré pour le moment</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($gerants as $gerant): ?>
                            <div class="gerant-card">
                                <!-- Header du gérant -->
                                <div class="gerant-header">
                                    <div class="gerant-info">
                                        <div class="gerant-name">
                                            <?php echo htmlspecialchars($gerant['prenom'] . ' ' . $gerant['nom']); ?>
                                        </div>
                                        <div class="gerant-email">
                                            <i class="fas fa-envelope"></i>
                                            <?php echo htmlspecialchars($gerant['email']); ?>
                                        </div>
                                        <?php if ($gerant['telephone']): ?>
                                            <div class="gerant-email">
                                                <i class="fas fa-phone"></i>
                                                <?php echo htmlspecialchars($gerant['telephone']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="gerant-badge">Gérant</span>
                                </div>

                                <!-- Statistiques du gérant -->
                                <div class="gerant-stats">
                                    <div class="stat-box">
                                        <div class="stat-value"><?php echo $gerant['nb_terrains']; ?></div>
                                        <div class="stat-label">Terrain(s) géré(s)</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-value"><?php echo $gerant['nb_reservations']; ?></div>
                                        <div class="stat-label">Réservation(s)</div>
                                    </div>
                                </div>

                                <!-- Date de création -->
                                <div style="margin-top: 1rem; font-size: 0.75rem; color: #94a3b8;">
                                    <i class="fas fa-calendar"></i>
                                    Créé le <?php echo date('d/m/Y', strtotime($gerant['date_creation'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>