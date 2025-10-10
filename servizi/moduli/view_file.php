<?php
session_start();
include_once("../../database.php");
$pdo1 = Database::getInstance('fillea');

// 1. Recupera i parametri
$file_id = $_GET['id'] ?? null;
$token = $_GET['token'] ?? null;

if (!$file_id) {
    http_response_code(400);
    die('ID file mancante.');
}

// 2. Autenticazione utente o admin
$user_id = null;
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($is_admin) {
    // L'admin è autenticato tramite sessione. Non serve altro per l'autenticazione.
} elseif ($token) {
    // L'utente è autenticato tramite token.
    $stmt_user = $pdo1->prepare("SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW() LIMIT 1");
    $stmt_user->execute([$token]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $user_id = $user['id'];
    }
}

// 3. Recupera le informazioni del file dal database
$stmt_file = $pdo1->prepare("SELECT * FROM `fillea-app`.`richieste_allegati` WHERE id = ?");
$stmt_file->execute([$file_id]);
$file_info = $stmt_file->fetch(PDO::FETCH_ASSOC);

if (!$file_info) {
    http_response_code(404);
    die('File non trovato nel database.');
}

// 4. Autorizzazione: verifica se l'utente ha il permesso di vedere il file
// Un admin può vedere qualsiasi file.
// Un utente può vedere solo i propri file.
if (!$is_admin && $user_id !== $file_info['user_id']) {
    http_response_code(403);
    die('Accesso non autorizzato a questo file.');
}

// 5. Servi il file
$file_path = $file_info['file_path'];
if (!file_exists($file_path) || !is_readable($file_path)) {
    http_response_code(404);
    die('File fisico non trovato o non leggibile sul server.');
}

// Determina il tipo MIME del file per un corretto rendering nel browser
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file_path);

// Prepara gli header per il browser
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));

// 'inline' suggerisce al browser di mostrare il file, se possibile.
// 'attachment' suggerirebbe di scaricarlo.
header('Content-Disposition: inline; filename="' . basename($file_info['original_filename']) . '"');

// Disabilita la cache del browser per questo file
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Leggi il file e invialo all'output
readfile($file_path);
exit;
?>