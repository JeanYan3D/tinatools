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
    $isHeroku = getenv('GOOGLE_APPLICATION_CREDENTIALS_JSON') ? true : false;
    
    // Configuration des identifiants OAuth
    if ($isHeroku) {
        // Sur Heroku, utiliser la variable d'environnement
        $credentials_json = getenv('GOOGLE_APPLICATION_CREDENTIALS_JSON');
        $credentials = json_decode($credentials_json, true);
        $client->setAuthConfig($credentials);
    } else {
        // En local, utiliser le fichier de clé OAuth
        $client->setAuthConfig('client_secret_897210672149-bdk9e05vo6gmnvnqdv0572ebt5voobe0.apps.googleusercontent.com.json');
    }
    
    // Gestion du token d'accès
    if ($isHeroku) {
        // Sur Heroku, utiliser la variable d'environnement GMAIL_TOKEN_JSON
        if (getenv('GMAIL_TOKEN_JSON')) {
            $accessToken = json_decode(getenv('GMAIL_TOKEN_JSON'), true);
            $client->setAccessToken($accessToken);
            
            // Rafraîchir le token s'il est expiré
            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    
                    // Note: Sur Heroku, nous ne pouvons pas mettre à jour automatiquement 
                    // la variable d'environnement. Un message de log sera ajouté pour 
                    // indiquer qu'une mise à jour manuelle est nécessaire.
                    error_log('ATTENTION: Le token Gmail a été rafraîchi. Vous devez mettre à jour manuellement la variable d\'environnement GMAIL_TOKEN_JSON sur Heroku avec le nouveau token.');
                    error_log('Nouveau token: ' . json_encode($client->getAccessToken()));
                } else {
                    // Erreur critique - pas de refresh token
                    error_log('ERREUR: Pas de refresh token disponible pour Gmail. Réauthentification nécessaire.');
                    
                    // En production, nous ne pouvons pas rediriger vers l'authentification
                    // car cela perturberait l'API. Nous retournons une erreur à la place.
                    throw new Exception('Authentification Gmail expirée. Veuillez réauthentifier l\'application.');
                }
            }
        } else {
            // Pas de token disponible sur Heroku
            error_log('ERREUR: Variable d\'environnement GMAIL_TOKEN_JSON non définie sur Heroku.');
            throw new Exception('Configuration Gmail incomplète. Variable d\'environnement GMAIL_TOKEN_JSON manquante.');
        }
    } else {
        // En local, utiliser le fichier
        $tokenPath = __DIR__ . '/gmail_token.json';
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
            
            // Rafraîchir le token s'il est expiré
            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    file_put_contents($tokenPath, json_encode($client->getAccessToken()));
                } else {
                    // Si pas de refresh token, rediriger vers l'authentification
                    header('Location: gmail_auth.php');
                    exit;
                }
            }
        } else {
            // Si pas de token, rediriger vers l'authentification
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
        // Obtenir un client Gmail authentifié
        $client = getGmailClient();
        $service = new Google\Service\Gmail($client);
        
        // Rechercher les emails correspondant à la requête
        $optParams = [
            'maxResults' => $maxResults,
            'q' => $query
        ];
        
        $results = $service->users_messages->listUsersMessages('me', $optParams);
        $messages = $results->getMessages();
        
        $emails = [];
        
        // Récupérer les détails de chaque message
        foreach ($messages as $message) {
            $msg = $service->users_messages->get('me', $message->getId(), ['format' => 'metadata']);
            
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
            
            // Extraire les en-têtes importants
            foreach ($headers as $header) {
                switch ($header->getName()) {
                    case 'Date':
                        $email['date'] = $header->getValue();
                        break;
                    case 'From':
                        $email['from'] = $header->getValue();
                        break;
                    case 'To':
                        $email['to'] = $header->getValue();
                        break;
                    case 'Subject':
                        $email['subject'] = $header->getValue();
                        break;
                }
            }
            
            $emails[] = $email;
        }
        
        return [
            'success' => true,
            'count' => count($emails),
            'emails' => $emails
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
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
        // Obtenir un client Gmail authentifié
        $client = getGmailClient();
        $service = new Google\Service\Gmail($client);
        
        // Récupérer le message complet
        $message = $service->users_messages->get('me', $emailId, ['format' => 'full']);
        
        $headers = $message->getPayload()->getHeaders();
        $email = [
            'id' => $message->getId(),
            'threadId' => $message->getThreadId(),
            'snippet' => $message->getSnippet(),
            'date' => '',
            'from' => '',
            'to' => '',
            'subject' => '',
            'body' => ''
        ];
        
        // Extraire les en-têtes importants
        foreach ($headers as $header) {
            switch ($header->getName()) {
                case 'Date':
                    $email['date'] = $header->getValue();
                    break;
                case 'From':
                    $email['from'] = $header->getValue();
                    break;
                case 'To':
                    $email['to'] = $header->getValue();
                    break;
                case 'Subject':
                    $email['subject'] = $header->getValue();
                    break;
            }
        }
        
        // Extraire le corps du message
        $parts = $message->getPayload()->getParts();
        $body = '';
        
        // Fonction récursive pour extraire le texte des parties du message
        function getMessageBody($part) {
            $body = '';
            
            // Si c'est une partie simple
            if ($part->getBody() && $part->getBody()->getData()) {
                if ($part->getMimeType() === 'text/plain') {
                    $body = base64_decode(str_replace(['-', '_'], ['+', '/'], $part->getBody()->getData()));
                }
            }
            
            // Si c'est une partie multipart, parcourir les sous-parties
            if ($part->getParts()) {
                foreach ($part->getParts() as $subpart) {
                    $body .= getMessageBody($subpart);
                }
            }
            
            return $body;
        }
        
        // Si le message a des parties
        if ($parts) {
            foreach ($parts as $part) {
                $body .= getMessageBody($part);
            }
        } else {
            // Sinon, essayer d'extraire directement du payload
            $data = $message->getPayload()->getBody()->getData();
            if ($data) {
                $body = base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
            }
        }
        
        $email['body'] = $body;
        
        return [
            'success' => true,
            'email' => $email
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
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
        // Obtenir un client Gmail authentifié
        $client = getGmailClient();
        $service = new Google\Service\Gmail($client);
        
        // Construire l'email au format RFC 2822
        $email = "From: me\r\n";
        $email .= "To: $to\r\n";
        
        if (!empty($cc)) {
            $email .= "Cc: $cc\r\n";
        }
        
        if (!empty($bcc)) {
            $email .= "Bcc: $bcc\r\n";
        }
        
        $email .= "Subject: $subject\r\n";
        $email .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $email .= "\r\n$body";
        
        // Encoder l'email en base64url
        $encodedEmail = rtrim(strtr(base64_encode($email), '+/', '-_'), '=');
        
        // Créer le brouillon
        $draft = new Google\Service\Gmail\Draft();
        $message = new Google\Service\Gmail\Message();
        $message->setRaw($encodedEmail);
        $draft->setMessage($message);
        
        $result = $service->users_drafts->create('me', $draft);
        
        return [
            'success' => true,
            'draftId' => $result->getId(),
            'message' => 'Brouillon créé avec succès'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
