<?php
// Configuration de la connexion à la base de données
function getDbConnection() {
    // Paramètres de connexion à la base de données
    // Pour les connexions depuis l'extérieur du serveur, utilisez l'adresse IP
    $host = '51.161.198.217';  // Adresse IP externe du serveur MariaDB
    // Si vous déployez ce code sur le même serveur que MariaDB, utilisez plutôt 'localhost'
    //$host = 'localhost';  // Décommentez cette ligne si le code s'exécute sur le même serveur que MariaDB
    
    $port = '3306';  // Port MariaDB (3306 est le port par défaut)
    $dbname = 'tinatools';  // Nom de votre base de données
    $username = 'tinatools';  // Nom d'utilisateur
    $password = 'nv2c_61J7!!';  // Mot de passe
    
    // Vérifier si nous sommes sur Heroku
    $isHeroku = getenv('CLEARDB_DATABASE_URL') ? true : false;
    
    if ($isHeroku) {
        // Sur Heroku, utiliser la variable d'environnement CLEARDB_DATABASE_URL
        $url = parse_url(getenv('CLEARDB_DATABASE_URL'));
        $host = $url['host'];
        $port = $url['port'];
        $dbname = substr($url['path'], 1);
        $username = $url['user'];
        $password = $url['pass'];
    }
    
    try {
        // Créer une nouvelle connexion PDO
        $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
        // Configurer PDO pour qu'il lance des exceptions en cas d'erreur
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        // En cas d'erreur, logger l'erreur et retourner null
        error_log('Erreur de connexion à la base de données : ' . $e->getMessage());
        return null;
    }
}

// Fonction pour récupérer un token depuis la base de données
function getTokenFromDb($tokenType) {
    $conn = getDbConnection();
    if (!$conn) {
        return null;
    }
    
    try {
        $stmt = $conn->prepare("SELECT token_data FROM oauth_tokens WHERE token_type = :token_type");
        $stmt->bindParam(':token_type', $tokenType);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return json_decode($result['token_data'], true);
        }
        return null;
    } catch(PDOException $e) {
        error_log('Erreur lors de la récupération du token : ' . $e->getMessage());
        return null;
    }
}

// Fonction pour sauvegarder un token dans la base de données
function saveTokenToDb($tokenType, $tokenData) {
    $conn = getDbConnection();
    if (!$conn) {
        return false;
    }
    
    try {
        // Convertir le token en JSON
        $tokenJson = json_encode($tokenData);
        
        // Vérifier si le token existe déjà
        $stmt = $conn->prepare("SELECT id FROM oauth_tokens WHERE token_type = :token_type");
        $stmt->bindParam(':token_type', $tokenType);
        $stmt->execute();
        
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            // Mettre à jour le token existant
            $stmt = $conn->prepare("UPDATE oauth_tokens SET token_data = :token_data, updated_at = NOW() WHERE token_type = :token_type");
            $stmt->bindParam(':token_data', $tokenJson);
            $stmt->bindParam(':token_type', $tokenType);
            $stmt->execute();
        } else {
            // Créer un nouveau token
            $stmt = $conn->prepare("INSERT INTO oauth_tokens (token_type, token_data) VALUES (:token_type, :token_data)");
            $stmt->bindParam(':token_type', $tokenType);
            $stmt->bindParam(':token_data', $tokenJson);
            $stmt->execute();
        }
        
        return true;
    } catch(PDOException $e) {
        error_log('Erreur lors de la sauvegarde du token : ' . $e->getMessage());
        return false;
    }
}
?>
