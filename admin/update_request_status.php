<?php
include_once __DIR__ . '/../session_config.php'; // Includi la configurazione della sessione
session_start(); // Avvia la sessione DOPO aver impostato i parametri

// Includi l'autoloader di Composer per usare la libreria WebPush
require_once __DIR__ . '/../vendor/autoload.php';
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// 1. Proteggi lo script: solo gli admin possono accedervi.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.0 403 Forbidden');
    die('Accesso non autorizzato.');
}

// 2. Verifica che i dati necessari siano stati inviati tramite POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['form_name']) || !isset($_POST['new_status'])) {
    header('Location: admin_documenti.php?error=invalid_request');
    exit;
}

$form_name = $_POST['form_name'];
$new_status = $_POST['new_status'];

// 3. Valida lo stato per sicurezza.
$allowed_statuses = ['abbandonato', 'inviato_in_cassa_edile', 'bozza']; // Aggiunto 'bozza' per il ripristino
if (!in_array($new_status, $allowed_statuses)) {
    header('Location: admin_documenti.php?error=invalid_status');
    exit;
}

include_once('../database.php');
$pdo1 = Database::getInstance('fillea');

// CONTROLLO DI SICUREZZA AGGIUNTIVO:
// 1. Impedisce di modificare lo stato di una richiesta in "bozza".
// 2. Verifica che la richiesta appartenga a un utente gestito da questo funzionario.
$funzionario_id = $_SESSION['funzionario_id'] ?? 0;
$stmt_check_request = $pdo1->prepare("
    SELECT rm.status, rm.user_id
    FROM `fillea-app`.`richieste_master` rm
    JOIN `fillea-app`.`users` u ON rm.user_id = u.id
    WHERE rm.form_name = ? AND u.id_funzionario = ?
");
$stmt_check_request->execute([$form_name, $funzionario_id]);
$current_request = $stmt_check_request->fetch(PDO::FETCH_ASSOC);

// Se lo stato corrente è 'bozza' e non si sta cercando di ripristinare (il che sarebbe illogico), blocca.
// Permette l'azione se lo stato corrente è 'abbandonato' e il nuovo stato è 'bozza'.
if (!$current_request || ($current_request['status'] === 'bozza' && $new_status !== 'bozza') || !$current_request['user_id']) {
    header('Location: admin_documenti.php?error=draft_action_forbidden');
    exit;
}

try {
    $pdo1->beginTransaction();

    // 4. Aggiorna lo stato nella tabella master.
    $stmt_master = $pdo1->prepare("UPDATE `fillea-app`.`richieste_master` SET status = ?, user_notification_unseen = 1 WHERE form_name = ? AND id_funzionario = ?");
    $stmt_master->execute([$new_status, $form_name, $funzionario_id]);

    // 5. Aggiorna lo stato nella tabella specifica del modulo (es. modulo1).
    $stmt_modulo = $pdo1->prepare("UPDATE `fillea-app`.`modulo1_richieste` SET status = ? WHERE form_name = ?");
    $stmt_modulo->execute([$new_status, $form_name]);

    $pdo1->commit();

    // --- INIZIO LOGICA INVIO NOTIFICA PUSH ---
    try {
        // 1. Recupera le sottoscrizioni dell'utente specifico
        $stmt_subs = $pdo1->prepare("SELECT * FROM `fillea-app`.push_subscriptions WHERE user_id = ?");
        $stmt_subs->execute([$current_request['user_id']]);
        $subscriptions = $stmt_subs->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($subscriptions)) {
            include_once(__DIR__ . '/../push_config.php');
            $webPush = PushService::getInstance();

            // Recupera il token dell'utente per costruire l'URL corretto
            $stmt_token = $pdo1->prepare("SELECT token FROM `fillea-app`.users WHERE id = ?");
            $stmt_token->execute([$current_request['user_id']]);
            $user_token = $stmt_token->fetchColumn();

            // 3. Prepara il payload della notifica
            $payload = json_encode([
                'title' => 'Aggiornamento Pratica',
                'body' => "Lo stato della tua pratica è cambiato in: " . str_replace('_', ' ', $new_status),
                // URL corretto che porta l'utente alla lista dei suoi servizi
                'url' => "https://www.filleaoffice.it:8013/servizifillea/servizi.php?token={$user_token}"
            ]);

            // 4. Invia la notifica a tutti i dispositivi dell'utente
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
                    // Logga l'errore specifico per ogni invio fallito
                    error_log("[Web-Push Aggiornamento Stato] Invio fallito per {$endpoint}: {$report->getReason()}");
                }
            }
        }
    } catch (Exception $e) {
        // Non bloccare il flusso principale se l'invio della notifica fallisce.
        // Logga l'errore per il debug.
        error_log("Errore invio notifica push: " . $e->getMessage());
    }
    // --- FINE LOGICA INVIO NOTIFICA PUSH ---

} catch (Exception $e) {
    $pdo1->rollBack();
    // In un'app reale, qui si dovrebbe loggare l'errore.
    header('Location: admin_documenti.php?error=db_error');
    exit;
}

// 6. Reindirizza alla pagina dei documenti con un messaggio di successo.
header('Location: admin_documenti.php?status_updated=true');
exit;