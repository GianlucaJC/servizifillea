<?php
session_start();

// 1. Proteggi lo script: solo gli admin possono accedervi.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.0 403 Forbidden');
    die('Accesso non autorizzato.');
}

// 2. Verifica che i dati necessari siano stati inviati tramite POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['form_name']) || !isset($_POST['new_status'])) {
    header('Location: ../admin/admin_documenti.php?error=invalid_request');
    exit;
}

$form_name = $_POST['form_name'];
$new_status = $_POST['new_status'];

// 3. Valida lo stato per sicurezza.
$allowed_statuses = ['abbandonato', 'inviato_in_cassa_edile'];
if (!in_array($new_status, $allowed_statuses)) {
    header('Location: ../admin/admin_documenti.php?error=invalid_status');
    exit;
}

include_once('../../database.php');
$pdo1 = Database::getInstance('fillea');

try {
    $pdo1->beginTransaction();

    // 4. Aggiorna lo stato nella tabella master.
    $stmt_master = $pdo1->prepare("UPDATE `fillea-app`.`richieste_master` SET status = ? WHERE form_name = ?");
    $stmt_master->execute([$new_status, $form_name]);

    // 5. Aggiorna lo stato nella tabella specifica del modulo (es. modulo1).
    $stmt_modulo = $pdo1->prepare("UPDATE `fillea-app`.`modulo1_richieste` SET status = ? WHERE form_name = ?");
    $stmt_modulo->execute([$new_status, $form_name]);

    $pdo1->commit();

} catch (Exception $e) {
    $pdo1->rollBack();
    // In un'app reale, qui si dovrebbe loggare l'errore.
    header('Location: ../admin/admin_documenti.php?error=db_error');
    exit;
}

// 6. Reindirizza alla pagina dei documenti con un messaggio di successo.
header('Location: ../admin/admin_documenti.php?status_updated=true');
exit;