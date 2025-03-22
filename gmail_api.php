<?php
/**
 * Point d'entrée API pour les fonctionnalités Gmail via Vapi.ai
 * 
 * Ce script reçoit des requêtes de Vapi.ai pour rechercher des emails,
 * lire des emails ou créer des brouillons d'emails.
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure l'autoloader de Composer et les fonctions Gmail
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/gmail_tools.php';

// Configurer les en-têtes CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Gérer les requêtes OPTIONS (pre-flight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Créer un dossier de logs s'il n'existe pas
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}

// Créer un nom de fichier unique basé sur la date et l'heure
$timestamp = date('Y-m-d_H-i-s');
$request_log_file = $log_dir . '/gmail_request_' . $timestamp . '.json';

// Extraire les données de la requête
$rawData = file_get_contents('php://input');
$requestData = json_decode($rawData, true);

// Enregistrer la requête brute complète
file_put_contents($request_log_file, $rawData);

// Extraire l'ID de l'appel d'outil (toolCallId) s'il existe
$toolCallId = null;
$debugInfo = [];

// Enregistrer la structure complète de la requête pour le débogage
$debugInfo['request_structure'] = json_encode($requestData);

if (isset($requestData['message']['toolCalls'][0]['id'])) {
    $toolCallId = $requestData['message']['toolCalls'][0]['id'];
    $debugInfo['extraction_method'] = 'message.toolCalls[0].id';
} elseif (isset($requestData['message']['tool_calls'][0]['id'])) {
    $toolCallId = $requestData['message']['tool_calls'][0]['id'];
    $debugInfo['extraction_method'] = 'message.tool_calls[0].id';
} elseif (isset($requestData['message']['tool_call_list'][0]['id'])) {
    $toolCallId = $requestData['message']['tool_call_list'][0]['id'];
    $debugInfo['extraction_method'] = 'message.tool_call_list[0].id';
} elseif (isset($requestData['message']['tool_with_tool_call_list'][0]['tool_call']['id'])) {
    $toolCallId = $requestData['message']['tool_with_tool_call_list'][0]['tool_call']['id'];
    $debugInfo['extraction_method'] = 'message.tool_with_tool_call_list[0].tool_call.id';
} elseif (isset($requestData['id'])) {
    // Format direct
    $toolCallId = $requestData['id'];
    $debugInfo['extraction_method'] = 'id';
} else {
    $debugInfo['extraction_method'] = 'none';
    $debugInfo['extraction_failed'] = true;
}

// Enregistrer les informations de débogage
$debugInfo['extracted_id'] = $toolCallId;
file_put_contents('gmail_debug_extraction.log', date('Y-m-d H:i:s') . ' - ' . json_encode($debugInfo) . "\n", FILE_APPEND);

// Fichier de log pour Gmail
$log_file = 'gmail_api.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Requête reçue\n", FILE_APPEND);
file_put_contents($log_file, "Tool Call ID: " . ($toolCallId ?? "non trouvé") . "\n", FILE_APPEND);

// Pour le débogage, loguer la requête entrante
logGmailRequest($requestData, null);

// Déterminer quelle fonction Gmail appeler
$function = null;
$params = [];

// Fonction pour extraire les paramètres de la fonction à partir de la requête
function extractFunctionAndParams($requestData) {
    global $function, $params;
    
    // Vérifier si la fonction et les paramètres sont dans le format toolCalls
    if (isset($requestData['message']['toolCalls'][0]['function']['name'])) {
        $function = $requestData['message']['toolCalls'][0]['function']['name'];
        if (isset($requestData['message']['toolCalls'][0]['function']['arguments'])) {
            // Vérifier si arguments est déjà un tableau ou un objet
            if (is_array($requestData['message']['toolCalls'][0]['function']['arguments'])) {
                $params = $requestData['message']['toolCalls'][0]['function']['arguments'];
            } else {
                $params = json_decode($requestData['message']['toolCalls'][0]['function']['arguments'], true);
            }
        }
        return true;
    }
    
    // Vérifier si la fonction et les paramètres sont dans le format tool_calls
    if (isset($requestData['message']['tool_calls'][0]['function']['name'])) {
        $function = $requestData['message']['tool_calls'][0]['function']['name'];
        if (isset($requestData['message']['tool_calls'][0]['function']['arguments'])) {
            // Vérifier si arguments est déjà un tableau ou un objet
            if (is_array($requestData['message']['tool_calls'][0]['function']['arguments'])) {
                $params = $requestData['message']['tool_calls'][0]['function']['arguments'];
            } else {
                $params = json_decode($requestData['message']['tool_calls'][0]['function']['arguments'], true);
            }
        }
        return true;
    }
    
    // Vérifier si la fonction et les paramètres sont dans le format direct
    if (isset($requestData['function']) && isset($requestData['params'])) {
        $function = $requestData['function'];
        $params = $requestData['params'];
        return true;
    }
    
    // Parcourir récursivement la structure pour trouver la fonction et les paramètres
    function findFunctionAndParams($data) {
        global $function, $params;
        
        if (is_array($data)) {
            // Vérifier si ce nœud contient function et params/arguments
            if (isset($data['function']) && (isset($data['params']) || isset($data['arguments']))) {
                $function = $data['function'];
                if (isset($data['params'])) {
                    $params = $data['params'];
                } else {
                    // Vérifier si arguments est déjà un tableau ou un objet
                    if (is_array($data['arguments'])) {
                        $params = $data['arguments'];
                    } else {
                        $params = json_decode($data['arguments'], true);
                    }
                }
                return true;
            }
            
            // Sinon, parcourir récursivement tous les éléments
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    if (findFunctionAndParams($value)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    
    return findFunctionAndParams($requestData);
}

// Extraire la fonction et les paramètres
$extractionSuccess = extractFunctionAndParams($requestData);

// Journaliser la fonction et les paramètres extraits
file_put_contents($log_file, "Fonction extraite: " . ($function ?? "non trouvée") . "\n", FILE_APPEND);
file_put_contents($log_file, "Paramètres extraits: " . print_r($params, true) . "\n", FILE_APPEND);

// Vérifier si l'extraction a réussi
if (!$extractionSuccess || !$function) {
    $response = [
        'results' => [
            [
                'toolCallId' => $toolCallId,
                'tool_call_id' => $toolCallId,
                'result' => 'Erreur: Impossible d\'extraire la fonction et les paramètres'
            ]
        ]
    ];
    
    logGmailRequest($requestData, $response);
    echo json_encode($response);
    exit;
}

// Exécuter la fonction appropriée
try {
    $result = null;
    
    switch ($function) {
        case 'SearchEmails':
            // Vérifier que les paramètres requis sont présents
            if (!isset($params['query'])) {
                throw new Exception('Le paramètre "query" est requis pour la recherche d\'emails');
            }
            
            $maxResults = isset($params['maxResults']) ? (int)$params['maxResults'] : 10;
            $result = searchEmails($params['query'], $maxResults);
            break;
            
        case 'ReadEmail':
            // Vérifier que les paramètres requis sont présents
            if (!isset($params['emailId'])) {
                throw new Exception('Le paramètre "emailId" est requis pour lire un email');
            }
            
            $result = readEmail($params['emailId']);
            break;
            
        case 'CreateDraft':
            // Vérifier que les paramètres requis sont présents
            if (!isset($params['to']) || !isset($params['subject']) || !isset($params['body'])) {
                throw new Exception('Les paramètres "to", "subject" et "body" sont requis pour créer un brouillon');
            }
            
            $cc = isset($params['cc']) ? $params['cc'] : '';
            $bcc = isset($params['bcc']) ? $params['bcc'] : '';
            $result = createDraft($params['to'], $params['subject'], $params['body'], $cc, $bcc);
            break;
            
        default:
            throw new Exception('Fonction non reconnue: ' . $function);
    }
    
    // Répondre avec succès dans le format attendu par Vapi.ai
    $response = [
        'results' => [
            [
                'toolCallId' => $toolCallId,
                'tool_call_id' => $toolCallId,
                'result' => json_encode($result)
            ]
        ]
    ];
    
    logGmailRequest($requestData, $response);
    echo json_encode($response);
} catch (Exception $e) {
    // Gérer les erreurs dans le format attendu par Vapi.ai
    $response = [
        'results' => [
            [
                'toolCallId' => $toolCallId,
                'tool_call_id' => $toolCallId,
                'result' => 'Erreur: ' . $e->getMessage()
            ]
        ]
    ];
    
    logGmailRequest($requestData, $response);
    echo json_encode($response);
}
