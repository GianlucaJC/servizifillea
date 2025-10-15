<?php
include_once("database.php");

$message = '';
$message_type = 'danger'; // 'danger' o 'success'

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        $pdo = Database::getInstance('fillea');

        // Cerca un utente con il token fornito che non sia scaduto
        $stmt = $pdo->prepare(
            "SELECT id, email_verification_expiry FROM `fillea-app`.users WHERE email_verification_token = ? AND status = 'non_verificato'"
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Controlla se il token è scaduto
            if (strtotime($user['email_verification_expiry']) > time()) {
                // Token valido, aggiorna l'utente
                $update_stmt = $pdo->prepare(
                    "UPDATE `fillea-app`.users SET status = 'attivo', email_verification_token = NULL, email_verification_expiry = NULL WHERE id = ?"
                );
                $update_stmt->execute([$user['id']]);

                $message = '<strong>Verifica completata!</strong> Il tuo account è stato attivato con successo. Ora puoi accedere.';
                $message_type = 'success';
            } else {
                // Token scaduto
                $message = '<strong>Link scaduto.</strong> Il link di verifica è scaduto. Per favore, contatta l\'assistenza per riattivare il tuo account.';
            }
        } else {
            // Token non trovato o account già attivo
            $message = '<strong>Link non valido.</strong> Questo link di verifica non è valido o è già stato utilizzato.';
        }
    } catch (PDOException $e) {
        error_log("Errore DB in verify_email.php: " . $e->getMessage());
        $message = 'Si è verificato un errore del server. Riprova più tardi.';
    }
} else {
    $message = '<strong>Token mancante.</strong> Nessun token di verifica fornito.';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica Email</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; }
        .verification-card { max-width: 600px; margin-top: 5rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card verification-card mx-auto shadow">
            <div class="card-body p-5 text-center">
                <div class="mb-4">
                    <img src="logo.jpg" alt="Logo Fillea CGIL Firenze" class="img-fluid mx-auto" style="max-width: 250px;">
                </div>
                <h3 class="card-title mb-4">Verifica Indirizzo Email</h3>
                <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                    <?php echo $message; ?>
                </div>
                <a href="login.php" class="btn btn-primary mt-3">Vai alla pagina di Login</a>
            </div>
        </div>
    </div>
</body>
</html>