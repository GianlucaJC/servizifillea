<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
session_start();

// 1. Proteggi lo script: solo gli admin possono accedervi.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.0 403 Forbidden');
    die('Accesso non autorizzato.');
}

// 2. Verifica che il nome del form sia stato passato.
if (!isset($_GET['form_name'])) {
    header('HTTP/1.0 400 Bad Request');
    die('Nome della pratica non specificato.');
}

$form_name = $_GET['form_name'];

// 3. Includi la connessione al database e la classe del generatore PDF.
include_once('../database.php');
require_once 'generate_pdf_summary.php';

$pdo1 = Database::getInstance('fillea');

// 4. Recupera i dati della pratica dal database.
// Determina la tabella corretta (modulo1 o modulo2) in base al nome del form.
$table_name = strpos($form_name, 'form2_') === 0 ? 'modulo2_richieste' : 'modulo1_richieste';

$stmt_data = $pdo1->prepare("SELECT * FROM `fillea-app`.`{$table_name}` WHERE form_name = ?");
$stmt_data->execute([$form_name]);
$form_data = $stmt_data->fetch(PDO::FETCH_ASSOC);

// Decodifica il campo JSON delle prestazioni, necessario per la logica di compilazione
if ($form_data && !empty($form_data['prestazioni'])) {
    $form_data['prestazioni_decoded'] = json_decode($form_data['prestazioni'], true);
}


if (!$form_data) {
    header('HTTP/1.0 404 Not Found');
    die('Dati della pratica non trovati.');
}

// 5. Genera e invia il PDF al browser.
try {
    $pdf_generator = new PDFTemplateFiller();
    
    // Chiamiamo il metodo che genera il PDF e lo invia direttamente al browser.
    $pdf_generator->generateAndOutputToBrowser($form_data, $table_name);

} catch (Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    die('Errore durante la generazione del PDF: ' . $e->getMessage());
}

exit;
?>