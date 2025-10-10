<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['endpoint'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Endpoint mancante.']);
    exit;
}

include_once('../database.php');
$pdo1 = Database::getInstance('fillea');

$user_id = null;
$funzionario_id = null;

// Identifica l'utente (logica simile a save_subscription.php)
// Priorità 1: Admin loggato da sessione
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && isset($_SESSION['funzionario_id'])) {
    $user_id = $_SESSION['funzionario_id'];
    $funzionario_id = $_SESSION['funzionario_id'];
}
// Priorità 2: Utente identificato tramite token nel payload
elseif (isset($data['token']) && !empty($data['token'])) {
    $stmt_user = $pdo1->prepare("SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW()");
    $stmt_user->execute([$data['token']]);
    $user = $stmt_user->fetch();
    if ($user) $user_id = $user['id'];
}
// Priorità 3: Utente da token in sessione (fallback)
if (!$user_id && isset($_SESSION['user_token'])) {
    // ... logica per recuperare utente da sessione ...
}

if (!$user_id) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Utente non autenticato.']);
    exit;
}

try {
    $sql = "DELETE FROM `fillea-app`.push_subscriptions WHERE endpoint = ? AND (funzionario_id = ? OR user_id = ?)";
    $stmt = $pdo1->prepare($sql);
    $stmt->execute([$data['endpoint'], $funzionario_id, $user_id]);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Errore durante la rimozione dal database: ' . $e->getMessage()]);
}