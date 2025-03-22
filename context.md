# Architecture et Workflow de l'Intégration Vapi.ai avec Google Docs et Gmail

## Vue d'ensemble du projet

Ce projet consiste en une intégration entre Vapi.ai (plateforme d'assistants vocaux) et les APIs Google (Docs et Gmail), permettant de créer des documents Google Docs et d'interagir avec Gmail via des commandes vocales. L'application est hébergée sur Heroku.

## Architecture technique

### Composants principaux

1. **Assistant vocal Vapi.ai (Tina)**
   - Reçoit les commandes vocales de l'utilisateur
   - Utilise des outils (tools) pour exécuter des actions spécifiques
   - Envoie des requêtes HTTP à notre API PHP

2. **API PHP**
   - **gdocs_creator.php** : Point d'entrée pour les requêtes de création de documents
   - **gmail_api.php** : Point d'entrée pour les requêtes liées à Gmail
   - Authentifie les requêtes auprès de Google via OAuth 2.0
   - Crée et modifie des documents Google Docs
   - Recherche, lit et crée des brouillons d'emails dans Gmail
   - Partage les documents créés avec les utilisateurs spécifiés

3. **Google API**
   - Google Docs API pour la création et modification de documents
   - Google Drive API pour la gestion des permissions et partages
   - Gmail API pour l'interaction avec les emails

### Flux de données

```
Utilisateur → Commande vocale → Vapi.ai → Requête HTTP → API PHP → Google API → Action exécutée
```

## Workflow de fonctionnement

### Google Docs

1. L'utilisateur demande à l'assistant vocal Tina de créer un document Google Docs
2. Tina analyse la demande et identifie qu'elle doit utiliser l'outil "CreateGoogleDoc"
3. Tina envoie une requête HTTP POST à notre API PHP avec les paramètres nécessaires (titre, contenu)
4. Notre API PHP reçoit la requête et l'analyse
5. L'API PHP extrait le titre et le contenu de la structure de données spécifique à Vapi.ai
6. L'API PHP utilise le compte de service Google pour s'authentifier
7. L'API PHP crée un nouveau document Google Docs avec le titre spécifié
8. L'API PHP ajoute le contenu au document
9. L'API PHP partage le document avec l'utilisateur spécifié (tehau.babois@gmail.com)
10. L'API PHP renvoie une réponse de succès à Vapi.ai avec l'URL du document
11. Tina informe l'utilisateur que le document a été créé avec succès

### Gmail

#### Recherche d'emails

1. L'utilisateur demande à l'assistant vocal Tina de rechercher des emails
2. Tina analyse la demande et identifie qu'elle doit utiliser l'outil "SearchEmails"
3. Tina envoie une requête HTTP POST à notre API PHP (gmail_api.php) avec les paramètres nécessaires (query, maxResults)
4. Notre API PHP reçoit la requête et l'analyse
5. L'API PHP extrait la fonction et les paramètres de la structure de données spécifique à Vapi.ai
6. L'API PHP utilise OAuth 2.0 pour s'authentifier auprès de Gmail
7. L'API PHP effectue la recherche d'emails selon les critères spécifiés
8. L'API PHP renvoie une réponse à Vapi.ai avec les résultats de la recherche
9. Tina présente les résultats à l'utilisateur

#### Lecture d'un email

1. L'utilisateur demande à l'assistant vocal Tina de lire un email spécifique
2. Tina analyse la demande et identifie qu'elle doit utiliser l'outil "ReadEmail"
3. Tina envoie une requête HTTP POST à notre API PHP avec l'ID de l'email à lire
4. Notre API PHP reçoit la requête et l'analyse
5. L'API PHP extrait la fonction et les paramètres de la structure de données
6. L'API PHP utilise OAuth 2.0 pour s'authentifier auprès de Gmail
7. L'API PHP récupère le contenu complet de l'email spécifié
8. L'API PHP renvoie une réponse à Vapi.ai avec le contenu de l'email
9. Tina lit le contenu de l'email à l'utilisateur

#### Création d'un brouillon d'email

1. L'utilisateur demande à l'assistant vocal Tina de créer un brouillon d'email
2. Tina analyse la demande et identifie qu'elle doit utiliser l'outil "CreateDraft"
3. Tina envoie une requête HTTP POST à notre API PHP avec les paramètres nécessaires (to, subject, body, cc, bcc)
4. Notre API PHP reçoit la requête et l'analyse
5. L'API PHP extrait la fonction et les paramètres de la structure de données
6. L'API PHP utilise OAuth 2.0 pour s'authentifier auprès de Gmail
7. L'API PHP crée un brouillon d'email avec les informations fournies
8. L'API PHP renvoie une réponse à Vapi.ai avec l'ID du brouillon créé
9. Tina informe l'utilisateur que le brouillon a été créé avec succès

## Intégration Gmail

### Fonctionnalités Gmail

Tina peut désormais interagir avec Gmail grâce à l'intégration de l'API Gmail. Les fonctionnalités suivantes sont disponibles :

1. **SearchEmails** : Recherche des emails selon des critères spécifiques
   - Paramètres : `query` (string), `maxResults` (number)
   - Exemple de requête : `{"query": "is:unread", "maxResults": 5}`

2. **ReadEmail** : Lit le contenu complet d'un email spécifique
   - Paramètres : `emailId` (string)
   - Exemple de requête : `{"emailId": "12345abcde"}`

3. **CreateDraft** : Crée un brouillon d'email
   - Paramètres : `to` (string), `subject` (string), `body` (string), `cc` (string, optionnel), `bcc` (string, optionnel)
   - Exemple de requête : `{"to": "destinataire@example.com", "subject": "Sujet", "body": "Contenu du message"}`

4. **AccessContacts** : Accède aux contacts Gmail
   - Fonctionnalités : Recherche, liste et récupération des détails des contacts
   - Utilise l'API Google People via des appels REST directs

### Authentification Gmail

L'authentification à Gmail utilise OAuth 2.0, comme pour Google Docs. Le processus est le suivant :

1. L'utilisateur accède à `gmail_auth.php` pour initier l'authentification
2. Après autorisation, un token d'accès est stocké dans `gmail_token.json` (en local) ou dans la variable d'environnement `GMAIL_TOKEN_JSON` (sur Heroku)
3. Les requêtes suivantes utilisent ce token pour s'authentifier

### Configuration sur Heroku

Pour que l'intégration Gmail fonctionne sur Heroku, les variables d'environnement suivantes doivent être configurées :

- `GOOGLE_APPLICATION_CREDENTIALS_JSON` : Contenu du fichier client_secret
- `GMAIL_TOKEN_JSON` : Contenu du fichier gmail_token.json généré après authentification

**Note importante** : Lorsque le token d'accès est rafraîchi, la variable d'environnement `GMAIL_TOKEN_JSON` doit être mise à jour manuellement sur Heroku. Un message d'erreur sera enregistré dans les logs Heroku lorsque cela se produit.

### Workflow pour les fonctionnalités Gmail

1. **Recherche d'emails** :
   - Vapi.ai envoie une requête à `gmail_api.php` avec l'outil `SearchEmails`
   - L'API extrait les paramètres et appelle la fonction `searchEmails` dans `gmail_tools.php`
   - Les résultats de la recherche sont renvoyés à Vapi.ai

2. **Lecture d'un email** :
   - Vapi.ai envoie une requête à `gmail_api.php` avec l'outil `ReadEmail`
   - L'API extrait l'ID de l'email et appelle la fonction `readEmail` dans `gmail_tools.php`
   - Le contenu de l'email est renvoyé à Vapi.ai

3. **Création d'un brouillon** :
   - Vapi.ai envoie une requête à `gmail_api.php` avec l'outil `CreateDraft`
   - L'API extrait les paramètres et appelle la fonction `createDraft` dans `gmail_tools.php`
   - L'ID du brouillon créé est renvoyé à Vapi.ai

## Mises à jour récentes

### Intégration de l'API Gmail avec la base de données MariaDB

1. **Objectif**
   - Remplacer le stockage des tokens OAuth dans des fichiers ou variables d'environnement par une base de données MariaDB
   - Améliorer la sécurité et la gestion des tokens d'authentification
   - Simplifier le déploiement et la maintenance de l'application

2. **Configuration de la base de données**
   - Base de données MariaDB configurée avec les paramètres suivants :
     - Hôte : 51.161.198.217 (ou localhost si déployé sur le même serveur)
     - Base de données : tinatools
     - Utilisateur : tinatools
     - Mot de passe : nv2c_61J7!!
   - Table `oauth_tokens` créée avec la structure suivante :
     - `id` : INT AUTO_INCREMENT PRIMARY KEY
     - `token_type` : VARCHAR(50) NOT NULL
     - `token_data` : TEXT NOT NULL
     - `created_at` : TIMESTAMP DEFAULT CURRENT_TIMESTAMP
     - `updated_at` : TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

3. **Modifications apportées**
   - Création du fichier `db_config.php` pour la gestion de la connexion à la base de données
   - Implémentation des fonctions `getTokenFromDb()` et `saveTokenToDb()` pour gérer les tokens
   - Mise à jour de `gmail_tools.php` pour utiliser la base de données en priorité
   - Modification de `oauth_callback.php` pour sauvegarder les tokens dans la base de données
   - Correction des chemins de fichiers en utilisant des chemins absolus avec `__DIR__`

4. **Flux d'authentification**
   - L'utilisateur accède à `gmail_auth.php` pour lancer le processus d'authentification
   - Après autorisation, l'utilisateur est redirigé vers `oauth_callback.php`
   - Le token est récupéré et sauvegardé dans la base de données
   - Les requêtes ultérieures à l'API Gmail utilisent le token stocké dans la base de données
   - Si le token est expiré, il est automatiquement rafraîchi et mis à jour dans la base de données

5. **Avantages**
   - Centralisation de la gestion des tokens
   - Amélioration de la sécurité (pas de tokens dans les fichiers)
   - Simplification du déploiement (pas besoin de mettre à jour les variables d'environnement)
   - Facilité de maintenance et de débogage

6. **Prochaines étapes**
   - Tester l'intégration en environnement de production
   - Mettre en place une stratégie de sauvegarde de la base de données
   - Étendre cette approche à d'autres services Google (Docs, Drive)

## Structure des requêtes et réponses

### Format de requête de Vapi.ai

```json
{
  "message": {
    "timestamp": 1742486877598,
    "type": "tool-calls",
    "tool_calls": [
      {
        "id": "call_tmEhPZKBiReNv3vCX2JwtO5A",
        "type": "function",
        "function": {
          "name": "CreateGoogleDoc",
          "arguments": {
            "title": "Titre du document",
            "content": "Contenu du document"
          }
        }
      }
    ],
    "tool_call_list": [
      {
        "id": "call_tmEhPZKBiReNv3vCX2JwtO5A",
        "type": "function",
        "function": {
          "name": "CreateGoogleDoc",
          "arguments": {
            "title": "Titre du document",
            "content": "Contenu du document"
          }
        }
      }
    ],
    "tool_with_tool_call_list": [
      {
        "type": "function",
        "function": {
          "name": "CreateGoogleDoc",
          "strict": false,
          "parameters": {
            "type": "object",
            "required": [
              "title",
              "content"
            ],
            "properties": {
              "title": {
                "type": "string",
                "description": "Titre du document à créer"
              },
              "content": {
                "type": "string",
                "description": "Contenu à ajouter au document"
              }
            }
          },
          "description": "Crée un document Google Docs à partir d'un titre et d'un contenu"
        },
        "tool_call": {
          "id": "call_tmEhPZKBiReNv3vCX2JwtO5A",
          "type": "function",
          "function": {
            "name": "CreateGoogleDoc",
            "arguments": {
              "title": "Titre du document",
              "content": "Contenu du document"
            }
          }
        }
      }
    ]
  }
}
```

### Format de réponse attendu par Vapi.ai

```json
{
  "results": [
    {
      "toolCallId": "call_tmEhPZKBiReNv3vCX2JwtO5A",
      "tool_call_id": "call_tmEhPZKBiReNv3vCX2JwtO5A",
      "result": "Document créé avec succès: Titre du document"
    }
  ]
}
```

## État actuel du projet (21 mars 2025)

### Problèmes rencontrés

1. **Extraction de l'identifiant d'appel d'outil (toolCallId)**
   - Le problème principal est que l'identifiant d'appel d'outil est renvoyé comme `null` dans la réponse à Vapi.ai
   - Cela empêche Tina de confirmer correctement la création du document
   - Les tests locaux montrent que l'extraction fonctionne, mais pas dans l'environnement de production

2. **Différence entre les requêtes de test et les requêtes réelles**
   - Les requêtes de test local extraient correctement l'identifiant
   - Les requêtes réelles de Vapi.ai semblent avoir une structure différente ou sont traitées différemment

3. **Structure complexe des requêtes Vapi.ai**
   - Les requêtes contiennent plusieurs chemins possibles pour l'identifiant d'appel d'outil
   - Certaines requêtes peuvent contenir plusieurs appels d'outils

### Solutions mises en place

1. **Système de journalisation robuste**
   - Ajout d'un système pour enregistrer les requêtes brutes complètes dans des fichiers JSON
   - Création d'une interface web pour visualiser, rechercher et supprimer les logs
   - Cela permettra d'analyser exactement ce qui est reçu de Vapi.ai

2. **Scripts de test**
   - Création de scripts pour tester différentes structures de requêtes
   - Simulation du workflow complet de Vapi.ai pour identifier où se situe le problème

3. **Format de réponse amélioré**
   - Modification de la réponse pour inclure à la fois `toolCallId` et `tool_call_id`
   - Cela assure la compatibilité avec différentes versions de l'API Vapi.ai

### Prochaines étapes

1. **Déployer les modifications sur Heroku**
   - Pousser les changements sur GitHub puis déployer sur Heroku
   - Cela permettra de capturer les vraies requêtes de Vapi.ai

2. **Analyser les logs des requêtes réelles**
   - Examiner la structure exacte des requêtes reçues
   - Identifier pourquoi l'extraction de l'identifiant échoue

3. **Ajuster l'algorithme d'extraction**
   - Modifier le code d'extraction en fonction des résultats de l'analyse
   - Tester avec des requêtes réelles pour valider la solution

4. **Améliorer la gestion des erreurs**
   - Ajouter plus de détails dans les logs d'erreur
   - Mettre en place un système de notification en cas d'échec

## Fonctionnalités de l'API Contacts

### Fichiers principaux

1. **contacts_tools.php**
   - Contient les fonctions pour interagir avec l'API Google People (Contacts)
   - Utilise des appels REST directs plutôt que la classe Google\Service\People
   - Fonctions principales :
     - `getContactsClient()` : Obtient un client Google authentifié pour l'API People
     - `listContacts()` : Liste les contacts de l'utilisateur
     - `searchContacts()` : Recherche des contacts par nom ou email
     - `getContactDetails()` : Récupère les détails d'un contact spécifique
     - `findContactByEmail()` : Recherche un contact par adresse email
     - `findContactsByName()` : Recherche des contacts par nom
     - `getEmailFromName()` : Récupère l'adresse email d'un contact à partir de son nom

2. **test_contacts_api.php**
   - Interface de test pour les fonctionnalités de contacts
   - Permet de tester les différentes fonctions de recherche et de récupération de contacts

### Intégration avec Vapi.ai

L'intégration des contacts avec Vapi.ai permettra à Tina de :
1. Rechercher des contacts par nom ou email
2. Récupérer les détails d'un contact
3. Utiliser les contacts pour composer des emails (ex: "Envoie un email à Jean")

## Déploiement et Environnements

### Configuration de la Base de Données

- **Base de données MariaDB externe**
  - Hôte : 51.161.198.217
  - Base de données : tinatools
  - Utilisateur : tinatools
  - Table principale : oauth_tokens
  - **Important** : Cette base de données est partagée entre l'environnement local et Heroku
  - Les tokens OAuth sont stockés dans cette base de données, ce qui signifie qu'une fois authentifié en local, l'application sur Heroku peut utiliser le même token sans réauthentification

### Déploiement sur Heroku

1. **Commiter les modifications**
   ```
   git add composer.json composer.lock contacts_tools.php test_contacts_api.php gmail_auth.php
   git commit -m "Ajout de l'intégration des contacts Gmail avec l'API Google People"
   ```

2. **Déployer sur Heroku**
   ```
   git push heroku master
   ```

3. **Vérification après déploiement**
   - Tester les fonctionnalités de contacts sur Heroku
   - Vérifier les logs Heroku en cas de problème : `heroku logs --tail`
