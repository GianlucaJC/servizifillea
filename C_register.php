<?php
    // Includi PHPMailer
    require_once 'vendor/autoload.php';
    use PHPMailer\PHPMailer\PHPMailer;
    // Includi il file di configurazione del database
    include_once 'database.php'; 
    
    // Ottieni l'istanza del database 'fillea' per recuperare i funzionari
    $pdo_fillea = Database::getInstance('fillea');
    $stmt_funzionari = $pdo_fillea->query("SELECT id, funzionario, zona FROM `fillea-app`.funzionari WHERE is_super_admin = 0 ORDER BY funzionario ASC");
    $funzionari = $stmt_funzionari->fetchAll(PDO::FETCH_ASSOC);
    // La connessione non viene chiusa qui per permettere ad altri script di usarla
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
        $action = $_POST['action'] ?? 'verify'; // 'verify' o 'register'
        $resp = ['header' => 'KO', 'message' => 'Azione non valida.'];

        if ($action === 'verify') {
            // --- FASE 1: VERIFICA UTENTE IN ANAGRAFE ---
            $cognome = trim($_POST['cognome'] ?? '');
            $nome = trim($_POST['nome'] ?? '');
            $codfisc = strtoupper(trim($_POST['codfisc'] ?? ''));
            $nominativo = strtoupper(trim("$cognome $nome"));
            $data_nascita = $_POST['data_nascita'] ?? '';

            if (empty($codfisc)) $codfisc = "xxx!!!";

            // Ottieni l'istanza del database 'anagrafe'
            $pdo_anagrafe = Database::getInstance('anagrafe');
            $sql = "SELECT sindacato FROM anagrafe.t2_tosc_a WHERE (nome = ? AND attivi = 'S' AND CAST(datanasc AS DATE) = ?) OR codfisc = ? LIMIT 1";
            $stmt = $pdo_anagrafe->prepare($sql);
            $stmt->execute([$nominativo, $data_nascita, $codfisc]);
            $check_anagrafe = $stmt->fetch(PDO::FETCH_ASSOC);
            $pdo_anagrafe = null;

            // Controlla se l'utente è già registrato in fillea-app.users
            $stmt_check_fillea = $pdo_fillea->prepare("SELECT id FROM `fillea-app`.users WHERE codfisc = ?");
            $stmt_check_fillea->execute([$codfisc]);
            if ($stmt_check_fillea->fetch()) {
                $resp['header'] = "KO";
                $resp['message'] = "Un account con questo Codice Fiscale esiste già. Se hai dimenticato la password, puoi recuperarla dalla pagina di login.";
            } else {
                $resp['header'] = "OK";
                $resp['info'] = $check_anagrafe;
            }

        } elseif ($action === 'register') {
            // --- FASE 2: CREAZIONE ACCOUNT ---
            $password = $_POST['password'] ?? '';
            if (empty($password) || strlen($password) < 8) {
                $resp['message'] = 'La password non è valida o è troppo corta.';
            } else {
                $email_post = trim($_POST['email'] ?? '');
                $codfisc_post = strtoupper(trim($_POST['codfisc'] ?? ''));
                $nome_post = trim($_POST['nome'] ?? '');
                $cognome_post = trim($_POST['cognome'] ?? '');
                $data_nascita_post = $_POST['data_nascita'] ?? null;

                // --- VALIDAZIONE PREVENTIVA PER EVITARE DUPLICATI ---
                $check_sql = "SELECT email, codfisc FROM `fillea-app`.users 
                              WHERE email = :email 
                              OR codfisc = :codfisc 
                              OR (nome = :nome AND cognome = :cognome AND data_nascita = :data_nascita)
                              LIMIT 1";
                $stmt_check = $pdo_fillea->prepare($check_sql);
                $stmt_check->execute([
                    ':email' => $email_post,
                    ':codfisc' => $codfisc_post,
                    ':nome' => $nome_post,
                    ':cognome' => $cognome_post,
                    ':data_nascita' => $data_nascita_post
                ]);
                $existing_user = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if ($existing_user) {
                    if (strcasecmp($existing_user['email'], $email_post) == 0) {
                        $resp['message'] = "Un account con questa email esiste già. Se hai dimenticato la password, puoi recuperarla dalla pagina di login.";
                    } elseif (strcasecmp($existing_user['codfisc'], $codfisc_post) == 0) {
                        $resp['message'] = "Un account con questo Codice Fiscale esiste già. Se hai dimenticato la password, puoi recuperarla dalla pagina di login.";
                    } else {
                        $resp['message'] = "Un account con lo stesso Nome, Cognome e Data di Nascita risulta già registrato.";
                    }
                } else {
                    // Nessun utente esistente trovato, procedi con la registrazione
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    // Crea un 'name' fittizio con cognome e iniziale del nome (es. RossiM)
                    $name = $cognome_post . substr($nome_post, 0, 1);

                    // Genera token di verifica e data di scadenza
                    $verification_token = bin2hex(random_bytes(32));
                    $verification_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));

                    $insert_sql = "INSERT INTO `fillea-app`.users 
                                    (nome, cognome, name, data_nascita, codfisc, telefono, email, password, settore, status, email_verification_token, email_verification_expiry, id_funzionario, created_at, updated_at) 
                                   VALUES 
                                    (:nome, :cognome, :name, :data_nascita, :codfisc, :telefono, :email, :password, :settore, 'non_verificato', :token, :expiry, 0, NOW(), NOW())";

                    $stmt_insert = $pdo_fillea->prepare($insert_sql);
                    
                    $success = $stmt_insert->execute([
                        ':nome' => $nome_post,
                        ':cognome' => $cognome_post,
                        ':name' => $name,
                        ':data_nascita' => $data_nascita_post,
                        ':codfisc' => $codfisc_post,
                        ':telefono' => trim($_POST['telefono'] ?? ''),
                        ':email' => $email_post,
                        ':password' => $hashed_password,
                        ':settore' => $_POST['settore'] ?? null,
                        ':token' => $verification_token,
                        ':expiry' => $verification_expiry
                    ]);

                    if ($success) {
                        // --- Invio Email di Verifica ---
                        // (Il codice per l'invio dell'email rimane invariato)
                        try {
                            require_once 'admin/config_mail.php'; // Includi configurazione SMTP
                            $mail = new PHPMailer(true);
                            $mail->isSMTP();
                            $mail->Host = SMTP_HOST;
                            $mail->Port = SMTP_PORT;
                            if (!empty(SMTP_USERNAME) && !empty(SMTP_PASSWORD)) {
                                $mail->SMTPAuth = true;
                                $mail->Username = SMTP_USERNAME;
                                $mail->Password = SMTP_PASSWORD;
                            } else {
                                $mail->SMTPAuth = false;
                            }
                            $mail->SMTPSecure = false;
                            $mail->SMTPAutoTLS = false;

                            $mail->setFrom(SMTP_FROM_ADDRESS, SMTP_FROM_NAME);
                            $mail->addAddress($email_post);

                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                            $host = $_SERVER['HTTP_HOST'];
                            $verification_link = $protocol . $host . dirname($_SERVER['PHP_SELF']) . '/verify_email.php?token=' . $verification_token;

                            $mail->isHTML(true);
                            $mail->Subject = 'Conferma il tuo indirizzo email - Fillea Service App';
                            $mail->Body    = "Ciao $nome_post,<br><br>Grazie per esserti registrato! Per favore, clicca sul link qui sotto per confermare il tuo indirizzo email.<br><br><a href='$verification_link'>Conferma la mia email</a><br><br>Questo link scadrà tra 7 giorni. Se non confermi l'email entro questo periodo, il tuo account verrà disattivato.<br><br>Lo staff di Fillea CGIL Firenze";
                            $mail->AltBody = "Ciao $nome_post, vai a questo link per confermare la tua email: $verification_link. Se non confermi l'email entro 7 giorni, il tuo account verrà disattivato.";

                            $mail->send();
                            $resp['header'] = "OK";
                            $resp['message'] = "Registrazione completata! Ti abbiamo inviato un'email per confermare il tuo account. Controlla la tua casella di posta (anche lo spam).<br><strong>Ricorda: se non confermi l'email entro 7 giorni, il tuo account verrà disattivato.</strong>";
                        } catch (Exception $e) {
                            error_log("Errore invio email di verifica: " . $mail->ErrorInfo);
                            $resp['message'] = "Registrazione completata, ma non è stato possibile inviare l'email di verifica. Contatta l'assistenza.";
                        }
                    } else {
                        error_log("Errore registrazione utente: La query di inserimento non è andata a buon fine. Dettagli: " . implode(" - ", $stmt_insert->errorInfo()));
                        $resp['message'] = "Si è verificato un errore imprevisto durante la creazione dell'account.";
                    }
                }
            }
        }

        print json_encode($resp);
        exit;
    }
?>