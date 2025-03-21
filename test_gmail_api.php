<?php
/**
 * Script de test pour les fonctionnalités Gmail
 * 
 * Ce script simule des requêtes Vapi.ai pour tester les fonctionnalités Gmail
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// URL de l'API Gmail (locale ou sur Heroku)
$api_url = 'http://localhost/tinatools/gmail_api.php';
// Pour Heroku, utilisez : $api_url = 'https://tinatools-gdocs-8657da134f6d.herokuapp.com/gmail_api.php';

// Fonction pour envoyer une requête à l'API Gmail
function testGmailApi($functionName, $params) {
    global $api_url;
    
    // Créer une requête au format Vapi.ai
    $request = [
        'message' => [
            'toolCalls' => [
                [
                    'id' => 'call_' . uniqid(),
                    'function' => [
                        'name' => $functionName,
                        'arguments' => json_encode($params)
                    ]
                ]
            ]
        ]
    ];
    
    // Convertir la requête en JSON
    $json_request = json_encode($request);
    
    // Configurer cURL
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json_request)
    ]);
    
    // Exécuter la requête
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Afficher les résultats
    echo "<h2>Test de la fonction: $functionName</h2>";
    echo "<h3>Requête:</h3>";
    echo "<pre>" . htmlspecialchars($json_request) . "</pre>";
    
    echo "<h3>Réponse (HTTP $http_code):</h3>";
    if ($response) {
        $formatted_response = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Si la réponse contient un résultat JSON valide, le formater pour l'affichage
            if (isset($formatted_response['results'][0]['result'])) {
                $result = $formatted_response['results'][0]['result'];
                // Vérifier si le résultat est lui-même un JSON
                $decoded_result = json_decode($result, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $formatted_response['results'][0]['result'] = $decoded_result;
                }
            }
            echo "<pre>" . htmlspecialchars(json_encode($formatted_response, JSON_PRETTY_PRINT)) . "</pre>";
        } else {
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        }
    } else {
        echo "<p>Aucune réponse reçue</p>";
    }
    
    echo "<hr>";
}

// Afficher le formulaire de test
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de l'API Gmail</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2, h3 {
            color: #333;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .test-section {
            margin-bottom: 30px;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
        }
        button {
            background-color: #4285f4;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        button:hover {
            background-color: #3367d6;
        }
        input, textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .results {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>Test de l'API Gmail</h1>
    
    <div class="test-section">
        <h2>1. Rechercher des emails</h2>
        <form method="post" action="">
            <label for="search_query">Requête de recherche:</label>
            <input type="text" id="search_query" name="search_query" value="is:unread" required>
            
            <label for="search_max_results">Nombre maximum de résultats:</label>
            <input type="number" id="search_max_results" name="search_max_results" value="5" min="1" max="50">
            
            <button type="submit" name="test_search">Rechercher des emails</button>
        </form>
    </div>
    
    <div class="test-section">
        <h2>2. Lire un email</h2>
        <form method="post" action="">
            <label for="email_id">ID de l'email:</label>
            <input type="text" id="email_id" name="email_id" required>
            
            <button type="submit" name="test_read">Lire l'email</button>
        </form>
    </div>
    
    <div class="test-section">
        <h2>3. Créer un brouillon d'email</h2>
        <form method="post" action="">
            <label for="to">Destinataire:</label>
            <input type="email" id="to" name="to" required>
            
            <label for="subject">Sujet:</label>
            <input type="text" id="subject" name="subject" required>
            
            <label for="body">Corps du message:</label>
            <textarea id="body" name="body" rows="5" required></textarea>
            
            <label for="cc">CC (optionnel):</label>
            <input type="text" id="cc" name="cc">
            
            <label for="bcc">BCC (optionnel):</label>
            <input type="text" id="bcc" name="bcc">
            
            <button type="submit" name="test_draft">Créer un brouillon</button>
        </form>
    </div>
    
    <div class="results">
        <?php
        // Traiter les soumissions de formulaire
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['test_search'])) {
                // Test de recherche d'emails
                $params = [
                    'query' => $_POST['search_query'],
                    'maxResults' => (int)$_POST['search_max_results']
                ];
                testGmailApi('SearchEmails', $params);
            } elseif (isset($_POST['test_read'])) {
                // Test de lecture d'un email
                $params = [
                    'emailId' => $_POST['email_id']
                ];
                testGmailApi('ReadEmail', $params);
            } elseif (isset($_POST['test_draft'])) {
                // Test de création d'un brouillon
                $params = [
                    'to' => $_POST['to'],
                    'subject' => $_POST['subject'],
                    'body' => $_POST['body'],
                    'cc' => $_POST['cc'],
                    'bcc' => $_POST['bcc']
                ];
                testGmailApi('CreateDraft', $params);
            }
        }
        ?>
    </div>
</body>
</html>
