<?php
session_start(); // Avvia la sessione

// 1. Inizializzazione e recupero token
// Dà priorità al token nella sessione, che è più affidabile.
$token_user = $_SESSION['user_token'] ?? ($_GET['token'] ?? null);
// Se il token è nell'URL, assicurati che sia salvato anche in sessione per coerenza
if (isset($_GET['token'])) {
    $_SESSION['user_token'] = $_GET['token'];
}

$prestazione_selezionata = $_GET['prestazione'] ?? null;
$form_name = $_GET['form_name'] ?? null;
$user_id_from_admin = $_GET['user_id'] ?? null; // ID utente passato dall'admin
$user_info = []; // Per contenere i dati dell'utente loggato
$user_id = null; // ID dell'utente la cui pratica viene visualizzata/modificata
$default_funzionario_id = null;
$saved_data = [];
$id_funzionario_assegnato = null; // Variabile per l'ID del funzionario da master
$is_admin_view = false;

// Mappa delle prestazioni per il titolo della pagina
$prestazioni_map = [
    'premio_matrimoniale' => 'Premio Matrimoniale / Unioni Civili',
    'premio_giovani' => 'Premio Giovani e Inserimento',
    'bonus_nascita' => 'Bonus Nascita o Adozione',
    'donazioni_sangue' => 'Donazioni del Sangue',
    'contributo_affitto' => 'Contributo Affitto Casa',
    'contributo_sfratto' => 'Contributo per Ingiunzione Sfratto',
    'contributo_disabilita' => 'Contributo Figli con Diversa Abilità',
    'post_licenziamento' => 'Contributo Post Licenziamento',
    'permesso_soggiorno' => 'Rimborso Permesso di Soggiorno',
    'attivita_sportive' => 'Attività Sportive e Ricreative',
];
$page_title = $prestazioni_map[$prestazione_selezionata] ?? 'Richiesta Prestazioni Varie';

// Connessione al database
// Il file database.php si trova due livelli sopra la cartella 'moduli'
include_once("../../database.php");
$pdo1 = Database::getInstance('fillea');

// 3. Validazione del token e recupero dati utente
// VISTA UTENTE: L'utente è loggato tramite token.
$sql_user = "SELECT id, nome, cognome, email, id_funzionario FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW() LIMIT 1";
$stmt_user_token = $pdo1->prepare($sql_user);
$stmt_user_token->execute([$token_user]);
$user_from_token = $stmt_user_token->fetch(PDO::FETCH_ASSOC);

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && $user_id_from_admin) {
    // VISTA ADMIN: L'admin è loggato e sta cercando di vedere il modulo di un utente specifico.
    $is_admin_view = true;
    $user_id = $user_id_from_admin;
    // L'admin non ha un token utente, ma ne recuperiamo uno valido per la navigazione (es. "Torna indietro")
    $stmt_token_for_admin_view = $pdo1->prepare("SELECT token FROM `fillea-app`.users WHERE id = ?");
    $stmt_token_for_admin_view->execute([$user_id]);
    $user_with_token = $stmt_token_for_admin_view->fetch(PDO::FETCH_ASSOC);
    if ($user_with_token) {
        $token_user = $user_with_token['token']; // Aggiorna il token per i link di navigazione
    }
} elseif ($user_from_token) {
    // VISTA UTENTE: L'utente è loggato tramite token.
    $user_info = $user_from_token;
    $user_id = $user_from_token['id'];
    $default_funzionario_id = $user_from_token['id_funzionario'];
    // Assicura che il token sia sempre salvato nella sessione per le pagine successive
    $_SESSION['user_token'] = $token_user;
} else {
    // Se non c'è un token valido e non è una vista admin, reindirizza al login.
    // Pulisce un eventuale token non valido dalla sessione
    unset($_SESSION['user_token']);
    header("Location: ../../login.php?error=session_failed");
    exit;
}

// 3. Se l'utente è stato identificato, carica i dati.
if ($user_id) {
    // Se il form_name non è stato ancora generato (es. prima visita), creane uno temporaneo
    // Questo è utile per la logica di selezione del form, ma non per il salvataggio iniziale
    if ($form_name === null && !$is_admin_view) {
        // Questo form_name sarà usato solo per la visualizzazione iniziale,
        // il vero form_name verrà generato da JS se l'utente crea una nuova richiesta.
        // Per evitare errori, lo impostiamo a una stringa vuota o un valore di default.
        // In questo contesto, se form_name è null, non caricheremo dati salvati.
    }

    if ($form_name) {
        $sql_data = "SELECT * FROM `fillea-app`.`modulo2_richieste` WHERE user_id = ? AND form_name = ? LIMIT 1";
        $stmt_data = $pdo1->prepare($sql_data);
        $stmt_data->execute([$user_id, $form_name]);
        $result = $stmt_data->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $saved_data = $result;
            $saved_data['prestazioni_decoded'] = !empty($saved_data['prestazioni']) ? json_decode($saved_data['prestazioni'], true) : [];

            // Recupera gli allegati per questo form
            $stmt_files = $pdo1->prepare("SELECT * FROM `fillea-app`.`richieste_allegati` WHERE user_id = ? AND form_name = ?");
            $stmt_files->execute([$user_id, $form_name]);
            $saved_data['allegati'] = [];
            while ($file = $stmt_files->fetch(PDO::FETCH_ASSOC)) {
                $saved_data['allegati'][$file['document_type']][] = $file;
            }
        }

        // Controlla se la richiesta è già stata inviata e recupera l'ID del funzionario assegnato
        $stmt_master_check = $pdo1->prepare("SELECT id, id_funzionario FROM `fillea-app`.`richieste_master` WHERE form_name = ? AND user_id = ?");
        $stmt_master_check->execute([$form_name, $user_id]);
        if ($master_record = $stmt_master_check->fetch(PDO::FETCH_ASSOC)) {
            $id_funzionario_assegnato = $master_record['id_funzionario'];
        }
    }

    // Recupera tutti i form di tipo modulo2 compilati dall'utente
    // Modifica: filtra per la prestazione corrente
    $sql_forms = "SELECT form_name, nome_completo FROM `fillea-app`.`modulo2_richieste` WHERE user_id = ? AND status != 'abbandonato' AND prestazioni LIKE ? ORDER BY last_update DESC";
    $stmt_forms = $pdo1->prepare($sql_forms);
    $stmt_forms->execute([$user_id, '%"'.$prestazione_selezionata.'"%']);
    $user_forms = $stmt_forms->fetchAll(PDO::FETCH_ASSOC);

    // Recupera il numero di telefono del funzionario per il link WhatsApp
    $funzionario_telefono = null;
    if (!$is_admin_view) {
        $stmt_funz = $pdo1->prepare("SELECT f.telefono FROM `fillea-app`.funzionari f JOIN `fillea-app`.users u ON f.id = u.id_funzionario WHERE u.id = ?");
        $stmt_funz->execute([$user_id]);
        $funzionario_telefono = $stmt_funz->fetchColumn();
    }

    // Recupera l'elenco di tutti i funzionari per il dropdown
    $stmt_funzionari = $pdo1->prepare("SELECT id, funzionario, zona FROM `fillea-app`.funzionari WHERE is_super_admin = 0 ORDER BY funzionario ASC");
    $stmt_funzionari->execute();
    $funzionari_list = $stmt_funzionari->fetchAll(PDO::FETCH_ASSOC);
}

// 5. Funzione helper per stampare in modo sicuro i valori
function e($value) {
    echo htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modulo Richiesta Prestazioni</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 'primary': '#d0112b', 'secondary': '#f97316', 'light': '#fbe6e8' },
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body { background-color: #f8f9fa; }
        .form-section { background-color: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); margin-bottom: 2rem; }
        .form-section-title { font-size: 1.25rem; font-weight: 700; color: #1f2937; border-bottom: 2px solid #d1d5db; padding-bottom: 0.75rem; margin-bottom: 1.5rem; }
        .form-label { font-weight: 600; color: #4b5563; }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-input:focus { outline: none; border-color: #d0112b; box-shadow: 0 0 0 2px rgba(208, 17, 43, 0.2); }
        .info-box { background-color: #fef2f2; border-left: 4px solid #d0112b; padding: 1rem; border-radius: 0.375rem; }
        .checkbox-group { background-color: #f9fafb; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e5e7eb; }
        .upload-box { border: 2px dashed #d1d5db; border-radius: 0.75rem; padding: 2rem; text-align: center; cursor: pointer; transition: background-color 0.2s, border-color 0.2s; }
        .upload-box:hover, .upload-box.dragover { background-color: #fef2f2; border-color: #d0112b; }
        .progress-bar-container { width: 100%; background-color: #e5e7eb; border-radius: 0.5rem; overflow: hidden; height: 1rem; }
        .progress-bar { height: 100%; width: 0; background-color: #16a34a; border-radius: 0.5rem; transition: width 0.3s ease-in-out; text-align: center; color: white; font-size: 0.75rem; line-height: 1rem; }
        .file-list-item { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.375rem; margin-top: 0.5rem; }
    </style>
</head>
<body class="bg-gray-50">

<!-- Barra superiore fissa -->
<div class="sticky top-0 bg-white shadow-sm z-50">
    <div class="container mx-auto max-w-4xl">
        <div class="flex justify-between items-center py-3 px-4">
            <?php if ($is_admin_view): ?>
            <div>
                <a href="../../admin/admin_documenti.php" class="inline-flex items-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg text-sm transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Torna alla Gestione Documenti
                </a>
            </div>
            <?php else: ?>
            <div>
                <a href="../../servizio_cassa_edile.php?token=<?php echo htmlspecialchars($token_user); ?>" class="inline-flex items-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg text-sm transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Torna Indietro
                </a>
            </div>
            <?php endif; ?>
            <?php if (!$is_admin_view):
                    $path_prefix = '../../';
                    include('user_menu.php');
            endif; ?>
        </div>
    </div>
</div>

<div class="container mx-auto p-4 md:p-8 max-w-4xl">

    <header class="text-center mb-8">
        <h1 class="text-3xl md:text-4xl font-bold text-primary">Modulo Richiesta <?php echo htmlspecialchars($page_title); ?></h1>
        <p class="text-lg text-gray-600 mt-2">Spett.le CASSA EDILE DELLA PROV. DI FIRENZE</p> 
    </header>

    <!-- Selezione Richiesta -->
    <div class="form-section">
        <label for="existing_form" class="form-label"><?php echo $form_name === null ? 'Inizia da qui: seleziona una richiesta o creane una nuova' : 'Puoi passare a un\'altra richiesta o crearne una nuova'; ?></label>
        <select id="existing_form" name="existing_form" class="form-input mt-2" onchange="handleFormSelection(this.value)">
            <option value="" <?php if ($form_name === null) echo 'selected'; ?> disabled>-- Scegli un'opzione --</option>
            <option value="new">+ Crea una nuova richiesta</option>
            <?php foreach ($user_forms as $form):
                $option_text = !empty($form['nome_completo']) ? htmlspecialchars($form['nome_completo']) . ' (' . htmlspecialchars($form['form_name']) . ')' : htmlspecialchars($form['form_name']);
            ?>
                <option value="<?php echo htmlspecialchars($form['form_name']); ?>" <?php if ($form['form_name'] == $form_name) echo 'selected'; ?>><?php echo $option_text; ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Contenitore per il resto del form, visibile solo dopo la selezione -->
    <div id="form-content" class="<?php if ($form_name === null) echo 'hidden'; ?>">

    <?php if (isset($_GET['status']) && $_GET['status'] === 'saved'): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg shadow-md" role="alert">
        <p class="font-bold text-lg">Operazione completata!</p>
        <p>I tuoi dati sono stati salvati con successo.</p>
        <?php if (isset($_GET['action']) && $_GET['action'] === 'submit_official' && $funzionario_telefono): ?>
            <?php
                // Prepara il numero di telefono
                $whatsapp_number = preg_replace('/[^0-9]/', '', $funzionario_telefono);
                if (substr($whatsapp_number, 0, 2) !== '39') {
                    $whatsapp_number = '39' . ltrim($whatsapp_number, '0');
                }
                // Prepara il messaggio
                $whatsapp_message = urlencode("Ciao, ti ho appena inviato la pratica per le prestazioni varie. Nome pratica: $form_name");
                $whatsapp_link = "https://wa.me/{$whatsapp_number}?text={$whatsapp_message}";
            ?>
            <div class="mt-4">
                <a href="<?php echo $whatsapp_link; ?>" target="_blank" class="inline-flex items-center bg-green-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-600 transition-colors">
                    <i class="fab fa-whatsapp mr-2"></i>Contatta il Funzionario su WhatsApp
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
        $status = $saved_data['status'] ?? 'bozza';
        $is_submitted = ($status === 'inviato' || $status === 'inviato_in_cassa_edile');

        // Mostra la notifica dell'admin all'utente, se presente.
        if (!$is_admin_view && !empty($saved_data['admin_notification'])):
    ?>
    <div id="admin-notification-banner" class="bg-yellow-100 border-l-4 border-yellow-500 p-4 mb-6 rounded-md shadow-md flex justify-between items-center" role="alert">
        <div>
            <p class="font-bold text-yellow-800"><i class="fas fa-info-circle mr-2"></i>Notifica dal Funzionario</p>
            <p class="text-yellow-700"><?php echo htmlspecialchars($saved_data['admin_notification']); ?></p>
        </div>
        <button onclick="document.getElementById('admin-notification-banner').style.display='none'" class="text-yellow-800 hover:text-yellow-900 text-2xl ml-4">&times;</button>
    </div>
    <?php endif; ?>

    <form 
        id="modulo2-form" action="modulo2_save.php" method="POST" autocomplete="off">

    <?php
        // Se è un admin, mostra sempre la sezione delle azioni admin.
        if ($is_admin_view):
    ?>
        <div class="form-section">
            <h2 class="form-section-title">Azioni Amministratore</h2>
            <?php if ($is_submitted): // Se la richiesta è stata inviata (o inoltrata), l'admin può sbloccare ?>
                <p class="text-gray-600 mb-4">Questa richiesta è stata inviata dall'utente. Puoi sbloccarla per consentire ulteriori modifiche.</p>
                <div class="mb-4">
                    <label for="admin_notification" class="form-label">Aggiungi una notifica per l'utente (opzionale)</label>
                    <textarea id="admin_notification" name="admin_notification" rows="2" class="form-input" placeholder="Es: Sbloccato. Per favore, carica il documento mancante."></textarea>
                </div>
                <button type="submit" id="unlock-for-user-btn" class="w-full md:w-auto bg-yellow-500 text-black font-bold py-3 px-6 rounded-lg shadow-lg hover:bg-yellow-600 transition-colors duration-300" name="action" value="unlock">
                    <i class="fas fa-unlock mr-2"></i> Sblocca Modifiche per l'Utente
                </button>
            <?php else: // Se la richiesta è in bozza, l'admin non può fare nulla ?>
                <p class="text-gray-600 mb-4 bg-gray-100 p-3 rounded-md">
                    <i class="fas fa-info-circle me-2"></i>Questa richiesta è in stato di "Bozza". L'utente sta ancora compilando i dati.
                </p>
            <?php endif; ?> 
        </div>
    <?php elseif ($is_submitted && !$is_admin_view): // Se l'utente visualizza una richiesta inviata ?>
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded-md" role="alert">
            <p class="font-bold">In attesa di riscontro</p>
            <p>Questa richiesta è stata inviata al funzionario e non è più modificabile.</p>
        </div>
    <?php elseif (!empty($saved_data) && !$is_admin_view): // Se l'utente sta compilando (non è admin) ?>
        <!-- Il pulsante di invio è ora gestito tramite la modale, come nel modulo1 -->
    <?php endif; ?>

        <!-- Sezione Dati Lavoratore -->
        <div class="form-section">
            <h2 class="form-section-title"><i class="fas fa-user mr-2"></i>Dati del Lavoratore</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-2">
                    <label for="nome_completo" class="form-label">Il sottoscritto (Nome e Cognome)</label>
                    <input type="text" id="nome_completo" name="nome_completo" class="form-input" value="<?php e($saved_data['nome_completo'] ?? ''); ?>">
                </div>
                <div>
                    <label for="pos_cassa_edile" class="form-label">Pos. Cassa Edile</label>
                    <input type="text" id="pos_cassa_edile" name="pos_cassa_edile" class="form-input" value="<?php e($saved_data['pos_cassa_edile'] ?? ''); ?>">
                </div>
                <div>
                    <label for="data_nascita" class="form-label">Nato il</label>
                    <input type="date" id="data_nascita" name="data_nascita" class="form-input" value="<?php e($saved_data['data_nascita'] ?? ''); ?>">
                </div>
                <div class="md:col-span-2">
                    <label for="codice_fiscale" class="form-label">Codice Fiscale</label>
                    <input type="text" id="codice_fiscale" name="codice_fiscale" class="form-input uppercase" maxlength="16" value="<?php e($saved_data['codice_fiscale'] ?? ''); ?>">
                    <p id="error-codice_fiscale" class="text-red-500 text-xs mt-1 hidden"></p>
                </div>
                <div>
                    <label for="via_piazza" class="form-label">Via/Piazza</label>
                    <input type="text" id="via_piazza" name="via_piazza" class="form-input" value="<?php e($saved_data['via_piazza'] ?? ''); ?>">
                </div>
                <div>
                    <label for="domicilio_a" class="form-label">Domiciliato a</label>
                    <input type="text" id="domicilio_a" name="domicilio_a" class="form-input" value="<?php e($saved_data['domicilio_a'] ?? ''); ?>">
                </div>
                <div>
                    <label for="cap" class="form-label">CAP</label>
                    <input type="text" id="cap" name="cap" class="form-input" maxlength="5" value="<?php e($saved_data['cap'] ?? ''); ?>">
                </div>
                <div class="md:col-span-3">
                    <label for="telefono" class="form-label">Tel.</label>
                    <input type="tel" id="telefono" name="telefono" class="form-input" value="<?php e($saved_data['telefono'] ?? ''); ?>">
                </div>
                <div class="md:col-span-3">
                    <label for="impresa_occupazione" class="form-label">Attualmente occupato presso l'impresa</label>
                    <input type="text" id="impresa_occupazione" name="impresa_occupazione" class="form-input" value="<?php e($saved_data['impresa_occupazione'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Sezione Allegati -->
        <div class="form-section">
            <h2 class="form-section-title">Allegati Richiesti</h2>
            <p class="text-gray-600 mb-6">Carica qui i documenti necessari per le prestazioni che hai selezionato. I formati consentiti sono PDF, JPG, PNG. Dimensione massima 5MB.</p>
            <div id="upload-container" class="space-y-8">
                <?php
                function render_upload_box($doc_type, $title, $description, $token_user, $saved_files = []) {
                    ob_start();
                ?>
                <div id="container-for-<?php echo $doc_type; ?>" class="upload-section-container hidden">
                    <h3 class="font-semibold text-lg text-gray-800 mb-2"><?php echo $title; ?></h3>
                    <p class="text-sm text-gray-500 mb-4"><?php echo $description; ?></p>
                    <?php if ($doc_type === 'autocertificazione_famiglia'): ?>
                        <div class="mb-2">
                            <a href="modulo_autocertificazione_stato_famiglia.php?token=<?php echo htmlspecialchars($token_user); ?>&origin_form_name=<?php echo htmlspecialchars($GLOBALS['form_name']); ?>&origin_prestazione=<?php echo htmlspecialchars($GLOBALS['prestazione_selezionata']); ?>&origin_module=modulo2" class="open-autocert-modal inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700">
                                <i class="fas fa-file-signature mr-2"></i> Compila autocertificazione
                            </a>
                        </div>
                    <?php else: ?>
                     <div class="upload-box">
                        <input type="file" class="hidden file-input" multiple>
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400"></i>
                        <p class="mt-2 text-gray-600">Trascina i file qui o <span class="text-primary font-semibold">clicca per selezionare</span></p>
                    </div>
                    <div class="progress-container mt-4 hidden"><div class="progress-bar-container"><div class="progress-bar"></div></div></div>
                    <div class="file-list mt-4">
                        <?php if (!empty($saved_files)): foreach ($saved_files as $file): ?>
                            <div class="file-list-item" data-file-id="<?php echo $file['id']; ?>">
                                <span class="truncate" title="<?php e($file['original_filename']); ?>"><i class="fas fa-file-alt text-gray-500 mr-2"></i><?php e($file['original_filename']); ?></span>
                                <div class="flex items-center space-x-4 ml-2 flex-shrink-0">
                                    <a href="view_file.php?id=<?php echo $file['id']; ?>&token=<?php echo htmlspecialchars($token_user); ?>" target="_blank" class="text-blue-500 hover:text-blue-700" title="Visualizza file"><i class="fas fa-eye"></i></a>
                                    <button type="button" class="delete-file-btn text-red-500 hover:text-red-700" title="Elimina file"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php
                    return ob_get_clean();
                }
                $allegati = $saved_data['allegati'] ?? []; // Recupera gli allegati salvati
                echo render_upload_box('certificato_matrimonio', 'Certificato di Matrimonio/Unione Civile', 'Certificato ufficiale o autocertificazione.', $token_user, $allegati['certificato_matrimonio'] ?? []);
                echo render_upload_box('documento_identita', 'Documento d\'Identità', 'Copia fronte/retro del documento del richiedente.', $token_user, $allegati['documento_identita'] ?? []);
                echo render_upload_box('certificato_nascita', 'Certificato di Nascita/Adozione', 'Estratto di nascita o provvedimento di adozione.', $token_user, $allegati['certificato_nascita'] ?? []);
                echo render_upload_box('attestazione_donazione', 'Attestazione Donazione', 'Certificato della struttura sanitaria che attesta la donazione.', $token_user, $allegati['attestazione_donazione'] ?? []);
                echo render_upload_box('certificazione_disabilita', 'Certificazione Disabilità', 'Documentazione sanitaria (es. L. 104/92) che attesti la condizione.', $token_user, $allegati['certificazione_disabilita'] ?? []);
                echo render_upload_box('lettera_licenziamento', 'Lettera di Licenziamento', 'Lettera con indicazione della causale di superamento comporto.', $token_user, $allegati['lettera_licenziamento'] ?? []);
                echo render_upload_box('ricevute_soggiorno', 'Ricevute Permesso di Soggiorno', 'Bollettini di pagamento per il rilascio/rinnovo.', $token_user, $allegati['ricevute_soggiorno'] ?? []);
                echo render_upload_box('ricevuta_attivita_sportiva', 'Ricevuta Attività Sportiva', 'Fattura o ricevuta che attesti l\'iscrizione e la spesa.', $token_user, $allegati['ricevuta_attivita_sportiva'] ?? []);
                echo render_upload_box('contratto_affitto', 'Contratto di Affitto', 'Copia del contratto registrato.', $token_user, $allegati['contratto_affitto'] ?? []);
                echo render_upload_box('autocertificazione_famiglia', 'Autocertificazione Stato di Famiglia', 'Documento che attesta la composizione del nucleo familiare.', $token_user, $allegati['autocertificazione_famiglia'] ?? []);
                echo render_upload_box('documentazione_sfratto', 'Documentazione Sfratto', 'Ordinanza del giudice o altri documenti ufficiali.', $token_user, $allegati['documentazione_sfratto'] ?? []);
                ?>
            </div>
        </div>

        <!-- Sezione Dichiarazioni e Privacy -->
        <div class="form-section">
            <h2 class="form-section-title">Dichiarazioni e Consenso Privacy</h2>
            <div class="space-y-4 text-sm text-gray-700">
                <p>Il sottoscritto, preso atto dei Regolamenti vigenti, dichiarando la propria disponibilità ad eventuali controlli disposti dalla Cassa Edile, preso atto dell'Informativa Privacy dell'Ente (disponibile su www.cassaedilefirenze.it) e prestando **consenso al trattamento dei propri dati personali**, particolari e di salute per i fini istituzionali dell'Ente stesso.</p>
            </div>
            <div class="mt-6 border-t pt-6">
                <label class="flex items-start space-x-3">
                    <input type="checkbox" id="privacy_consent" name="privacy_consent" class="mt-1 h-5 w-5 text-primary rounded border-gray-300 focus:ring-primary" <?php if (!empty($saved_data['privacy_consent'])) echo 'checked'; ?>>
                    <div>
                        <span class="font-bold text-gray-800">Presa visione e consenso al trattamento dati</span>
                    </div>
                </label>
            </div>
        </div>

        <!-- Sezione Firma -->
        <div class="form-section">
            <h2 class="form-section-title">Luogo, Data e Firma</h2>
            <?php
                $status = $saved_data['status'] ?? 'bozza';
                $has_signature = !empty($saved_data['firma_data']);
                $can_sign_or_modify = ($status === 'bozza');
            ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="luogo_firma" class="form-label">Luogo</label>
                    <input type="text" id="luogo_firma" name="luogo_firma" class="form-input" value="<?php e($saved_data['luogo_firma'] ?? 'Firenze'); ?>">
                </div>
                <div>
                    <label for="data_firma" class="form-label">Data</label>
                    <input type="date" id="data_firma" name="data_firma" class="form-input" value="<?php e($saved_data['data_firma'] ?? ''); ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Firma Digitale</label>
                    <div id="signature-container" class="w-full mt-2 border border-gray-300 rounded-lg relative">
                        <img id="signature-image" src="<?php echo $has_signature ? $saved_data['firma_data'] : ''; ?>" alt="Firma salvata" class="w-full h-auto <?php if (!$has_signature) echo 'hidden'; ?>">
                        <canvas id="signature-pad" class="w-full h-48 <?php if ($has_signature || !$can_sign_or_modify) echo 'hidden'; ?>"></canvas>
                    </div>
                    <div id="signature-controls" class="flex justify-end mt-2">
                        <?php if ($can_sign_or_modify): ?>
                            <?php if ($has_signature): ?>
                                <button type="button" id="modify-signature" class="text-sm text-blue-600 hover:text-blue-800 font-semibold"><i class="fas fa-pencil-alt mr-1"></i> Modifica Firma</button>
                            <?php else: ?>
                                <button type="button" id="clear-signature" class="text-sm text-gray-600 hover:text-primary">Pulisci</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" name="form_name" value="<?php echo htmlspecialchars($form_name ?? uniqid('form2_')); ?>">
        <input type="hidden" name="prestazione" value="<?php echo htmlspecialchars($prestazione_selezionata); ?>">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token_user); ?>">
        <input type="hidden" name="firma_data" id="firma_data" value="<?php e($saved_data['firma_data'] ?? ''); ?>">

        <!-- Campo nascosto per l'ID del funzionario già assegnato -->

        <input type="hidden" id="IDfunz" value="<?php echo htmlspecialchars($id_funzionario_assegnato ?? ''); ?>">

        <?php
            $can_edit = false;
            if (!$is_admin_view && $status === 'bozza') $can_edit = true;
            if ($is_admin_view && ($status === 'inviato' || $status === 'inviato_in_cassa_edile')) $can_edit = true;

            if ($can_edit):
        ?>

        <div class="mt-8 text-center">
            <button type="submit" id="save-btn" class="w-full md:w-auto bg-primary text-white font-bold py-3 px-8 rounded-lg shadow-lg hover:bg-red-700 transition-colors duration-300" name="action" value="save">
                <i class="fas fa-save mr-2"></i> Salva Dati
            </button>
            <?php if (!$is_admin_view): // Il pulsante di invio è solo per l'utente ?>
            <button type="button" id="submit-official-btn" class="w-full md:w-auto bg-green-600 text-white font-bold py-3 px-8 rounded-lg shadow-lg hover:bg-green-700 transition-colors duration-300 mt-4 md:mt-0 md:ml-4">
                <i class="fas fa-paper-plane mr-2"></i> Invia dati al funzionario
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </form>
    </div>

</div>

<!-- Toast Notification -->
<div id="toast-notification" class="fixed top-5 right-5 bg-green-500 text-white py-3 px-5 rounded-lg shadow-lg hidden transition-opacity duration-300" style="z-index: 1002;">
    <p id="toast-message"></p>
</div>


<!-- Modale per Iframe Autocertificazione -->
<div id="autocert-modal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center z-[1001] hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl h-[90vh] mx-4 flex flex-col">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-xl font-bold text-gray-800">Compilazione Autocertificazione Stato di Famiglia</h3>
            <button id="autocert-modal-close-btn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
        </div>
        <div class="flex-grow p-0">
            <iframe id="autocert-iframe" src="about:blank" class="w-full h-full border-0"></iframe>
        </div>
    </div>
</div>


<!-- jQuery CDN -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Signature Pad Library -->
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

<!-- Finestra Modale di Conferma (come in modulo1) -->
<div id="confirmation-modal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">Conferma Invio</h3>
            <button id="modal-close-btn" class="text-gray-500 hover:text-gray-800">&times;</button>
        </div>
        <div id="modal-content-area">
            <p class="text-gray-600 mb-6">
                Stai per inviare la richiesta. Una volta inviata, non potrai più modificarla.
            </p>
            <!-- Contenitore per il selettore del funzionario, visibile solo al primo invio -->
            <div id="funzionario-selector-container" class="mb-6">
                <label for="id_funzionario_modal" class="form-label">Assegna a un Funzionario</label>
                <select id="id_funzionario_modal" name="id_funzionario" class="form-input mt-1">
                    <option value="" disabled selected>-- Seleziona un funzionario --</option>
                    <?php foreach ($funzionari_list as $funzionario): ?>
                        <option value="<?php echo $funzionario['id']; ?>" <?php if ($funzionario['id'] == $default_funzionario_id) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($funzionario['funzionario'] . ' (' . $funzionario['zona'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p id="error-id_funzionario_modal" class="text-red-500 text-xs mt-1 hidden"></p>
            </div>
        </div>
        <div class="flex justify-end space-x-4">
            <button id="modal-cancel-btn" class="py-2 px-4 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                Annulla
            </button>
            <button id="modal-confirm-btn" class="py-2 px-4 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                Sì, invia
            </button>
        </div>
    </div>
</div>

<!-- Script per la firma (caricato prima degli altri script JS) -->
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<script src="signature_pad_logic.js?v=<?php echo time(); ?>"></script>


<!-- Logica di visualizzazione dinamica (spostata qui) -->
<script>
    // Funzione per mostrare la notifica Toast, chiamata dall'iframe
    function showToast(message) {
        const toast = document.getElementById('toast-notification');
        const toastMessage = document.getElementById('toast-message');
        if (toast && toastMessage) {
            toastMessage.textContent = message;
            toast.classList.remove('hidden');
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000); // Nasconde dopo 3 secondi
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        // --- Inizializzazione Data Firma ---
        const dataFirmaInput = document.getElementById('data_firma');
        if (!dataFirmaInput.value) {
            dataFirmaInput.value = new Date().toISOString().split('T')[0];
        }

        // --- Logica per mostrare i box di upload corretti ---
        const prestazioneSelezionata = '<?php echo htmlspecialchars($prestazione_selezionata ?? ''); ?>';
        
        // Mappa delle prestazioni ai documenti richiesti
        const uploadRequirements = {
            'premio_matrimoniale': ['certificato_matrimonio', 'documento_identita'],
            'premio_giovani': ['documento_identita'], // Non richiede autocertificazione stato famiglia
            'bonus_nascita': ['certificato_nascita', 'autocertificazione_famiglia', 'documento_identita'],
            'donazioni_sangue': ['attestazione_donazione', 'documento_identita'],
            'contributo_affitto': ['contratto_affitto', 'autocertificazione_famiglia', 'documento_identita'],
            'contributo_sfratto': ['documentazione_sfratto', 'documento_identita'],
            'contributo_disabilita': ['certificazione_disabilita', 'autocertificazione_famiglia', 'documento_identita'],
            'post_licenziamento': ['lettera_licenziamento', 'documento_identita'],
            'permesso_soggiorno': ['ricevute_soggiorno', 'documento_identita'],
            'attivita_sportive': ['ricevuta_attivita_sportiva', 'documento_identita']
        };

        function showRequiredUploads(prestazione) {
            const requiredDocs = uploadRequirements[prestazione];
            // Nascondi tutti i contenitori prima di mostrare quelli necessari
            document.querySelectorAll('.upload-section-container').forEach(container => {
                container.classList.add('hidden');
            });

            if (requiredDocs) {
                requiredDocs.forEach(docType => {
                    const container = document.getElementById(`container-for-${docType}`);
                    if (container) {
                        container.classList.remove('hidden');
                    }
                });
            }
        }

        // Mostra i box corretti al caricamento della pagina
        if (prestazioneSelezionata) {
            showRequiredUploads(prestazioneSelezionata);
        }

        // Logica per modale autocertificazione
        const autocertModal = document.getElementById('autocert-modal');
        const autocertIframe = document.getElementById('autocert-iframe');
        const openModalLinks = document.querySelectorAll('.open-autocert-modal');
        const closeModalBtn = document.getElementById('autocert-modal-close-btn');

        openModalLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.href;
                autocertIframe.src = url;
                autocertModal.classList.remove('hidden');
            });
        });
        if(closeModalBtn) {
            closeModalBtn.addEventListener('click', () => autocertModal.classList.add('hidden'));
        }        
    });
</script>

<script>
    // La funzione deve essere globale per essere chiamata da onchange
function handleFormSelection(selectedValue) {
    const userId = '<?php echo htmlspecialchars($user_id); ?>';
    const prestazione = '<?php echo htmlspecialchars($prestazione_selezionata); ?>';
    let formName = '';

    if (selectedValue === 'new') {
        formName = `form2_${userId}_${Date.now()}`;
    } else {
        formName = selectedValue;
    }

    const isAdmin = <?php echo json_encode($is_admin_view); ?>;
    const userToken = '<?php echo htmlspecialchars($token_user); ?>';
    window.location.href = isAdmin ? `modulo2.php?user_id=${userId}&form_name=${formName}&prestazione=${prestazione}` : `modulo2.php?token=${userToken}&form_name=${formName}&prestazione=${prestazione}`;
}

document.addEventListener('DOMContentLoaded', function() {
        <?php
            $can_edit = false;
            if (!$is_admin_view && $status === 'bozza') $can_edit = true; 
            if ($is_admin_view && ($status === 'inviato' || $status === 'inviato_in_cassa_edile')) $can_edit = true; 

            if (!$can_edit && $form_name !== null):
        ?>
            // Disabilita tutti i campi tranne quelli necessari per la navigazione e le azioni admin
            $('#modulo2-form :input').not('#existing_form, #unlock-for-user-btn, #admin_notification, [name="action"]').prop('disabled', true);
        <?php endif; ?>
      
});
</script>
<!-- Script di validazione custom -->
<script src="modulo2.js?v=<?php echo time(); ?>"></script>
<!-- Script per l'upload -->
<script src="modulo2_upload.js?v=<?php echo time(); ?>"></script>


</body>
</html>