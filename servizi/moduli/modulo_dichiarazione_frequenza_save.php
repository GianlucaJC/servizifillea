<?php
session_start();
header('Content-Type: application/json');

require_once '../../admin/generate_pdf_summary.php';

// 1. Inizializzazione e recupero dati
$token = $_POST['token'] ?? null;
$origin_form_name = $_POST['origin_form_name'] ?? null;

if (!$token || !$origin_form_name) {
    echo json_encode(['status' => 'error', 'message' => 'Accesso non autorizzato o parametri mancanti.']);
    exit;
}

include_once("../../database.php");
$pdo1 = Database::getInstance('fillea');

// 2. Verifica utente
$stmt_user = $pdo1->prepare("SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW()");
$stmt_user->execute([$token]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'Utente non valido o sessione scaduta.']);
    exit;
}
$user_id = $user['id'];

// 3. Raccolta dati dal form
$data_to_save = $_POST;
unset($data_to_save['token']); // Rimuovi dati non necessari

// 4. Salvataggio dati in file JSON
$data_dir = __DIR__ . '/autocertificazioni_data/';
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0755, true);
}
$json_filename = 'dich_frequenza_' . $origin_form_name . '.json';
$json_filepath = $data_dir . $json_filename;

if (file_put_contents($json_filepath, json_encode($data_to_save, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['status' => 'error', 'message' => 'Impossibile salvare i dati della dichiarazione.']);
    exit;
}

// 5. Generazione del PDF
$file_basename = 'dich_frequenza_' . $origin_form_name;
$pdf_generator = new PDFTemplateFiller();
$pdf_path = $pdf_generator->generate($data_to_save, 'dichiarazione_frequenza', $file_basename);
if (!$pdf_path || !file_exists($pdf_path)) {
    echo json_encode(['status' => 'error', 'message' => 'Impossibile generare il PDF della dichiarazione.']);
    exit;
}

// 6. Registrazione dell'allegato nella tabella richieste_allegati
$pdo1->beginTransaction();
try {
    // Controlla se esiste già un allegato di questo tipo per questo form
    $stmt_check = $pdo1->prepare("SELECT id FROM `fillea-app`.richieste_allegati WHERE form_name = ? AND document_type = 'dichiarazione_frequenza'");
    $stmt_check->execute([$origin_form_name]);
    $existing_attachment = $stmt_check->fetch(PDO::FETCH_ASSOC);

    $original_filename = 'dichiarazione_frequenza.pdf';
    $file_path = 'uploads/' . basename($pdf_path); // Percorso relativo alla cartella 'moduli'

    if ($existing_attachment) {
        // Aggiorna il record esistente
        $stmt_update = $pdo1->prepare("
            UPDATE `fillea-app`.richieste_allegati 
            SET file_path = ?, original_filename = ?
            WHERE id = ?
        ");
        $stmt_update->execute([$file_path, $original_filename, $existing_attachment['id']]);
    } else {
        // Inserisci un nuovo record
        $stmt_insert = $pdo1->prepare("
            INSERT INTO `fillea-app`.richieste_allegati 
            (user_id, form_name, document_type, file_path, original_filename) 
            VALUES (?, ?, 'dichiarazione_frequenza', ?, ?)
        ");
        $stmt_insert->execute([$user_id, $origin_form_name, $file_path, $original_filename]);
    }

    $pdo1->commit();

} catch (Exception $e) {
    $pdo1->rollBack();
    error_log("Errore DB in modulo_dichiarazione_frequenza_save.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Errore Database: ' . $e->getMessage()]);
    exit;
}

// 7. Risposta di successo
echo json_encode(['status' => 'success', 'message' => 'Dichiarazione salvata con successo.']);
exit;
?>