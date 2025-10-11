<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Proteggi lo script: solo gli admin possono accedervi.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.0 403 Forbidden');
    die('Accesso non autorizzato.');
}

// 2. Verifica che i dati necessari siano stati inviati tramite POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['form_name'])) {
    header('Location: admin_documenti.php?error=invalid_request');
    exit;
}

$form_name = $_POST['form_name'];
$funzionario_id = $_SESSION['funzionario_id'] ?? 0;

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
    header('Location: admin_documenti.php?error=no_recipients');
    exit;
}

include_once('../database.php');
$pdo1 = Database::getInstance('fillea');

// 4. Recupera gli allegati dal database
$stmt_files = $pdo1->prepare("
    SELECT ra.file_path, ra.original_filename
    FROM `fillea-app`.richieste_allegati ra
    JOIN `fillea-app`.richieste_master rm ON ra.form_name = rm.form_name COLLATE utf8mb4_unicode_ci
    WHERE ra.form_name = ? AND rm.id_funzionario = ?
");
$stmt_files->execute([$form_name, $funzionario_id]);
$files_to_zip = $stmt_files->fetchAll(PDO::FETCH_ASSOC);

// 5. Crea il file ZIP
$downloads_dir = __DIR__ . '/downloads';
if (!is_dir($downloads_dir)) {
    mkdir($downloads_dir, 0755, true);
}
$zip_filename = $downloads_dir . '/' . $form_name . '_' . time() . '.zip';
$zip = new ZipArchive();

if ($zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    header('Location: admin_documenti.php?error=zip_creation_failed');
    exit;
}

// Aggiungi gli allegati caricati dall'utente
foreach ($files_to_zip as $file) {
    $file_path_on_server = __DIR__ . '/../servizi/moduli/' . $file['file_path'];
    if (file_exists($file_path_on_server)) {
        $zip->addFile($file_path_on_server, $file['original_filename']);
    }
}

// Aggiungi il PDF statico (per ora)
$static_pdf_path = __DIR__ . '/../modulo.pdf';
if (file_exists($static_pdf_path)) {
    $zip->addFile($static_pdf_path, 'modulo_riepilogativo.pdf');
}

$zip->close();
// 6. Invia l'email con PHPMailer
$mail = new PHPMailer(true);

try {
    // Includi e utilizza la configurazione centralizzata
    require_once __DIR__ . '/../config_mail.php';

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
    
    // Mittente
    $mail->setFrom(SMTP_FROM_ADDRESS, SMTP_FROM_NAME);

    // Destinatari
    foreach ($recipients as $recipient) {
        $mail->addAddress($recipient);
    }

    // 6a. Genera un link di download sicuro
    $download_token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
    $stmt_link = $pdo1->prepare("INSERT INTO `fillea-app`.`download_links` (token, file_path, expires_at) VALUES (?, ?, ?)");
    $stmt_link->execute([$download_token, $zip_filename, $expires_at]);

    // Assicurati di usare il protocollo corretto (http o https) e il nome host
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $download_link = $protocol . $host . dirname($_SERVER['PHP_SELF']) . '/download.php?token=' . $download_token;

    // Contenuto
    $mail->isHTML(true);
    $mail->Subject = 'Invio Pratica Cassa Edile: ' . $form_name;
    $mail->Body    = "È stata inviata la pratica <strong>{$form_name}</strong>.<br><br>" .
                     "Puoi scaricare tutta la documentazione cliccando sul seguente link. Il link scadrà tra 7 giorni.<br><br>" .
                     "<a href='{$download_link}'>Scarica Documenti</a>";
    $mail->AltBody = "È stata inviata la pratica {$form_name}.\n\n" .
                     "Puoi scaricare tutta la documentazione visitando il seguente link. Il link scadrà tra 7 giorni.\n" .
                     $download_link;


    $mail->send();

    // 7. Aggiorna lo stato della pratica nel database
    $stmt_master = $pdo1->prepare("UPDATE `fillea-app`.`richieste_master` SET status = 'inviato_in_cassa_edile' WHERE form_name = ?");
    $stmt_master->execute([$form_name]);

    // Determina la tabella del modulo specifico (modulo1 o modulo2)
    $table_name = strpos($form_name, 'form2_') === 0 ? 'modulo2_richieste' : 'modulo1_richieste';
    $stmt_modulo = $pdo1->prepare("UPDATE `fillea-app`.`{$table_name}` SET status = 'inviato_in_cassa_edile' WHERE form_name = ?");
    $stmt_modulo->execute([$form_name]);

    // Reindirizza con successo
    header('Location: admin_documenti.php?status_updated=true&mail_sent=true');

} catch (Exception $e) {
    // Log dell'errore e reindirizzamento
    $error_message = "PHPMailer Error: {$mail->ErrorInfo} | Exception: {$e->getMessage()}";
    header('Location: admin_documenti.php?error=mail_failed&reason=' . urlencode($mail->ErrorInfo));

}

exit;
?>