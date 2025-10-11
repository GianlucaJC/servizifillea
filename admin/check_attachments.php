<?php
session_start();
header('Content-Type: application/json');

// 1. Protezione dello script
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Accesso non autorizzato.']);
    exit;
}

if (!isset($_GET['form_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Nome del form mancante.']);
    exit;
}

include_once('../database.php');
$pdo1 = Database::getInstance('fillea');

$form_name = $_GET['form_name'];
$funzionario_id = $_SESSION['funzionario_id'] ?? 0;

// 2. Query per contare gli allegati
$stmt_files = $pdo1->prepare("
    SELECT COUNT(ra.id)
    FROM `fillea-app`.richieste_allegati ra
    JOIN `fillea-app`.richieste_master rm ON ra.form_name = rm.form_name AND ra.user_id = rm.user_id
    WHERE ra.form_name = ? AND rm.id_funzionario = ? COLLATE utf8mb4_unicode_ci
");

$stmt_files->execute([$form_name, $funzionario_id]);
$count = $stmt_files->fetchColumn();

// 3. Restituisce il risultato in formato JSON
echo json_encode([
    'has_attachments' => $count > 0,
    'count' => $count
]);
?>