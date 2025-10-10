<?php

require_once 'vendor/autoload.php';
session_start();
include_once 'database.php';

use Webauthn\PublicKeyCredentialLoader;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;

header('Content-Type: application/json');

// CRITICO: Leggiamo il corpo della richiesta UNA SOLA VOLTA e lo salviamo.
$json_data = file_get_contents('php://input');

// --- INIZIO CODICE DI DEBUG ---
// Questo codice cattura la richiesta in arrivo e la salva nel file "debug_log.txt".
// È il modo migliore per vedere esattamente cosa sta inviando il browser.
$debug_data = "--- Inizio Richiesta " . date('Y-m-d H:i:s') . " ---\n";
$debug_data .= "Metodo Richiesta: " . $_SERVER['REQUEST_METHOD'] . "\n\n";
$debug_data .= "HEADER:\n";
$debug_data .= json_encode(getallheaders(), JSON_PRETTY_PRINT) . "\n\n";
$debug_data .= "CORPO (RAW):\n";
$debug_data .= $json_data . "\n";
$debug_data .= "--- Fine Richiesta ---\n\n";

// Scrive nel file di log. Se il file non esiste, lo crea.
file_put_contents('debug_log.txt', $debug_data, FILE_APPEND);
// --- FINE CODICE DI DEBUG ---

$data = json_decode($json_data, true);

// Ottieni l'istanza del database per le credenziali
$pdo1 = Database::getInstance('fillea');

try {
    // 1. Recupera le opzioni di sfida dalla sessione
    if (!isset($_SESSION['webauthn_creation_options'])) {
        throw new Exception('Nessuna sfida di registrazione trovata nella sessione.');
    }
    $publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::createFromArray($_SESSION['webauthn_creation_options']);

    // 2. Inizializza i componenti necessari per la validazione
    $attestationStatementSupportManager = new AttestationStatementSupportManager();
    $attestationStatementSupportManager->add(new NoneAttestationStatementSupport()); // Supporta l'attestazione "none"
    $attestationObjectLoader = new AttestationObjectLoader($attestationStatementSupportManager);

    $publicKeyCredentialLoader = new PublicKeyCredentialLoader($attestationObjectLoader);
    $publicKeyCredential = $publicKeyCredentialLoader->load($json_data);
    $response = $publicKeyCredential->getResponse();

    if (!$response instanceof AuthenticatorAttestationResponse) {
        throw new Exception('Risposta di attestazione non valida.');
    }

    // 3. Verifica la risposta
    // Semplifichiamo il validatore. Per la registrazione con attestazione 'none',
    // non è necessario passare il repository. Questo evita conflitti interni alla libreria.
    $authenticatorAttestationResponseValidator = new AuthenticatorAttestationResponseValidator(
        $attestationStatementSupportManager, // Argomento 1: corretto
        null, // Argomento 2: publicKeyCredentialSourceRepository (non necessario qui)
        null, // Argomento 3: tokenBindingHandler (non necessario)
        new ExtensionOutputCheckerHandler()
    );
    // --- DEBUG: Impostazione statica dell'rpId ---
    // Per eliminare ogni dubbio, impostiamo l'rpId staticamente in base all'ambiente.
    if (str_contains($_SERVER['SERVER_NAME'], 'localhost')) {
        $rpId = 'localhost';
    } else {
        $rpId = 'filleaoffice.it'; // Ambiente di produzione (dominio principale senza 'www')
    }
    // --- FINE DEBUG ---
    $origin = 'https://www.filleaoffice.it:8013'; // Impostazione statica per massima coerenza
    // L'rpId è già stato "pulito", quindi possiamo usarlo direttamente.
    $allowedRpIds = [$rpId];

    $publicKeyCredentialSource = $authenticatorAttestationResponseValidator->check(
        $response,
        $publicKeyCredentialCreationOptions,
        // CRITICO: Dalla v4.10, il terzo parametro è l'origine della richiesta (es. 'https://filleaoffice.it:8013')
        $origin,
        $allowedRpIds // E un array di rpId consentiti
    );

    // 4. Salva la nuova credenziale nel database
    // Assicurati che la tabella 'webauthn_credentials' abbia le colonne 'counter' e 'aaguid'
    $transportsAsJson = json_encode($publicKeyCredentialSource->getTransports());
    $stmt = $pdo1->prepare(
        "INSERT INTO `fillea-app`.webauthn_credentials (user_id, credential_id_base64, public_key_base64, attestation_type, aaguid, transports, counter, device_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    // Recupera l'ID utente numerico originale dalle opzioni salvate in sessione.
    $userId = unpack('N', $publicKeyCredentialSource->getUserHandle())[1];
    
    // Salva l'ID utente numerico corretto.
    $stmt->execute([
        $userId,
        // CRITICO: Usiamo Base64URL Safe per coerenza con il processo di login.
        \ParagonIE\ConstantTime\Base64UrlSafe::encodeUnpadded($publicKeyCredentialSource->getPublicKeyCredentialId()),
        base64_encode($publicKeyCredentialSource->getCredentialPublicKey()),
        $publicKeyCredentialSource->getAttestationType(),
        $publicKeyCredentialSource->getAaguid()->toRfc4122(),
        $transportsAsJson,
        $publicKeyCredentialSource->getCounter(),
        $data['deviceName'] ?? 'Dispositivo Sconosciuto'
    ]);
    
    // Rimuovi la sfida dalla sessione dopo l'uso
    unset($_SESSION['webauthn_creation_options']);

    echo json_encode(['status' => 'success', 'message' => 'Dispositivo registrato con successo!']);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}