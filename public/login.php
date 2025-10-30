<?php
session_start();
require_once '../config/database.php';
require_once '../config/config.php';

$errors = [];

// Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin_dashboard.php');
            exit;
        case 'gerant_terrain':
            header('Location: dashboard_gerant.php');
            exit;
        case 'client':
        default:
            header('Location: home.php');
            exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // Validation
    if (empty($email)) {
        $errors[] = "L'email est requis.";
    }
    if (empty($mot_de_passe)) {
        $errors[] = "Le mot de passe est requis.";
    }

    // Vérification reCAPTCHA
    if (empty($recaptcha_response)) {
        $errors[] = "Veuillez cocher la case reCAPTCHA.";
    } else {
        $recaptcha_secret = RECAPTCHA_SECRET_KEY;
        $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
        $recaptcha_data = [
            'secret' => $recaptcha_secret,
            'response' => $recaptcha_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($recaptcha_data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($recaptcha_url, false, $context);
        $result_json = json_decode($result);

        if (!$result_json->success) {
            $errors[] = "La vérification reCAPTCHA a échoué. Veuillez réessayer.";
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id_utilisateur, nom, prenom, email, mot_de_passe, role, email_verified FROM Utilisateur WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {
                // Vérifier si l'email est vérifié
                if ($user['email_verified'] == 0) {
                    $errors[] = "Veuillez vérifier votre email avant de vous connecter. Consultez votre boîte de réception.";
                } else {
                    // Mise à jour de la dernière connexion
                    $stmt = $pdo->prepare("UPDATE Utilisateur SET date_derniere_connexion = NOW() WHERE id_utilisateur = ?");
                    $stmt->execute([$user['id_utilisateur']]);

                    // Création de la session
                    $_SESSION['user_id'] = $user['id_utilisateur'];
                    $_SESSION['nom'] = $user['nom'];
                    $_SESSION['prenom'] = $user['prenom'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];

                    // Redirection selon le rôle
                    switch ($user['role']) {
                        case 'admin':
                            header('Location: dashboard_admin.php');
                            exit;
                        case 'gerant_terrain':
                            header('Location: gerant_dashboard.php');
                            exit;
                        case 'client':
                        default:
                            header('Location: home.php');
                            exit;
                    }
                }
            } else {
                $errors[] = "Email ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la connexion. Veuillez réessayer.";
            error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - FootBooking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            padding: 2.5rem;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 2rem;
            font-weight: bold;
            color: #16a34a;
            margin-bottom: 0.5rem;
        }

        .login-header h1 {
            font-size: 1.8rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #6b7280;
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

        input[type="email"],
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

        .alert ul {
            margin-left: 1.5rem;
        }

        .forgot-password {
            text-align: right;
            margin-top: 0.5rem;
        }

        .forgot-password a {
            color: #16a34a;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .recaptcha-wrapper {
            margin: 1.5rem 0;
            display: flex;
            justify-content: center;
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            background: #15803d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        .btn-submit:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #6b7280;
        }

        .register-link a {
            color: #16a34a;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .back-home {
            text-align: center;
            margin-top: 1rem;
        }

        .back-home a {
            color: #6b7280;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-home a:hover {
            color: #16a34a;
        }

        @media (max-width: 768px) {
            .login-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-futbol"></i>
                FootBooking
            </div>
            <h1>Connexion</h1>
            <p>Accédez à votre compte FootBooking</p>
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

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="mot_de_passe">Mot de passe</label>
                <div class="password-wrapper">
                    <input type="password" id="mot_de_passe" name="mot_de_passe" required>
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <i class="fas fa-eye" id="eye-icon"></i>
                    </button>
                </div>
                <div class="forgot-password">
                    <a href="forgot_password.php">Mot de passe oublié ?</a>
                </div>
            </div>

            <div class="recaptcha-wrapper">
                <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        </form>

        <div class="register-link">
            Vous n'avez pas de compte ? <a href="register.php">Créer un compte</a>
        </div>

        <div class="back-home">
            <a href="home.php">
                <i class="fas fa-arrow-left"></i> Retour à l'accueil
            </a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const field = document.getElementById('mot_de_passe');
            const eye = document.getElementById('eye-icon');
            
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
    </script>
</body>
</html>