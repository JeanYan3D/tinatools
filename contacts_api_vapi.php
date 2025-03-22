<?php
/**
 * Point d'entrée API pour les fonctionnalités de contacts Gmail
 * Version spécifique pour Vapi.ai sans commentaire HTML
 */

// Activer l'affichage des erreurs pour le débogage (mais ne pas les afficher dans la réponse)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Démarrer la mise en tampon de sortie pour éviter tout texte avant le JSON
ob_start();

// Inclure les fichiers nécessaires
require_once __DIR__ . '/contacts_tools.php';

// Autoriser les requêtes CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Si c'est une requête OPTIONS (pre-flight), renvoyer juste les en-têtes
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier si la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Nettoyer la sortie tampon
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée. Utilisez POST.'
    ]);
    exit;
}

// Récupérer les données de la requête
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// Journaliser la requête brute pour le débogage
file_put_contents('contacts_api_vapi.log', date('Y-m-d H:i:s') . " - Requête brute: " . $rawInput . "\n", FILE_APPEND);
file_put_contents('contacts_api_vapi.log', date('Y-m-d H:i:s') . " - Requête décodée: " . print_r($input, true) . "\n", FILE_APPEND);

// Extraire les paramètres de la structure complexe de Vapi.ai
$params = [];
$toolCallId = null;

// Vérifier si nous avons une structure Vapi.ai
if (isset($input['message']) && isset($input['message']['tool_calls'])) {
    // Structure complète de Vapi
    $toolCall = $input['message']['tool_calls'][0];
    $toolCallId = isset($toolCall['id']) ? $toolCall['id'] : null;
    if (isset($toolCall['function']['arguments'])) {
        // Les arguments peuvent être une chaîne JSON ou un objet déjà décodé
        if (is_string($toolCall['function']['arguments'])) {
            $params = json_decode($toolCall['function']['arguments'], true);
        } else {
            $params = $toolCall['function']['arguments'];
        }
    }
} elseif (isset($input['message']) && isset($input['message']['toolCalls'])) {
    // Structure alternative avec toolCalls (C majuscule)
    $toolCall = $input['message']['toolCalls'][0];
    $toolCallId = isset($toolCall['id']) ? $toolCall['id'] : null;
    if (isset($toolCall['function']['arguments'])) {
        if (is_string($toolCall['function']['arguments'])) {
            $params = json_decode($toolCall['function']['arguments'], true);
        } else {
            $params = $toolCall['function']['arguments'];
        }
    }
} elseif (isset($input['tool_calls'])) {
    // Autre format possible de Vapi
    $toolCall = $input['tool_calls'][0];
    $toolCallId = isset($toolCall['id']) ? $toolCall['id'] : null;
    if (isset($toolCall['function']['arguments'])) {
        if (is_string($toolCall['function']['arguments'])) {
            $params = json_decode($toolCall['function']['arguments'], true);
        } else {
            $params = $toolCall['function']['arguments'];
        }
    }
} elseif (isset($input['action']) || isset($input['Action'])) {
    // Format simple pour les tests directs
    $params = $input;
    $toolCallId = 'test_' . uniqid();
}

// Journaliser les paramètres extraits
file_put_contents('contacts_api_vapi.log', date('Y-m-d H:i:s') . " - Paramètres extraits: " . print_r($params, true) . "\n", FILE_APPEND);
file_put_contents('contacts_api_vapi.log', date('Y-m-d H:i:s') . " - Tool Call ID: " . $toolCallId . "\n", FILE_APPEND);

// Normaliser les clés pour gérer différentes casses (action/Action, name/Name, etc.)
$normalizedParams = [];
if (is_array($params)) {
    foreach ($params as $key => $value) {
        $normalizedParams[strtolower($key)] = $value;
    }
}

// Journaliser les paramètres normalisés
file_put_contents('contacts_api_vapi.log', date('Y-m-d H:i:s') . " - Paramètres normalisés: " . print_r($normalizedParams, true) . "\n", FILE_APPEND);

// Vérifier si les données sont valides
if (empty($normalizedParams) || !isset($normalizedParams['action'])) {
    // Nettoyer la sortie tampon
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Données invalides. Le paramètre "action" est requis.'
    ]);
    exit;
}

// Récupérer l'action demandée
$action = strtolower($normalizedParams['action']);

// Traiter l'action demandée
$result = null;

try {
    switch ($action) {
        case 'list':
            $pageSize = isset($normalizedParams['pagesize']) ? intval($normalizedParams['pagesize']) : 
                       (isset($normalizedParams['page_size']) ? intval($normalizedParams['page_size']) : 10);
            $pageToken = isset($normalizedParams['pagetoken']) ? $normalizedParams['pagetoken'] : null;
            $result = listContacts($pageSize, $pageToken);
            break;
            
        case 'search':
            $query = isset($normalizedParams['query']) ? $normalizedParams['query'] : '';
            if (empty($query)) {
                throw new Exception('Le paramètre "query" est requis pour l\'action "search".');
            }
            $result = searchContacts($query);
            break;
            
        case 'findbyemail':
            $email = isset($normalizedParams['email']) ? $normalizedParams['email'] : '';
            if (empty($email)) {
                throw new Exception('Le paramètre "email" est requis pour l\'action "findByEmail".');
            }
            $result = findContactByEmail($email);
            break;
            
        case 'findbyname':
            $name = isset($normalizedParams['name']) ? $normalizedParams['name'] : '';
            if (empty($name)) {
                throw new Exception('Le paramètre "name" est requis pour l\'action "findByName".');
            }
            $result = findContactsByName($name);
            break;
            
        case 'getemailfromname':
            $name = isset($normalizedParams['name']) ? $normalizedParams['name'] : '';
            if (empty($name)) {
                throw new Exception('Le paramètre "name" est requis pour l\'action "getEmailFromName".');
            }
            $result = getEmailFromName($name);
            break;
            
        default:
            throw new Exception('Action non reconnue : ' . $action);
    }
    
    // Nettoyer la sortie tampon
    ob_clean();
    
    // Préparer la réponse au format attendu par Vapi.ai
    $response = [

        'results' => [
            [
                'tool_call_id' => $toolCallId,
                'data' => $result
            ]
        ]
    ];
    
    // Renvoyer le résultat
    echo json_encode($response);
    
} catch (Exception $e) {
    // Nettoyer la sortie tampon
    ob_clean();
    
    // Gérer les erreurs au format attendu par Vapi.ai
    $response = [
        'results' => [
            [
                'tool_call_id' => $toolCallId,
                'error' => $e->getMessage()
            ]
        ]
    ];
    
    // Renvoyer l'erreur
    echo json_encode($response);
}

// Journaliser la réponse pour le débogage
file_put_contents('contacts_api_vapi.log', date('Y-m-d H:i:s') . " - Réponse envoyée: " . json_encode(['success' => isset($result), 'tool_call_id' => $toolCallId, 'data' => $result ?? $e->getMessage()]) . "\n", FILE_APPEND);
