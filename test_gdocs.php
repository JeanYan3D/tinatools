<?php
/**
 * Script de test pour simuler une requête Vapi.ai vers gdocs_creator.php
 */

// Données de test
$testData = [
    'title' => 'Test de document via API en ligne',
    'content' => 'Voici un document de test créé automatiquement via l\'API Google Docs en ligne. Ce test a été effectué le ' . date('d/m/Y à H:i:s') . '.'
];

// URL du script à tester (en ligne)
$url = 'https://louisy.ai/tinatools/gdocs_creator.php';

// Initialisation de cURL
$ch = curl_init($url);

// Configuration de la requête
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

// Exécution de la requête
$response = curl_exec($ch);

// Vérification des erreurs
if (curl_errno($ch)) {
    echo "Erreur cURL: " . curl_error($ch) . "\n";
} else {
    // Affichage de la réponse
    echo "Réponse du serveur:\n";
    $responseData = json_decode($response, true);
    echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    // Si le document a été créé avec succès, afficher l'URL
    if (isset($responseData['success']) && $responseData['success'] === true) {
        echo "\nDocument créé avec succès !\n";
        echo "URL du document: " . $responseData['documentUrl'] . "\n";
    }
}

// Fermeture de la connexion cURL
curl_close($ch);
