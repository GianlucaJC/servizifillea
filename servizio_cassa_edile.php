<?php
include_once 'session_config.php'; // Includi la configurazione della sessione
session_start(); // Avvia la sessione DOPO aver impostato i parametri

// 1. Recupera e verifica il token per la sicurezza
$token = $_GET['token'] ?? '';
$is_user_logged_in = false;
$user_id = null;
$user_forms_by_prestazione = [];

if (!empty($token)) {
    include_once("database.php");
    $pdo1 = Database::getInstance('fillea');

    $sql = "SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW() LIMIT 1";
    $stmt = $pdo1->prepare($sql);
    $stmt->execute([$token]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $is_user_logged_in = true;
        $user_id = $user['id'];
        $_SESSION['user_token'] = $token; // Memorizza il token nella sessione per un accesso consistente

        // Recupera le notifiche non lette per l'utente
        $stmt_notifications = $pdo1->prepare("
            SELECT 
                rm.form_name, 
                rm.status, 
                COALESCE(m1.prestazioni, m2.prestazioni) AS prestazioni,
                rm.modulo_nome
            FROM `fillea-app`.richieste_master rm
            LEFT JOIN `fillea-app`.modulo1_richieste m1 ON rm.form_name = m1.form_name COLLATE utf8mb4_unicode_ci
            LEFT JOIN `fillea-app`.modulo2_richieste m2 ON rm.form_name = m2.form_name COLLATE utf8mb4_unicode_ci
            WHERE rm.user_id = :user_id AND rm.user_notification_unseen = 1
            ORDER BY rm.data_invio DESC
        ");
        $stmt_notifications->execute([':user_id' => $user_id]);
        $unread_notifications = $stmt_notifications->fetchAll(PDO::FETCH_ASSOC);
        $unread_notifications_count = count($unread_notifications);

        // Recupera lo stato dell'ultima richiesta e il conteggio totale per ogni tipo di prestazione
        // MODIFICA: La query ora unisce i dati da modulo1 e modulo2 per includere anche le bozze non ancora inviate (che non sono in richieste_master).
        $stmt_forms = $pdo1->prepare("
            WITH AllRequests AS (
                SELECT user_id, 
                       prestazioni COLLATE utf8mb4_unicode_ci AS prestazioni, 
                       status COLLATE utf8mb4_unicode_ci AS status, 
                       last_update 
                FROM `fillea-app`.`modulo1_richieste` 
                WHERE user_id = :user_id AND status != 'abbandonato'
                UNION ALL
                SELECT user_id, 
                       prestazioni COLLATE utf8mb4_unicode_ci AS prestazioni, 
                       status COLLATE utf8mb4_unicode_ci AS status, 
                       last_update 
                FROM `fillea-app`.`modulo2_richieste` 
                WHERE user_id = :user_id AND status != 'abbandonato'
            ),
            RankedRequests AS (
                SELECT 
                    SUBSTRING_INDEX(SUBSTRING_INDEX(prestazioni, '\"', 2), '\"', -1) AS prestazione_key,
                    status,
                    ROW_NUMBER() OVER(PARTITION BY SUBSTRING_INDEX(SUBSTRING_INDEX(prestazioni, '\"', 2), '\"', -1) ORDER BY last_update DESC) as rn,
                    COUNT(*) OVER(PARTITION BY SUBSTRING_INDEX(SUBSTRING_INDEX(prestazioni, '\"', 2), '\"', -1)) as total_count
                FROM AllRequests
                WHERE prestazioni IS NOT NULL AND prestazioni != '[]' AND prestazioni != '{}'
            )
            SELECT 
                prestazione_key, 
                status,
                total_count AS count
            FROM RankedRequests 
            WHERE rn = 1
        ");
        $stmt_forms->execute([':user_id' => $user_id]);
        $results = $stmt_forms->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $key => $value) {
            $user_forms_by_prestazione[$value['prestazione_key']] = [
                'status' => $value['status'],
                'count' => $value['count']
            ];
        }
    }
    $pdo1 = null;
}

// 2. Se l'utente non è loggato, reindirizza alla pagina di login
if (!$is_user_logged_in) {
    header("Location: login.php");
    exit;
}

// Array associativo per la configurazione delle prestazioni
$prestazioni_config = [
    'premio_matrimoniale' => ['label' => 'Premio Matrimoniale / Unioni Civili', 'icon' => 'fa-solid fa-ring', 'module' => 'modulo2', 'modal_target' => '#modal_matrimonio', 'active' => true],
    'premio_giovani' => ['label' => 'Premio Giovani e Inserimento', 'icon' => 'fa-solid fa-person-running', 'module' => 'modulo2', 'modal_target' => '#modal_premiogiovani', 'active' => true],
    'bonus_nascita' => ['label' => 'Bonus Nascita o Adozione', 'icon' => 'fa-solid fa-baby', 'module' => 'modulo2', 'modal_target' => '#modal_nascita', 'active' => true],
    'donazioni_sangue' => ['label' => 'Donazioni del Sangue', 'icon' => 'fa-solid fa-pills', 'module' => 'modulo2', 'modal_target' => '#modal_donazioni', 'active' => true],
    'contributo_affitto' => ['label' => 'Contributo Affitto Casa', 'icon' => 'fa-solid fa-house-chimney', 'module' => 'modulo2', 'modal_target' => '#modal_affitto', 'active' => true],
    'contributo_sfratto' => ['label' => 'Contributo per Ingiunzione Sfratto', 'icon' => 'fa-solid fa-gavel', 'module' => 'modulo2', 'modal_target' => '#modal_sfratto', 'active' => true],
    'contributo_disabilita' => ['label' => 'Contributo Figli con Diversa Abilità', 'icon' => 'fa-solid fa-wheelchair', 'module' => 'modulo2', 'modal_target' => '#modal_disabilita', 'active' => true],
    'insinuazioni_passivo' => ['label' => 'Insinuazioni al Passivo Procedure', 'icon' => 'fa-solid fa-file-invoice', 'module' => null, 'modal_target' => '#modal_passivo', 'active' => false],
    'post_licenziamento' => ['label' => 'Contributo Post Licenziamento', 'icon' => 'fa-solid fa-briefcase', 'module' => 'modulo2', 'modal_target' => '#modal_licenziamento', 'active' => true],
    'centri_estivi' => ['label' => 'Bonus Centri Estivi', 'icon' => 'fa-solid fa-sun', 'module' => 'modulo1', 'modal_target' => '#modal_centriestivi', 'active' => true],
    'permesso_soggiorno' => ['label' => 'Rimborso Permesso di Soggiorno', 'icon' => 'fa-solid fa-passport', 'module' => 'modulo2', 'modal_target' => '#modal_soggiorno', 'active' => true],
    'premio_fedelta' => ['label' => 'Premio Fedeltà Una Tantum', 'icon' => 'fa-solid fa-medal', 'module' => null, 'modal_target' => '#modal_fedelta', 'active' => false],
    'attivita_sportive' => ['label' => 'Attività Sportive e Ricreative', 'icon' => 'fa-solid fa-futbol', 'module' => 'modulo2', 'modal_target' => '#modal_sportive', 'active' => true],
    'asili_nido' => ['label' => 'Contributi Asilo Nido', 'icon' => 'fa-solid fa-child-reaching', 'module' => 'modulo1', 'modal_target' => '#modal_nido', 'active' => true],
    'scuole_elementari' => ['label' => 'Contributi Studio Scuole Elementari', 'icon' => 'fa-solid fa-book-open-reader', 'module' => 'modulo1', 'modal_target' => '#modal_elementari', 'active' => true],
    'scuole_medie_inferiori' => ['label' => 'Contributi Studio Scuole Medie', 'icon' => 'fa-solid fa-user-graduate', 'module' => 'modulo1', 'modal_target' => '#modal_medie', 'active' => true],
    'superiori_iscrizione' => ['label' => 'Contributi Studio Scuole Superiori', 'icon' => 'fa-solid fa-school-flag', 'module' => 'modulo1', 'modal_target' => '#modal_superiori', 'active' => true],
    'universita_iscrizione' => ['label' => 'Contributi Studio Università', 'icon' => 'fa-solid fa-building-columns', 'module' => 'modulo1', 'modal_target' => '#modal_universita', 'active' => true],
];

// Funzione helper per generare gli attributi del badge di stato
function get_status_attributes($prestazione_key, $user_forms) {
    if (isset($user_forms[$prestazione_key])) {
        $status = $user_forms[$prestazione_key]['status'];
        $label = '';

        switch ($status) {
            case 'bozza': $label = 'IN COMPILAZIONE'; break;
            case 'ricevuta': $label = 'INVIATA'; break;
            case 'inviato_in_cassa_edile': $label = 'INOLTRATA A CASSA EDILE'; break;
            case 'letto_da_cassa_edile': $label = 'PRESA IN CARICO'; break;
            default: $label = ''; // Nessun badge per altri stati
        }

        if ($label) {
            return " data-status=\"{$status}\" data-status-label=\"{$label}\"";
        }
    }
    return '';
}

// Funzione helper per generare il contatore delle pratiche
function get_count_badge($prestazione_key, $user_forms) {
    if (isset($user_forms[$prestazione_key]['count']) && $user_forms[$prestazione_key]['count'] > 0) {
        $count = $user_forms[$prestazione_key]['count'];
        return "<span class=\"pratiche-counter\">{$count}</span>";
    }
    return '';
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prestazioni Cassa Edile</title>
    <link rel="icon" href="https://placehold.co/32x32/d0112b/ffffff?text=C">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .service-container {
            max-width: 800px;
            background-color: #fff;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        .service-header {
            color: #d0112b;
        }
        .modal-header {
            background-color: #d0112b;
            color: white;
        }
        /* Stili per la nuova visualizzazione a card */
        .service-card-link {
            text-decoration: none;
            color: inherit;
        }
        .service-card {
            border: 1px solid #e9ecef;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            height: 100%;
        }
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.08);
        }
        .service-card .card-body {
            display: flex;
            align-items: center;
        }
        .service-card .icon {
            font-size: 1.75rem;
            width: 50px;
            color: #d0112b;
        }
        .list-group-item-action:hover {
            background-color: #f1f1f1;
            color: #d0112b;
        }
        .list-group-item-action .icon {
            font-size: 1.75rem;
            width: 50px;
            color: #d0112b;
        }
        .service-card .content {
            margin-left: 1.5rem;
        }
        .service-card .content h5 {
            font-weight: 600;
        }
        /* Nuovi stili per le card delle prestazioni */
        .prestazione-card {
            display: flex;
            align-items: center;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            color: #212529;
            position: relative; /* Necessario per il posizionamento del badge */
            height: 100%;
        }
        .prestazione-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: #d0112b; /* Usa il colore del tema */
        }
        .prestazione-card.disabled {
            background-color: #f8f9fa;
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none; /* Disabilita i click */
        }
        .prestazione-icon {
            font-size: 1.5rem;
            color: #d0112b;
            margin-right: 1rem;
            width: 30px;
            text-align: center;
        }
        .prestazione-text {
            font-weight: 500;
            font-size: 0.875rem; /* Testo leggermente più piccolo per una migliore leggibilità */
        }
        .modal-body strong {
            color: #d0112b; /* Usa il colore del tema */
        }
        /* Stile per le card attive */
        .prestazione-card.attiva {
            border-color: #d0112b;
            border-width: 1.5px;
            background-color: #fffafb;
        }
        .prestazione-card.attiva::after {
            content: 'ONLINE';
            position: absolute;
            top: 5px;
            right: 8px;
            background-color: #d0112b;
            color: white;
            font-size: 0.6rem;
            font-weight: bold;
            padding: 2px 5px;
            border-radius: 4px;
        }
        .prestazione-card[data-status]::before {
            content: attr(data-status-label);
            position: absolute;
            top: 28px; /* Posizionato sotto il badge 'ONLINE' */
            right: 8px;
            font-size: 0.55rem;
            font-weight: bold;
            padding: 2px 5px;
            border-radius: 4px;
            color: white;
        }
        /* Colori specifici per stato */
        .prestazione-card[data-status="bozza"]::before {
            background-color: #ffc107; /* Giallo */
            color: #212529;
        }
        .prestazione-card[data-status="ricevuta"]::before {
            background-color: #0d6efd; /* Blu */
        }
        .prestazione-card[data-status="inviato_in_cassa_edile"]::before {
            background-color: #6f42c1; /* Viola */
        }
        .prestazione-card[data-status="letto_da_cassa_edile"]::before {
            background-color: #198754; /* Verde */
        }
        /* Stile per il contatore delle pratiche */
        .prestazione-card .pratiche-counter {
            position: absolute;
            top: 5px;
            left: 8px;
            background-color: #dc3545; /* Rosso per attenzione */
            color: white;
            font-size: 0.65rem;
            font-weight: bold;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Stile per il bollino del modulo */
        .module-dot {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 1px solid rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<!-- Barra superiore fissa -->
<div class="sticky-top bg-white shadow-sm">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center py-3">
            <div>
                <a href="servizi.php?token=<?php echo htmlspecialchars($token); ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Torna ai Servizi</a>
            </div>
            <div class="d-flex align-items-center">
                <!-- Icona Notifiche -->
                <div id="notification-bell" class="position-relative me-3" style="cursor: pointer;">
                    <i class="fas fa-bell fa-lg text-secondary"></i>
                    <?php if ($unread_notifications_count > 0): ?>
                        <span id="notification-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo $unread_notifications_count; ?></span>
                    <?php endif; ?>
                </div>
                <!-- Menu Utente -->
                <div class="dropdown">
                    <button class="btn rounded-circle" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #d0112b; color: white; width: 48px; height: 48px;">
                        <i class="fas fa-user fa-lg"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuButton">
                        <li><a class="dropdown-item" href="profilo.php?token=<?php echo htmlspecialchars($token); ?>">Profilo</a></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="container service-container">
    <div class="text-center mb-5">
        <i class="fas fa-hand-holding-dollar fa-3x service-header"></i>
        <h2 class="mt-3">Prestazioni Cassa Edile</h2>
        <p class="text-muted">Seleziona il tipo di prestazione che desideri richiedere.</p>
    </div>

    <!-- Area Alert Notifiche -->
    <div id="notification-alerts-container" class="mb-4 <?php if ($unread_notifications_count === 0) echo 'd-none'; ?>">
        <h5 class="text-danger"><i class="fas fa-bell me-2"></i>Avvisi Importanti</h5>
        <?php foreach ($unread_notifications as $notification): ?>
            <?php
                // Logica per costruire il link corretto alla pratica
                $module_file = ($notification['modulo_nome'] === 'Contributi di Studio') ? 'modulo1.php' : 'modulo2.php';
                $prestazione_key = '';
                $prestazione_label = 'N/D'; // Etichetta di default
                if (!empty($notification['prestazioni'])) {
                    $prest_array = json_decode($notification['prestazioni'], true);
                    if (is_array($prest_array)) {
                        $prestazione_key = key($prest_array);
                        if (isset($prestazioni_config[$prestazione_key]['label'])) {
                            $prestazione_label = $prestazioni_config[$prestazione_key]['label'];
                        }
                    }
                }
                $link = "servizi/moduli/{$module_file}?token=" . htmlspecialchars($token) . "&form_name=" . urlencode($notification['form_name']) . "&prestazione=" . urlencode($prestazione_key);
                
                // Messaggio in base allo stato
                $status_message = 'aggiornato';
                switch ($notification['status']) {
                    case 'bozza': $status_message = 'stato <strong>sbloccato per modifiche</strong>'; break;
                    case 'inviato_in_cassa_edile': $status_message = 'stato <strong>inoltrato a Cassa Edile</strong>'; break;
                    case 'abbandonato': $status_message = 'stato <strong>archiviato</strong>'; break;
                }
            ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                La tua pratica per il servizio 
                <a href="<?php echo $link; ?>" class="alert-link">"<?php echo htmlspecialchars($prestazione_label); ?>"</a> 
                è <?php echo $status_message; ?>.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    </div>


    <div class="alert alert-info border-start border-4 border-info">
        <p class="mb-2">La Cassa Edile effettua a favore degli operai regolarmente iscritti numerose prestazioni decise dalle Organizzazioni Territoriali dei Datori di Lavoro e dei lavoratori.</p>
        <p class="mb-0">Invitiamo gli interessati a prendere visione del Regolamento e stampare le domande da inviare alla Cassa Edile.</p>
    </div>

    <!-- Regolamento Attuale -->
    <h4 class="mt-5 service-header">REGOLAMENTO DELLE PRESTAZIONI VALIDITA’ DAL 10/2022 CON AGGIORNAMENTI 01/2024</h4>
    <div class="list-group mt-3">
        <a href="https://www.cassaedilefirenze.it/wp-content/uploads/2024/03/REGOLAMENTO-DEFINITIVO-PRESTAZIONI-3-2024-2.2.pdf" target='_blank' class="list-group-item list-group-item-action d-flex align-items-center">
            <div class="icon"><i class="fas fa-file-pdf fa-fw"></i></div>
            <span class="ms-3">Regolamento delle prestazioni</span>
        </a>
    </div>

    <!-- Modulistica -->
    <h4 class="mt-5 service-header">MODULISTICA PER RICHIEDERE LE PRINCIPALI PRESTAZIONI</h4>

    <!--inizio lista!-->
    <div class="my-5">
        <p class="text-muted mb-4">Seleziona una delle prestazioni attive per compilare la richiesta online. Le altre prestazioni saranno rese disponibili a breve.</p>
        
        <div class="row g-4">

            <?php foreach ($prestazioni_config as $key => $config): ?>
                <div class="col-6">
                    <?php
                        $card_classes = 'prestazione-card';
                        if ($config['active']) $card_classes .= ' attiva';
                        else $card_classes .= ' disabled';
                    ?>
                    <div class="<?php echo $card_classes; ?>" <?php echo get_status_attributes($key, $user_forms_by_prestazione); ?> data-bs-toggle="modal" data-bs-target="<?php echo $config['modal_target']; ?>">
                        <?php echo get_count_badge($key, $user_forms_by_prestazione); ?>
                        <div class="prestazione-icon"><i class="<?php echo $config['icon']; ?>"></i></div>
                        <div class="prestazione-text"><?php echo htmlspecialchars($config['label']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </div>    
    <!-- fine lista !-->




</div>

<!-- INIZIO DEFINIZIONE MODALI -->

<!-- Modal Matrimonio -->
<div class="modal fade" id="modal_matrimonio" tabindex="-1" aria-labelledby="modal_matrimonio_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_matrimonio_label">Premio Matrimoniale / Unioni Civili</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Contributo erogato al lavoratore iscritto in occasione della contrazione di matrimonio o unione civile. L'importo e i requisiti in termini di ore lavorate sono specificati nel regolamento, e la domanda deve essere presentata entro un termine di decadenza dall'evento.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Certificato di matrimonio</strong> o di unione civile (o trascrizione se estero).</li>

                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo2.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=premio_matrimoniale" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Premio Giovani -->
<div class="modal fade" id="modal_premiogiovani" tabindex="-1" aria-labelledby="modal_premiogiovani_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_premiogiovani_label">Premio Giovani e Inserimento nel Settore</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Premio riservato ai giovani lavoratori che iniziano la loro attività o che rientrano nel settore edile, spesso legato a requisiti anagrafici (ad esempio, età inferiore a 25/29 anni) e di ore lavorate nel periodo precedente.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Congedo militare</strong> All'occorrenza (se richiesto).</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo2.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=premio_giovani" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nascita -->
<div class="modal fade" id="modal_nascita" tabindex="-1" aria-labelledby="modal_nascita_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_nascita_label">Bonus Nascita o Adozione</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Contributo erogato al lavoratore in occasione della nascita o adozione di un figlio. La domanda deve essere presentata, a pena di decadenza, entro un termine specificato dall'evento (es. 90 o 180 giorni).</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Certificato</strong> (estratto) di nascita o provvedimento di adozione.</li>
                    <li><strong>Autocertificazione</strong> che attesti la paternità/maternità e l'eventuale carico fiscale del figlio.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo2.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=bonus_nascita" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Donazioni -->
<div class="modal fade" id="modal_donazioni" tabindex="-1" aria-labelledby="modal_donazioni_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_donazioni_label">Donazioni del Sangue</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Contributo erogato per ogni donazione di sangue effettuata dal lavoratore iscritto, come incentivo all'attività socialmente utile. La richiesta viene solitamente liquidata una volta all'anno.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Dichiarazione</strong> o <strong>Certificazione</strong> della struttura sanitaria (es. Centro Trasfusionale) che attesti la/le donazione/i avvenute.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo2.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=donazioni_sangue" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Affitto -->
<div class="modal fade" id="modal_affitto" tabindex="-1" aria-labelledby="modal_affitto_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_affitto_label">Contributo Una Tantum Affitto Casa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Contributo a sostegno del canone di locazione, spesso destinato a lavoratori con particolari requisiti (es. giovani o famiglie numerose). Richiede che il contratto sia registrato e intestato al lavoratore.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li>Copia del <strong>contratto di affitto</strong> intestato al lavoratore o ad un componente il nucleo familiare.</li>
                    <li><strong>Ricevuta ultimo pagamento</strong> riferita al massimo entro i 6 mesi antecedenti il mese in cui viene presentata la domanda.</li>
                    <li><strong>Attestazione ISEE</strong> inferiore a euro 30.000.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo2.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=contributo_affitto" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sfratto -->
<div class="modal fade" id="modal_sfratto" tabindex="-1" aria-labelledby="modal_sfratto_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_sfratto_label">Contributo per Ingiunzione Sfratto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Contributo straordinario concesso in situazioni di particolare disagio abitativo, in presenza di un'ingiunzione di sfratto esecutivo. Mira a coprire spese legali o canoni arretrati per evitare l'esecuzione.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li>Copia del <strong>contratto di affitto</strong> intestato al lavoratore o ad un componente il nucleo familiare.</li>
                    <li>Copia atto <strong>ingiunzione sfratto</strong>.</li>
                    <li><strong>Attestazione ISEE</strong> inferiore a euro 30.000.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo2.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=contributo_sfratto" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Disabilità -->
<div class="modal fade" id="modal_disabilita" tabindex="-1" aria-labelledby="modal_disabilita_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_disabilita_label">Contributo Figli Portatori di Diversa Abilità</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Contributo annuo o una tantum per il sostegno dei figli a carico portatori di handicap o diversa abilità, con una percentuale d'invalidità riconosciuta superiore ad una soglia minima (es. 50% o 60%).</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Autocertificazione</strong> che attesti che il lavoratore è il padre.</li>
                    <li><strong>Documentazione sanitaria</strong> (es. Certificazione ex L. 104/92 o verbale della Commissione Medica) che attesti la condizione e la percentuale d'invalidità.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo2.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=contributo_disabilita" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Passivo -->
<div class="modal fade" id="modal_passivo" tabindex="-1" aria-labelledby="modal_passivo_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_passivo_label">Contributo Insinuazioni al Passivo Procedure</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Contributo volto a coprire le spese sostenute dal lavoratore per l'insinuazione al passivo in caso di fallimento, liquidazione coatta o altre procedure concorsuali che coinvolgono l'impresa datrice di lavoro.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Documentazione</strong> che attesti l'avvenuta insinuazione al passivo (es. ricevuta di deposito o atto dell'Avvocato/Curatore).</li>
                    <li><strong>Documentazione</strong> che attesti l'apertura della procedura concorsuale per l'impresa.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Modal Licenziamento -->
<div class="modal fade" id="modal_licenziamento" tabindex="-1" aria-labelledby="modal_licenziamento_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_licenziamento_label">Contributo Una Tantum Post Licenziamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Contributo erogato in caso di licenziamento avvenuto per superamento del periodo di comporto per malattia (o infortunio), o per altre specifiche casistiche di cessazione del rapporto di lavoro definite nel regolamento.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Lettera di licenziamento</strong> con indicazione della causale.</li>
                    <li><strong>Documentazione medica</strong> (se il licenziamento è per superamento del comporto).</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo2.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=post_licenziamento" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Centri Estivi -->
<div class="modal fade" id="modal_centriestivi" tabindex="-1" aria-labelledby="modal_centriestivi_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_centriestivi_label">Erogazione Bonus Centri Estivi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Contributo per il rimborso parziale delle spese sostenute per l'iscrizione dei figli a carico a centri estivi, colonie o attività ricreative durante la stagione estiva.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Fattura/ricevuta fiscale</strong> (intestata al lavoratore o coniuge) comprovante la spesa sostenuta e il periodo di frequenza.</li>
                    <li><strong>Documento</strong> che attesta la composizione del nucleo familiare.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo1.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=centri_estivi" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Soggiorno -->
<div class="modal fade" id="modal_soggiorno" tabindex="-1" aria-labelledby="modal_soggiorno_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_soggiorno_label">Rimborso Permesso di Soggiorno</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Rimborso totale o parziale delle spese sostenute dal lavoratore straniero per il rilascio o il rinnovo del permesso o della carta di soggiorno o per l'ottenimento della cittadinanza italiana.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li>Copia del <strong>permesso di soggiorno</strong> rilasciato / rinnovato dal 1° luglio 2023 o la <strong>ricevuta</strong> di presentazione di richiesta o rinnovo del permesso di soggiorno.</li>
                    <li>Fotocopia del <strong>Codice Fiscale</strong> del lavoratore.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo2.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=permesso_soggiorno" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Fedeltà -->
<div class="modal fade" id="modal_fedelta" tabindex="-1" aria-labelledby="modal_fedelta_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_fedelta_label">Premio Fedeltà Una Tantum</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Premio riconosciuto ai lavoratori che raggiungono un determinato periodo di anzianità nel settore edile (es. 10, 15, 20 o 25 anni di iscrizione). L'Ente generalmente verifica il requisito d'ufficio.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Documento d'identità</strong>.</li>
                    <li>Non è solitamente richiesta documentazione aggiuntiva, in quanto l'anzianità è verificata dagli archivi della Cassa Edile.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sportive -->
<div class="modal fade" id="modal_sportive" tabindex="-1" aria-labelledby="modal_sportive_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_sportive_label">Attività Sportive e Ricreative</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Contributo per il rimborso delle spese sostenute per l'iscrizione ad attività sportive, corsi o palestre del lavoratore stesso e/o dei figli a carico, fino a un massimale stabilito.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Fattura/ricevuta fiscale</strong> che attesti l'iscrizione e la spesa sostenuta.</li>
                    <li><strong>Certificato di frequenza</strong> alla attività sportiva o ricreativa, per un tempo non inferiore a quattro mesi continuativi.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo2.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=attivita_sportive" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Asilo Nido -->
<div class="modal fade" id="modal_nido" tabindex="-1" aria-labelledby="modal_nido_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_nido_label">Contributi Asilo Nido</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Contributo erogato per il rimborso di una parte delle rette pagate per l'iscrizione dei figli a carico presso Asili Nido pubblici o privati accreditati.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Certificato</strong> di iscrizione all'Asilo Nido.</li>
                    <li><strong>Autocertificazione</strong> dello stato di famiglia.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo1.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=asili_nido" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Elementari -->
<div class="modal fade" id="modal_elementari" tabindex="-1" aria-labelledby="modal_elementari_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_elementari_label">Contributi di Studio Scuole Elementari</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Assegno di studio o contributo per l'acquisto di materiale scolastico per i figli a carico che frequentano la Scuola Primaria (Elementare), spesso basato sul requisito del merito o della frequenza.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Certificato di frequenza</strong> o <strong>pagella</strong> (per attestare l'anno scolastico).</li>
                    <li><strong>Autocertificazione</strong> dello stato di famiglia.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo1.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=scuole_elementari" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Medie -->
<div class="modal fade" id="modal_medie" tabindex="-1" aria-labelledby="modal_medie_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_medie_label">Contributi di Studio - Scuole Medie Inferiori</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Assegno di studio o contributo per l'acquisto di materiale scolastico per i figli a carico che frequentano la Scuola Secondaria di Primo Grado (Media Inferiore).</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Certificato di frequenza</strong> o <strong>pagella</strong> (per attestare l'anno scolastico).</li>
                    <li><strong>Autocertificazione</strong> dello stato di famiglia.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo1.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=scuole_medie_inferiori" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Superiori -->
<div class="modal fade" id="modal_superiori" tabindex="-1" aria-labelledby="modal_superiori_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_superiori_label">Contributi di Studio - Scuole Superiori</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Assegno di studio per i figli a carico che frequentano la Scuola Secondaria di Secondo Grado (Superiore).</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Certificato di iscrizione e frequenza</strong>.</li>
                    <li><strong>Autocertificazione</strong> dello stato di famiglia.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo1.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=superiori_iscrizione" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Università -->
<div class="modal fade" id="modal_universita" tabindex="-1" aria-labelledby="modal_universita_label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_universita_label">Contributi di Studio - Università</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Contributo per il rimborso delle tasse di iscrizione all'università per i figli a carico.</p>
                <div class="alert alert-warning mt-3" role="alert">
                    <h6 class="alert-heading mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Attenzione!</h6>
                    <p class="mb-0 small">Per tutte le richieste è sempre necessario allegare copia del <strong>documento di riconoscimento</strong> in corso di <strong>validità</strong> e del <strong>codice fiscale</strong>.</p>
                </div>
                <h6 class="mt-3"><strong>Documentazione da Produrre</strong></h6>
                <ul>
                    <li><strong>Dichiarazione universitaria</strong> attestante esami sostenuti, crediti, votazioni e conformità al piano di studi dell'anno accademico.</li>
                    <li>Per studenti fuori corso (massimo un anno): fotocopia del <strong>libretto universitario</strong>.</li>
                    <li class="small text-muted">Per scuole estere: il certificato in lingua originale sarà tradotto e verificato dalla Cassa Edile.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <a href="servizi/moduli/modulo1.php?token=<?php echo htmlspecialchars($token); ?>&prestazione=universita_iscrizione" class="btn btn-primary w-100">Compila Modulo Online</a>
            </div>
        </div>
    </div>
</div>

<!-- FINE DEFINIZIONE MODALI -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    $('#notification-bell').on('click', function() {
        const badge = $('#notification-badge');
        if (badge.length > 0) {
            // Chiamata AJAX per marcare le notifiche come lette
            $.post('mark_notifications_read.php', { token: '<?php echo htmlspecialchars($token); ?>' })
                .done(function(response) {
                    if (response.status === 'success') {
                        // Rimuovi il badge e nascondi l'area alert
                        badge.remove();
                        $('#notification-alerts-container').slideUp();
                    } else {
                        console.error('Errore nel marcare le notifiche come lette.');
                    }
                });
        }
    });
});
</script>

</body>
</html>