<?php
/**
 * Script d'authentification OAuth 2.0 pour Gmail
 * 
 * Ce script permet à l'utilisateur de s'authentifier auprès de Google
 * et d'autoriser l'application à accéder à Gmail.
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure l'autoloader de Composer
require_once __DIR__ . '/vendor/autoload.php';

// Créer un client Google
$client = new Google\Client();
$client->setApplicationName('Tina Gmail Integration');

// Configurer les scopes nécessaires
$client->setScopes([
    Google\Service\Gmail::GMAIL_READONLY,
    Google\Service\Gmail::GMAIL_COMPOSE,
    Google\Service\Docs::DOCUMENTS,
    Google\Service\Drive::DRIVE,
    Google\Service\Drive::DRIVE_FILE
]);

// Vérifier si nous sommes sur Heroku
$isHeroku = getenv('GOOGLE_OAUTH_CREDENTIALS_JSON') ? true : false;

// Configurer les identifiants OAuth
if ($isHeroku) {
    // Sur Heroku, utiliser la variable d'environnement spécifique pour OAuth
    $credentials_json = getenv('GOOGLE_OAUTH_CREDENTIALS_JSON');
    $credentials = json_decode($credentials_json, true);
    $client->setAuthConfig($credentials);
} else {
    // En local, utiliser le fichier de clé OAuth
    $client->setAuthConfig('client_secret_897210672149-bdk9e05vo6gmnvnqdv0572ebt5voobe0.apps.googleusercontent.com.json');
}

// Définir l'URL de redirection après authentification
if ($isHeroku) {
    // Sur Heroku, utiliser l'URL de l'application Heroku
    $redirect_uri = 'https://tinatools-gdocs-8657da134f6d.herokuapp.com/oauth_callback.php';
} else {
    // En local, utiliser localhost
    $redirect_uri = 'http://localhost/tinatools/oauth_callback.php';
}
$client->setRedirectUri($redirect_uri);

// Définir le type d'accès et l'état de l'authentification
$client->setAccessType('offline');        // Pour obtenir un refresh token
$client->setPrompt('consent');            // Forcer l'affichage du consentement

// Générer l'URL d'authentification
$auth_url = $client->createAuthUrl();

// Rediriger l'utilisateur vers l'URL d'authentification Google
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit;
