<?php
// Avvia la sessione per gestire l'accesso
session_start();

$error_message = '';
$success_message = '';

// Verifica se il form è stato inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include("database.php");
    $pdo1 = Database::getInstance('fillea');

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    // 1. Recuperare la password criptata (hashed) dell'utente.
    // 2. Verificare la password usando `password_verify()`.


    // Query SQL
    $sql="SELECT count(id),password FROM `fillea-app`.users WHERE (email = ?) LIMIT 0,1";
    $stmt = $pdo1->prepare($sql);
    $stmt->execute([$username]);
    $check_account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (isset($check_account['password'])) {
        $pw=$check_account['password'];
        if (password_verify($password,$pw)) {
            // Autenticazione riuscita:
            // Genera un token casuale (ad esempio, 32 caratteri esadecimali)
            $token = bin2hex(random_bytes(16)); 
            // Imposta una scadenza per il token (ad esempio, 1 ora da adesso)
            $expiry = time() + 3600; // 3600 secondi = 1 ora
            $data_ora_formattata = date('Y/m/d H:i:s', $expiry);
            // Salva il token e la sua scadenza nel database per l'utente
            // Assicurati di avere una colonna 'token' e 'token_expiry' nella tua tabella 'users'
            
            $sql_update_token = "UPDATE `fillea-app`.users SET token = ?, token_expiry = ? WHERE email = ?";
            $stmt_update_token = $pdo1->prepare($sql_update_token);
            $stmt_update_token->execute([$token, $data_ora_formattata, $username]);
            

            // Reindirizza alla pagina principale dei servizi.
            header("Location: servizi.php?token=" . $token);
            exit;
        } else {
            // Credenziali non valide
            $error_message = 'Credenziali non valide. Riprova.';
        }
        $pdo1 = null; // Chiudi la connessione PDO
    }
    else {
            // Utente inesistente
            $error_message = 'Credenziali non valide';
    }

}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Lavoratore</title>
    <!-- Icona rossa per la pagina (favicon) -->
    <link rel="icon" href="https://placehold.co/32x32/d0112b/ffffff?text=L">
    <link rel="apple-touch-icon" href="https://placehold.co/192x192/d0112b/ffffff?text=L">
    <meta name="theme-color" content="#d0112b">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background-color: #d0112b; /* Colore di sfondo richiesto */
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
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        /* Stile per i messaggi di errore/successo */
        .alert {
            margin-bottom: 1.5rem;
        }
    </style>
    </head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <i class="fas fa-user-circle fa-4x" style="color: #d0112b;"></i>
        <h2 class="mt-3">Area Riservata</h2>
        <p class="text-muted">Effettua il login per accedere.</p>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST">


        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <input type="email" class="form-control" id="username" name="username" required>
            </div>
        </div>
        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
        </div>
        
        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary" style="background-color: #d0112b; border-color: #d0112b;">
                Accedi <i class="fas fa-sign-in-alt"></i>
            </button>
            
            <a href="servizi.php" class="btn btn-secondary">
                Torna ai Servizi <i class="fas fa-home"></i>
            </a>
        </div>

    </form>
    
    <!-- Pulsante per accesso con impronta, spostato fuori dal form per evitare conflitti -->
    <div class="d-grid gap-2 mt-2">
        <button type="button" id="fingerprint-login-btn" class="btn btn-dark d-none">Accedi con impronta <i class="fas fa-fingerprint"></i></button>
    </div>

    <div class="text-center mt-3">
        <a href="forgot_password.php" class="text-muted">Password dimenticata?</a>
        <p class='mt-2'><a href="register.php" class="text-muted">Non sei registrato? Clicca quì</a></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="webauthn_helpers.js?v=<?php echo time(); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        /*
         * --- ACCESSO BIOMETRICO TEMPORANEAMENTE DISABILITATO ---
         * // Controlla se il browser supporta WebAuthn e mostra il pulsante
         * if (window.PublicKeyCredential) {
         *     document.getElementById('fingerprint-login-btn').classList.remove('d-none');
         * }
        */

        // --- Logica per il pulsante di fallback ---
        $('#fingerprint-login-btn').on('click', async function() {
            const username = $('#username').val();
            let options;
            let assertion;

            // Step 1: Ottenere le opzioni dal server
            try {
                const response = await fetch('webauthn_login_start.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username: username })
                });
                if (!response.ok) throw new Error(`Errore HTTP: ${response.statusText}`);
                options = await response.json();
                if (options.error) throw new Error(`Errore restituito dal server: ${options.error}`);
                console.log("✅ Step 1/5: Opzioni ricevute correttamente dal server.");
            } catch (err) {
                alert('Login Fallito (Step 1/5): Impossibile ottenere le opzioni dal server.\n\n' + err.message);
                console.error("Errore Step 1:", err);
                return;
            }

            // Step 2: Preparare le opzioni per il browser
            try {
                options.challenge = base64UrlToBuffer(options.challenge);
                if (options.allowCredentials && options.allowCredentials.length > 0) {
                    options.allowCredentials.forEach(cred => cred.id = base64UrlToBuffer(cred.id));
                }
                console.log("✅ Step 2/5: Opzioni preparate per il browser.");
            } catch (err) {
                alert('Login Fallito (Step 2/5): Errore durante la preparazione delle opzioni.\n\n' + err.message);
                console.error("Errore Step 2:", err);
                return;
            }

            // Step 3: Chiamare l'API biometrica del browser
            try {
                assertion = await navigator.credentials.get({ publicKey: options });
                console.log("✅ Step 3/5: Asserzione biometrica ottenuta con successo.");
            } catch (err) {
                // DEBUG POTENZIATO: Invia l'errore client-side a un logger sul server
                const optionsForLog = JSON.parse(JSON.stringify(options, (key, value) => {
                    if (value instanceof ArrayBuffer) {
                        return `ArrayBuffer(${value.byteLength} bytes)`;
                    }
                    return value;
                }));

                const errorData = {
                    step: 'Step 3/5 - navigator.credentials.get()',
                    error: { name: err.name, message: err.message },
                    options: optionsForLog
                };

                // Invia l'errore al server per il logging
                fetch('log_client_error.php', { method: 'POST', body: JSON.stringify(errorData), keepalive: true });

                const errorMessage = 'Login Fallito (Step 3/5): Operazione annullata o non permessa dal browser.\n\n' +
                                   `Nome Errore: ${err.name}\n` +
                                   `Messaggio: ${err.message}`;
                alert(errorMessage);
                console.error("Errore Step 3:", err, "Opzioni inviate al browser:", options);
                return;
            }

            // Step 4: Inviare l'asserzione al server per la verifica
            let verificationResult;
            try {
                const verificationResponse = await fetch('webauthn_login_finish.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: bufferToBase64Url(assertion.rawId),
                        rawId: bufferToBase64Url(assertion.rawId), // CRITICO: Aggiungiamo rawId, richiesto dalla libreria
                        type: assertion.type,
                        response: {
                            authenticatorData: bufferToBase64Url(assertion.response.authenticatorData),
                            clientDataJSON: bufferToBase64Url(assertion.response.clientDataJSON),
                            signature: bufferToBase64Url(assertion.response.signature),
                            userHandle: assertion.response.userHandle ? bufferToBase64Url(assertion.response.userHandle) : null
                        }
                    })
                });
                if (!verificationResponse.ok) throw new Error(`Errore HTTP: ${verificationResponse.statusText}`);
                verificationResult = await verificationResponse.json();
                console.log("✅ Step 4/5: Risposta di verifica ricevuta dal server.");
            } catch (err) {
                alert('Login Fallito (Step 4/5): Errore durante la comunicazione con il server di verifica.\n\n' + err.message);
                console.error("Errore Step 4:", err);
                return;
            }

            // Step 5: Gestire il risultato finale
            try {
                if (verificationResult.status === 'success') {
                    console.log("✅ Step 5/5: Login completato con successo!");
                    window.location.href = 'servizi.php?token=' + verificationResult.token;
                } else {
                    throw new Error(verificationResult.message || 'Verifica fallita.');
                }
            } catch (err) {
                alert('Login Fallito (Step 5/5): La verifica del server non è andata a buon fine.\n\n' + err.message);
                console.error("Errore Step 5:", err);
            }
        });
    });
</script>

</body>
</html>

</script>

</body>
</html>