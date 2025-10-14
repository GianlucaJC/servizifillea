<?php
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
                try {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $nome_post = trim($_POST['nome'] ?? '');
                    $cognome_post = trim($_POST['cognome'] ?? '');
                    // Crea uno username fittizio con cognome e iniziale del nome (es. Rossi.M)
                    $username_fittizio = $cognome_post . '.' . mb_substr($nome_post, 0, 1);

                    $insert_sql = "INSERT INTO `fillea-app`.users 
                                    (nome, cognome, username, data_nascita, codfisc, telefono, email, password, settore, status, id_funzionario, created_at, updated_at) 
                                   VALUES 
                                    (:nome, :cognome, :username, :data_nascita, :codfisc, :telefono, :email, :password, :settore, 'attivo', 0, NOW(), NOW())";
                    
                    $stmt_insert = $pdo_fillea->prepare($insert_sql);
                    $stmt_insert->execute([
                        ':nome' => $nome_post,
                        ':cognome' => $cognome_post,
                        ':username' => $username_fittizio,
                        ':data_nascita' => $_POST['data_nascita'] ?? null,
                        ':codfisc' => strtoupper(trim($_POST['codfisc'] ?? '')),
                        ':telefono' => trim($_POST['telefono'] ?? ''),
                        ':email' => trim($_POST['email'] ?? ''),
                        ':password' => $hashed_password,
                        ':settore' => $_POST['settore'] ?? null,
                    ]);

                    $user_id = $pdo_fillea->lastInsertId();

                    if ($user_id) {
                        $resp['header'] = "OK";
                        $resp['message'] = "Registrazione completata con successo! Verrai reindirizzato al login.";
                    } else {
                        $resp['message'] = "Si è verificato un errore imprevisto durante la creazione dell'account.";
                    }

                } catch (PDOException $e) {
                    // Controlla se l'errore è una violazione di chiave unica (es. email o codfisc già esistente)
                    if ($e->getCode() == 23000) {
                        $resp['message'] = "Errore: Codice Fiscale o Email già presenti nel sistema.";
                    } else {
                        $resp['message'] = "Errore del database. Riprova più tardi.";
                        error_log("Errore registrazione utente: " . $e->getMessage());
                    }
                }
            }
        }

        print json_encode($resp);
        exit;
    }
?>