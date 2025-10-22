<?php
include_once 'session_config.php';
session_start();

header('Content-Type: application/json');

// 1. Verifica che la richiesta sia di tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metodo non consentito.']);
    exit;
}

// 2. Recupera e verifica il token
$token = $_POST['token'] ?? '';
if (empty($token)) {
    echo json_encode(['status' => 'error', 'message' => 'Token mancante.']);
    exit;
}

include_once("database.php");
$pdo1 = Database::getInstance('fillea');

// 3. Recupera l'ID utente dal token
$stmt_user = $pdo1->prepare("SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW()");
$stmt_user->execute([$token]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'Utente non valido o sessione scaduta.']);
    exit;
}

// 4. Aggiorna il flag delle notifiche per l'utente
$stmt_update = $pdo1->prepare("UPDATE `fillea-app`.richieste_master SET user_notification_unseen = 0 WHERE user_id = ?");
$stmt_update->execute([$user['id']]);

echo json_encode(['status' => 'success']);
?>