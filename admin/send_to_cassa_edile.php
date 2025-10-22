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

if (empty($files_to_zip)) {
    // Anche se l'avviso è già nella modale, questo è un controllo di sicurezza lato server.
    // Non blocchiamo l'invio ma procediamo senza creare uno ZIP vuoto.
    // La logica di creazione dello ZIP e del link verrà saltata.
}

// 5. Crea il file ZIP
// --- INIZIO LOGICA PDF DINAMICO ---
// Determina la tabella del modulo specifico per recuperare i dati
$table_name = strpos($form_name, 'form2_') === 0 ? 'modulo2_richieste' : 'modulo1_richieste';
$stmt_data = $pdo1->prepare("SELECT * FROM `fillea-app`.`{$table_name}` WHERE form_name = ?");
$stmt_data->execute([$form_name]);
$form_data = $stmt_data->fetch(PDO::FETCH_ASSOC);

// Decodifica il campo JSON delle prestazioni, necessario per la logica di compilazione
if ($form_data && !empty($form_data['prestazioni'])) {
    $form_data['prestazioni_decoded'] = json_decode($form_data['prestazioni'], true);
}

// Estrai il nome del lavoratore per personalizzare l'email
$worker_name = '';
if ($form_data) {
    if ($table_name === 'modulo1_richieste') {
        $worker_name = $form_data['lavoratore_nome_cognome'] ?? '';
    } elseif ($table_name === 'modulo2_richieste') {
        $worker_name = $form_data['nome_completo'] ?? '';
    }
}


// Crea il PDF solo se abbiamo trovato i dati del modulo
$dynamic_pdf_path = null;
if ($form_data) {
    require_once 'generate_pdf_summary.php'; // Includiamo la logica di generazione
    $pdf_generator = new PDFTemplateFiller();
    $dynamic_pdf_path = $pdf_generator->generate($form_data, $table_name);
}
// --- FINE LOGICA PDF DINAMICO ---


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

// Aggiungi il PDF riepilogativo (dinamico se generato, altrimenti statico come fallback)
if ($dynamic_pdf_path && file_exists($dynamic_pdf_path)) {
    $zip->addFile($dynamic_pdf_path, 'modulo_compilato_' . $form_name . '.pdf');
} else {
    // Fallback al PDF statico se la generazione dinamica fallisce
    $static_pdf_path = __DIR__ . '/../modulo.pdf';
    if (file_exists($static_pdf_path)) { $zip->addFile($static_pdf_path, 'modulo_template_vuoto.pdf'); }
}

$zip->close();
// 6. Invia l'email con PHPMailer
$mail = new PHPMailer(true);

try {
    // Includi e utilizza la configurazione centralizzata
    require_once __DIR__ . '/config_mail.php';

    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->Port = SMTP_PORT;
    $mail->Priority = 1; // Priorità massima

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

    // --- Creazione del corpo dell'email in HTML ---
    $email_title = 'Invio Pratica Cassa Edile';
    $primary_color = '#d0112b'; // Colore primario della piattaforma
    $bg_color = '#f4f6f9';
    $text_color = '#333333';
    
    // Imposta la codifica dei caratteri per risolvere problemi con gli accenti
    $mail->CharSet = 'UTF-8';

    $htmlBody = '
    <body style="margin: 0; padding: 0; background-color: '.$bg_color.'; font-family: Inter, Arial, sans-serif;">
        <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr><td style="padding: 20px 0;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <tr>
                        <td align="center" style="background-color: '.$primary_color.'; padding: 20px; border-top-left-radius: 8px; border-top-right-radius: 8px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;">Fillea Service App</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px; color: '.$text_color.'; font-size: 18px; line-height: 1.6;">
                            <h2 style="margin-top: 0; color: '.$primary_color.';">'.$email_title.'</h2>
                            <p>È stata inviata la pratica: <strong>'.$form_name.'</strong>.</p>';
    if (!empty($worker_name)) {
        $htmlBody .= '<p>Lavoratore: <strong>'.htmlspecialchars($worker_name).'</strong>.</p>';
    }

    if (!empty($files_to_zip)) {
        // Genera un link di download sicuro solo se ci sono file
        $download_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        $stmt_link = $pdo1->prepare("INSERT INTO `fillea-app`.`download_links` (token, file_path, expires_at) VALUES (?, ?, ?)");
        $stmt_link->execute([$download_token, $zip_filename, $expires_at]);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $download_link = $protocol . $host . dirname($_SERVER['PHP_SELF']) . '/download.php?token=' . $download_token;

        $htmlBody .= '
                            <p>Puoi scaricare tutta la documentazione cliccando sul pulsante qui sotto. Il link scadrà tra 7 giorni.</p>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr><td align="center" style="padding: 20px 0;">
                                    <a href="'.$download_link.'" style="background-color: '.$primary_color.'; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">Scarica Documenti</a>
                                </td></tr>
                            </table>';
        $altBody = "È stata inviata la pratica {$form_name}.\n";
        if (!empty($worker_name)) {
            $altBody .= "Lavoratore: " . htmlspecialchars($worker_name) . ".\n";
        }
        $altBody .= "\nPuoi scaricare tutta la documentazione visitando il seguente link:\n" . $download_link;
    } else {
        $htmlBody .= '<p>Non sono stati allegati nuovi documenti a questo invio.</p>';
        $altBody = "È stata inviata la pratica {$form_name}.\n\nNon sono stati allegati nuovi documenti a questo invio.";
    }

    $htmlBody .= '
                            <p style="margin-top: 30px; font-size: 12px; color: #888888;">Questa è una mail generata automaticamente. Per favore, non rispondere.</p>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </body>';

    // Imposta il contenuto dell'email
    $mail->isHTML(true);
    $subject = 'Invio Pratica Cassa Edile: ' . $form_name;
    if (!empty($worker_name)) {
        $subject .= ' - ' . htmlspecialchars($worker_name);
    }
    $mail->Subject = $subject;
    $mail->Body    = $htmlBody;
    $mail->AltBody = $altBody;

    $mail->send();

    // 7. Aggiorna lo stato della pratica nel database
    $stmt_master = $pdo1->prepare("UPDATE `fillea-app`.`richieste_master` SET status = 'inviato_in_cassa_edile', user_notification_unseen = 1 WHERE form_name = ? AND id_funzionario = ?");
    $stmt_master->execute([$form_name, $funzionario_id]);

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