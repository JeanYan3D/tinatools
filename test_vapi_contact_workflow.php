<?php
/**
 * Script de test pour simuler le workflow complet de Vapi.ai
 * en envoyant une requête à contacts_api_vapi.php
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialiser les variables
$response = null;
$requestData = null;
$error = null;

// Traiter la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['vapi_request'])) {
    try {
        // Décoder la requête JSON
        $requestData = json_decode($_POST['vapi_request'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erreur de décodage JSON: " . json_last_error_msg());
        }
        
        // URL de contacts_api_vapi.php (locale)
        $url = 'http://localhost/tinatools/contacts_api_vapi.php';
        
        // Initialisation de cURL
        $ch = curl_init($url);
        
        // Configuration de la requête
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $_POST['vapi_request']); // Envoyer la requête brute
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        // Activer le débogage cURL
        $curl_log = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $curl_log);
        
        // Exécution de la requête
        $response = curl_exec($ch);
        
        // Récupérer les informations de débogage
        rewind($curl_log);
        $curl_debug = stream_get_contents($curl_log);
        
        // Vérifier s'il y a eu une erreur
        if ($response === false) {
            throw new Exception("Erreur cURL: " . curl_error($ch));
        }
        
        // Récupérer le code de statut HTTP
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Fermeture de la session cURL
        curl_close($ch);
        
        // Vérifier si la réponse est du JSON valide
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erreur de décodage de la réponse JSON: " . json_last_error_msg() . "\n\nRéponse brute:\n" . $response);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fonction pour afficher le JSON formaté
function prettyPrintJson($json) {
    if (is_string($json)) {
        $json = json_decode($json, true);
    }
    return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// Exemple de requête Vapi.ai
$exampleRequest = [
    "message" => [
        "tool_calls" => [
            [
                "function" => [
                    "name" => "AccessContacts",
                    "arguments" => json_encode([
                        "action" => "getEmailFromName",
                        "name" => "Dorothée"
                    ])
                ]
            ]
        ]
    ]
];

$exampleRequestJson = json_encode($exampleRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de l'API Contacts pour Vapi.ai</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .left-panel, .right-panel {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .left-panel {
            width: 48%;
        }
        .right-panel {
            width: 48%;
        }
        textarea {
            width: 100%;
            height: 300px;
            margin-bottom: 10px;
            font-family: monospace;
            padding: 10px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        pre {
            background-color: #f8f8f8;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .debug-info {
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
        .example-button {
            background-color: #2196F3;
            margin-right: 10px;
        }
        .example-button:hover {
            background-color: #0b7dda;
        }
    </style>
</head>
<body>
    <h1>Test de l'API Contacts pour Vapi.ai</h1>
    
    <div class="container">
        <div class="left-panel">
            <h2>Requête Vapi.ai</h2>
            <form method="post" action="">
                <button type="button" class="example-button" onclick="document.getElementById('vapi_request').value = JSON.stringify(<?php echo json_encode($exampleRequest); ?>, null, 2);">Utiliser exemple</button>
                <textarea id="vapi_request" name="vapi_request" placeholder="Collez ici votre requête JSON Vapi.ai..."><?php echo isset($_POST['vapi_request']) ? htmlspecialchars($_POST['vapi_request']) : ''; ?></textarea>
                <button type="submit">Envoyer la requête</button>
            </form>
        </div>
        
        <div class="right-panel">
            <h2>Réponse</h2>
            <?php if ($error): ?>
                <div class="error">Erreur:<br><?php echo nl2br(htmlspecialchars($error)); ?></div>
            <?php elseif ($response): ?>
                <div class="success">Requête envoyée avec succès!</div>
                <h3>Réponse JSON:</h3>
                <pre><?php echo htmlspecialchars(prettyPrintJson($response)); ?></pre>
                
                <?php if (isset($curl_debug)): ?>
                    <div class="debug-info">
                        <h3>Informations de débogage cURL:</h3>
                        <pre><?php echo htmlspecialchars($curl_debug); ?></pre>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
