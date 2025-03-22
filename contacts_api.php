<?php
/**
 * Point d'entrée API pour les fonctionnalités de contacts Gmail
 * 
 * Ce fichier sert de point d'entrée pour les appels API de Vapi.ai
 * concernant les contacts Gmail.
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée. Utilisez POST.'
    ]);
    exit;
}

// Récupérer les données de la requête
$input = json_decode(file_get_contents('php://input'), true);

// Vérifier si les données sont valides
if (!$input || !isset($input['action'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Données invalides. Le paramètre "action" est requis.'
    ]);
    exit;
}

// Récupérer l'action demandée
$action = $input['action'];

// Traiter l'action demandée
$result = null;

try {
    switch ($action) {
        case 'list':
            $pageSize = isset($input['pageSize']) ? intval($input['pageSize']) : 10;
            $pageToken = isset($input['pageToken']) ? $input['pageToken'] : null;
            $result = listContacts($pageSize, $pageToken);
            break;
            
        case 'search':
            $query = isset($input['query']) ? $input['query'] : '';
            if (empty($query)) {
                throw new Exception('Le paramètre "query" est requis pour l\'action "search".');
            }
            $result = searchContacts($query);
            break;
            
        case 'findByEmail':
            $email = isset($input['email']) ? $input['email'] : '';
            if (empty($email)) {
                throw new Exception('Le paramètre "email" est requis pour l\'action "findByEmail".');
            }
            $result = findContactByEmail($email);
            break;
            
        case 'findByName':
            $name = isset($input['name']) ? $input['name'] : '';
            if (empty($name)) {
                throw new Exception('Le paramètre "name" est requis pour l\'action "findByName".');
            }
            $result = findContactsByName($name);
            break;
            
        case 'getEmailFromName':
            $name = isset($input['name']) ? $input['name'] : '';
            if (empty($name)) {
                throw new Exception('Le paramètre "name" est requis pour l\'action "getEmailFromName".');
            }
            $result = getEmailFromName($name);
            break;
            
        default:
            throw new Exception('Action non reconnue : ' . $action);
    }
    
    // Renvoyer le résultat
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    
} catch (Exception $e) {
    // Gérer les erreurs
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
