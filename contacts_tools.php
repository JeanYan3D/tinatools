<?php
// Message de débogage pour vérifier que cette version est bien exécutée
echo "<!-- Version REST API directe - " . date('Y-m-d H:i:s') . " -->\n";

/**
 * Fonctions pour interagir avec l'API Google People (Contacts)
 * 
 * Ce fichier contient des fonctions pour récupérer et manipuler les contacts
 * via l'API Google People.
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure l'autoloader de Composer
require_once __DIR__ . '/vendor/autoload.php';

// Inclure le fichier de configuration de la base de données
require_once __DIR__ . '/db_config.php';

/**
 * Obtient un client Google authentifié pour l'API People
 * 
 * @return Google\Client Client Google authentifié
 */
function getContactsClient() {
    // Vérifier si nous sommes sur Heroku
    $isHeroku = getenv('GOOGLE_OAUTH_CREDENTIALS_JSON') ? true : false;
    
    // Créer un client Google
    $client = new Google\Client();
    $client->setApplicationName('Tina Gmail Integration');
    
    // Configurer les identifiants OAuth
    if ($isHeroku) {
        // Sur Heroku, utiliser la variable d'environnement
        $credentials_json = getenv('GOOGLE_OAUTH_CREDENTIALS_JSON');
        $credentials = json_decode($credentials_json, true);
        $client->setAuthConfig($credentials);
    } else {
        // En local, utiliser le fichier de clé OAuth
        $client->setAuthConfig(__DIR__ . '/client_secret_897210672149-bdk9e05vo6gmnvnqdv0572ebt5voobe0.apps.googleusercontent.com.json');
    }
    
    // Configurer le scope pour les contacts
    $client->setScopes(['https://www.googleapis.com/auth/contacts.readonly']);
    
    // Récupérer le token depuis la base de données
    $token = getTokenFromDb('gmail');
    
    if ($token) {
        $client->setAccessToken($token);
        
        // Vérifier si le token est expiré
        if ($client->isAccessTokenExpired()) {
            // Rafraîchir le token
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                
                // Sauvegarder le nouveau token
                saveTokenToDb('gmail', $client->getAccessToken());
            }
        }
    }
    
    return $client;
}

/**
 * Liste les contacts de l'utilisateur
 * 
 * @param int $pageSize Nombre de contacts à récupérer par page
 * @param string $pageToken Token de pagination pour récupérer la page suivante
 * @return array Liste des contacts
 */
function listContacts($pageSize = 100, $pageToken = null) {
    $client = getContactsClient();
    
    // Utiliser l'API REST directement au lieu de la classe Google\Service\People
    $url = 'https://people.googleapis.com/v1/people/me/connections';
    $params = [
        'personFields' => 'names,emailAddresses,phoneNumbers,organizations,photos',
        'pageSize' => $pageSize
    ];
    
    if ($pageToken) {
        $params['pageToken'] = $pageToken;
    }
    
    // Construire l'URL avec les paramètres
    $url .= '?' . http_build_query($params);
    
    try {
        // Exécuter la requête
        $response = $client->getHttpClient()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $client->getAccessToken()['access_token']
            ]
        ]);
        
        // Décoder la réponse JSON
        $data = json_decode($response->getBody()->getContents(), true);
        
        // Formater les résultats
        $contacts = [];
        if (isset($data['connections']) && is_array($data['connections'])) {
            foreach ($data['connections'] as $person) {
                $contact = [
                    'resourceName' => $person['resourceName'],
                    'names' => [],
                    'emails' => [],
                    'phones' => [],
                    'organizations' => [],
                    'photos' => []
                ];
                
                // Récupérer les noms
                if (isset($person['names']) && is_array($person['names'])) {
                    foreach ($person['names'] as $name) {
                        $contact['names'][] = [
                            'displayName' => $name['displayName'] ?? '',
                            'givenName' => $name['givenName'] ?? '',
                            'familyName' => $name['familyName'] ?? ''
                        ];
                    }
                }
                
                // Récupérer les emails
                if (isset($person['emailAddresses']) && is_array($person['emailAddresses'])) {
                    foreach ($person['emailAddresses'] as $email) {
                        $contact['emails'][] = [
                            'value' => $email['value'] ?? '',
                            'type' => $email['type'] ?? ''
                        ];
                    }
                }
                
                // Récupérer les numéros de téléphone
                if (isset($person['phoneNumbers']) && is_array($person['phoneNumbers'])) {
                    foreach ($person['phoneNumbers'] as $phone) {
                        $contact['phones'][] = [
                            'value' => $phone['value'] ?? '',
                            'type' => $phone['type'] ?? ''
                        ];
                    }
                }
                
                // Récupérer les organisations
                if (isset($person['organizations']) && is_array($person['organizations'])) {
                    foreach ($person['organizations'] as $org) {
                        $contact['organizations'][] = [
                            'name' => $org['name'] ?? '',
                            'title' => $org['title'] ?? ''
                        ];
                    }
                }
                
                // Récupérer les photos
                if (isset($person['photos']) && is_array($person['photos'])) {
                    foreach ($person['photos'] as $photo) {
                        $contact['photos'][] = [
                            'url' => $photo['url'] ?? ''
                        ];
                    }
                }
                
                $contacts[] = $contact;
            }
        }
        
        // Ajouter les informations de pagination
        $result = [
            'contacts' => $contacts,
            'nextPageToken' => $data['nextPageToken'] ?? null,
            'totalItems' => $data['totalPeople'] ?? count($contacts)
        ];
        
        return $result;
    } catch (Exception $e) {
        return [
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Recherche des contacts par nom ou email
 * 
 * @param string $query Terme de recherche
 * @param int $pageSize Nombre de résultats par page
 * @return array Résultats de la recherche
 */
function searchContacts($query, $pageSize = 30) {
    $client = getContactsClient();
    
    // Utiliser l'API REST directement au lieu de la classe Google\Service\People
    $url = 'https://people.googleapis.com/v1/people:searchContacts';
    $params = [
        'query' => $query,
        'pageSize' => $pageSize,
        'readMask' => 'names,emailAddresses,phoneNumbers,organizations,photos'
    ];
    
    // Construire l'URL avec les paramètres
    $url .= '?' . http_build_query($params);
    
    try {
        // Exécuter la requête
        $response = $client->getHttpClient()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $client->getAccessToken()['access_token']
            ]
        ]);
        
        // Décoder la réponse JSON
        $data = json_decode($response->getBody()->getContents(), true);
        
        // Formater les résultats
        $contacts = [];
        if (isset($data['results']) && is_array($data['results'])) {
            foreach ($data['results'] as $result) {
                $person = $result['person'];
                
                $contact = [
                    'resourceName' => $person['resourceName'],
                    'names' => [],
                    'emails' => [],
                    'phones' => [],
                    'organizations' => [],
                    'photos' => []
                ];
                
                // Récupérer les noms
                if (isset($person['names']) && is_array($person['names'])) {
                    foreach ($person['names'] as $name) {
                        $contact['names'][] = [
                            'displayName' => $name['displayName'] ?? '',
                            'givenName' => $name['givenName'] ?? '',
                            'familyName' => $name['familyName'] ?? ''
                        ];
                    }
                }
                
                // Récupérer les emails
                if (isset($person['emailAddresses']) && is_array($person['emailAddresses'])) {
                    foreach ($person['emailAddresses'] as $email) {
                        $contact['emails'][] = [
                            'value' => $email['value'] ?? '',
                            'type' => $email['type'] ?? ''
                        ];
                    }
                }
                
                // Récupérer les numéros de téléphone
                if (isset($person['phoneNumbers']) && is_array($person['phoneNumbers'])) {
                    foreach ($person['phoneNumbers'] as $phone) {
                        $contact['phones'][] = [
                            'value' => $phone['value'] ?? '',
                            'type' => $phone['type'] ?? ''
                        ];
                    }
                }
                
                // Récupérer les organisations
                if (isset($person['organizations']) && is_array($person['organizations'])) {
                    foreach ($person['organizations'] as $org) {
                        $contact['organizations'][] = [
                            'name' => $org['name'] ?? '',
                            'title' => $org['title'] ?? ''
                        ];
                    }
                }
                
                // Récupérer les photos
                if (isset($person['photos']) && is_array($person['photos'])) {
                    foreach ($person['photos'] as $photo) {
                        $contact['photos'][] = [
                            'url' => $photo['url'] ?? ''
                        ];
                    }
                }
                
                $contacts[] = $contact;
            }
        }
        
        return [
            'contacts' => $contacts,
            'totalItems' => count($contacts)
        ];
    } catch (Exception $e) {
        return [
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Récupère les détails d'un contact spécifique
 * 
 * @param string $resourceName Identifiant du contact (format: people/c1234567890)
 * @return array Détails du contact
 */
function getContactDetails($resourceName) {
    $client = getContactsClient();
    
    // Utiliser l'API REST directement au lieu de la classe Google\Service\People
    $url = 'https://people.googleapis.com/v1/people/' . $resourceName;
    $params = [
        'personFields' => 'names,emailAddresses,phoneNumbers,organizations,photos,addresses,birthdays,urls'
    ];
    
    // Construire l'URL avec les paramètres
    $url .= '?' . http_build_query($params);
    
    try {
        // Exécuter la requête
        $response = $client->getHttpClient()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $client->getAccessToken()['access_token']
            ]
        ]);
        
        // Décoder la réponse JSON
        $data = json_decode($response->getBody()->getContents(), true);
        
        // Formater les résultats
        $contact = [
            'resourceName' => $data['resourceName'],
            'names' => [],
            'emails' => [],
            'phones' => [],
            'organizations' => [],
            'photos' => [],
            'addresses' => [],
            'birthdays' => [],
            'urls' => []
        ];
        
        // Récupérer les noms
        if (isset($data['names']) && is_array($data['names'])) {
            foreach ($data['names'] as $name) {
                $contact['names'][] = [
                    'displayName' => $name['displayName'] ?? '',
                    'givenName' => $name['givenName'] ?? '',
                    'familyName' => $name['familyName'] ?? ''
                ];
            }
        }
        
        // Récupérer les emails
        if (isset($data['emailAddresses']) && is_array($data['emailAddresses'])) {
            foreach ($data['emailAddresses'] as $email) {
                $contact['emails'][] = [
                    'value' => $email['value'] ?? '',
                    'type' => $email['type'] ?? ''
                ];
            }
        }
        
        // Récupérer les numéros de téléphone
        if (isset($data['phoneNumbers']) && is_array($data['phoneNumbers'])) {
            foreach ($data['phoneNumbers'] as $phone) {
                $contact['phones'][] = [
                    'value' => $phone['value'] ?? '',
                    'type' => $phone['type'] ?? ''
                ];
            }
        }
        
        // Récupérer les organisations
        if (isset($data['organizations']) && is_array($data['organizations'])) {
            foreach ($data['organizations'] as $org) {
                $contact['organizations'][] = [
                    'name' => $org['name'] ?? '',
                    'title' => $org['title'] ?? ''
                ];
            }
        }
        
        // Récupérer les photos
        if (isset($data['photos']) && is_array($data['photos'])) {
            foreach ($data['photos'] as $photo) {
                $contact['photos'][] = [
                    'url' => $photo['url'] ?? ''
                ];
            }
        }
        
        // Récupérer les adresses
        if (isset($data['addresses']) && is_array($data['addresses'])) {
            foreach ($data['addresses'] as $address) {
                $contact['addresses'][] = [
                    'formattedValue' => $address['formattedValue'] ?? '',
                    'type' => $address['type'] ?? '',
                    'streetAddress' => $address['streetAddress'] ?? '',
                    'city' => $address['city'] ?? '',
                    'postalCode' => $address['postalCode'] ?? '',
                    'country' => $address['country'] ?? ''
                ];
            }
        }
        
        // Récupérer les anniversaires
        if (isset($data['birthdays']) && is_array($data['birthdays'])) {
            foreach ($data['birthdays'] as $birthday) {
                $date = $birthday['date'];
                $contact['birthdays'][] = [
                    'year' => $date['year'] ?? null,
                    'month' => $date['month'] ?? null,
                    'day' => $date['day'] ?? null
                ];
            }
        }
        
        // Récupérer les URLs
        if (isset($data['urls']) && is_array($data['urls'])) {
            foreach ($data['urls'] as $url) {
                $contact['urls'][] = [
                    'value' => $url['value'] ?? '',
                    'type' => $url['type'] ?? ''
                ];
            }
        }
        
        return $contact;
    } catch (Exception $e) {
        return [
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Recherche un contact par adresse email
 * 
 * @param string $email Adresse email à rechercher
 * @return array|null Contact trouvé ou null si aucun contact n'est trouvé
 */
function findContactByEmail($email) {
    $client = getContactsClient();
    
    // Utiliser l'API REST directement
    $url = 'https://people.googleapis.com/v1/people:searchContacts';
    $params = [
        'query' => $email,
        'readMask' => 'names,emailAddresses'
    ];
    
    // Construire l'URL avec les paramètres
    $url .= '?' . http_build_query($params);
    
    try {
        // Exécuter la requête
        $response = $client->getHttpClient()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $client->getAccessToken()['access_token']
            ]
        ]);
        
        // Décoder la réponse JSON
        $data = json_decode($response->getBody()->getContents(), true);
        
        // Vérifier si des résultats ont été trouvés
        if (isset($data['results']) && is_array($data['results']) && count($data['results']) > 0) {
            foreach ($data['results'] as $result) {
                $person = $result['person'];
                
                // Vérifier si le contact a des adresses email
                if (isset($person['emailAddresses']) && is_array($person['emailAddresses'])) {
                    foreach ($person['emailAddresses'] as $emailAddress) {
                        // Vérifier si l'adresse email correspond
                        if (strtolower($emailAddress['value']) === strtolower($email)) {
                            // Formater le contact
                            $contact = [
                                'resourceName' => $person['resourceName'],
                                'names' => [],
                                'emails' => []
                            ];
                            
                            // Récupérer les noms
                            if (isset($person['names']) && is_array($person['names'])) {
                                foreach ($person['names'] as $name) {
                                    $contact['names'][] = [
                                        'displayName' => $name['displayName'] ?? '',
                                        'givenName' => $name['givenName'] ?? '',
                                        'familyName' => $name['familyName'] ?? ''
                                    ];
                                }
                            }
                            
                            // Récupérer les emails
                            foreach ($person['emailAddresses'] as $email) {
                                $contact['emails'][] = [
                                    'value' => $email['value'] ?? '',
                                    'type' => $email['type'] ?? ''
                                ];
                            }
                            
                            return $contact;
                        }
                    }
                }
            }
        }
        
        return null;
    } catch (Exception $e) {
        return [
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Recherche des contacts par nom
 * 
 * @param string $name Nom à rechercher
 * @return array Liste des contacts trouvés
 */
function findContactsByName($name) {
    $client = getContactsClient();
    
    // Utiliser l'API REST directement
    $url = 'https://people.googleapis.com/v1/people:searchContacts';
    $params = [
        'query' => $name,
        'readMask' => 'names,emailAddresses,phoneNumbers'
    ];
    
    // Construire l'URL avec les paramètres
    $url .= '?' . http_build_query($params);
    
    try {
        // Exécuter la requête
        $response = $client->getHttpClient()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $client->getAccessToken()['access_token']
            ]
        ]);
        
        // Décoder la réponse JSON
        $data = json_decode($response->getBody()->getContents(), true);
        
        // Formater les résultats
        $contacts = [];
        
        if (isset($data['results']) && is_array($data['results'])) {
            foreach ($data['results'] as $result) {
                $person = $result['person'];
                
                // Vérifier si le contact a un nom qui correspond
                if (isset($person['names']) && is_array($person['names'])) {
                    $nameMatches = false;
                    
                    foreach ($person['names'] as $personName) {
                        if (stripos($personName['displayName'], $name) !== false ||
                            (isset($personName['givenName']) && stripos($personName['givenName'], $name) !== false) ||
                            (isset($personName['familyName']) && stripos($personName['familyName'], $name) !== false)) {
                            $nameMatches = true;
                            break;
                        }
                    }
                    
                    if ($nameMatches) {
                        // Formater le contact
                        $contact = [
                            'resourceName' => $person['resourceName'],
                            'names' => [],
                            'emails' => [],
                            'phones' => []
                        ];
                        
                        // Récupérer les noms
                        foreach ($person['names'] as $personName) {
                            $contact['names'][] = [
                                'displayName' => $personName['displayName'] ?? '',
                                'givenName' => $personName['givenName'] ?? '',
                                'familyName' => $personName['familyName'] ?? ''
                            ];
                        }
                        
                        // Récupérer les emails
                        if (isset($person['emailAddresses']) && is_array($person['emailAddresses'])) {
                            foreach ($person['emailAddresses'] as $email) {
                                $contact['emails'][] = [
                                    'value' => $email['value'] ?? '',
                                    'type' => $email['type'] ?? ''
                                ];
                            }
                        }
                        
                        // Récupérer les téléphones
                        if (isset($person['phoneNumbers']) && is_array($person['phoneNumbers'])) {
                            foreach ($person['phoneNumbers'] as $phone) {
                                $contact['phones'][] = [
                                    'value' => $phone['value'] ?? '',
                                    'type' => $phone['type'] ?? ''
                                ];
                            }
                        }
                        
                        $contacts[] = $contact;
                    }
                }
            }
        }
        
        return $contacts;
    } catch (Exception $e) {
        return [
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Récupère l'adresse email d'un contact à partir de son nom
 * 
 * @param string $name Nom du contact
 * @return string|null Adresse email du contact ou null si aucun contact n'est trouvé
 */
function getEmailFromName($name) {
    $contacts = findContactsByName($name);
    
    if (is_array($contacts) && !isset($contacts['error']) && count($contacts) > 0) {
        // Prendre le premier contact trouvé
        $contact = $contacts[0];
        
        // Vérifier si le contact a des adresses email
        if (isset($contact['emails']) && is_array($contact['emails']) && count($contact['emails']) > 0) {
            // Retourner la première adresse email
            return $contact['emails'][0]['value'];
        }
    }
    
    return null;
}
