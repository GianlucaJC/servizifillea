<?php
session_start();
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

include_once("../../database.php");
require_once '../../admin/generate_pdf_summary.php';

function write_debug_log($message) {
    $log_file = __DIR__ . '/debug_autocert_save.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] - $message\n", FILE_APPEND);
}

if (!function_exists('send_json_error')) {
    function send_json_error($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    }
}

write_debug_log("--- Inizio salvataggio autocertificazione ---");

$token = $_POST['token'] ?? null;
$origin_form_name = $_POST['origin_form_name'] ?? null;
$origin_prestazione = $_POST['origin_prestazione'] ?? null;
$origin_module = $_POST['origin_module'] ?? null;

if (!$token) {
    send_json_error("Accesso non autorizzato.", 403);
    write_debug_log("ERRORE: Token mancante.");
    exit;
}

$pdo1 = Database::getInstance('fillea');

// Recupera user_id dal token
$stmt_user = $pdo1->prepare("SELECT id FROM `fillea-app`.users WHERE token = ?");
$stmt_user->execute([$token]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    send_json_error("Utente non valido.", 403);
    write_debug_log("ERRORE: Utente non trovato con il token fornito.");
    exit;
}
$user_id = $user['id'];
write_debug_log("Utente valido trovato. ID: $user_id. Form di origine: $origin_form_name");

// --- Validazione dei dati in input ---
$required_fields = [
    'sottoscrittore_nome_cognome', 'sottoscrittore_luogo_nascita', 'sottoscrittore_data_nascita',
    'sottoscrittore_residenza_comune', 'sottoscrittore_residenza_indirizzo',
    'luogo_firma', 'data_firma', 'firma_data'
];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        write_debug_log("ERRORE: Campo obbligatorio mancante: $field");
        send_json_error("Il campo '$field' è obbligatorio.", 400);
    }
}
// Prepara i dati principali da salvare
$data = [
    'user_id' => $user_id,
    'sottoscrittore_nome_cognome' => $_POST['sottoscrittore_nome_cognome'] ?? null,
    'sottoscrittore_luogo_nascita' => $_POST['sottoscrittore_luogo_nascita'] ?? null,
    'sottoscrittore_data_nascita' => $_POST['sottoscrittore_data_nascita'] ?? null,
    'sottoscrittore_residenza_comune' => $_POST['sottoscrittore_residenza_comune'] ?? null,
    'sottoscrittore_residenza_indirizzo' => $_POST['sottoscrittore_residenza_indirizzo'] ?? null,
    'luogo_firma' => $_POST['luogo_firma'] ?? null, 
    'data_firma' => $_POST['data_firma'] ?? null, 
    'firma_data' => $_POST['firma_data'] ?? null, 
];

// Prepara i dati dei membri della famiglia
$membri_famiglia_json = $_POST['membri_famiglia_json'] ?? '[]';
$membri_famiglia = json_decode($membri_famiglia_json, true);

// Aggiungi i membri della famiglia ai dati principali per il salvataggio su file
$data['membri_famiglia'] = $membri_famiglia;

// Definisci il percorso della cartella e il nome del file JSON
$data_dir = __DIR__ . '/autocertificazioni_data/';
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0775, true);
}
$json_filename = 'autocert_' . $origin_form_name . '.json';
$json_filepath = $data_dir . $json_filename;

$pdo1->beginTransaction();
try {
    // 1. Salva i dati in un file JSON. Sovrascrive il file se esiste già.
    write_debug_log("Azione: Salvataggio dati nel file: $json_filepath");
    $json_data_to_save = json_encode($data, JSON_PRETTY_PRINT);
    if (file_put_contents($json_filepath, $json_data_to_save) === false) {
        throw new Exception("Impossibile scrivere il file JSON dei dati.");
    }
    write_debug_log("Dati salvati con successo nel file JSON.");

    // 2. Genera il PDF
    write_debug_log("Inizio generazione PDF.");
    // Rimuoviamo il timestamp per rendere il nome del file PDF univoco e sovrascrivibile, come il JSON.
    $file_basename = 'autocert_' . $origin_form_name;
    $pdf_generator = new PDFTemplateFiller();
    // Passa i dati completi al generatore di PDF
    $pdf_data = $data; // $data contiene già tutto
    $pdf_path = $pdf_generator->generate($pdf_data, 'autocertificazione_stato_famiglia', $file_basename);

    write_debug_log("PDF generato in: $pdf_path. Controllo allegato...");
    // 3. Se la generazione del PDF ha successo, allega il file alla pratica di origine
    if ($pdf_path && file_exists($pdf_path) && $origin_form_name) {
        $file_rel_path = 'uploads/' . basename($pdf_path);
        $related_id = $json_filename; // L'ID di riferimento è ora il nome del file JSON
        
        // Controlla se esiste già un allegato di questo tipo per questo form
        $stmt_check_attach = $pdo1->prepare("SELECT id FROM `fillea-app`.`richieste_allegati` WHERE form_name = ? AND document_type = ? AND user_id = ?");
        $stmt_check_attach->execute([$origin_form_name, 'autocertificazione_famiglia', $user_id]);
        $existing_attach_id = $stmt_check_attach->fetchColumn();

        if ($existing_attach_id) {
            // UPDATE esistente
            $stmt_attach = $pdo1->prepare("UPDATE `fillea-app`.`richieste_allegati` SET file_path = ?, related_id = ? WHERE id = ?");
            $stmt_attach->execute([$file_rel_path, $related_id, $existing_attach_id]);
        } else {
            // INSERT nuovo
            $stmt_attach = $pdo1->prepare(
                "INSERT INTO `fillea-app`.`richieste_allegati` (user_id, form_name, document_type, original_filename, file_path, related_id) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt_attach->execute([
                $user_id, $origin_form_name, 'autocertificazione_famiglia', 
                'autocertificazione_stato_famiglia.pdf', $file_rel_path, $related_id
            ]);
        }
    }

    $pdo1->commit();
    write_debug_log("Salvataggio completato con successo. Commit eseguito.");

    // Rispondi con un JSON di successo per la chiamata AJAX
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Dati salvati con successo.']);
    exit;

} catch (Exception $e) {
    write_debug_log("!!! ECCEZIONE CATTURATA !!!\n" . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    $pdo1->rollBack();
    error_log("Errore in modulo_autocertificazione_stato_famiglia_save.php: " . $e->getMessage());
    send_json_error('Errore durante il salvataggio: ' . $e->getMessage(), 500);
    exit;
}
?>
