<?php
session_start();

// 1. Proteggi lo script: non è necessario essere loggati, ma il token è la chiave.
if (!isset($_GET['token'])) {
    header('HTTP/1.0 403 Forbidden');
    die('Accesso non autorizzato: token mancante.');
}

$token = $_GET['token'];

include_once('../database.php');
$pdo1 = Database::getInstance('fillea');

try {
    // 2. Cerca il link di download nel database
    $stmt = $pdo1->prepare("SELECT file_path FROM `fillea-app`.`download_links` WHERE token = ?");
    $stmt->execute([$token]);
    $link_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$link_data) {
        die('Link non valido o non trovato.');
    }
    $file_path = $link_data['file_path'];
    
    // 3. Aggiorna lo stato della pratica a "letto_da_cassa_edile" SOLO SE richiesto dal parametro 'track'
    if (isset($_GET['track']) && $_GET['track'] == '1') {
        // Estrai il form_name dal nome del file ZIP. Es: /path/to/form1_123_456.zip -> form1_123
        $basename = basename($file_path);
        preg_match('/^(form[12]_\d+_\d+)/', $basename, $matches);
        if (!isset($matches[1])) {
            die('Impossibile determinare il nome della pratica dal file.');
        }
        $form_name = $matches[1];

        // La clausola WHERE `status = 'inviato_in_cassa_edile'` garantisce che l'aggiornamento avvenga solo la prima volta.
        $pdo1->beginTransaction();

        $sql_update_master = "UPDATE `fillea-app`.`richieste_master` SET status = 'letto_da_cassa_edile' WHERE form_name = ? AND status = 'inviato_in_cassa_edile'";
        $stmt_update_master = $pdo1->prepare($sql_update_master);
        $stmt_update_master->execute([$form_name]);

        $table_name = strpos($form_name, 'form2_') === 0 ? 'modulo2_richieste' : 'modulo1_richieste';
        $sql_update_module = "UPDATE `fillea-app`.`{$table_name}` SET status = 'letto_da_cassa_edile' WHERE form_name = ? AND status = 'inviato_in_cassa_edile'";
        $stmt_update_module = $pdo1->prepare($sql_update_module);
        $stmt_update_module->execute([$form_name]);

        $pdo1->commit();
    }

    // 4. Forza sempre il download del file, indipendentemente dal tracciamento
    if (file_exists($file_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } else {
        die('File non trovato sul server.');
    }

} catch (Exception $e) {
    if ($pdo1->inTransaction()) {
        $pdo1->rollBack();
    }
    error_log("Errore in download.php: " . $e->getMessage());
    die('Si è verificato un errore durante il processamento della richiesta.');
}
?>