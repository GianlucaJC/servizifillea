<?php

// Abilita la visualizzazione degli errori per il debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';
session_start();
include_once 'database.php';

use Webauthn\{PublicKeyCredentialDescriptor, PublicKeyCredentialRequestOptions};
use Webauthn\PublicKeyCredentialSource;

header('Content-Type: application/json');

$input_data = json_decode(file_get_contents('php://input'), true);
$email = $input_data['username'] ?? '';
$pdo1 = Database::getInstance('fillea');

// 1. Trova l'utente e le sue credenziali registrate
$allowedCredentialDescriptors = [];

// STRATEGIA CORRETTA: Se l'utente ha fornito un'email, recuperiamo solo le sue credenziali.
if (!empty($email)) {
    // Trova l'ID dell'utente dall'email
    $stmt_user = $pdo1->prepare("SELECT id FROM `fillea-app`.users WHERE email = ?");
    $stmt_user->execute([$email]);
    $user = $stmt_user->fetch(\PDO::FETCH_ASSOC);
    
    if ($user) {
        // Recupera solo le credenziali per questo utente specifico
        $stmt_creds = $pdo1->prepare("SELECT credential_id_base64 FROM `fillea-app`.webauthn_credentials WHERE user_id = ?");
        $stmt_creds->execute([$user['id']]);
        $results = $stmt_creds->fetchAll(\PDO::FETCH_ASSOC);
        
        // Popola l'array di descrittori di credenziali consentite
        foreach ($results as $row) {
            $allowedCredentialDescriptors[] = new PublicKeyCredentialDescriptor(
                'public-key',
                base64_decode($row['credential_id_base64'])
            );
        }
    }
}

if (str_contains($_SERVER['SERVER_NAME'], 'localhost')) {
    $rpId = 'localhost';
} else {
    $rpId = 'filleaoffice.it'; // Ambiente di produzione (dominio principale senza 'www')
}
/*
 * CRITICO: Determina il tipo di userVerification. Se non ci sono credenziali specifiche,
 * usiamo 'preferred' per attivare la modalità "discoverable credentials" (passkey).
 * Altrimenti, 'required' è corretto.
 */
$userVerification = !empty($allowedCredentialDescriptors) ? 'required' : 'preferred';

// 2. Genera le opzioni per la richiesta di login (la "sfida")
$publicKeyCredentialRequestOptions = PublicKeyCredentialRequestOptions::create(
    random_bytes(32),
    $rpId,
    $allowedCredentialDescriptors,
    $userVerification,
    60000             // timeout
);

// 3. Salva le opzioni nella sessione per la verifica successiva
$optionsAsArray = $publicKeyCredentialRequestOptions->jsonSerialize();

// CRITICO: Il metodo jsonSerialize() della libreria omette la chiave 'allowCredentials' se l'array è vuoto.
// Questo crea un JSON non valido per il browser. Lo aggiungiamo manualmente per risolvere il problema.
if (!array_key_exists('allowCredentials', $optionsAsArray)) {
    $optionsAsArray['allowCredentials'] = [];
}

$_SESSION['webauthn_challenge_options'] = $optionsAsArray;

echo json_encode($optionsAsArray);