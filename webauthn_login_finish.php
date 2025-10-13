<?php

// CRITICO: Test di scrittura immediato per confermare che lo script si avvia.
// Se questo file non viene creato, l'errore è nella configurazione del server (es. permessi)
// e non nel codice PHP.
file_put_contents('webauthn_login_finish_steps.log', '['.date('Y-m-d H:i:s').'] - Script avviato.'."\n");

require_once 'vendor/autoload.php';
session_start();
include_once 'database.php';

// Riduciamo le dipendenze al minimo indispensabile per il nostro flusso di validazione manuale.
// Questo previene errori fatali se classi non necessarie cambiano o vengono rimosse nella libreria.
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;

header('Content-Type: application/json');

// CRITICO: Avvia il buffer di output. Questo cattura qualsiasi output prematuro (es. errori PHP)
// e ci permette di pulirlo prima di inviare la nostra risposta JSON.
ob_start();

// --- INIZIO LOGGER DI DEBUG DETTAGLIATO ---
function write_log($message) {
    $log_file = 'webauthn_login_finish_steps.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] - $message\n", FILE_APPEND);
}
// --- FINE LOGGER DI DEBUG DETTAGLIATO ---

// Recupera il corpo della richiesta come stringa JSON grezza
$json_data = file_get_contents('php://input');
write_log("Corpo della richiesta ricevuto: " . $json_data);

try {
    // Ottieni l'istanza del database per le credenziali
    $pdo1 = Database::getInstance('fillea');
    write_log("Connessione al database 'fillea' ottenuta.");

    // 1. Recupera le opzioni di sfida dalla sessione
    if (!isset($_SESSION['webauthn_challenge_options'])) {
        throw new Exception('Nessuna sfida di autenticazione trovata nella sessione.');
    }
    $publicKeyCredentialRequestOptionsArray = $_SESSION['webauthn_challenge_options'];
    write_log("Opzioni della sfida recuperate dalla sessione.");

    // 2. Inizializza il loader con i componenti necessari per il parsing
    // CRITICO: Il PublicKeyCredentialLoader richiede SEMPRE un AttestationObjectLoader valido
    // per poter fare il parsing della risposta del browser, anche se non validiamo l'attestazione.
    $attestationStatementSupportManager = new AttestationStatementSupportManager();
    $attestationStatementSupportManager->add(new NoneAttestationStatementSupport());
    $attestationObjectLoader = new AttestationObjectLoader($attestationStatementSupportManager);
    $publicKeyCredentialLoader = new PublicKeyCredentialLoader($attestationObjectLoader);
    $publicKeyCredential = $publicKeyCredentialLoader->load($json_data);
    $response = $publicKeyCredential->getResponse();
    write_log("Oggetto PublicKeyCredential caricato con successo.");

    if (!$response instanceof AuthenticatorAssertionResponse) {
        throw new Exception('Risposta di asserzione non valida.');
    }

    // La firma corretta del metodo check in questo scenario richiede solo la risposta e le opzioni.
    // =================================================================================
    // == APPROCCIO DI VALIDAZIONE MANUALE PER BYPASSARE IL FATAL ERROR DELLA LIBRERIA ==
    // =================================================================================
    write_log("Inizio validazione manuale.");

    // 3. Recupera la credenziale dal DB usando l'ID ricevuto dal browser
    // CRITICO: L'ID deve essere codificato in Base64URL Safe per corrispondere a come viene salvato
    // durante la registrazione. Leggiamo l'ID direttamente dal JSON inviato dal client
    // per evitare problemi di doppia codifica.
    // SOLUZIONE DEFINITIVA: Usiamo il metodo ufficiale della libreria per ottenere l'ID binario
    // e lo ri-codifichiamo noi stessi in Base64URL Safe, garantendo coerenza con la registrazione.
    $credentialIdBase64 = \ParagonIE\ConstantTime\Base64UrlSafe::encodeUnpadded($publicKeyCredential->getRawId());
    write_log("ID calcolato dalla risposta del browser: " . $credentialIdBase64);

    $stmt = $pdo1->prepare("SELECT * FROM `fillea-app`.webauthn_credentials WHERE credential_id_base64 = ?");
    $stmt->execute([$credentialIdBase64]);
    $credentialData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$credentialData) {
        throw new Exception("Credenziale non trovata nel database.");
    }
    write_log("Credenziale trovata nel DB per l'utente ID: " . $credentialData['user_id']);

    // 4. Verifica l'origine (Origin)
    // CRITICO: Costruiamo l'origine dinamicamente per gestire sia 'www' che non-'www'.
    // Questo risolve l'errore di mancate corrispondenze dell'origine.
    $origin = 'https://www.filleaoffice.it:8013'; // Impostazione statica per massima coerenza
    $clientDataJSON = $response->clientDataJSON;
    if ($clientDataJSON->origin !== $origin) {
        throw new Exception("L'origine della richiesta non corrisponde. Atteso: $origin, Ricevuto: " . $clientDataJSON->origin);
    }
    write_log("Validazione origine OK.");

    // 5. Verifica la sfida (Challenge)
    $challengeInResponse = $clientDataJSON->challenge;
    // CRITICO: La sfida salvata in sessione è già codificata in Base64URL.
    // Dobbiamo solo decodificarla per confrontarla con quella (binaria) ricevuta dal browser.
    // Questo risolve il TypeError che causava il fallimento dello Step 4.
    $challengeFromSession = \ParagonIE\ConstantTime\Base64UrlSafe::decodeNoPadding($publicKeyCredentialRequestOptionsArray['challenge']);
    if (!hash_equals($challengeInResponse, $challengeFromSession)) {
        throw new Exception("La sfida non corrisponde.");
    }
    write_log("Validazione sfida OK.");

    // 6. Verifica la firma
    $authenticatorData = $response->authenticatorData;
    $signature = $response->signature;
    $publicKey = base64_decode($credentialData['public_key_base64']);
    // CRITICO: I dati da verificare sono la concatenazione dei dati dell'autenticatore
    // e l'hash SHA-256 del clientDataJSON grezzo.
    // Usiamo il metodo getAuthenticatorData() della libreria per essere sicuri del formato.
    $dataToVerify = $response->authenticatorData->getAuthenticatorData() . hash('sha256', $clientDataJSON->getRawData(), true);

    $isSignatureValid = openssl_verify($dataToVerify, $signature, $publicKey, OPENSSL_ALGO_SHA256);
    if ($isSignatureValid !== 1) {
        throw new Exception("Firma non valida.");
    }
    write_log("Validazione firma OK.");

    // 7. Verifica il contatore per prevenire attacchi di replay
    $counterFromAuthenticator = $authenticatorData->signCount;
    $counterFromDb = (int)$credentialData['counter'];
    if ($counterFromAuthenticator <= $counterFromDb) {
        throw new Exception("Contatore non valido, possibile attacco di replay. DB: $counterFromDb, Auth: $counterFromAuthenticator");
    }
    write_log("Validazione contatore OK.");

    // 8. Autenticazione riuscita: aggiorna il contatore e genera il token di sessione
    write_log("VALIDAZIONE MANUALE COMPLETATA CON SUCCESSO!");
    $stmt = $pdo1->prepare("UPDATE `fillea-app`.webauthn_credentials SET counter = ? WHERE credential_id_base64 = ?");
    $stmt->execute([$counterFromAuthenticator, $credentialIdBase64]);

    $userId = $credentialData['user_id'];

    $token = bin2hex(random_bytes(16));
    $expiry = date('Y-m-d H:i:s', time() + 3600);
    $stmt = $pdo1->prepare("UPDATE `fillea-app`.users SET token = ?, token_expiry = ? WHERE id = ?");
    $stmt->execute([$token, $expiry, $userId]);

    $_SESSION['user_token'] = $token; // Salva il nuovo token nella sessione
    // Per evitare conflitti, se un utente si logga, distruggiamo qualsiasi sessione admin attiva.
    if (isset($_SESSION['admin_logged_in'])) {
        unset($_SESSION['admin_logged_in']);
        unset($_SESSION['admin_username']);
        unset($_SESSION['funzionario_id']);
        unset($_SESSION['is_super_admin']);
    }
    unset($_SESSION['webauthn_challenge_options']);

    $final_response = ['status' => 'success', 'token' => $token];

} catch (Throwable $e) {
    write_log("ERRORE CATTURATO: " . $e->getMessage() . " nel file " . $e->getFile() . " alla linea " . $e->getLine());
    
    // CRITICO: Pulisci qualsiasi output precedente (errori, warning, etc.) dal buffer.
    ob_end_clean();

    http_response_code(400);
    $final_response = ['status' => 'error', 'message' => $e->getMessage()];
} finally {
    // Invia la risposta JSON finale al browser.
    write_log("Invio risposta finale al browser: " . json_encode($final_response));
    // Se non siamo già nel blocco catch, assicuriamoci che il buffer sia pulito prima di inviare la risposta finale.
    // Questo è utile se ob_start() è stato chiamato ma il blocco catch non è stato eseguito.
    if (ob_get_level() > 0 && !isset($e)) { ob_clean(); }
    echo json_encode($final_response);
    if (ob_get_level() > 0) { ob_end_flush(); } // Invia il contenuto del buffer al client.
}