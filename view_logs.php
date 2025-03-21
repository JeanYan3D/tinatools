<?php
/**
 * Script pour lister, afficher et effacer les fichiers de logs
 * Ce script permet de voir les requêtes reçues de Vapi.ai
 */

// Définir le dossier des logs
$log_dir = __DIR__ . '/logs';

// Créer le dossier s'il n'existe pas
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}

// Traitement des actions
$action = $_GET['action'] ?? 'list';
$file = $_GET['file'] ?? '';
$message = '';

// Sécurité : vérifier que le fichier est bien dans le dossier des logs
if ($file && !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $file)) {
    $message = "Nom de fichier invalide";
    $action = 'list';
    $file = '';
}

// Action pour effacer un fichier
if ($action === 'delete' && $file) {
    $filepath = $log_dir . '/' . $file;
    if (file_exists($filepath) && is_file($filepath)) {
        unlink($filepath);
        $message = "Fichier supprimé avec succès";
    } else {
        $message = "Fichier introuvable";
    }
    $action = 'list';
}

// Action pour effacer tous les fichiers
if ($action === 'delete_all') {
    $files = glob($log_dir . '/*');
    $count = 0;
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            $count++;
        }
    }
    $message = "$count fichiers supprimés";
    $action = 'list';
}

// Récupérer la liste des fichiers
$files = [];
if ($action === 'list') {
    $glob_pattern = $log_dir . '/*';
    $file_paths = glob($glob_pattern);
    
    foreach ($file_paths as $file_path) {
        if (is_file($file_path)) {
            $files[] = [
                'name' => basename($file_path),
                'size' => filesize($file_path),
                'time' => filemtime($file_path)
            ];
        }
    }
    
    // Trier par date (plus récent en premier)
    usort($files, function($a, $b) {
        return $b['time'] - $a['time'];
    });
}

// Afficher le contenu d'un fichier
$file_content = '';
if ($action === 'view' && $file) {
    $filepath = $log_dir . '/' . $file;
    if (file_exists($filepath) && is_file($filepath)) {
        $file_content = file_get_contents($filepath);
        
        // Essayer de formater le JSON si c'est du JSON valide
        if (json_decode($file_content) !== null) {
            $file_content = json_encode(json_decode($file_content), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    } else {
        $message = "Fichier introuvable";
        $action = 'list';
    }
}

// Fonction pour formater la taille du fichier
function formatFileSize($size) {
    $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
    $power = $size > 0 ? floor(log($size, 1024)) : 0;
    return round($size / pow(1024, $power), 2) . ' ' . $units[$power];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualisation des logs Vapi.ai</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2 {
            color: #333;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            background-color: #f8f9fa;
            border-left: 4px solid #28a745;
        }
        .message.error {
            border-left-color: #dc3545;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        pre {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        .actions {
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            margin-right: 10px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .search-box input {
            padding: 8px;
            width: 300px;
        }
        .highlight {
            background-color: yellow;
        }
        .file-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>Visualisation des logs Vapi.ai</h1>
    
    <?php if ($message): ?>
    <div class="message <?php echo strpos($message, 'erreur') !== false ? 'error' : ''; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="actions">
        <a href="?action=list" class="btn">Liste des fichiers</a>
        <a href="?action=delete_all" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer tous les fichiers de logs ?');">Supprimer tous les logs</a>
    </div>
    
    <?php if ($action === 'list'): ?>
        <?php if (empty($files)): ?>
            <p>Aucun fichier de log trouvé.</p>
        <?php else: ?>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Rechercher dans les noms de fichiers..." onkeyup="searchFiles()">
            </div>
            
            <table id="filesTable">
                <thead>
                    <tr>
                        <th>Nom du fichier</th>
                        <th>Taille</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file_info): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file_info['name']); ?></td>
                        <td><?php echo formatFileSize($file_info['size']); ?></td>
                        <td><?php echo date('Y-m-d H:i:s', $file_info['time']); ?></td>
                        <td>
                            <a href="?action=view&file=<?php echo urlencode($file_info['name']); ?>" class="btn">Voir</a>
                            <a href="?action=delete&file=<?php echo urlencode($file_info['name']); ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?');">Supprimer</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if ($action === 'view' && $file): ?>
        <div class="file-info">
            <h2>Fichier : <?php echo htmlspecialchars($file); ?></h2>
            <p>
                <input type="text" id="jsonSearch" placeholder="Rechercher dans le contenu..." style="width: 300px; padding: 5px;">
                <button onclick="searchInJson()">Rechercher</button>
            </p>
        </div>
        
        <pre id="jsonContent"><?php echo htmlspecialchars($file_content); ?></pre>
        
        <script>
            function searchInJson() {
                const searchTerm = document.getElementById('jsonSearch').value.toLowerCase();
                const content = document.getElementById('jsonContent');
                const originalText = content.textContent;
                
                if (!searchTerm) {
                    content.innerHTML = originalText;
                    return;
                }
                
                let highlightedText = originalText;
                const regex = new RegExp(searchTerm, 'gi');
                highlightedText = highlightedText.replace(regex, match => `<span class="highlight">${match}</span>`);
                
                content.innerHTML = highlightedText;
            }
        </script>
    <?php endif; ?>
    
    <script>
        function searchFiles() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('filesTable');
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td')[0];
                if (td) {
                    const txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = '';
                    } else {
                        tr[i].style.display = 'none';
                    }
                }
            }
        }
    </script>
</body>
</html>
