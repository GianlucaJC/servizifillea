<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['endpoint'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Endpoint mancante.']);
    exit;
}

include_once('database.php');
$pdo1 = Database::getInstance('fillea');

$user_id = null;
$funzionario_id = null;

// Identifica l'utente (logica simile a save_subscription.php)
if (isset($data['token'])) {
    $stmt_user = $pdo1->prepare("SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW()");
    $stmt_user->execute([$data['token']]);
    $user = $stmt_user->fetch();
    if ($user) $user_id = $user['id'];
} elseif (isset($_SESSION['admin_logged_in'])) {
    $funzionario_id = $_SESSION['funzionario_id'];
}

if (!$user_id && !$funzionario_id) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Utente non autenticato per la rimozione.']);
    exit;
}

$stmt = $pdo1->prepare("DELETE FROM `fillea-app`.push_subscriptions WHERE endpoint = ?");
$stmt->execute([$data['endpoint']]);

echo json_encode(['status' => 'success']);