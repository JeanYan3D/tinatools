<?php
/**
 * Outils pour interagir avec l'API Gmail
 * 
 * Ce fichier contient des fonctions pour rechercher des emails,
 * lire le contenu des emails et créer des brouillons d'emails
 * en utilisant l'API Gmail de Google.
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure l'autoloader de Composer
require_once __DIR__ . '/vendor/autoload.php';

// Inclure le fichier de configuration de la base de données
require_once 'db_config.php';

// Fichier de log
$log_file = __DIR__ . '/gmail_tools.log';

// Fonction pour logger les requêtes et réponses
function logGmailRequest($request, $response) {
    global $log_file;
    $log_entry = date('Y-m-d H:i:s') . " - Requête: " . print_r($request, true) . "\n";
    $log_entry .= "Réponse: " . print_r($response, true) . "\n";
    $log_entry .= "------------------------------------------------\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Obtient un client Google authentifié avec les scopes Gmail
 * 
 * @return Google\Client Client Google authentifié
 */
function getGmailClient() {
    // Vérifier si nous sommes sur Heroku
    $isHeroku = getenv('GOOGLE_OAUTH_CREDENTIALS_JSON') ? true : false;
    
    // Récupérer les informations d'identification OAuth
    $credentials_path = 'credentials.json';
    if ($isHeroku) {
        $credentials_json = getenv('GOOGLE_OAUTH_CREDENTIALS_JSON');
    } else {
        $credentials_json = file_get_contents($credentials_path);
    }
    
    // Créer le client Google API
    $client = new Google\Client();
    $client->setApplicationName('Tina Gmail Integration');
    
    // Définir les scopes nécessaires
    $client->setScopes([
        Google\Service\Gmail::GMAIL_READONLY,
        Google\Service\Gmail::GMAIL_COMPOSE,
        Google\Service\Gmail::GMAIL_MODIFY,
        Google\Service\Drive::DRIVE_FILE
    ]);
    
    // Configuration des identifiants OAuth
    if ($isHeroku) {
        // Sur Heroku, utiliser la variable d'environnement pour les identifiants OAuth
        $credentials = json_decode($credentials_json, true);
        $client->setAuthConfig($credentials);
        
        // Définir l'URL de redirection pour Heroku
        $redirect_uri = 'https://tinatools-gdocs.herokuapp.com/oauth_callback.php';
        $client->setRedirectUri($redirect_uri);
    } else {
        // En local, utiliser les fichiers
        $client->setAuthConfig(__DIR__ . '/client_secret_897210672149-bdk9e05vo6gmnvnqdv0572ebt5voobe0.apps.googleusercontent.com.json');
        $redirect_uri = 'http://localhost/tinatools/oauth_callback.php';
        $client->setRedirectUri($redirect_uri);
    }
    
    // Charger le token d'accès précédemment sauvegardé
    $token_path = 'token.json';
    
    // Essayer d'abord de récupérer le token depuis la base de données
    $token = getTokenFromDb('gmail');
    
    if (!$token) {
        // Si le token n'existe pas dans la base de données
        if ($isHeroku) {
            // Sur Heroku, essayer la variable d'environnement
            $token_json = getenv('GMAIL_TOKEN_JSON');
            if ($token_json) {
                $token = json_decode($token_json, true);
                // Sauvegarder le token dans la base de données pour la prochaine fois
                saveTokenToDb('gmail', $token);
            }
        } else {
            // En local, essayer de récupérer le token depuis le fichier
            if (file_exists($token_path)) {
                $token = json_decode(file_get_contents($token_path), true);
                // Sauvegarder le token dans la base de données pour la prochaine fois
                saveTokenToDb('gmail', $token);
            }
        }
    }
    
    if (isset($token)) {
        $client->setAccessToken($token);
    }
    
    // Rafraîchir le token s'il est expiré
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            try {
                // Rafraîchir le token
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                
                // Sauvegarder le nouveau token
                $new_token = $client->getAccessToken();
                
                // Sauvegarder le token dans la base de données (que ce soit Heroku ou local)
                saveTokenToDb('gmail', $new_token);
                
                if ($isHeroku) {
                    // Ajouter un avertissement pour informer l'utilisateur que le token a été rafraîchi
                    $warning = "<!-- WARNING: Le token Gmail a été rafraîchi automatiquement. Le nouveau token a été sauvegardé dans la base de données. -->\n";
                    $_ENV['TOKEN_REFRESHED_WARNING'] = $warning;
                    
                    // Journaliser le rafraîchissement du token
                    error_log('Token Gmail rafraîchi et sauvegardé dans la base de données. Nouveau token: ' . json_encode($new_token));
                } else {
                    // En local, sauvegarder également le token dans le fichier pour compatibilité
                    file_put_contents($token_path, json_encode($new_token));
                    error_log('Token Gmail rafraîchi et sauvegardé dans la base de données et le fichier local.');
                }
            } catch (Exception $e) {
                // En cas d'erreur, journaliser l'erreur
                error_log('Erreur lors du rafraîchissement du token : ' . $e->getMessage());
                
                // Rediriger vers la page d'authentification
                header('Location: oauth.php');
                exit;
            }
        } else {
            // Rediriger vers la page d'authentification
            header('Location: oauth.php');
            exit;
        }
    }
    
    return $client;
}

/**
 * Recherche des emails dans Gmail en fonction d'une requête
 * 
 * @param string $query Requête de recherche Gmail (format: https://support.google.com/mail/answer/7190)
 * @param int $maxResults Nombre maximum de résultats à retourner
 * @return array Liste des emails correspondants avec leurs métadonnées
 */
function searchEmails($query, $maxResults = 10) {
    try {
        // Obtenir un client authentifié
        $client = getGmailClient();
        
        // Créer le service Gmail
        $service = new Google\Service\Gmail($client);
        
        // Paramètres de la requête
        $opt_params = [
            'maxResults' => $maxResults,
            'q' => $query
        ];
        
        // Exécuter la recherche
        $results = $service->users_messages->listUsersMessages('me', $opt_params);
        
        // Tableau pour stocker les résultats formatés
        $emails = [];
        
        // Si des messages ont été trouvés
        if (count($results->getMessages()) > 0) {
            foreach ($results->getMessages() as $message) {
                // Récupérer les détails du message
                $msg = $service->users_messages->get('me', $message->getId(), ['format' => 'metadata']);
                
                // Extraire les en-têtes importants
                $headers = $msg->getPayload()->getHeaders();
                $email = [
                    'id' => $message->getId(),
                    'threadId' => $message->getThreadId(),
                    'snippet' => $msg->getSnippet(),
                    'date' => '',
                    'from' => '',
                    'to' => '',
                    'subject' => ''
                ];
                
                // Parcourir les en-têtes pour extraire les informations
                foreach ($headers as $header) {
                    if ($header->getName() == 'Date') {
                        $email['date'] = $header->getValue();
                    } elseif ($header->getName() == 'From') {
                        $email['from'] = $header->getValue();
                    } elseif ($header->getName() == 'To') {
                        $email['to'] = $header->getValue();
                    } elseif ($header->getName() == 'Subject') {
                        $email['subject'] = $header->getValue();
                    }
                }
                
                $emails[] = $email;
            }
        }
        
        // Logger la requête et la réponse
        logGmailRequest($query, $emails);
        
        return $emails;
    } catch (Exception $e) {
        // En cas d'erreur, logger et retourner un tableau vide
        logGmailRequest($query, 'Erreur: ' . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Lit le contenu complet d'un email spécifique
 * 
 * @param string $emailId ID de l'email à lire
 * @return array Contenu complet de l'email
 */
function readEmail($emailId) {
    try {
        // Obtenir un client authentifié
        $client = getGmailClient();
        
        // Créer le service Gmail
        $service = new Google\Service\Gmail($client);
        
        // Récupérer le message complet
        $message = $service->users_messages->get('me', $emailId, ['format' => 'full']);
        
        // Extraire les en-têtes
        $headers = $message->getPayload()->getHeaders();
        $email = [
            'id' => $message->getId(),
            'threadId' => $message->getThreadId(),
            'labelIds' => $message->getLabelIds(),
            'snippet' => $message->getSnippet(),
            'historyId' => $message->getHistoryId(),
            'internalDate' => $message->getInternalDate(),
            'date' => '',
            'from' => '',
            'to' => '',
            'cc' => '',
            'bcc' => '',
            'subject' => '',
            'body' => [
                'plain' => '',
                'html' => ''
            ],
            'attachments' => []
        ];
        
        // Parcourir les en-têtes pour extraire les informations
        foreach ($headers as $header) {
            if ($header->getName() == 'Date') {
                $email['date'] = $header->getValue();
            } elseif ($header->getName() == 'From') {
                $email['from'] = $header->getValue();
            } elseif ($header->getName() == 'To') {
                $email['to'] = $header->getValue();
            } elseif ($header->getName() == 'Cc') {
                $email['cc'] = $header->getValue();
            } elseif ($header->getName() == 'Bcc') {
                $email['bcc'] = $header->getValue();
            } elseif ($header->getName() == 'Subject') {
                $email['subject'] = $header->getValue();
            }
        }
        
        // Fonction récursive pour extraire le corps du message et les pièces jointes
        function processMessageParts($part, &$email) {
            $mimeType = $part->getMimeType();
            
            if ($part->getParts()) {
                // Si la partie a des sous-parties, les traiter récursivement
                foreach ($part->getParts() as $subpart) {
                    processMessageParts($subpart, $email);
                }
            } else {
                // Si c'est une partie finale, extraire le contenu
                $body = $part->getBody();
                $data = $body->getData();
                
                if ($data) {
                    // Décoder les données
                    $decodedData = base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
                    
                    if ($mimeType == 'text/plain') {
                        $email['body']['plain'] = $decodedData;
                    } elseif ($mimeType == 'text/html') {
                        $email['body']['html'] = $decodedData;
                    } elseif ($body->getAttachmentId()) {
                        // C'est une pièce jointe
                        $email['attachments'][] = [
                            'id' => $body->getAttachmentId(),
                            'filename' => $part->getFilename(),
                            'mimeType' => $mimeType,
                            'size' => $body->getSize()
                        ];
                    }
                }
            }
        }
        
        // Traiter les parties du message
        if ($message->getPayload()->getParts()) {
            foreach ($message->getPayload()->getParts() as $part) {
                processMessageParts($part, $email);
            }
        } else {
            // Si le message n'a pas de parties, traiter directement le corps
            $body = $message->getPayload()->getBody();
            $data = $body->getData();
            
            if ($data) {
                $decodedData = base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
                $mimeType = $message->getPayload()->getMimeType();
                
                if ($mimeType == 'text/plain') {
                    $email['body']['plain'] = $decodedData;
                } elseif ($mimeType == 'text/html') {
                    $email['body']['html'] = $decodedData;
                }
            }
        }
        
        // Logger la requête et la réponse
        logGmailRequest('readEmail: ' . $emailId, $email);
        
        return $email;
    } catch (Exception $e) {
        // En cas d'erreur, logger et retourner un tableau avec l'erreur
        logGmailRequest('readEmail: ' . $emailId, 'Erreur: ' . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Crée un brouillon d'email
 * 
 * @param string $to Destinataire(s) de l'email
 * @param string $subject Sujet de l'email
 * @param string $body Corps du message
 * @param string $cc Destinataires en copie (optionnel)
 * @param string $bcc Destinataires en copie cachée (optionnel)
 * @return array Résultat de la création du brouillon
 */
function createDraft($to, $subject, $body, $cc = '', $bcc = '') {
    try {
        // Obtenir un client authentifié
        $client = getGmailClient();
        
        // Créer le service Gmail
        $service = new Google\Service\Gmail($client);
        
        // Créer le contenu de l'email
        $email = "To: $to\r\n";
        if ($cc) {
            $email .= "Cc: $cc\r\n";
        }
        if ($bcc) {
            $email .= "Bcc: $bcc\r\n";
        }
        $email .= "Subject: $subject\r\n";
        $email .= "MIME-Version: 1.0\r\n";
        $email .= "Content-Type: text/html; charset=UTF-8\r\n";
        $email .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $email .= $body;
        
        // Encoder l'email en base64
        $encodedEmail = rtrim(strtr(base64_encode($email), '+/', '-_'), '=');
        
        // Créer le message
        $message = new Google\Service\Gmail\Message();
        $message->setRaw($encodedEmail);
        
        // Créer le brouillon
        $draft = new Google\Service\Gmail\Draft();
        $draft->setMessage($message);
        
        // Sauvegarder le brouillon
        $result = $service->users_drafts->create('me', $draft);
        
        // Logger la requête et la réponse
        logGmailRequest('createDraft', $result);
        
        return [
            'success' => true,
            'draftId' => $result->getId(),
            'messageId' => $result->getMessage()->getId()
        ];
    } catch (Exception $e) {
        // En cas d'erreur, logger et retourner un tableau avec l'erreur
        logGmailRequest('createDraft', 'Erreur: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
