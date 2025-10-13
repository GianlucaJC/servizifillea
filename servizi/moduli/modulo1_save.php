<?php
session_start();

// Pulisci le informazioni di debug precedenti all'inizio di ogni richiesta
require_once __DIR__ . '/../../vendor/autoload.php'; // Corretto per raggiungere la root
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
 
// 1. Inizializzazione e recupero dati
$token = $_SESSION['user_token'] ?? null; // Leggi il token dalla sessione, non dall'URL
$action = $_POST['action'] ?? 'save'; // 'save' o 'submit_official'
$form_name = $_POST['form_name'] ?? null;
$is_admin_save = false;

if (empty($token) || empty($form_name)) {
    // Se è un admin, il token utente non è presente, quindi controlliamo la sessione admin
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        die("Accesso non autorizzato o dati mancanti.");
    }
}

// Includi il file di connessione al database
include_once("../../database.php");
$pdo1 = Database::getInstance('fillea');

// 2. Recupera l'ID dell'utente dal token o verifica se è un admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) { // Se è un admin
    // È un admin. Recuperiamo l'user_id dal form name, perché l'admin non ha un token utente.
    $stmt_get_user = $pdo1->prepare("SELECT user_id FROM `fillea-app`.`modulo1_richieste` WHERE form_name = ?");
    $stmt_get_user->execute([$form_name]);
    $request_owner = $stmt_get_user->fetch(PDO::FETCH_ASSOC);
    $user_id = $request_owner['user_id'] ?? null;
    $is_admin_save = true;

} else { // Se è un utente
    $sql_user = "SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW() LIMIT 1";
    $stmt_user = $pdo1->prepare($sql_user);
    $stmt_user->execute([$token]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $user_id = $user['id'] ?? null;
}

if (!$user_id) {
    die("Utente non identificato.");
}

// 3. Prepara i dati da salvare
$data = [
    'studente_nome_cognome' => $_POST['studente_nome_cognome'] ?? null,
    'studente_data_nascita' => $_POST['studente_data_nascita'] ?? null,
    'studente_luogo_nascita' => $_POST['studente_luogo_nascita'] ?? null,
    'studente_codice_fiscale' => $_POST['studente_codice_fiscale'] ?? null,
    'studente_indirizzo' => $_POST['studente_indirizzo'] ?? null,
    'studente_cap' => $_POST['studente_cap'] ?? null,
    'studente_comune' => $_POST['studente_comune'] ?? null,
    'lavoratore_nome_cognome' => $_POST['lavoratore_nome_cognome'] ?? null,
    'lavoratore_data_nascita' => $_POST['lavoratore_data_nascita'] ?? null,
    'lavoratore_codice_cassa' => $_POST['lavoratore_codice_cassa'] ?? null,
    'lavoratore_telefono' => $_POST['lavoratore_telefono'] ?? null,
    'lavoratore_impresa' => $_POST['lavoratore_impresa'] ?? null,
    'iban' => $_POST['iban'] ?? null,
    'intestatario_conto' => $_POST['intestatario_conto'] ?? null,
    'altri_redditi' => $_POST['altri_redditi'] ?? null,
    'luogo_firma' => $_POST['luogo_firma'] ?? null,
    'data_firma' => $_POST['data_firma'] ?? null,
    'firma_data' => $_POST['firma_data'] ?? null,
    'privacy_consent' => isset($_POST['privacy_consent']) ? 1 : 0,
];


// 4. Prepara il JSON per le prestazioni
// La prestazione è ora singola e passata tramite un campo hidden
$prestazioni_data = [];
if (isset($_POST['prestazione']) && !empty($_POST['prestazione'])) {
    $tipo_prestazione = $_POST['prestazione'];
    $prestazioni_data[$tipo_prestazione] = $tipo_prestazione; // Salviamo il tipo di prestazione nel JSON
}
$data['prestazioni'] = json_encode($prestazioni_data);

// 5. Controlla se esiste già un record per questo utente e form
$sql_check = "SELECT id, status FROM `fillea-app`.`modulo1_richieste` WHERE form_name = ? AND user_id = ?";
$stmt_check = $pdo1->prepare($sql_check);
$stmt_check->execute([$form_name, $user_id]);
$existing_record = $stmt_check->fetch(PDO::FETCH_ASSOC);

// Inizia una transazione per garantire l'integrità dei dati
$pdo1->beginTransaction();

try {
    // Determina lo stato finale della richiesta
    $status = $existing_record['status'] ?? 'bozza'; // Default a bozza se nuovo
    if ($action === 'submit_official') {
        $status = 'inviato';
    } elseif ($action === 'unlock') {
        $status = 'bozza';
    }
    $data['status'] = $status;

    if ($is_admin_save && $action === 'unlock') {
        // AZIONE ADMIN: Sblocco della richiesta per l'utente
        $admin_notification = $_POST['admin_notification'] ?? 'La tua richiesta è stata sbloccata per modifiche.';
        if (empty($admin_notification)) {
            $admin_notification = 'La tua richiesta è stata sbloccata per modifiche.';
        }

        $stmt_unlock = $pdo1->prepare("UPDATE `fillea-app`.`modulo1_richieste` SET status = 'bozza', admin_notification = ? WHERE form_name = ? AND user_id = ?");
        $stmt_unlock->execute([$admin_notification, $form_name, $user_id]);

        // --- INIZIO LOGICA INVIO NOTIFICA PUSH DI SBLOCCO ---
        // Per costruire l'URL corretto per la notifica, dobbiamo recuperare il token dell'utente.
        $stmt_get_token = $pdo1->prepare("SELECT token FROM `fillea-app`.users WHERE id = ?");
        $stmt_get_token->execute([$user_id]);
        $user_for_token = $stmt_get_token->fetch(PDO::FETCH_ASSOC);
        $user_token_for_notification = $user_for_token['token'] ?? '';
        try {
            $stmt_subs = $pdo1->prepare("SELECT * FROM `fillea-app`.push_subscriptions WHERE user_id = ?");
            $stmt_subs->execute([$user_id]);
            $subscriptions = $stmt_subs->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($subscriptions)) {
                include_once(__DIR__ . '/../../push_config.php');
                $webPush = PushService::getInstance();
                $url_to_open = "https://www.filleaoffice.it:8013/servizifillea/servizi/moduli/modulo1.php?token={$user_token_for_notification}&form_name={$form_name}";
                $payload = json_encode(['title' => 'Pratica Sbloccata', 'body' => $admin_notification, 'url' => $url_to_open]);

                foreach ($subscriptions as $sub_data) {
                    // Mappa i campi del DB ai nomi attesi dalla libreria
                    $subscription = Subscription::create([
                        'endpoint' => $sub_data['endpoint'],
                        'publicKey' => $sub_data['p256dh'],
                        'authToken' => $sub_data['auth'],
                        'contentEncoding' => $sub_data['content_encoding'] ?? 'aesgcm',
                    ]);
                    $webPush->queueNotification($subscription, $payload);
                }
                // Controlla i risultati dell'invio per catturare errori silenti
                foreach ($webPush->flush() as $report) {
                    $endpoint = $report->getRequest()->getUri()->__toString();
                    if (!$report->isSuccess()) {
                        error_log("[Web-Push Sblocco] Invio fallito per {$endpoint}: {$report->getReason()}");
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Errore invio notifica push di sblocco: " . $e->getMessage());
        }
        // --- FINE LOGICA INVIO NOTIFICA PUSH ---
    }

    // Esegui il salvataggio (INSERT/UPDATE) solo se l'azione è 'save' o 'submit_official'
    if ($action === 'save' || $action === 'submit_official') {
        if ($existing_record) {
            // Record esistente, esegui un UPDATE
            $data['id'] = $existing_record['id'];

            // CONTROLLO DI SICUREZZA: L'admin può salvare solo se lo stato è 'inviato'.
            if ($is_admin_save && $existing_record['status'] !== 'inviato') {
                throw new Exception("L'amministratore può modificare una richiesta solo se si trova nello stato 'Inviato'.");
            }

            // La notifica dell'admin viene cancellata (impostata a NULL) solo quando l'utente invia ufficialmente la pratica.
            $admin_notification_sql = ($action === 'submit_official') ? ", admin_notification = NULL" : "";

            // Non sovrascrivere una firma esistente con una vuota.
            // Aggiorna la firma solo se ne viene fornita una nuova.
            $firma_sql_part = !empty($data['firma_data']) ? ", firma_data = :firma_data" : "";

            $sql = "UPDATE `fillea-app`.`modulo1_richieste` SET 
                        studente_nome_cognome = :studente_nome_cognome, studente_data_nascita = :studente_data_nascita, studente_luogo_nascita = :studente_luogo_nascita, studente_codice_fiscale = :studente_codice_fiscale, studente_indirizzo = :studente_indirizzo, studente_cap = :studente_cap, studente_comune = :studente_comune, 
                        lavoratore_nome_cognome = :lavoratore_nome_cognome, lavoratore_data_nascita = :lavoratore_data_nascita, lavoratore_codice_cassa = :lavoratore_codice_cassa, lavoratore_telefono = :lavoratore_telefono, lavoratore_impresa = :lavoratore_impresa, 
                        prestazioni = :prestazioni, iban = :iban, intestatario_conto = :intestatario_conto, altri_redditi = :altri_redditi, luogo_firma = :luogo_firma, data_firma = :data_firma, privacy_consent = :privacy_consent, status = :status, last_update = NOW()
                        {$admin_notification_sql} {$firma_sql_part}
                    WHERE id = :id";
            if (empty($data['firma_data'])) unset($data['firma_data']); // Rimuovi dal binding se vuoto
        } else {
            // Nuovo record, esegui un INSERT
            $data['user_id'] = $user_id;
            $data['form_name'] = $form_name;
            $sql = "INSERT INTO `fillea-app`.`modulo1_richieste` 
                        (user_id, form_name, status, studente_nome_cognome, studente_data_nascita, studente_luogo_nascita, studente_codice_fiscale, studente_indirizzo, studente_cap, studente_comune, lavoratore_nome_cognome, lavoratore_data_nascita, lavoratore_codice_cassa, lavoratore_telefono, lavoratore_impresa, prestazioni, iban, intestatario_conto, altri_redditi, luogo_firma, data_firma, privacy_consent, firma_data, last_update, admin_notification) 
                    VALUES 
                        (:user_id, :form_name, :status, :studente_nome_cognome, :studente_data_nascita, :studente_luogo_nascita, :studente_codice_fiscale, :studente_indirizzo, :studente_cap, :studente_comune, :lavoratore_nome_cognome, :lavoratore_data_nascita, :lavoratore_codice_cassa, :lavoratore_telefono, :lavoratore_impresa, :prestazioni, :iban, :intestatario_conto, :altri_redditi, :luogo_firma, :data_firma, :privacy_consent, :firma_data, NOW(), NULL)";
        }
        
        $stmt = $pdo1->prepare($sql);
        $stmt->execute($data);
        $richiesta_id = $existing_record ? $existing_record['id'] : $pdo1->lastInsertId();
    }
    
    // 7. Aggiorna la tabella master delle richieste
    if ($action === 'submit_official') {
        // Se l'utente ha selezionato un funzionario dal dropdown, usa quello.
        // Altrimenti, recupera quello di default associato all'utente.
        $id_funzionario_scelto = $_POST['id_funzionario'] ?? null;

        // ESEGUI SEMPRE L'INSERIMENTO/AGGIORNAMENTO NELLA TABELLA MASTER
        // CORREZIONE: Aggiunto richiesta_id per risolvere il bug della sovrascrittura.
        $sql_master = "INSERT INTO `fillea-app`.`richieste_master` (user_id, id_funzionario, modulo_nome, form_name, richiesta_id, data_invio, status, is_new) 
                       VALUES (:user_id, :id_funzionario, 'Contributi di Studio', :form_name, :richiesta_id, NOW(), 'inviato', 1) 
                       ON DUPLICATE KEY UPDATE data_invio = NOW(), status = 'inviato', is_new = 1, id_funzionario = :id_funzionario_upd";
        $stmt_master = $pdo1->prepare($sql_master);
        $stmt_master->execute(['user_id' => $user_id, 'id_funzionario' => $id_funzionario_scelto, 'form_name' => $form_name, 'richiesta_id' => $richiesta_id, 'id_funzionario_upd' => $id_funzionario_scelto]);

        // --- INVIA NOTIFICA PUSH AL FUNZIONARIO ---
        try {
            // 1. Recupera l'ID del funzionario associato all'utente
            $stmt_funzionario = $pdo1->prepare("SELECT id_funzionario FROM `fillea-app`.users WHERE id = ?");
            $stmt_funzionario->execute([$user_id]);
            $funzionario = $stmt_funzionario->fetch(PDO::FETCH_ASSOC);

            if ($funzionario && $funzionario['id_funzionario']) {
                // 2. Recupera le sottoscrizioni del funzionario
                // CORREZIONE: I funzionari sono anch'essi utenti. Le loro sottoscrizioni
                // sono salvate con il loro user_id, non con un campo 'funzionario_id'.
                $stmt_subs = $pdo1->prepare("SELECT * FROM `fillea-app`.push_subscriptions WHERE user_id = ?");
                $stmt_subs->execute([$funzionario['id_funzionario']]); // L'ID del funzionario è l'user_id del funzionario
                $subscriptions = $stmt_subs->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($subscriptions)) {
                    include_once(__DIR__ . '/../../push_config.php');
                    $webPush = PushService::getInstance();
                    $url_to_open = "https://www.filleaoffice.it:8013/servizifillea/admin/admin_documenti.php"; // L'admin atterra sulla lista documenti
                    $payload = json_encode(['title' => 'Nuova Pratica Ricevuta', 'body' => "L'utente ha inviato una nuova pratica da visionare.", 'url' => $url_to_open]);

                    foreach ($subscriptions as $sub_data) {
                        // Mappa i campi del DB ai nomi attesi dalla libreria
                        $subscription = Subscription::create([
                            'endpoint' => $sub_data['endpoint'],
                            'publicKey' => $sub_data['p256dh'],
                            'authToken' => $sub_data['auth'],
                            'contentEncoding' => $sub_data['content_encoding'] ?? 'aesgcm',
                        ]);
                        $webPush->queueNotification($subscription, $payload);
                    }
                    // Controlla i risultati dell'invio per catturare errori silenti
                    foreach ($webPush->flush() as $report) {
                        $endpoint = $report->getRequest()->getUri()->__toString();
                        if (!$report->isSuccess()) {
                            error_log("[Web-Push Funzionario] Invio fallito per {$endpoint}: {$report->getReason()}");
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Errore invio notifica push al funzionario: " . $e->getMessage());
        }
    } elseif ($action === 'unlock') {
        // Se l'admin sblocca, aggiorna lo stato anche nella tabella master
        $sql_master_unlock = "UPDATE `fillea-app`.`richieste_master` SET status = 'bozza' WHERE form_name = ? AND user_id = ?";
        $stmt_master_unlock = $pdo1->prepare($sql_master_unlock);
        $stmt_master_unlock->execute([$form_name, $user_id]);
    }

    // Se l'azione è un salvataggio (non invio ufficiale), aggiorna solo la data di modifica nella master
    if ($action === 'save') {
        // NOTA: La tabella 'richieste_master' non ha una colonna 'last_update'.
        // Se un record esiste già, la data di invio viene aggiornata con ON DUPLICATE KEY UPDATE.
        // Se è un salvataggio intermedio (bozza), non è necessario aggiornare la tabella master
        // finché non avviene l'invio ufficiale. Pertanto, questo blocco è stato commentato
        // per prevenire l'errore "column not found".
    }

    $pdo1->commit();

} catch (Exception $e) {
    $pdo1->rollBack();
    // Log dell'errore
    error_log("Errore in modulo1_save.php: " . $e->getMessage());
    $error_message = urlencode($e->getMessage());
    // Reindirizzamento con messaggio di errore
    if ($is_admin_save) {
        header("Location: modulo1.php?form_name=$form_name&user_id=$user_id&error=$error_message");
    } else {
        header("Location: modulo1.php?token=$token&form_name=$form_name&error=$error_message");
    }
    exit;
}

// 8. Reindirizza alla pagina del modulo con un messaggio di successo
if ($is_admin_save) {
    // Se è un admin, il token non è necessario, ma user_id sì
    header("Location: modulo1.php?form_name=$form_name&user_id=$user_id&status=saved&action=$action&prestazione=" . urlencode($_POST['prestazione']));
} else {
    // Se è un utente, usa il token
    header("Location: modulo1.php?token=$token&form_name=$form_name&status=saved&action=$action&prestazione=" . urlencode($_POST['prestazione']));
        }
exit;
