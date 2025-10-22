<?php
session_start();

require_once __DIR__ . '/../../vendor/autoload.php'; // Includi autoloader per WebPush
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// Funzione di logging dedicata per le notifiche push
function log_push($message) {
    $log_file = __DIR__ . '/../../push_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] - $message\n", FILE_APPEND);
}

function log_sql($sql, $params, $context = '') {
    $log_file = __DIR__ . '/../../sql_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] - CONTEXT: $context\nSQL: " . preg_replace('/\s+/', ' ', $sql) . "\nPARAMS: " . json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n---\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// 1. Inizializzazione e recupero dati
$token = $_SESSION['user_token'] ?? null; // Leggi il token dalla sessione, non dall'URL
$action = $_POST['action'] ?? 'save'; // 'save' o 'submit_official'
$form_name = $_POST['form_name'] ?? null;
$user_id = null;
$is_admin_save = false;

include_once("../../database.php");
$pdo1 = Database::getInstance('fillea');

// 2. Recupera l'ID dell'utente dal token o verifica se è un admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $stmt_get_user = $pdo1->prepare("SELECT user_id FROM `fillea-app`.`modulo2_richieste` WHERE form_name = ?");
    $stmt_get_user->execute([$form_name]);
    $user_id = $stmt_get_user->fetchColumn();
    $is_admin_save = true;
} else { // Se è un utente
    $stmt_user = $pdo1->prepare("SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW() LIMIT 1");
    $stmt_user->execute([$token]);
    $user_id = $stmt_user->fetchColumn();
}

if (!$user_id || empty($form_name)) {
    die("Accesso non autorizzato o dati mancanti.");
}

// 3. Prepara i dati da salvare
$data = [
    'nome_completo' => $_POST['nome_completo'] ?? null,
    'pos_cassa_edile' => $_POST['pos_cassa_edile'] ?? null,
    'data_nascita' => $_POST['data_nascita'] ?? null,
    'codice_fiscale' => $_POST['codice_fiscale'] ?? null,
    'via_piazza' => $_POST['via_piazza'] ?? null,
    'domicilio_a' => $_POST['domicilio_a'] ?? null,
    'cap' => $_POST['cap'] ?? null,
    'telefono' => $_POST['telefono'] ?? null,
    'impresa_occupazione' => $_POST['impresa_occupazione'] ?? null,
    'luogo_firma' => $_POST['luogo_firma'] ?? null,
    'data_firma' => $_POST['data_firma'] ?? null,
    'firma_data' => $_POST['firma_data'] ?? null,
    'privacy_consent' => isset($_POST['privacy_consent']) ? 1 : 0,
];

// 4. Prepara il JSON per le prestazioni
// La prestazione è ora singola e passata tramite un campo hidden
$prestazioni_data = [];
if (isset($_POST['prestazione']) && !empty($_POST['prestazione'])) {
    $prestazioni_data[$_POST['prestazione']] = true; // Salviamo solo il tipo di prestazione
}
$data['prestazioni'] = json_encode($prestazioni_data);

// 5. Controlla se esiste già un record
$sql_check = "SELECT id, status FROM `fillea-app`.`modulo2_richieste` WHERE form_name = ? AND user_id = ?";
$stmt_check = $pdo1->prepare($sql_check);
$stmt_check->execute([$form_name, $user_id]);
$existing_record = $stmt_check->fetch(PDO::FETCH_ASSOC);

$pdo1->beginTransaction();
try {
    if ($is_admin_save && $action === 'unlock') {
        $admin_notification = $_POST['admin_notification'] ?? 'La tua richiesta è stata sbloccata per modifiche.';
        if (empty($admin_notification)) {
            $admin_notification = 'La tua richiesta è stata sbloccata per modifiche.';
        }
        $stmt_unlock = $pdo1->prepare("UPDATE `fillea-app`.`modulo2_richieste` SET status = 'bozza', admin_notification = ? WHERE form_name = ? AND user_id = ?");
        $stmt_unlock->execute([$admin_notification, $form_name, $user_id]);

        // Aggiorna anche la tabella master, impostando il flag di notifica per l'utente
        $stmt_master_unlock = $pdo1->prepare("UPDATE `fillea-app`.`richieste_master` SET status = 'bozza', is_new = 0, user_notification_unseen = 1 WHERE form_name = ? AND user_id = ?");
        $stmt_master_unlock->execute([$form_name, $user_id]);

        // --- INIZIO LOGICA INVIO NOTIFICA PUSH DI SBLOCCO ---
        log_push("[SBLOCCO UTENTE - M2] Avvio invio notifica per user_id: $user_id, form_name: $form_name.");
        try {
            $stmt_get_token = $pdo1->prepare("SELECT token FROM `fillea-app`.users WHERE id = ?");
            $stmt_get_token->execute([$user_id]);
            $user_token_for_notification = $stmt_get_token->fetchColumn();

            $stmt_subs = $pdo1->prepare("SELECT * FROM `fillea-app`.push_subscriptions WHERE user_id = ?");
            $stmt_subs->execute([$user_id]);
            $subscriptions = $stmt_subs->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($subscriptions)) {
                log_push("[SBLOCCO UTENTE - M2] Trovate " . count($subscriptions) . " sottoscrizioni per user_id: $user_id.");
                include_once(__DIR__ . '/../../push_config.php');
                $webPush = PushService::getInstance();
                $prestazione_param = urlencode($_POST['prestazione'] ?? '');
                $url_to_open = "https://www.filleaoffice.it:8013/servizifillea/servizi/moduli/modulo2.php?token={$user_token_for_notification}&form_name={$form_name}&prestazione={$prestazione_param}";
                $payload = json_encode(['title' => 'Pratica Sbloccata', 'body' => $admin_notification, 'url' => $url_to_open]);

                foreach ($subscriptions as $sub_data) {
                    $subscription = Subscription::create(['endpoint' => $sub_data['endpoint'], 'publicKey' => $sub_data['p256dh'], 'authToken' => $sub_data['auth'], 'contentEncoding' => $sub_data['content_encoding'] ?? 'aesgcm']);
                    $webPush->queueNotification($subscription, $payload);
                }
                foreach ($webPush->flush() as $report) {
                    $endpoint = $report->getRequest()->getUri()->__toString();
                    if (!$report->isSuccess()) {
                        log_push("[SBLOCCO UTENTE - M2] Invio fallito per {$endpoint}: {$report->getReason()}");
                    } else {
                        log_push("[SBLOCCO UTENTE - M2] Invio riuscito per {$endpoint}.");
                    }
                }
            } else {
                log_push("[SBLOCCO UTENTE - M2] Nessuna sottoscrizione push trovata per user_id: $user_id.");
            }
        } catch (Exception $e) {
            log_push("[SBLOCCO UTENTE - M2] ERRORE: " . $e->getMessage());
        }
        // --- FINE LOGICA INVIO NOTIFICA PUSH ---
    } else if ($action === 'save' || $action === 'submit_official') {
        $status = $existing_record['status'] ?? 'bozza';
        if ($action === 'submit_official') {
            $status = 'ricevuta';
        }
        $data['status'] = $status;

        if ($existing_record) {
            // UPDATE
            $data['id'] = $existing_record['id'];
            if ($is_admin_save && !in_array($existing_record['status'], ['ricevuta', 'letto_da_cassa_edile'])) {
                throw new Exception("L'amministratore può modificare una richiesta solo se è stata ricevuta o è in lettura.");
            }
            $firma_sql_part = !empty($data['firma_data']) ? ", firma_data = :firma_data" : "";
            $admin_notification_sql = ($action === 'submit_official') ? ", admin_notification = NULL" : "";

            $sql = "UPDATE `fillea-app`.`modulo2_richieste` SET 
                        nome_completo=:nome_completo, pos_cassa_edile=:pos_cassa_edile, data_nascita=:data_nascita, codice_fiscale=:codice_fiscale, via_piazza=:via_piazza, domicilio_a=:domicilio_a, cap=:cap, telefono=:telefono, impresa_occupazione=:impresa_occupazione, 
                        prestazioni=:prestazioni, luogo_firma=:luogo_firma, data_firma=:data_firma, privacy_consent=:privacy_consent, status=:status, last_update=NOW() {$admin_notification_sql}
                        {$firma_sql_part}
                    WHERE id = :id";
            if (empty($data['firma_data'])) unset($data['firma_data']);
        } else {
            // INSERT
            $data['user_id'] = $user_id;
            $data['form_name'] = $form_name;
            $sql = "INSERT INTO `fillea-app`.`modulo2_richieste` 
                        (user_id, form_name, status, nome_completo, pos_cassa_edile, data_nascita, codice_fiscale, via_piazza, domicilio_a, cap, telefono, impresa_occupazione, prestazioni, luogo_firma, data_firma, privacy_consent, firma_data, admin_notification, last_update) 
                    VALUES 
                        (:user_id, :form_name, :status, :nome_completo, :pos_cassa_edile, :data_nascita, :codice_fiscale, :via_piazza, :domicilio_a, :cap, :telefono, :impresa_occupazione, :prestazioni, :luogo_firma, :data_firma, :privacy_consent, :firma_data, NULL, NOW())";
        }
        
        log_sql($sql, $data, 'Salvataggio in modulo2_richieste');
        $stmt = $pdo1->prepare($sql);
        $stmt->execute($data);
        $richiesta_id = $existing_record ? $existing_record['id'] : $pdo1->lastInsertId();
    }

    if ($action === 'submit_official') {
        // Se l'utente ha selezionato un funzionario dal dropdown, usa quello.
        $id_funzionario_scelto = $_POST['id_funzionario'] ?? null;

        // CORREZIONE: Aggiunto richiesta_id per risolvere il bug della sovrascrittura.
        $sql_master = "INSERT INTO `fillea-app`.`richieste_master` (user_id, id_funzionario, modulo_nome, form_name, richiesta_id, data_invio, status, is_new) 
                       VALUES (:user_id, :id_funzionario, 'Prestazioni Varie', :form_name, :richiesta_id, NOW(), 'ricevuta', 1) 
                       ON DUPLICATE KEY UPDATE data_invio = NOW(), status = 'ricevuta', is_new = 1, id_funzionario = :id_funzionario_upd";
        $stmt_master = $pdo1->prepare($sql_master);
        $master_params = ['user_id' => $user_id, 'id_funzionario' => $id_funzionario_scelto, 'form_name' => $form_name, 'richiesta_id' => $richiesta_id, 'id_funzionario_upd' => $id_funzionario_scelto];
        log_sql($sql_master, $master_params, 'Salvataggio in richieste_master');
        $stmt_master->execute($master_params);

        // --- INVIA NOTIFICA PUSH AL FUNZIONARIO ---
        try {
            log_push("[INVIO FUNZIONARIO - M2] Avvio invio notifica per pratica: $form_name, funzionario_id: $id_funzionario_scelto.");
            $stmt_subs = $pdo1->prepare("SELECT * FROM `fillea-app`.push_subscriptions WHERE user_id = ?");
            $stmt_subs->execute([$id_funzionario_scelto]);
            $subscriptions = $stmt_subs->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($subscriptions)) {
                log_push("[INVIO FUNZIONARIO - M2] Trovate " . count($subscriptions) . " sottoscrizioni per funzionario_id: " . $id_funzionario_scelto);
                include_once(__DIR__ . '/../../push_config.php');
                $webPush = PushService::getInstance();
                $url_to_open = "https://www.filleaoffice.it:8013/servizifillea/admin/admin_documenti.php";
                $stmt_user_name = $pdo1->prepare("SELECT CONCAT(nome, ' ', cognome) FROM `fillea-app`.users WHERE id = ?");
                $stmt_user_name->execute([$user_id]);
                $user_full_name = $stmt_user_name->fetchColumn();
                $payload = json_encode(['title' => 'Nuova Pratica Ricevuta', 'body' => "L'utente {$user_full_name} ha inviato una nuova pratica da visionare.", 'url' => $url_to_open]);

                foreach ($subscriptions as $sub_data) {
                    $subscription = Subscription::create(['endpoint' => $sub_data['endpoint'], 'publicKey' => $sub_data['p256dh'], 'authToken' => $sub_data['auth'], 'contentEncoding' => $sub_data['content_encoding'] ?? 'aesgcm']);
                    $webPush->queueNotification($subscription, $payload);
                }
                foreach ($webPush->flush() as $report) {
                    $endpoint = $report->getRequest()->getUri()->__toString();
                    if (!$report->isSuccess()) {
                        log_push("[INVIO FUNZIONARIO - M2] Invio fallito per {$endpoint}: {$report->getReason()}");
                    } else {
                        log_push("[INVIO FUNZIONARIO - M2] Invio riuscito per {$endpoint}.");
                    }
                }
            } else {
                log_push("[INVIO FUNZIONARIO - M2] Nessuna sottoscrizione push trovata per funzionario_id: " . $id_funzionario_scelto);
            }
        } catch (Exception $e) {
            log_push("[INVIO FUNZIONARIO - M2] ERRORE: " . $e->getMessage());
        }
        // --- FINE NOTIFICA PUSH ---

    }

    $pdo1->commit();

} catch (Exception $e) {

    $pdo1->rollBack();
    error_log("Errore in modulo2_save.php: " . $e->getMessage());
    $error_message = urlencode($e->getMessage());
    $prestazione_param = urlencode($_POST['prestazione'] ?? '');
    $redirect_url = $is_admin_save ? "modulo2.php?form_name=$form_name&user_id=$user_id&prestazione=$prestazione_param&error=$error_message" : "modulo2.php?token=$token&form_name=$form_name&prestazione=$prestazione_param&error=$error_message";
    header("Location: $redirect_url");
    exit;
}

// 8. Reindirizza con messaggio di successo
$prestazione_param = urlencode($_POST['prestazione'] ?? '');
$redirect_url = $is_admin_save ? "modulo2.php?form_name=$form_name&user_id=$user_id&status=saved&action=$action&prestazione=$prestazione_param" : "modulo2.php?token=$token&form_name=$form_name&status=saved&action=$action&prestazione=$prestazione_param";
header("Location: $redirect_url");
exit;
?>