<?php
session_start();
require_once '../config/database.php';
require_once '../config/config.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $confirmer_mot_de_passe = $_POST['confirmer_mot_de_passe'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // Validation
    if (empty($nom)) $errors[] = "Le nom est requis.";
    if (empty($prenom)) $errors[] = "Le prénom est requis.";
    if (empty($email)) $errors[] = "L'email est requis.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "L'email n'est pas valide.";
    if (empty($mot_de_passe)) $errors[] = "Le mot de passe est requis.";
    if (strlen($mot_de_passe) < 8) $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
    if ($mot_de_passe !== $confirmer_mot_de_passe) $errors[] = "Les mots de passe ne correspondent pas.";

    //captcha
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
           
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM Utilisateur WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $errors[] = "Cet email est déjà utilisé.";
            } else {
               
                $verification_token = bin2hex(random_bytes(32));
                $hashed_password = password_hash($mot_de_passe, PASSWORD_DEFAULT);

                
                $stmt = $pdo->prepare("
                    INSERT INTO Utilisateur (nom, prenom, email, telephone, mot_de_passe, role, verification_token, email_verified) 
                    VALUES (?, ?, ?, ?, ?, 'client', ?, 0)
                ");
                
                $stmt->execute([$nom, $prenom, $email, $telephone, $hashed_password, $verification_token]);

                require_once '../config/mailer.php';
                
                $mailer = new Mailer();
                $verification_link = SITE_URL . "/public/verify.php?token=" . $verification_token;
                $full_name = $prenom . ' ' . $nom;

                if ($mailer->sendVerificationEmail($email, $full_name, $verification_link)) {
                    $success = "Inscription réussie ! Un email de vérification a été envoyé à votre adresse.";
                } else {
                    $errors[] = "Erreur lors de l'envoi de l'email de vérification. Veuillez réessayer.";
                   
                    $stmt = $pdo->prepare("DELETE FROM Utilisateur WHERE email = ?");
                    $stmt->execute([$email]);
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de l'inscription. Veuillez réessayer.";
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
    <title>Inscription - FootBooking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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

        .register-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 2.5rem;
        }

        .register-header {
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

        .register-header h1 {
            font-size: 1.8rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .register-header p {
            color: #6b7280;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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

        .alert-success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert ul {
            margin-left: 1.5rem;
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

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #6b7280;
        }

        .login-link a {
            color: #16a34a;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
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
            .form-row {
                grid-template-columns: 1fr;
            }

            .register-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="logo">
                <i class="fas fa-futbol"></i>
                FootBooking
            </div>
            <h1>Créer un compte</h1>
            <p>Rejoignez FootBooking et réservez vos terrains facilement</p>
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
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" id="prenom" name="prenom" required value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="mot_de_passe">Mot de passe * (min. 8 caractères)</label>
                    <div class="password-wrapper">
                        <input type="password" id="mot_de_passe" name="mot_de_passe" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('mot_de_passe')">
                            <i class="fas fa-eye" id="eye-mot_de_passe"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmer_mot_de_passe">Confirmer le mot de passe *</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirmer_mot_de_passe" name="confirmer_mot_de_passe" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirmer_mot_de_passe')">
                            <i class="fas fa-eye" id="eye-confirmer_mot_de_passe"></i>
                        </button>
                    </div>
                </div>

                <div class="recaptcha-wrapper">
                    <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-user-plus"></i> Créer mon compte
                </button>
            </form>
        <?php endif; ?>

        <div class="login-link">
            Vous avez déjà un compte ? <a href="login.php">Se connecter</a>
        </div>

        <div class="back-home">
            <a href="home.php">
                <i class="fas fa-arrow-left"></i> Retour à l'accueil
            </a>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById('eye-' + fieldId);
            
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