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

// Créer un client Google
$client = new Google\Client();
$client->setApplicationName('Tina Gmail Integration');

// Vérifier si nous sommes sur Heroku
$isHeroku = getenv('GOOGLE_APPLICATION_CREDENTIALS_JSON') ? true : false;

// Configurer les identifiants OAuth
if ($isHeroku) {
    // Sur Heroku, utiliser la variable d'environnement
    $credentials_json = getenv('GOOGLE_APPLICATION_CREDENTIALS_JSON');
    $credentials = json_decode($credentials_json, true);
    $client->setAuthConfig($credentials);
} else {
    // En local, utiliser le fichier de clé OAuth
    $client->setAuthConfig('client_secret_897210672149-bdk9e05vo6gmnvnqdv0572ebt5voobe0.apps.googleusercontent.com.json');
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
        // Sur Heroku, afficher les instructions pour mettre à jour la variable d'environnement
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
                    text-align: center;
                }
                .success {
                    background-color: #d4edda;
                    color: #155724;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
                .warning {
                    background-color: #fff3cd;
                    color: #856404;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
                .code-box {
                    background-color: #f8f9fa;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    padding: 15px;
                    text-align: left;
                    overflow-x: auto;
                    margin: 20px 0;
                }
                .btn {
                    display: inline-block;
                    background-color: #4285f4;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 20px;
                }
                .btn:hover {
                    background-color: #3367d6;
                }
            </style>
        </head>
        <body>
            <h1>Authentification Gmail réussie</h1>
            <div class="success">
                <p>Vous avez été authentifié avec succès auprès de Gmail.</p>
            </div>
            <div class="warning">
                <h2>Action requise</h2>
                <p>Vous devez mettre à jour la variable d\'environnement <strong>GMAIL_TOKEN_JSON</strong> sur Heroku avec le token ci-dessous :</p>
            </div>
            <div class="code-box">
                <pre>' . htmlspecialchars($token_json) . '</pre>
            </div>
            <p>Copiez ce token et mettez à jour la variable d\'environnement dans les paramètres de votre application Heroku.</p>
            <a href="test_gmail_api.php" class="btn">Retour aux tests Gmail</a>
        </body>
        </html>';
    } else {
        // En local, sauvegarder dans un fichier
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
                    text-align: center;
                }
                .success {
                    background-color: #d4edda;
                    color: #155724;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
                .btn {
                    display: inline-block;
                    background-color: #4285f4;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 20px;
                }
                .btn:hover {
                    background-color: #3367d6;
                }
            </style>
        </head>
        <body>
            <h1>Authentification Gmail réussie</h1>
            <div class="success">
                <p>Vous avez été authentifié avec succès auprès de Gmail.</p>
                <p>Les tokens d\'accès ont été sauvegardés et vous pouvez maintenant utiliser les fonctionnalités Gmail.</p>
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
