<?php
/**
 * Script de test pour l'API Google People (Contacts)
 * 
 * Ce script permet de tester les fonctions d'accès aux contacts
 * via l'API Google People.
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure les fichiers nécessaires
require_once __DIR__ . '/contacts_tools.php';

// Fonction pour afficher les résultats de manière lisible
function displayResults($data) {
    echo '<pre>' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</pre>';
}

// Vérifier si une action a été demandée
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Traiter l'action demandée
$result = null;
$actionLabel = '';

switch ($action) {
    case 'list':
        $pageSize = isset($_GET['pageSize']) ? intval($_GET['pageSize']) : 10;
        $pageToken = isset($_GET['pageToken']) ? $_GET['pageToken'] : null;
        $result = listContacts($pageSize, $pageToken);
        $actionLabel = 'Liste des contacts';
        break;
        
    case 'search':
        $query = isset($_GET['query']) ? $_GET['query'] : '';
        $result = searchContacts($query);
        $actionLabel = 'Recherche de contacts pour : ' . htmlspecialchars($query);
        break;
        
    case 'details':
        $resourceName = isset($_GET['resourceName']) ? $_GET['resourceName'] : '';
        $result = getContactDetails($resourceName);
        $actionLabel = 'Détails du contact : ' . htmlspecialchars($resourceName);
        break;
        
    case 'findByEmail':
        $email = isset($_GET['email']) ? $_GET['email'] : '';
        $result = findContactByEmail($email);
        $actionLabel = 'Recherche de contact par email : ' . htmlspecialchars($email);
        break;
        
    case 'findByName':
        $name = isset($_GET['name']) ? $_GET['name'] : '';
        $result = findContactsByName($name);
        $actionLabel = 'Recherche de contacts par nom : ' . htmlspecialchars($name);
        break;
        
    case 'getEmailFromName':
        $name = isset($_GET['name']) ? $_GET['name'] : '';
        $result = getEmailFromName($name);
        $actionLabel = 'Récupération de l\'email pour le nom : ' . htmlspecialchars($name);
        break;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de l'API Google People (Contacts)</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1, h2 {
            color: #4285f4;
        }
        .container {
            margin-bottom: 30px;
        }
        .card {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4285f4;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #3367d6;
        }
        .result {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
            overflow-x: auto;
        }
        pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .nav a {
            text-decoration: none;
            color: #4285f4;
            padding: 5px 10px;
            border: 1px solid #4285f4;
            border-radius: 4px;
        }
        .nav a:hover, .nav a.active {
            background-color: #4285f4;
            color: white;
        }
        .pagination {
            margin-top: 20px;
        }
        .contact-card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .contact-name {
            font-weight: bold;
            font-size: 1.1em;
        }
        .contact-email, .contact-phone {
            color: #666;
        }
    </style>
</head>
<body>
    <h1>Test de l'API Google People (Contacts)</h1>
    
    <div class="nav">
        <a href="test_contacts_api.php" <?php echo $action === '' ? 'class="active"' : ''; ?>>Accueil</a>
        <a href="test_contacts_api.php?action=list" <?php echo $action === 'list' ? 'class="active"' : ''; ?>>Liste des contacts</a>
        <a href="test_gmail_api.php">Retour aux tests Gmail</a>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>Recherche de contacts</h2>
            <form action="test_contacts_api.php" method="get">
                <input type="hidden" name="action" value="search">
                <div class="form-group">
                    <label for="query">Terme de recherche :</label>
                    <input type="text" id="query" name="query" placeholder="Nom, email, etc." required>
                </div>
                <button type="submit">Rechercher</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Recherche par email</h2>
            <form action="test_contacts_api.php" method="get">
                <input type="hidden" name="action" value="findByEmail">
                <div class="form-group">
                    <label for="email">Adresse email :</label>
                    <input type="text" id="email" name="email" placeholder="exemple@gmail.com" required>
                </div>
                <button type="submit">Rechercher</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Recherche par nom</h2>
            <form action="test_contacts_api.php" method="get">
                <input type="hidden" name="action" value="findByName">
                <div class="form-group">
                    <label for="name">Nom :</label>
                    <input type="text" id="name" name="name" placeholder="Jean Dupont" required>
                </div>
                <button type="submit">Rechercher</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Récupérer l'email à partir d'un nom</h2>
            <form action="test_contacts_api.php" method="get">
                <input type="hidden" name="action" value="getEmailFromName">
                <div class="form-group">
                    <label for="name2">Nom :</label>
                    <input type="text" id="name2" name="name" placeholder="Jean Dupont" required>
                </div>
                <button type="submit">Récupérer l'email</button>
            </form>
        </div>
    </div>
    
    <?php if ($action && $result !== null): ?>
    <div class="container">
        <h2><?php echo htmlspecialchars($actionLabel); ?></h2>
        
        <?php if ($action === 'list' && !isset($result['error'])): ?>
            <div class="result">
                <h3>Nombre total de contacts : <?php echo $result['totalItems'] ?? count($result['contacts']); ?></h3>
                
                <?php foreach ($result['contacts'] as $contact): ?>
                    <div class="contact-card">
                        <div class="contact-name">
                            <?php 
                            echo !empty($contact['names']) 
                                ? htmlspecialchars($contact['names'][0]['displayName']) 
                                : 'Sans nom';
                            ?>
                        </div>
                        
                        <?php if (!empty($contact['emails'])): ?>
                            <div class="contact-email">
                                Email : <?php echo htmlspecialchars($contact['emails'][0]['value']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($contact['phones'])): ?>
                            <div class="contact-phone">
                                Téléphone : <?php echo htmlspecialchars($contact['phones'][0]['value']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($contact['organizations'])): ?>
                            <div class="contact-org">
                                Organisation : <?php echo htmlspecialchars($contact['organizations'][0]['name'] ?? ''); ?>
                                <?php if (!empty($contact['organizations'][0]['title'])): ?>
                                    (<?php echo htmlspecialchars($contact['organizations'][0]['title']); ?>)
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <a href="test_contacts_api.php?action=details&resourceName=<?php echo urlencode($contact['resourceName']); ?>">
                            Voir les détails
                        </a>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!empty($result['nextPageToken'])): ?>
                    <div class="pagination">
                        <a href="test_contacts_api.php?action=list&pageToken=<?php echo urlencode($result['nextPageToken']); ?>">
                            Page suivante
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($action === 'getEmailFromName'): ?>
            <div class="result">
                <?php if ($result): ?>
                    <p>Email trouvé : <strong><?php echo htmlspecialchars($result); ?></strong></p>
                <?php else: ?>
                    <p>Aucun email trouvé pour ce nom.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="result">
                <?php displayResults($result); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
</body>
</html>
