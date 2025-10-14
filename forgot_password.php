<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpmailer/phpmailer/src/Exception.php';
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';

include_once("database.php");

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Indirizzo email non valido.';
        $message_type = 'danger';
    } else {
        try {
            $pdo = Database::getInstance('fillea');
            $stmt = $pdo->prepare("SELECT id FROM `fillea-app`.users WHERE email = ? AND status = 'attivo'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Genera un token sicuro e una data di scadenza (es. 1 ora)
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', time() + 3600);

                // Salva il token e la scadenza nel database
                $update_stmt = $pdo->prepare("UPDATE `fillea-app`.users SET password_reset_token = ?, password_reset_expiry = ? WHERE id = ?");
                $update_stmt->execute([$token, $expiry, $user['id']]);

                // Invia l'email con PHPMailer
                $mail = new PHPMailer(true);
                
                // Includi e utilizza la configurazione centralizzata
                require_once __DIR__ . '/admin/config_mail.php';

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

                $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : false;
                $mail->SMTPAutoTLS = defined('SMTP_AUTOTLS') ? SMTP_AUTOTLS : false;

                // Mittente e destinatario
                $mail->setFrom(SMTP_FROM_ADDRESS, SMTP_FROM_NAME);
                $mail->addAddress($email);

                // Contenuto dell'email
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/set_new_password.php?token=" . $token;
                $mail->isHTML(true);
                $mail->Subject = 'Reset della password - Fillea Service App';
                $mail->Body    = "Ciao,<br><br>Hai richiesto di resettare la tua password. Clicca sul link qui sotto per procedere:<br><br>"
                               . "<a href='{$reset_link}'>{$reset_link}</a><br><br>"
                               . "Se non hai richiesto tu il reset, ignora questa email.<br><br>Grazie,<br>Il team di Fillea Service App";
                $mail->AltBody = "Ciao, vai a questo link per resettare la tua password: {$reset_link}";

                $mail->send();
            }

            // Mostra sempre un messaggio generico per motivi di sicurezza
            $message = 'Se l\'indirizzo email è presente nel nostro sistema, riceverai un link per resettare la password.';
            $message_type = 'success';

        } catch (Exception $e) {
            error_log("Errore invio email reset password: " . $e->getMessage());
            $message = 'Si è verificato un errore. Riprova più tardi:'.$e->getMessage();
            $message_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Dimenticata</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #d0112b; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .reset-card { max-width: 450px; width: 100%; background-color: #fff; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
    </style>
</head>
<body>
<div class="reset-card">
    <div class="text-center mb-4">
        <i class="fas fa-key fa-4x" style="color: #d0112b;"></i>
        <h2 class="mt-3">Recupera Password</h2>
        <p class="text-muted">Inserisci la tua email per ricevere le istruzioni di reset.</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form action="forgot_password.php" method="POST">
        <div class="mb-3">
            <label for="email" class="form-label">Indirizzo Email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
        </div>
        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary" style="background-color: #d0112b; border-color: #d0112b;">Invia Link di Reset</button>
            <a href="login.php" class="btn btn-secondary">Torna al Login</a>
        </div>
    </form>
</div>
</body>
</html>