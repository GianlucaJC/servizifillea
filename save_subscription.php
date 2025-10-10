<?php
session_start();

function write_sub_log($message) {
    $log_file = 'subscription_log.log';
    file_put_contents($log_file, '['.date('Y-m-d H:i:s').'] - ' . $message . "\n", FILE_APPEND);
}

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['endpoint'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dati di iscrizione mancanti.']);
    exit;
}

write_sub_log("Richiesta ricevuta. Endpoint: " . ($data['endpoint'] ?? 'N/A'));

include_once('database.php');
$pdo1 = Database::getInstance('fillea');

$user_id = null;
$funzionario_id = null;

// Determina chi si sta iscrivendo.
// Priorità 1: Un amministratore loggato (identificato dalla sessione PHP).
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && isset($_SESSION['funzionario_id'])) {
    // Se è un admin, salviamo l'ID solo nel campo funzionario_id.
    // user_id deve essere NULL per rispettare i vincoli del database.
    $funzionario_id = $_SESSION['funzionario_id'];
    write_sub_log("Identificato come ADMIN da sessione. ID Funzionario: " . $funzionario_id);
}
// Priorità 2: Utente/Lavoratore identificato tramite token nella richiesta (es. dalla pagina profilo.php)
elseif (isset($data['token']) && !empty($data['token'])) {
    $stmt_user = $pdo1->prepare("SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW()");
    $stmt_user->execute([$data['token']]);
    $user = $stmt_user->fetch();
    if ($user) {
        $user_id = $user['id'];
        write_sub_log("Identificato come UTENTE da token nel payload. ID: " . $user_id);
    }
}
// Priorità 3: Utente/Lavoratore da token in sessione (fallback, se il payload non lo contiene)
elseif (isset($_SESSION['user_token'])) { // Modificato da if a elseif per coerenza logica
    $stmt_user = $pdo1->prepare("SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW()");
    $stmt_user->execute([$_SESSION['user_token']]);
    $user = $stmt_user->fetch();
    if ($user) {
        $user_id = $user['id'];
        write_sub_log("Identificato come UTENTE da token in sessione. ID: " . $user_id);
    }
}


// Spostato il controllo alla fine per permettere a tutti i fallback di essere eseguiti.
if (!$user_id && !$funzionario_id) {
    write_sub_log("FALLIMENTO: Utente non autenticato dopo tutti i controlli.");
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Utente non autenticato.']);
    exit;
}
write_sub_log("Tentativo di salvare sottoscrizione con user_id: " . ($user_id ?? 'NULL') . " e funzionario_id: " . ($funzionario_id ?? 'NULL'));
try {
    // La query ON DUPLICATE KEY UPDATE è stata semplificata per maggiore chiarezza.
    $sql = "INSERT INTO `fillea-app`.push_subscriptions (user_id, funzionario_id, endpoint, p256dh, auth, content_encoding) 
            VALUES (:user_id, :funzionario_id, :endpoint, :p256dh, :auth, :content_encoding)
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), funzionario_id = VALUES(funzionario_id), content_encoding = VALUES(content_encoding)";
    
    $stmt = $pdo1->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
        ':funzionario_id' => $funzionario_id,
        ':endpoint' => $data['endpoint'],
        ':p256dh' => $data['keys']['p256dh'],
        ':auth' => $data['keys']['auth'],
        ':content_encoding' => $data['contentEncoding'] ?? 'aesgcm' // Salva il content encoding, con fallback a 'aesgcm'
    ]);
    
    //write_sub_log("SUCCESS: Sottoscrizione salvata nel DB per user_id: " . ($user_id ?? 'NULL') . " o funzionario_id: " . ($funzionario_id ?? 'NULL'));
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    //write_sub_log("ERRORE DB: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore durante il salvataggio nel database: ' . $e->getMessage()]);
}