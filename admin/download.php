<?php
session_start();

// 1. Protezione dello script
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('Accesso non autorizzato.');
}

if (!isset($_GET['token'])) {
    http_response_code(400);
    die('Token di download mancante.');
}

include_once('../database.php');
$pdo1 = Database::getInstance('fillea');

$token = $_GET['token'];

// 2. Cerca il token nel database
$stmt = $pdo1->prepare("SELECT * FROM `fillea-app`.`download_links` WHERE token = ? AND expires_at > NOW()");
$stmt->execute([$token]);
$link_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$link_data) {
    http_response_code(404);
    die('Link di download non valido o scaduto.');
}

$file_path = $link_data['file_path'];

if (!file_exists($file_path)) {
    http_response_code(404);
    die('File non trovato sul server.');
}

// 3. Invalida il token per prevenire riutilizzi
try {
    $stmt_delete = $pdo1->prepare("DELETE FROM `fillea-app`.`download_links` WHERE id = ?");
    $stmt_delete->execute([$link_data['id']]);
} catch (Exception $e) {
    // Logga l'errore ma non bloccare il download
    error_log("Impossibile eliminare il token di download ID {$link_data['id']}: " . $e->getMessage());
}

// 4. Servi il file per il download
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// Pulisci il buffer di output prima di inviare il file
ob_clean();
flush();

readfile($file_path);

// Dopo il download, il file ZIP può essere eliminato
unlink($file_path);

exit;
?>