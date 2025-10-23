<?php
/**
 * Chargeur de fichier .env
 * Charge les variables d'environnement depuis le fichier .env
 */

function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception("Le fichier .env est introuvable à l'emplacement: $path");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Ignorer les commentaires
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parser la ligne KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Supprimer les guillemets si présents
            $value = trim($value, '"\'');

            // Définir la variable d'environnement
            if (!array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Charger le fichier .env
$envPath = dirname(__DIR__) . '/.env';
loadEnv($envPath);

// Fonction helper pour récupérer une variable d'environnement
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    
    // Convertir les valeurs booléennes
    if (strtolower($value) === 'true') return true;
    if (strtolower($value) === 'false') return false;
    if (strtolower($value) === 'null') return null;
    
    return $value;
}
?>