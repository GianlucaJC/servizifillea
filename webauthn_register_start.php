<?php

require_once 'vendor/autoload.php';
include_once 'session_config.php'; // Includi la configurazione della sessione
session_start(); // Avvia la sessione DOPO aver impostato i parametri
include_once 'database.php';

use Webauthn\PublicKeyCredentialCreationOptions; // Assicuriamoci che sia presente
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialDescriptor;

header('Content-Type: application/json');

// 1. Verifica che l'utente sia loggato tramite token
$token = $_SESSION['user_token'] ?? '';
if (empty($token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Utente non autenticato.']);
    exit;
}

$pdo1 = Database::getInstance('fillea');

$stmt = $pdo1->prepare("SELECT id, email, nome, cognome FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Token non valido o scaduto.']);
    exit;
}

// Costruisci l'ID del Relying Party (dominio) e l'origine completa
// --- DEBUG: Impostazione statica dell'rpId ---
// Per eliminare ogni dubbio, impostiamo l'rpId staticamente in base all'ambiente.
if (str_contains($_SERVER['SERVER_NAME'], 'localhost')) {
    $rpId = 'localhost';
} else {
    $rpId = 'filleaoffice.it'; // Ambiente di produzione (dominio principale senza 'www')
}
// --- FINE DEBUG ---

// 2. Definisci l'entità che richiede la registrazione (il tuo sito)
$rpEntity = PublicKeyCredentialRpEntity::create(
    'Fillea Service App', // Nome del tuo servizio
    $rpId, // ID del dominio (es. 'localhost' o 'www.tuosito.it')
    null // URL dell'icona (opzionale)
);

// 3. Definisci l'utente che sta registrando il dispositivo
$userEntity = PublicKeyCredentialUserEntity::create(
    $user['email'],
    // Convertiamo l'ID utente in una stringa binaria.
    // Questo assicura che la libreria lo tratti come un handle binario,
    // risolvendo l'errore "insecure operation" nel browser.
    pack('N', $user['id']),
    $user['cognome'] . ' ' . $user['nome']
);

// 4. Crea le opzioni di registrazione (la "sfida")
$challenge = random_bytes(32);

// Definisci gli algoritmi di crittografia supportati (ES256 è il più comune)
$pubKeyCredParams = [
    new PublicKeyCredentialParameters('public-key', -7), // ES256
    new PublicKeyCredentialParameters('public-key', -257), // RS256
];

// 5. (NUOVO) Recupera le credenziali esistenti per escluderle
// Questo è un passaggio di sicurezza importante e risolve l'errore "insecure"
$excludeCredentials = [];
$stmt_exclude = $pdo1->prepare("SELECT credential_id_base64 FROM `fillea-app`.webauthn_credentials WHERE user_id = ?");
$stmt_exclude->execute([$user['id']]);
$existing_credentials = $stmt_exclude->fetchAll(PDO::FETCH_ASSOC);

foreach ($existing_credentials as $cred) {
    $excludeCredentials[] = new PublicKeyCredentialDescriptor(
        'public-key',
        base64_decode($cred['credential_id_base64'])
    );
}

// (NUOVO) Specifichiamo di preferire l'autenticatore integrato (impronta, viso, PIN).
// Questo risolve il problema della richiesta di una chiavetta USB.
// Usiamo i parametri nominati per evitare errori di posizione.
$authenticatorSelectionCriteria = new AuthenticatorSelectionCriteria(
    authenticatorAttachment: 'platform',
    userVerification: 'required', // Richiede la verifica dell'utente (impronta, PIN, etc.)
    // Passando 'true' qui, la libreria imposterà correttamente 'residentKey' a 'required'
    requireResidentKey: true 
);

// Usiamo i parametri nominati per chiarezza e per evitare errori di posizione.
$creationOptions = PublicKeyCredentialCreationOptions::create(
    rp: $rpEntity,
    user: $userEntity,
    challenge: $challenge,
    pubKeyCredParams: $pubKeyCredParams,
    timeout: 60000, // Aumenta il timeout a 60 secondi
    authenticatorSelection: $authenticatorSelectionCriteria, // Aggiungiamo la preferenza qui
    excludeCredentials: $excludeCredentials, // Passiamo le credenziali da escludere qui
    attestation: 'none' // Impostiamo esplicitamente l'attestazione
);

// 6. Salva le opzioni ORIGINALI nella sessione per la verifica successiva.
// La libreria si aspetta l'oggetto completo per la validazione.
$_SESSION['webauthn_creation_options'] = json_decode(json_encode($creationOptions), true);

// 7. Prepara le opzioni per il client, rimuovendo il campo deprecato per massima compatibilità.
$optionsForClient = $_SESSION['webauthn_creation_options'];

// CRITICO: La libreria può generare sia 'residentKey' che il deprecato 'requireResidentKey'.
// Rimuoviamo manualmente quello deprecato per evitare che i browser (specialmente Firefox)
// generino un errore a causa del conflitto.
unset($optionsForClient['authenticatorSelection']['requireResidentKey']);

// 8. Invia le opzioni "pulite" al client.
echo json_encode($optionsForClient);