<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Funzione di logging per il debug
function write_log($message) {
    $log_file = __DIR__ . '/send_to_cassa_edile.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] - $message\n", FILE_APPEND | LOCK_EX);
}

// 1. Proteggi lo script: solo gli admin possono accedervi.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.0 403 Forbidden');
    die('Accesso non autorizzato.');
}

// 2. Verifica che i dati necessari siano stati inviati tramite POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['form_name'])) {
    write_log("ERRORE: Richiesta non valida (non POST o form_name mancante).");
    header('Location: admin_documenti.php?error=invalid_request');
    exit;
}

$form_name = $_POST['form_name'];
$funzionario_id = $_SESSION['funzionario_id'] ?? 0;

write_log("--- INIZIO PROCESSO INVIO ---");
write_log("Pratica: {$form_name}, Funzionario ID: {$funzionario_id}");

// 3. Recupera i destinatari
$recipients = [];
if (isset($_POST['send_to_cassa_edile']) && !empty($_POST['send_to_cassa_edile'])) {
    $recipients[] = trim($_POST['send_to_cassa_edile']);
}
if (isset($_POST['additional_recipients']) && !empty($_POST['additional_recipients'])) {
    $additional = array_map('trim', explode(',', $_POST['additional_recipients']));
    $recipients = array_merge($recipients, $additional);
}
$recipients = array_unique(array_filter($recipients, 'filter_var', FILTER_VALIDATE_EMAIL));

if (empty($recipients)) {
    write_log("ERRORE: Nessun destinatario valido trovato. Uscita.");
    header('Location: admin_documenti.php?error=no_recipients');
    exit;
}
write_log("Destinatari validati: " . implode(', ', $recipients));

include_once('../database.php');
$pdo1 = Database::getInstance('fillea');
write_log("Connessione al database stabilita.");

// 4. Recupera gli allegati dal database
$stmt_files = $pdo1->prepare("
    SELECT ra.file_path, ra.original_filename
    FROM `fillea-app`.richieste_allegati ra
    JOIN `fillea-app`.richieste_master rm ON ra.form_name = rm.form_name COLLATE utf8mb4_unicode_ci
    WHERE ra.form_name = ? AND rm.id_funzionario = ?
");
$stmt_files->execute([$form_name, $funzionario_id]);
$files_to_zip = $stmt_files->fetchAll(PDO::FETCH_ASSOC);

write_log("Trovati " . count($files_to_zip) . " file allegati nel database.");

if (empty($files_to_zip)) {
    write_log("ERRORE: Nessun allegato trovato per questa pratica. Uscita.");
    header('Location: admin_documenti.php?error=no_attachments');
    exit;
}

// // 5. Crea il file ZIP (TEMPORANEAMENTE DISABILITATO PER TEST)
// $zip_filename = sys_get_temp_dir() . '/' . $form_name . '.zip';
// $zip = new ZipArchive();
// 
// if ($zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
//     header('Location: admin_documenti.php?error=zip_creation_failed');
//     exit;
// }
// 
// // Aggiungi gli allegati caricati dall'utente
// foreach ($files_to_zip as $file) {
//     $file_path_on_server = __DIR__ . '/../servizi/moduli/' . $file['file_path'];
//     if (file_exists($file_path_on_server)) {
//         $zip->addFile($file_path_on_server, $file['original_filename']);
//     }
// }
// 
// // Aggiungi il PDF statico (per ora)
// $static_pdf_path = __DIR__ . '/../modulo.pdf';
// if (file_exists($static_pdf_path)) {
//     $zip->addFile($static_pdf_path, 'modulo_riepilogativo.pdf');
// }
// 
// $zip->close();
// 6. Invia l'email con PHPMailer
$mail = new PHPMailer(true);

try {
    // Includi e utilizza la configurazione centralizzata
    require_once __DIR__ . '/config_mail.php';
    write_log("Configurazione PHPMailer caricata. Host: " . SMTP_HOST . ", Porta: " . SMTP_PORT);

    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->Port = SMTP_PORT;

    // Se ci sono username e password, abilita l'autenticazione.
    if (!empty(SMTP_USERNAME) && !empty(SMTP_PASSWORD)) {
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
    } else {
        $mail->SMTPAuth = false;
    }

    // Disabilita STARTTLS esplicitamente. Il tuo server sulla porta 25 non sembra supportarlo.
    $mail->SMTPSecure = false;
    $mail->SMTPAutoTLS = false;
    
    // DEBUG SMTP AVANZATO: Cattura l'output di debug e lo scrive nel file di log.
    $mail->SMTPDebug = 2; // Livello 2: mostra i comandi e le risposte del server.
    $mail->Debugoutput = function($str, $level) {
        // Scriviamo l'output di PHPMailer nel log per analizzare la conversazione SMTP.
        write_log("SMTP -> " . trim($str));
    };

    // Mittente
    $mail->setFrom(SMTP_FROM_ADDRESS, SMTP_FROM_NAME);

    // Destinatari
    foreach ($recipients as $recipient) {
        $mail->addAddress($recipient);
    }

    // // Allegato (TEMPORANEAMENTE DISABILITATO PER TEST)
    // $mail->addAttachment($zip_filename);

    // Contenuto
    $mail->isHTML(true);
    $mail->Subject = 'Invio Pratica Cassa Edile: ' . $form_name;
    $mail->Body    = "È stata inviata la pratica <strong>{$form_name}</strong>. I documenti sono disponibili sulla piattaforma.";
    $mail->AltBody = "È stata inviata la pratica {$form_name}. I documenti sono disponibili sulla piattaforma.";

    write_log("Tentativo di invio email...");
    $mail->send();
    write_log("Email inviata con successo.");

    // 7. Aggiorna lo stato della pratica nel database
    write_log("Inizio aggiornamento stato nel database.");
    $stmt_master = $pdo1->prepare("UPDATE `fillea-app`.`richieste_master` SET status = 'inviato_in_cassa_edile' WHERE form_name = ?");
    $stmt_master->execute([$form_name]);

    // Determina la tabella del modulo specifico (modulo1 o modulo2)
    $table_name = strpos($form_name, 'form2_') === 0 ? 'modulo2_richieste' : 'modulo1_richieste';
    write_log("Aggiornamento stato per la tabella specifica: {$table_name}.");
    $stmt_modulo = $pdo1->prepare("UPDATE `fillea-app`.`{$table_name}` SET status = 'inviato_in_cassa_edile' WHERE form_name = ?");
    $stmt_modulo->execute([$form_name]);
    write_log("Stato aggiornato con successo. Reindirizzamento in corso.");

    // Reindirizza con successo
    write_log("--- PROCESSO COMPLETATO CON SUCCESSO ---");
    header('Location: admin_documenti.php?status_updated=true&mail_sent=true');

} catch (Exception $e) {
    // Log dell'errore e reindirizzamento
    $error_message = "PHPMailer Error: {$mail->ErrorInfo} | Exception: {$e->getMessage()}";
    write_log("ERRORE CRITICO: " . $error_message);
    header('Location: admin_documenti.php?error=mail_failed&reason=' . urlencode($mail->ErrorInfo));

} finally {
    // 8. Pulisci il file ZIP temporaneo
    // if (isset($zip_filename) && file_exists($zip_filename)) {
    //     unlink($zip_filename);
    // }
}

exit;
?>