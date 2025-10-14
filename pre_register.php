<?php
session_start();
include_once("database.php");

$pdo1 = Database::getInstance('fillea');
$error_message = '';
$success_message = '';
$show_email_form = false;
$user_data = null;

// --- LOGICA DI GESTIONE DEL FORM ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- FASE 2: Completamento della registrazione ---
    if (isset($_POST['action']) && $_POST['action'] === 'complete_registration' && isset($_SESSION['pre_reg_user_id'])) {
        $user_id = $_SESSION['pre_reg_user_id'];
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $email_confirm = filter_var(trim($_POST['email_confirm']), FILTER_SANITIZE_EMAIL);
        $nome = trim($_POST['nome']);
        $cognome = trim($_POST['cognome']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Per favore, inserisci un indirizzo email valido.';
            $show_email_form = true; // Mostra di nuovo il form
        } elseif ($email !== $email_confirm) {
            $error_message = 'Gli indirizzi email non coincidono.';
            $show_email_form = true;
        } elseif (empty($nome) || empty($cognome)) {
            $error_message = 'Nome e Cognome sono campi obbligatori.';
            $show_email_form = true; // Mostra di nuovo il form
        } elseif (empty($password) || strlen($password) < 8) {
            $error_message = 'La password deve essere di almeno 8 caratteri.';
            $show_email_form = true;
        } elseif ($password !== $password_confirm) {
            $error_message = 'Le password non coincidono.';
            $show_email_form = true;
        } else {
            try {
                // Controlla se l'email è già in uso da un altro utente
                $stmt_check_email = $pdo1->prepare("SELECT id FROM `fillea-app`.users WHERE email = ? AND id != ?");
                $stmt_check_email->execute([$email, $user_id]);
                if ($stmt_check_email->fetch()) {
                    $error_message = 'Questo indirizzo email è già utilizzato da un altro account.';
                    $show_email_form = true;
                } else {
                    // Crea l'hash della nuova password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Aggiorna l'utente: imposta email, nome, cognome, la nuova password hashata, lo status e rimuovi la password temporanea
                    $update_sql = "UPDATE `fillea-app`.users SET 
                                    email = :email, 
                                    nome = :nome, 
                                    cognome = :cognome, 
                                    password = :password,
                                    status = 'attivo', 
                                    temp_password = NULL 
                                   WHERE id = :user_id";
                    $stmt_update = $pdo1->prepare($update_sql);
                    $stmt_update->execute([
                        ':email' => $email,
                        ':nome' => $nome,
                        ':cognome' => $cognome,
                        ':password' => $hashed_password,
                        ':user_id' => $user_id
                    ]);

                    // Pulisci la sessione di pre-registrazione
                    unset($_SESSION['pre_reg_user_id']);
                    $success_message = 'Registrazione completata con successo! Ora puoi accedere all\'app con le tue credenziali definitive.';
                }
            } catch (PDOException $e) {
                $error_message = "Errore durante l'aggiornamento del profilo. Riprova più tardi.";
                error_log("Errore DB in pre_register.php: " . $e->getMessage());
                $show_email_form = true;
            }
        }
    }
    // --- FASE 1: Primo accesso con Codice Fiscale e Password Temporanea ---
    elseif (isset($_POST['codice_fiscale']) && isset($_POST['temp_password'])) {
        $codice_fiscale = strtoupper(trim($_POST['codice_fiscale']));
        $temp_password = $_POST['temp_password'];

        if (empty($codice_fiscale) || empty($temp_password)) {
            $error_message = 'Tutti i campi sono obbligatori.';
        } else {
            // Cerca l'utente con status 'pre-registrato' e credenziali corrispondenti
            $stmt = $pdo1->prepare("SELECT * FROM `fillea-app`.users WHERE codfisc = ? AND temp_password = ? AND status = 'pre-registrato' LIMIT 1");
            $stmt->execute([$codice_fiscale, $temp_password]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Utente valido, procedi alla fase 2
                $_SESSION['pre_reg_user_id'] = $user['id'];
                $user_data = $user;
                $show_email_form = true;
            } else {
                $error_message = 'Credenziali non valide, account non trovato o già attivato.';
            }
        }
    }
}

// Se stiamo mostrando il form di completamento, recuperiamo i dati dell'utente
if ($show_email_form && isset($_SESSION['pre_reg_user_id']) && !$user_data) {
    $stmt = $pdo1->prepare("SELECT * FROM `fillea-app`.users WHERE id = ?");
    $stmt->execute([$_SESSION['pre_reg_user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attivazione Profilo Fillea Service App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; }
        .activation-card { max-width: 500px; }
        .card-header { background-color: #d0112b; color: white; }
    </style>
</head>
<body>

<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card activation-card shadow-lg">
        <div class="card-header text-center p-3">
            <h4 class="mb-0">Attivazione Profilo</h4>
        </div>
        <div class="card-body p-4 p-lg-5">

            <!-- Logo -->
            <div class="text-center mb-4">
                <img src="logo.jpg" alt="Logo Fillea CGIL Firenze" class="img-fluid mx-auto" style="max-width: 300px;">
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success text-center">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h5 class="fw-bold">Attivazione Completata!</h5>
                    <p><?php echo $success_message; ?></p>
                    <a href="login.php" class="btn btn-primary mt-3">Vai al Login</a>
                </div>
            <?php elseif ($show_email_form): ?>
                <!-- FORM FASE 2: COMPLETAMENTO REGISTRAZIONE -->
                <h5 class="card-title text-center mb-4 fw-bold">Completa il tuo profilo</h5>
                <p class="text-muted text-center small mb-4">Inserisci i tuoi dati per attivare l'account. L'email diventerà la tua username per gli accessi futuri.</p>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger small p-2"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form action="pre_register.php" method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="complete_registration">
                    
                    <div class="input-group mb-3">
                        <span class="input-group-text" style="width: 150px;"><i class="fas fa-id-card me-2"></i>Codice Fiscale</span>
                        <input type="text" id="codice_fiscale_display" class="form-control" value="<?php echo htmlspecialchars($user_data['codfisc']); ?>" readonly>
                    </div>
                    <div class="input-group mb-3">
                        <span class="input-group-text" style="width: 150px;"><i class="fas fa-user me-2"></i>Nome</span>
                        <input type="text" id="nome" name="nome" class="form-control" value="<?php echo htmlspecialchars($user_data['nome'] ?? ''); ?>" placeholder="Il tuo nome" required autocomplete="off">
                    </div>
                    <div class="input-group mb-3">
                        <span class="input-group-text" style="width: 150px;"><i class="fas fa-user me-2"></i>Cognome</span>
                        <input type="text" id="cognome" name="cognome" class="form-control" value="<?php echo htmlspecialchars($user_data['cognome'] ?? ''); ?>" placeholder="Il tuo cognome" required autocomplete="off">
                    </div>
                    <div class="input-group mb-3">
                        <span class="input-group-text" style="width: 150px;"><i class="fas fa-envelope me-2"></i>Email</span>
                        <input type="email" id="email" name="email" class="form-control" placeholder="mario.rossi@email.com" required autocomplete="off">
                    </div>
                    <p class="text-muted small mb-3" style="margin-top: -0.5rem; margin-left: 155px;">L'email sarà il tuo username per l'accesso.</p>

                    <div class="input-group mb-3">
                        <span class="input-group-text" style="width: 150px;"><i class="fas fa-envelope-circle-check me-2"></i>Conferma Email</span>
                        <input type="email" id="email_confirm" name="email_confirm" class="form-control" placeholder="Ripeti l'email" required autocomplete="off">
                        <div id="email-confirm-feedback" class="invalid-feedback"></div>
                    </div>

                    <div class="input-group mb-3">
                        <span class="input-group-text" style="width: 150px;"><i class="fas fa-lock me-2"></i>Crea Password</span>
                            <input type="password" id="password" name="password" class="form-control" required minlength="8" aria-describedby="password-feedback" autocomplete="new-password">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                <i class="fas fa-eye"></i>
                            </button>
                        <div id="password-feedback" class="invalid-feedback">La password deve essere di almeno 8 caratteri.</div>
                    </div>

                    <div class="input-group mb-3">
                        <span class="input-group-text" style="width: 150px;"><i class="fas fa-lock me-2"></i>Conferma Pass</span>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control" required minlength="8" aria-describedby="password-confirm-feedback" autocomplete="new-password">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_confirm">
                                <i class="fas fa-eye"></i>
                            </button>
                        <div id="password-confirm-feedback" class="invalid-feedback">Le password non coincidono.</div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary fw-bold">Attiva il mio Account</button>
                        <a href="servizi.php" class="btn btn-secondary">Hai già l'account? Vai ai servizi</a>
                    </div>
                </form>

            <?php else: ?>
                <!-- FORM FASE 1: PRIMO ACCESSO -->
                <h5 class="card-title text-center mb-4 fw-bold">Benvenuto!</h5>
                <p class="text-muted text-center small mb-4">Effettua il primo accesso utilizzando il tuo Codice Fiscale e la password temporanea che hai ricevuto via SMS.</p>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger small p-2"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form action="pre_register.php" method="POST" autocomplete="off">
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-id-card me-2"></i>Codice Fiscale</span>
                        <input type="text" id="codice_fiscale" name="codice_fiscale" class="form-control text-uppercase" required autocomplete="off">
                    </div>
                    <div class="input-group mb-3">
                        <span class="input-group-text" style="width: 150px;"><i class="fas fa-key me-2"></i>Password Temp.</span>
                        <input type="password" id="temp_password" name="temp_password" class="form-control" required autocomplete="off">
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="temp_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary fw-bold">Procedi</button>
                        <a href="servizi.php" class="btn btn-secondary">Hai già laccount? Vai ai servizi</a>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Logica per mostrare/nascondere la password (ora globale per tutta la pagina) ---
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    togglePasswordButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const targetInput = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                targetInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Seleziona il form di completamento registrazione, se esiste
    const registrationForm = document.querySelector('form input[name="action"][value="complete_registration"]');

    if (registrationForm) {
        const form = registrationForm.closest('form');
        const passwordInput = document.getElementById('password');
        const passwordConfirmInput = document.getElementById('password_confirm');
        const passwordFeedback = document.getElementById('password-feedback');
        const passwordConfirmFeedback = document.getElementById('password-confirm-feedback');
        const emailInput = document.getElementById('email');
        const emailConfirmInput = document.getElementById('email_confirm');
        const emailConfirmFeedback = document.getElementById('email-confirm-feedback');

        // Disabilita l'operazione "incolla" sui campi di conferma per forzare la digitazione
        emailConfirmInput.addEventListener('paste', (e) => {
            e.preventDefault();
            alert("Per sicurezza, per favore digita nuovamente l'email.");
        });
        passwordConfirmInput.addEventListener('paste', (e) => {
            e.preventDefault();
            alert("Per sicurezza, per favore digita nuovamente la password.");
        });

        const validateEmails = () => {
            let isValid = true;
            const email = emailInput.value;
            const confirmEmail = emailConfirmInput.value;

            emailConfirmInput.classList.remove('is-invalid', 'is-valid');

            if (confirmEmail.length > 0) {
                if (email !== confirmEmail) {
                    emailConfirmInput.classList.add('is-invalid');
                    emailConfirmFeedback.textContent = 'Gli indirizzi email non coincidono.';
                    isValid = false;
                } else {
                    emailConfirmInput.classList.add('is-valid');
                }
            }
            return isValid;
        };

        const validatePasswords = () => {
            let isValid = true;
            const pass = passwordInput.value;
            const confirmPass = passwordConfirmInput.value;

            // Reset
            passwordInput.classList.remove('is-invalid', 'is-valid');
            passwordConfirmInput.classList.remove('is-invalid', 'is-valid');

            // 1. Controllo lunghezza password
            if (pass.length > 0 && pass.length < 8) {
                passwordInput.classList.add('is-invalid');
                passwordFeedback.textContent = 'La password deve essere di almeno 8 caratteri.';
                isValid = false;
            } else if (pass.length >= 8) {
                passwordInput.classList.add('is-valid');
            }

            // 2. Controllo coincidenza password
            if (confirmPass.length > 0) {
                if (pass !== confirmPass) {
                    passwordConfirmInput.classList.add('is-invalid');
                    passwordConfirmFeedback.textContent = 'Le password non coincidono.';
                    isValid = false;
                } else if (pass.length >= 8) {
                    passwordConfirmInput.classList.add('is-valid');
                }
            }
            
            return isValid;
        };

        // Aggiungi listener per la validazione in tempo reale
        emailInput.addEventListener('input', validateEmails);
        emailConfirmInput.addEventListener('input', validateEmails);
        passwordInput.addEventListener('input', validatePasswords);
        passwordConfirmInput.addEventListener('input', validatePasswords);

        // Aggiungi listener per il submit del form
        form.addEventListener('submit', function(event) {
            // Esegui la validazione finale prima di inviare
            const emailsValid = validateEmails();
            const passwordsValid = validatePasswords();
            if (!emailsValid || !passwordsValid) {
                event.preventDefault(); // Blocca l'invio del form se non è valido
                // Trova il primo campo con errore e mostra un alert o fai lo scroll
                const firstInvalidField = form.querySelector('.is-invalid');
                if (firstInvalidField) {
                    firstInvalidField.focus();
                }
            }
        });
    }
});
</script>
</body>
</html>

<!--
### Note sulla configurazione del database

Perché questo sistema funzioni, la tua tabella `users` nel database `fillea-app` deve essere predisposta per contenere i seguenti campi:

*   `codfisc`: Per il codice fiscale.
*   `telefono`: Per il numero di telefono a cui inviare l'SMS.
*   `temp_password`: Per la password temporanea.
*   `status`: Un campo di tipo `VARCHAR` o `ENUM` che può contenere valori come `'pre-registrato'` e `'attivo'`.

Quando importi i dati per la campagna SMS, dovrai popolarla con `codfisc`, `telefono`, `temp_password` e impostare lo `status` a `'pre-registrato'`. La pagina che ho creato si occuperà di completare il resto.
!-->
<!--
[PROMPT_SUGGESTION]Come posso generare le password temporanee e aggiornare il database in massa?[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]Modifica la pagina per richiedere all'utente di creare una nuova password personale durante l'attivazione.[/PROMPT_SUGGESTION]
