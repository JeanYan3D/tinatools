<?php
/**
 * Script minimal pour créer un document Google Docs à partir d'une requête Vapi.ai
 * 
 * Ce script reçoit une requête POST de Vapi.ai contenant un titre et un contenu,
 * puis crée un document Google Docs avec ces informations.
 */

// Autoriser les requêtes cross-origin (CORS) - Version PHP
// Ces en-têtes fonctionnent même sans mod_headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (pre-flight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Répondre avec un statut 200 OK sans contenu
    http_response_code(200);
    exit;
}

// Définir le type de contenu de la réponse
header('Content-Type: application/json');

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

// Vérifier que les données nécessaires sont présentes
if (!isset($requestData['title']) || !isset($requestData['content'])) {
    $response = [
        'success' => false,
        'message' => 'Erreur: Titre et contenu requis'
    ];
    
    logRequest($requestData, $response);
    echo json_encode($response);
    exit;
}

try {
    // Créer un client Google authentifié
    $client = getClient();
    $service = new Google\Service\Docs($client);
    
    // Créer un nouveau document
    $document = new Google\Service\Docs\Document([
        'title' => $requestData['title']
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
                'text' => $requestData['content']
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
        'message' => 'Erreur: ' . $e->getMessage(),
        'error_details' => $e->getTraceAsString()
    ];
    
    // Afficher l'erreur dans la console du navigateur pour faciliter le débogage
    error_log('Erreur Google Docs API: ' . $e->getMessage());
    
    logRequest($requestData, $response);
    echo json_encode($response);
}
