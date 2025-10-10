<?php
session_start();

// 1. Proteggi lo script: solo i super-admin possono accedervi.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
    header('HTTP/1.0 403 Forbidden');
    die('Accesso non autorizzato.');
}

// 2. Verifica che i dati necessari siano stati inviati tramite POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['request_id']) || !isset($_POST['new_funzionario_id'])) {
    header('Location: admin_reassign.php?error=invalid_request');
    exit;
}

$request_id = $_POST['request_id'];
$new_funzionario_id = $_POST['new_funzionario_id'];

// 3. Validazione dati: assicurati che siano numerici e validi.
if (!is_numeric($request_id) || !is_numeric($new_funzionario_id) || $new_funzionario_id <= 0) {
    header('Location: admin_reassign.php?error=invalid_data');
    exit;
}

include_once('../database.php');
$pdo1 = Database::getInstance('fillea');

try {
    // 4. Aggiorna l'assegnazione del funzionario nella tabella master delle richieste.
    $stmt = $pdo1->prepare("UPDATE `fillea-app`.`richieste_master` SET id_funzionario = ? WHERE id = ?");
    $success = $stmt->execute([$new_funzionario_id, $request_id]);

    if ($success) {
        // 5. Reindirizza con messaggio di successo.
        header('Location: admin_reassign.php?success=true');
    } else {
        throw new Exception("L'aggiornamento del database non è riuscito.");
    }

} catch (Exception $e) {
    // In caso di errore, loggalo e reindirizza con un messaggio di errore.
    error_log("Errore in admin_update_assignment.php: " . $e->getMessage());
    header('Location: admin_reassign.php?error=db_error');
}

exit;