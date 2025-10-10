<?php
session_start();
header('Content-Type: application/json');

include_once("../../database.php");
$pdo1 = Database::getInstance('fillea');

// --- Funzioni Helper ---
function send_json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// --- Autenticazione e Autorizzazione ---
$token = $_POST['token'] ?? null;
$user_id = null;

if ($token) {
    $stmt_user = $pdo1->prepare("SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW() LIMIT 1");
    $stmt_user->execute([$token]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $user_id = $user['id'];
    }
}

if (!$user_id) {
    send_json_error('Accesso non autorizzato.', 403);
}

// --- Gestione Azioni (Upload o Delete) ---
$action = $_POST['action'] ?? 'upload';

if ($action === 'delete') {
    // --- Logica di Eliminazione ---
    $file_id = $_POST['file_id'] ?? null;
    if (!$file_id) {
        send_json_error('ID file mancante.');
    }

    // Trova il file nel DB per assicurarsi che appartenga all'utente
    $stmt_find = $pdo1->prepare("SELECT * FROM `fillea-app`.`richieste_allegati` WHERE id = ? AND user_id = ?");
    $stmt_find->execute([$file_id, $user_id]);
    $file_to_delete = $stmt_find->fetch(PDO::FETCH_ASSOC);

    if (!$file_to_delete) {
        send_json_error('File non trovato o non autorizzato.', 404);
    }

    // Elimina il file dal filesystem
    if (file_exists($file_to_delete['file_path'])) {
        unlink($file_to_delete['file_path']);
    }

    // Elimina il record dal DB
    $stmt_delete = $pdo1->prepare("DELETE FROM `fillea-app`.`richieste_allegati` WHERE id = ?");
    $stmt_delete->execute([$file_id]);

    echo json_encode(['status' => 'success', 'message' => 'File eliminato.']);
    exit;

} elseif ($action === 'upload') {
    // --- Logica di Upload ---
    $form_name = $_POST['form_name'] ?? null;
    $document_type = $_POST['document_type'] ?? null;

    if (empty($form_name) || empty($document_type)) {
        send_json_error('Dati del form mancanti (form_name o document_type).');
    }

    if (!isset($_FILES['files'])) {
        send_json_error('Nessun file ricevuto.');
    }

    $files = $_FILES['files'];
    $upload_dir = __DIR__ . '/../../uploads/' . $user_id . '/' . $form_name . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }

    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5 MB

    $uploaded_files_info = [];

    $file_count = count($files['name']);
    for ($i = 0; $i < $file_count; $i++) {
        $file_error = $files['error'][$i];
        $file_size = $files['size'][$i];
        $file_tmp_name = $files['tmp_name'][$i];
        $original_filename = basename($files['name'][$i]);

        if ($file_error !== UPLOAD_ERR_OK) {
            send_json_error("Errore durante l'upload di '{$original_filename}'. Codice: {$file_error}");
            continue;
        }

        // Validazione MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file_tmp_name);
        if (!in_array($mime_type, $allowed_types)) {
            send_json_error("Tipo di file non consentito per '{$original_filename}'.");
            continue;
        }

        if ($file_size > $max_size) {
            send_json_error("Il file '{$original_filename}' supera la dimensione massima di 5MB.");
            continue;
        }

        // Genera un nome univoco per il file
        $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        $safe_filename = preg_replace('/[^a-zA-Z0-9-_\.]/', '', pathinfo($original_filename, PATHINFO_FILENAME));
        $stored_filename = $safe_filename . '_' . uniqid() . '.' . $file_extension;
        $destination = $upload_dir . $stored_filename;

        if (move_uploaded_file($file_tmp_name, $destination)) {
            // Inserisci nel DB
            $stmt_insert = $pdo1->prepare(
                "INSERT INTO `fillea-app`.`richieste_allegati` (form_name, user_id, document_type, original_filename, stored_filename, file_path) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt_insert->execute([$form_name, $user_id, $document_type, $original_filename, $stored_filename, $destination]);
            $last_id = $pdo1->lastInsertId();

            $uploaded_files_info[] = [
                'id' => $last_id,
                'original_filename' => $original_filename
            ];
        } else {
            send_json_error("Impossibile spostare il file caricato '{$original_filename}'.");
        }
    }

    if (empty($uploaded_files_info)) {
        send_json_error('Nessun file è stato caricato con successo.');
    }

    echo json_encode(['status' => 'success', 'files' => $uploaded_files_info]);
    exit;
}

send_json_error('Azione non valida.');
?>
```

Spero che questa implementazione sia di tuo gradimento. È robusta, sicura e migliora notevolmente l'esperienza utente nella compilazione del modulo.

<!--
[PROMPT_SUGGESTION]Come posso aggiungere una validazione lato server per assicurarmi che l'utente abbia caricato tutti i documenti obbligatori prima di poter inviare il modulo al funzionario?[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]Modifica la pagina di gestione documentale dell'admin (`admin_documenti.php`) per mostrare e permettere il download dei file allegati a ciascuna richiesta.[/PROMPT_SUGGESTION]
-->