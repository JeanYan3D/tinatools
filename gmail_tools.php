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
    $client = new Google\Client();
    $client->setApplicationName('Tina Gmail Integration');
    
    // Ajouter tous les scopes nécessaires pour Gmail
    $client->setScopes([
        Google\Service\Gmail::GMAIL_READONLY,  // Pour lire et rechercher des emails
        Google\Service\Gmail::GMAIL_COMPOSE,   // Pour créer des brouillons
        Google\Service\Docs::DOCUMENTS,        // Conserver les scopes existants
        Google\Service\Drive::DRIVE,
        Google\Service\Drive::DRIVE_FILE
    ]);
    
    // Vérifier si nous sommes sur Heroku (variable d'environnement définie)
    $isHeroku = getenv('GOOGLE_OAUTH_CREDENTIALS_JSON') ? true : false;
    
    // Configuration des identifiants OAuth
    if ($isHeroku) {
        // Sur Heroku, utiliser la variable d'environnement pour les identifiants OAuth
        $credentials_json = getenv('GOOGLE_OAUTH_CREDENTIALS_JSON');
        $credentials = json_decode($credentials_json, true);
        $client->setAuthConfig($credentials);
        
        // Définir l'URL de redirection pour Heroku
        $redirect_uri = 'https://tinatools-gdocs-8657da134f6d.herokuapp.com/oauth_callback.php';
        $client->setRedirectUri($redirect_uri);
        
        // Récupérer le token depuis la variable d'environnement
        $token_json = getenv('GMAIL_TOKEN_JSON');
        if ($token_json) {
            $token = json_decode($token_json, true);
            $client->setAccessToken($token);
        }
    } else {
        // En local, utiliser les fichiers
        $client->setAuthConfig('client_secret_897210672149-bdk9e05vo6gmnvnqdv0572ebt5voobe0.apps.googleusercontent.com.json');
        $redirect_uri = 'http://localhost/tinatools/oauth_callback.php';
        $client->setRedirectUri($redirect_uri);
        
        // Charger le token depuis le fichier local
        if (file_exists('gmail_token.json')) {
            $token_json = file_get_contents('gmail_token.json');
            $token = json_decode($token_json, true);
            $client->setAccessToken($token);
        }
    }
    
    // Vérifier si le token est expiré
    if ($client->isAccessTokenExpired()) {
        // Si nous avons un refresh token, on l'utilise pour obtenir un nouveau token
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            
            // Sauvegarder le nouveau token
            if ($isHeroku) {
                // Sur Heroku, on ne peut pas sauvegarder directement, on affiche un message
                echo '<div class="warning">
                    <h2>Token expiré</h2>
                    <p>Le token d\'accès a expiré et a été renouvelé. Vous devez mettre à jour la variable d\'environnement GMAIL_TOKEN_JSON sur Heroku avec le nouveau token :</p>
                    <pre>' . json_encode($client->getAccessToken()) . '</pre>
                </div>';
            } else {
                // En local, on sauvegarde dans le fichier
                file_put_contents('gmail_token.json', json_encode($client->getAccessToken()));
            }
        } else {
            // Si nous n'avons pas de refresh token, on redirige vers l'authentification
            header('Location: gmail_auth.php');
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
