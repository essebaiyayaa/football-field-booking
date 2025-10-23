<?php
session_start();
require_once '../config/database.php';
require_once '../config/config.php';

$message = '';
$success = false;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
     
        $stmt = $pdo->prepare("
            SELECT id_utilisateur, email, nom, prenom, date_creation 
            FROM Utilisateur 
            WHERE verification_token = ? AND email_verified = 0
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
           
            $created_time = strtotime($user['date_creation']);
            $current_time = time();
            $time_diff = $current_time - $created_time;
            
            if ($time_diff > 86400) { //24 seaa
                $message = "Ce lien de vérification a expiré. Veuillez vous réinscrire.";
                // delete unverified acc
                $stmt = $pdo->prepare("DELETE FROM Utilisateur WHERE id_utilisateur = ?");
                $stmt->execute([$user['id_utilisateur']]);
            } else {
                // acitvate acc
                $stmt = $pdo->prepare("
                    UPDATE Utilisateur 
                    SET email_verified = 1, verification_token = NULL 
                    WHERE id_utilisateur = ?
                ");
                $stmt->execute([$user['id_utilisateur']]);
                
                $success = true;
                $message = "Votre compte a été vérifié avec succès ! Vous pouvez maintenant vous connecter.";
                
             
                require_once '../config/mailer.php';
                
                $mailer = new Mailer();
                $full_name = $user['prenom'] . ' ' . $user['nom'];
                
                if ($mailer->sendWelcomeEmail($user['email'], $full_name)) {
                    
                    error_log("Email de bienvenue envoyé à: " . $user['email']);
                } else {
                   //log
                    error_log("Erreur envoi email bienvenue à: " . $user['email']);
                }
            }
        } else {
            $message = "Lien de vérification invalide ou compte déjà vérifié.";
        }
    } catch (PDOException $e) {
        $message = "Une erreur est survenue lors de la vérification.";
        error_log($e->getMessage());
    }
} else {
    $message = "Token de vérification manquant.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification Email - FootBooking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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

        .verify-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 3rem;
            text-align: center;
        }

        .icon-wrapper {
            width: 100px;
            height: 100px;
            margin: 0 auto 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
        }

        .success-icon {
            background: #d1fae5;
            color: #16a34a;
            animation: scaleIn 0.5s ease-out;
        }

        .error-icon {
            background: #fee2e2;
            color: #dc2626;
            animation: shake 0.5s ease-out;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        h1 {
            font-size: 2rem;
            color: #1f2937;
            margin-bottom: 1rem;
        }

        .message {
            font-size: 1.1rem;
            color: #6b7280;
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            margin: 0.5rem;
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
            background: #f3f4f6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: #16a34a;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="logo">
            <i class="fas fa-futbol"></i>
            FootBooking
        </div>

        <?php if ($success): ?>
            <div class="icon-wrapper success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Email vérifié !</h1>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
            <a href="login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </a>
        <?php else: ?>
            <div class="icon-wrapper error-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1>Vérification échouée</h1>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
            <a href="register.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> S'inscrire à nouveau
            </a>
        <?php endif; ?>

        <a href="../index.php" class="btn btn-secondary">
            <i class="fas fa-home"></i> Retour à l'accueil
        </a>
    </div>
</body>
</html>