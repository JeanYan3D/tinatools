<?php
/**
 * Script minimal pour créer un document Google Docs à partir d'une requête Vapi.ai
 * 
 * Ce script reçoit une requête POST de Vapi.ai contenant un titre et un contenu,
 * puis crée un document Google Docs avec ces informations.
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure l'autoloader de Composer
require_once __DIR__ . '/vendor/autoload.php';

// Fichier de log
$log_file = __DIR__ . '/gdocs_creator.log';

// Fonction pour logger les requêtes et réponses
function logRequest($request, $response) {
    global $log_file;
    $log_entry = date('Y-m-d H:i:s') . " - Requête: " . print_r($request, true) . "\n";
    $log_entry .= "Réponse: " . print_r($response, true) . "\n";
    $log_entry .= "------------------------------------------------\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Configurer les en-têtes CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Gérer les requêtes OPTIONS (pre-flight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Récupérer les données brutes
$rawData = file_get_contents('php://input');
$requestData = json_decode($rawData, true);

// Pour le débogage, loguer la requête entrante
logRequest($requestData, null);

// Version ultra simplifiée pour extraire le titre et le contenu
$title = null;
$content = null;

// Essayer d'extraire directement depuis le JSON brut avec une expression régulière
if (preg_match('/"title":\s*"([^"]+)"/', $rawData, $titleMatches) && 
    preg_match('/"content":\s*"([^"]+)"/', $rawData, $contentMatches)) {
    $title = $titleMatches[1];
    $content = $contentMatches[1];
}

// Journaliser les valeurs extraites pour le débogage
file_put_contents($log_file, "Titre extrait (regex): " . ($title ?? "non trouvé") . "\n", FILE_APPEND);
file_put_contents($log_file, "Contenu extrait (regex): " . ($content ?? "non trouvé") . "\n", FILE_APPEND);

// Si l'extraction par regex a échoué, essayer avec la méthode JSON
if (!$title || !$content) {
    // Parcourir toute la structure JSON à la recherche de title et content
    function findTitleAndContent($data, &$title, &$content) {
        if (is_array($data)) {
            // Vérifier si ce nœud contient title et content
            if (isset($data['title']) && isset($data['content'])) {
                $title = $data['title'];
                $content = $data['content'];
                return true;
            }
            
            // Sinon, parcourir récursivement tous les éléments
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    if (findTitleAndContent($value, $title, $content)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    
    findTitleAndContent($requestData, $title, $content);
    
    file_put_contents($log_file, "Titre extrait (récursif): " . ($title ?? "non trouvé") . "\n", FILE_APPEND);
    file_put_contents($log_file, "Contenu extrait (récursif): " . ($content ?? "non trouvé") . "\n", FILE_APPEND);
}

// Vérifier que les paramètres nécessaires sont présents
if (!$title || !$content) {
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
        
        // Créer une permission pour l'email spécifié
        $userPermission = new Google\Service\Drive\Permission([
            'type' => 'user',
            'role' => 'writer',
            'emailAddress' => 'tehau.babois@gmail.com'
        ]);
        
        // Appliquer la permission au document
        $driveService->permissions->create($documentId, $userPermission, [
            'sendNotificationEmail' => false
        ]);
        
        // Créer une permission publique pour que le document soit accessible à tous
        $publicPermission = new Google\Service\Drive\Permission([
            'type' => 'anyone',
            'role' => 'writer', // Donner des droits d'écriture à tous
            'allowFileDiscovery' => true
        ]);
        
        // Appliquer la permission publique au document
        $driveService->permissions->create($documentId, $publicPermission);
        
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

// Fonction pour obtenir un client Google authentifié
function getClient() {
    $client = new Google\Client();
    $client->setApplicationName('Tina Google Docs Integration');
    
    // Ajouter tous les scopes nécessaires, y compris DRIVE pour les permissions
    $client->setScopes([
        Google\Service\Docs::DOCUMENTS,
        Google\Service\Drive::DRIVE,
        Google\Service\Drive::DRIVE_FILE
    ]);
    
    // Vérifier si nous sommes sur Heroku (variable d'environnement définie)
    if (getenv('GOOGLE_APPLICATION_CREDENTIALS_JSON')) {
        // Sur Heroku, utiliser la variable d'environnement
        $credentials_json = getenv('GOOGLE_APPLICATION_CREDENTIALS_JSON');
        $credentials = json_decode($credentials_json, true);
        $client->setAuthConfig($credentials);
    } else {
        // En local, utiliser le fichier de clé
        $client->setAuthConfig('tina-gdocs-service.json');
    }
    
    return $client;
}
