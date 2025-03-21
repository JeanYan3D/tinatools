<?php
/**
 * Script de test pour simuler le workflow complet de Vapi.ai
 * en envoyant une requête à gdocs_creator.php
 */

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
        
        // URL de gdocs_creator.php (locale)
        $url = 'http://localhost/tinatools/gdocs_creator.php';
        
        // Initialisation de cURL
        $ch = curl_init($url);
        
        // Configuration de la requête
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        // Exécution de la requête
        $response = curl_exec($ch);
        
        // Vérification des erreurs cURL
        if (curl_errno($ch)) {
            throw new Exception("Erreur cURL: " . curl_error($ch));
        }
        
        // Fermeture de la connexion cURL
        curl_close($ch);
        
        // Décoder la réponse JSON
        $responseData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erreur de décodage de la réponse JSON: " . json_last_error_msg());
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Exemple de requête Vapi.ai
$exampleRequest = json_encode([
    'message' => [
        'timestamp' => time() * 1000,
        'type' => 'tool-calls',
        'tool_calls' => [
            [
                'id' => 'call_example123',
                'type' => 'function',
                'function' => [
                    'name' => 'CreateGoogleDoc',
                    'arguments' => [
                        'title' => 'Exemple de titre',
                        'content' => 'Exemple de contenu pour le document.'
                    ]
                ],
                'is_preceded_by_text' => true
            ]
        ],
        'tool_call_list' => [
            [
                'id' => 'call_example123',
                'type' => 'function',
                'function' => [
                    'name' => 'CreateGoogleDoc',
                    'arguments' => [
                        'title' => 'Exemple de titre',
                        'content' => 'Exemple de contenu pour le document.'
                    ]
                ],
                'is_preceded_by_text' => true
            ]
        ]
    ]
], JSON_PRETTY_PRINT);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test du workflow Vapi.ai</title>
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
            max-height: 400px;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        textarea {
            width: 100%;
            min-height: 200px;
            font-family: monospace;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover {
            background-color: #45a049;
        }
        .copy-btn {
            background-color: #2196F3;
            margin-left: 10px;
        }
        .copy-btn:hover {
            background-color: #0b7dda;
        }
        .highlight {
            background-color: #ffff99;
            padding: 2px;
        }
    </style>
</head>
<body>
    <h1>Test du workflow Vapi.ai complet</h1>
    
    <div class="section">
        <h2>Coller une requête Vapi.ai complète</h2>
        <form method="post" action="">
            <textarea name="vapi_request" placeholder="Collez ici la requête JSON complète de Vapi.ai"><?php echo isset($_POST['vapi_request']) ? htmlspecialchars($_POST['vapi_request']) : ''; ?></textarea>
            <div>
                <button type="submit">Envoyer à gdocs_creator.php</button>
                <button type="button" class="copy-btn" onclick="copyExample()">Copier l'exemple</button>
            </div>
        </form>
    </div>
    
    <?php if ($error): ?>
    <div class="section">
        <h2>Erreur</h2>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if ($response): ?>
    <div class="section">
        <h2>Réponse de gdocs_creator.php</h2>
        
        <?php 
        // Vérifier si la réponse contient un toolCallId non null
        $hasValidToolCallId = false;
        if (isset($responseData['results'][0]['toolCallId']) && $responseData['results'][0]['toolCallId'] !== null) {
            $hasValidToolCallId = true;
        } elseif (isset($responseData['results'][0]['tool_call_id']) && $responseData['results'][0]['tool_call_id'] !== null) {
            $hasValidToolCallId = true;
        }
        ?>
        
        <?php if ($hasValidToolCallId): ?>
        <p class="success">✓ La réponse contient un identifiant d'appel d'outil valide</p>
        <?php else: ?>
        <p class="error">✗ La réponse ne contient pas d'identifiant d'appel d'outil valide (toolCallId est null)</p>
        <?php endif; ?>
        
        <h3>Réponse JSON</h3>
        <pre><?php 
        // Mettre en évidence les identifiants dans la réponse
        $prettyResponse = json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $highlightedResponse = preg_replace('/"(toolCallId|tool_call_id)": (null|"[^"]*")/', '"$1": <span class="highlight">$2</span>', $prettyResponse);
        echo $highlightedResponse;
        ?></pre>
        
        <h3>Réponse brute</h3>
        <pre><?php echo htmlspecialchars($response); ?></pre>
    </div>
    <?php endif; ?>
    
    <?php if ($requestData): ?>
    <div class="section">
        <h2>Requête envoyée</h2>
        <pre><?php echo htmlspecialchars(json_encode($requestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
    </div>
    <?php endif; ?>
    
    <div class="section">
        <h2>Exemple de requête Vapi.ai</h2>
        <pre id="example-request"><?php echo htmlspecialchars($exampleRequest); ?></pre>
    </div>
    
    <script>
        function copyExample() {
            const exampleText = document.getElementById('example-request').textContent;
            const textarea = document.querySelector('textarea[name="vapi_request"]');
            textarea.value = exampleText;
            textarea.focus();
        }
    </script>
</body>
</html>
