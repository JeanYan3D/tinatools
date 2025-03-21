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

// Configurer les identifiants OAuth
$client->setAuthConfig('client_secret_897210672149-bdk9e05vo6gmnvnqdv0572ebt5voobe0.apps.googleusercontent.com.json');

// Définir l'URL de redirection
$redirect_uri = 'http://localhost/tinatools/oauth_callback.php';
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
    
    // Sauvegarder le token dans un fichier
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
} else {
    // Si le code d'autorisation n'est pas présent, afficher une erreur
    echo '<h1>Erreur</h1>';
    echo '<p>Aucun code d\'autorisation n\'a été reçu de Google.</p>';
    echo '<a href="gmail_auth.php">Réessayer l\'authentification</a>';
}
