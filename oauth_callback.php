<?php
/**
 * Script de callback OAuth 2.0 pour Gmail
 * 
 * Ce script reçoit le code d'autorisation de Google et obtient les tokens d'accès.
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure l'autoloader de Composer
require_once __DIR__ . '/vendor/autoload.php';

// Inclure le fichier de configuration de la base de données
require_once __DIR__ . '/db_config.php';

// Créer un client Google
$client = new Google\Client();
$client->setApplicationName('Tina Gmail Integration');

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
    $client->setAuthConfig(__DIR__ . '/client_secret_897210672149-bdk9e05vo6gmnvnqdv0572ebt5voobe0.apps.googleusercontent.com.json');
}

// Définir l'URL de redirection
if ($isHeroku) {
    // Sur Heroku, utiliser l'URL de l'application Heroku
    $redirect_uri = 'https://tinatools-gdocs-8657da134f6d.herokuapp.com/oauth_callback.php';
} else {
    // En local, utiliser localhost
    $redirect_uri = 'http://localhost/tinatools/oauth_callback.php';
}
$client->setRedirectUri($redirect_uri);

// Vérifier si le code d'autorisation est présent dans l'URL
if (isset($_GET['code'])) {
    // Échanger le code d'autorisation contre un token d'accès
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    // Vérifier s'il y a des erreurs
    if (isset($token['error'])) {
        echo '<h1>Erreur d\'authentification</h1>';
        echo '<p>Une erreur s\'est produite lors de l\'authentification : ' . $token['error'] . '</p>';
        echo '<p>' . $token['error_description'] . '</p>';
        exit;
    }
    
    // Sauvegarder le token
    if ($isHeroku) {
        // Sur Heroku, sauvegarder le token dans la base de données
        saveTokenToDb('gmail', $token);
        
        // Afficher les instructions et le token pour référence
        $token_json = json_encode($token);
        echo '<!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Authentification Gmail réussie</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                    line-height: 1.6;
                }
                h1 {
                    color: #4285f4;
                }
                .success {
                    background-color: #d4edda;
                    color: #155724;
                    padding: 15px;
                    border-radius: 4px;
                    margin-bottom: 20px;
                }
                .code-box {
                    background-color: #f8f9fa;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    padding: 15px;
                    overflow-x: auto;
                    margin-bottom: 20px;
                }
                pre {
                    margin: 0;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                }
                .btn {
                    display: inline-block;
                    background-color: #4285f4;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 4px;
                    font-weight: bold;
                }
                .btn:hover {
                    background-color: #3367d6;
                }
            </style>
        </head>
        <body>
            <h1>Authentification Gmail réussie !</h1>
            <div class="success">
                <p>Le token d\'accès a été obtenu avec succès et sauvegardé dans la base de données.</p>
            </div>
            <h2>Token obtenu (pour référence) :</h2>
            <div class="code-box">
                <pre>' . htmlspecialchars($token_json) . '</pre>
            </div>
            <p>Le token a été automatiquement sauvegardé dans la base de données.</p>
            <a href="test_gmail_api.php" class="btn">Retour aux tests Gmail</a>
        </body>
        </html>';
    } else {
        // En local, sauvegarder dans la base de données et dans le fichier pour compatibilité
        saveTokenToDb('gmail', $token);
        file_put_contents('gmail_token.json', json_encode($token));
        
        // Afficher un message de succès
        echo '<!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Authentification Gmail réussie</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                    line-height: 1.6;
                }
                h1 {
                    color: #4285f4;
                }
                .success {
                    background-color: #d4edda;
                    color: #155724;
                    padding: 15px;
                    border-radius: 4px;
                    margin-bottom: 20px;
                }
                .btn {
                    display: inline-block;
                    background-color: #4285f4;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 4px;
                    font-weight: bold;
                }
                .btn:hover {
                    background-color: #3367d6;
                }
            </style>
        </head>
        <body>
            <h1>Authentification Gmail réussie !</h1>
            <div class="success">
                <p>Le token d\'accès a été obtenu avec succès et sauvegardé dans la base de données et dans un fichier local.</p>
            </div>
            <a href="test_gmail_api.php" class="btn">Retour aux tests Gmail</a>
        </body>
        </html>';
    }
} else {
    // Si le code d'autorisation n'est pas présent, afficher une erreur
    echo '<h1>Erreur</h1>';
    echo '<p>Aucun code d\'autorisation n\'a été reçu de Google.</p>';
    echo '<a href="gmail_auth.php">Réessayer l\'authentification</a>';
}
