<?php
/**
 * Script minimal pour créer un document Google Docs à partir d'une requête Vapi.ai
 * 
 * Ce script reçoit une requête POST de Vapi.ai contenant un titre et un contenu,
 * puis crée un document Google Docs avec ces informations.
 */

// Journaliser toutes les requêtes pour le débogage
$log_file = __DIR__ . '/request_log.txt';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Nouvelle requête reçue\n", FILE_APPEND);
file_put_contents($log_file, "Méthode: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
file_put_contents($log_file, "Headers: " . json_encode(getallheaders()) . "\n", FILE_APPEND);
file_put_contents($log_file, "Input: " . file_get_contents('php://input') . "\n\n", FILE_APPEND);

// Autoriser les requêtes cross-origin (CORS) - Version PHP très permissive
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400'); // 24 heures

// Gérer les requêtes OPTIONS (pre-flight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Répondre avec un statut 200 OK sans contenu
    http_response_code(200);
    exit;
}

// Définir le type de contenu de la réponse
header('Content-Type: application/json');

// Journaliser les informations de requête pour le débogage
$request_data = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'get' => $_GET,
    'post' => $_POST,
    'raw_input' => file_get_contents('php://input')
];

// Si c'est une requête GET, afficher une page de test/diagnostic
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'status' => 'ok',
        'message' => 'Le service Google Docs Creator est en ligne',
        'debug_info' => $request_data,
        'instructions' => 'Envoyez une requête POST avec les paramètres "title" et "content" pour créer un document',
        'test_curl' => 'curl -X POST -H "Content-Type: application/json" -d \'{"title":"Test Document","content":"Contenu de test"}\' ' . 
                      (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
    ], JSON_PRETTY_PRINT);
    exit;
}

// Inclure la bibliothèque Google API Client
require_once __DIR__ . '/vendor/autoload.php';

// Fonction pour enregistrer les logs
function logRequest($request, $response) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'request' => $request,
        'response' => $response
    ];
    
    file_put_contents(__DIR__ . '/api_logs.txt', json_encode($logData) . "\n", FILE_APPEND);
}

// Fonction pour créer un client Google authentifié
function getClient() {
    $client = new Google\Client();
    $client->setApplicationName('Tina Google Docs Integration');
    $client->setScopes([
        \Google\Service\Docs::DOCUMENTS,
        \Google\Service\Drive::DRIVE_FILE // Ajout du scope Drive pour les permissions
    ]);
    
    // Vérifier si nous sommes sur Heroku (variable d'environnement présente)
    if (getenv('GOOGLE_APPLICATION_CREDENTIALS_JSON')) {
        // Utiliser la variable d'environnement
        $credentials_json = getenv('GOOGLE_APPLICATION_CREDENTIALS_JSON');
        
        // Créer un fichier temporaire avec les informations d'identification
        $temp_file = tempnam(sys_get_temp_dir(), 'google_credentials');
        file_put_contents($temp_file, $credentials_json);
        
        // Utiliser ce fichier temporaire pour l'authentification
        $client->setAuthConfig($temp_file);
        
        // Supprimer le fichier temporaire après utilisation
        register_shutdown_function(function() use ($temp_file) {
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
        });
    } else {
        // Utiliser le fichier JSON du compte de service (pour le développement local)
        $client->setAuthConfig(__DIR__ . '/tina-gdocs-service.json');
    }
    
    return $client;
}

// Récupérer les données envoyées par Vapi.ai
$requestData = json_decode(file_get_contents('php://input'), true);

// Pour le débogage, loguer la requête entrante
logRequest($requestData, null);

// Essayer d'extraire le titre de la requête
$title = null;
$content = "Ceci est un document test créé automatiquement pour vérifier l'intégration entre Vapi.ai et Google Docs.";

// Format direct : {"title": "...", "content": "..."}
if (isset($requestData['title'])) {
    $title = $requestData['title'];
} 
// Format Vapi.ai complet
else if (isset($requestData['message']) && isset($requestData['message']['tool_calls']) && is_array($requestData['message']['tool_calls'])) {
    foreach ($requestData['message']['tool_calls'] as $toolCall) {
        if (isset($toolCall['function'])) {
            // Vérifier si les arguments sont directement accessibles comme un tableau
            if (isset($toolCall['function']['arguments']) && is_array($toolCall['function']['arguments']) && isset($toolCall['function']['arguments']['title'])) {
                $title = $toolCall['function']['arguments']['title'];
                break;
            }
            // Vérifier si les arguments sont une chaîne JSON à décoder
            else if (isset($toolCall['function']['arguments']) && is_string($toolCall['function']['arguments'])) {
                $args = json_decode($toolCall['function']['arguments'], true);
                if (isset($args['title'])) {
                    $title = $args['title'];
                    break;
                }
            }
        }
    }
}

// Si aucun titre n'a été trouvé, utiliser un titre par défaut
if (!$title) {
    $title = "Document test créé le " . date('Y-m-d H:i:s');
}

// Journaliser les valeurs pour le débogage
file_put_contents($log_file, "Titre extrait ou par défaut: " . $title . "\n", FILE_APPEND);
file_put_contents($log_file, "Contenu par défaut: " . $content . "\n", FILE_APPEND);
file_put_contents($log_file, "Structure de la requête originale: " . print_r($requestData, true) . "\n", FILE_APPEND);

try {
    // Créer un client Google authentifié
    $client = getClient();
    $service = new Google\Service\Docs($client);
    
    // Créer un nouveau document
    $document = new Google\Service\Docs\Document([
        'title' => $title
    ]);
    
    $document = $service->documents->create($document);
    $documentId = $document->getDocumentId();
    
    // Ajouter du contenu au document
    $requests = [
        new Google\Service\Docs\Request([
            'insertText' => [
                'location' => [
                    'index' => 1
                ],
                'text' => $content
            ]
        ])
    ];
    
    $batchUpdateRequest = new Google\Service\Docs\BatchUpdateDocumentRequest([
        'requests' => $requests
    ]);
    
    $service->documents->batchUpdate($documentId, $batchUpdateRequest);
    
    // Partager le document avec l'utilisateur spécifié
    try {
        // Créer un service Drive
        $driveService = new Google\Service\Drive($client);
        
        // Créer une permission pour l'email spécifié (propriétaire)
        $ownerPermission = new Google\Service\Drive\Permission([
            'type' => 'user',
            'role' => 'writer',
            'emailAddress' => 'tehau.babois@gmail.com'
        ]);
        
        // Appliquer la permission au document
        $driveService->permissions->create($documentId, $ownerPermission, [
            'sendNotificationEmail' => false
        ]);
        
        // Créer également une permission pour que le document soit accessible via le lien
        $anyonePermission = new Google\Service\Drive\Permission([
            'type' => 'anyone',
            'role' => 'reader',
            'allowFileDiscovery' => false
        ]);
        
        // Appliquer la permission au document
        $driveService->permissions->create($documentId, $anyonePermission);
        
    } catch (Exception $e) {
        // Si le partage échoue, on continue quand même (le document est créé)
        error_log('Erreur lors du partage du document: ' . $e->getMessage());
    }
    
    // Répondre avec succès
    $response = [
        'success' => true,
        'message' => 'Document créé avec succès',
        'documentId' => $documentId,
        'documentUrl' => "https://docs.google.com/document/d/{$documentId}/edit"
    ];
    
    logRequest($requestData, $response);
    echo json_encode($response);
} catch (Exception $e) {
    // Gérer les erreurs
    $response = [
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ];
    
    logRequest($requestData, $response);
    echo json_encode($response);
}
