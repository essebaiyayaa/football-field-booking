<?php
/**
 * Configuration générale de l'application
 * Utilise les variables d'environnement du fichier .env
 */

// Charger les variables d'environnement
require_once __DIR__ . '/env.php';

// Configuration du site
define('SITE_URL', env('SITE_URL', 'http://localhost/football-field-booking'));
define('SITE_NAME', env('SITE_NAME', 'FootBooking'));

// Configuration Email
define('MAIL_FROM', env('MAIL_FROM', 'noreply@footbooking.com'));

// Configuration reCAPTCHA
define('RECAPTCHA_SITE_KEY', env('RECAPTCHA_SITE_KEY'));
define('RECAPTCHA_SECRET_KEY', env('RECAPTCHA_SECRET_KEY'));

// Configuration de sécurité
define('SESSION_LIFETIME', env('SESSION_LIFETIME', 3600));
define('TOKEN_EXPIRY', env('TOKEN_EXPIRY', 86400));

// Fuseau horaire
date_default_timezone_set(env('TIMEZONE', 'Africa/Casablanca'));

// Mode debug
define('DEBUG_MODE', env('DEBUG_MODE', false));

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
?>