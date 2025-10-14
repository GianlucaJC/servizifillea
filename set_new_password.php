<?php
session_start();
include_once("database.php");

$token = $_GET['token'] ?? '';
$message = '';
$message_type = 'danger';
$show_form = false;

if (empty($token)) {
    $message = 'Token non valido o mancante.';
} else {
    try {
        $pdo = Database::getInstance('fillea');
        $stmt = $pdo->prepare("SELECT id FROM `fillea-app`.users WHERE password_reset_token = ? AND password_reset_expiry > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $show_form = true;
            $user_id = $user['id'];
        } else {
            $message = 'Token non valido o scaduto. Richiedi un nuovo reset.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];

            if (strlen($password) < 8) {
                $message = 'La password deve essere di almeno 8 caratteri.';
            } elseif ($password !== $password_confirm) {
                $message = 'Le password non coincidono.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                // Aggiorna la password e invalida il token
                $update_stmt = $pdo->prepare("UPDATE `fillea-app`.users SET password = ?, password_reset_token = NULL, password_reset_expiry = NULL WHERE id = ?");
                $update_stmt->execute([$hashed_password, $user_id]);

                $message = 'Password aggiornata con successo! Ora puoi effettuare il login.';
                $message_type = 'success';
                $show_form = false;
            }
        }
    } catch (Exception $e) {
        error_log("Errore reset password: " . $e->getMessage());
        $message = 'Si è verificato un errore. Riprova più tardi.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imposta Nuova Password</title>
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
        <i class="fas fa-shield-alt fa-4x" style="color: #d0112b;"></i>
        <h2 class="mt-3">Crea Nuova Password</h2>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($show_form): ?>
        <form action="set_new_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
            <div class="mb-3">
                <label for="password" class="form-label">Nuova Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required minlength="8">
                </div>
            </div>
            <div class="mb-4">
                <label for="password_confirm" class="form-label">Conferma Nuova Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8">
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary" style="background-color: #d0112b; border-color: #d0112b;">Imposta Password</button>
            </div>
        </form>
    <?php else: ?>
        <div class="d-grid">
            <a href="login.php" class="btn btn-primary" style="background-color: #d0112b; border-color: #d0112b;">Vai al Login</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>