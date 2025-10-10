<?php
session_start();
include_once('../database.php');

$error_message = '';

// Se l'utente è già loggato, reindirizza alla dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_documenti.php');
    exit;
}

// Verifica se il form è stato inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        $pdo1 = Database::getInstance('fillea');
        $stmt = $pdo1->prepare("SELECT id, username, password_hash, is_super_admin FROM `fillea-app`.funzionari WHERE username = ?");
        $stmt->execute([$username]);
        $funzionario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verifica se l'utente esiste e se la password è corretta
        if ($funzionario && password_verify($password, $funzionario['password_hash'])) {
            // Credenziali corrette: imposta la sessione
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $funzionario['username'];
            $_SESSION['funzionario_id'] = $funzionario['id']; // ID del funzionario
            $_SESSION['is_super_admin'] = (bool)$funzionario['is_super_admin']; // Flag per super admin
            
            // Se un admin si logga, distruggiamo qualsiasi sessione utente attiva.
            if (isset($_SESSION['user_token'])) {
                unset($_SESSION['user_token']);
            }

            // Reindirizza alla pagina corretta in base al ruolo
            if ($_SESSION['is_super_admin']) {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: admin_documenti.php');
            }
            exit;
        } else {
            // Credenziali errate
            $error_message = 'Credenziali non valide. Riprova.';
        }
    } catch (Exception $e) {
        // In un'app reale, qui si dovrebbe loggare l'errore.
        // Credenziali errate
        $error_message = 'Credenziali non valide. Riprova.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin</title>
    <link rel="icon" href="https://placehold.co/32x32/343a40/ffffff?text=A">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background-color: #343a40; /* Sfondo scuro per l'admin */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-card {
            max-width: 450px;
            width: 100%;
            background-color: #fff;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.25);
        }
        .admin-icon {
            color: #d0112b; /* Icona rossa Fillea */
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <i class="fas fa-user-shield fa-4x admin-icon"></i>
        <h2 class="mt-3">Area Amministrazione</h2>
        <p class="text-muted">Accesso riservato ai funzionari.</p>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <form action="index.php" method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
        </div>
        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
        </div>
        <button type="submit" class="btn btn-dark w-100">Accedi <i class="fas fa-sign-in-alt"></i></button>
    </form>
    <a href="../servizi.php" class="btn btn-secondary w-100 mt-2">
        Torna ai Servizi <i class="fas fa-home"></i>
    </a>
</div>

</body>
</html>