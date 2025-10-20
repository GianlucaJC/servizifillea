<?php
session_start();

// 1. Inizializzazione e recupero token (dà priorità alla sessione)
$token = $_SESSION['user_token'] ?? ($_GET['token'] ?? null);

// Se il token è nell'URL, assicurati che sia salvato anche in sessione per coerenza
if (isset($_GET['token'])) {
    $_SESSION['user_token'] = $_GET['token'];
}

$prestazione_selezionata = $_GET['prestazione'] ?? null;
$form_name = $_GET['form_name'] ?? null;
$user_info = []; // Per contenere i dati dell'utente
$user_id_from_admin = $_GET['user_id'] ?? null; // ID utente passato dall'admin
$user_id = null;
$default_funzionario_id = null;
$saved_data = [];
$id_funzionario_assegnato = null; // Variabile per l'ID del funzionario da master
$is_admin_view = false;

// Mappa delle prestazioni per il titolo della pagina
$prestazioni_map = [
    'asili_nido' => 'Contributi Asili Nido',
    'centri_estivi' => 'Bonus Centri Estivi',
    'scuole_obbligo' => 'Contributi Scuole Obbligo',
    'superiori_iscrizione' => 'Contributo Iscrizione Scuole Superiori',
    'universita_iscrizione' => 'Contributo Iscrizione Università',
];
$page_title = $prestazioni_map[$prestazione_selezionata] ?? 'Richiesta Contributi di Studio';

// Il file database.php si trova due livelli sopra
include_once("../../database.php");
$pdo1 = Database::getInstance('fillea');

// 2. Logica di autorizzazione: determina se è un utente o un admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && $user_id_from_admin) {
    // VISTA ADMIN: L'admin è loggato e sta cercando di vedere il modulo di un utente specifico.
    $is_admin_view = true;
    $user_id = $user_id_from_admin;
    // L'admin non ha un token utente, ma ne recuperiamo uno valido per la navigazione (es. "Torna indietro")
    $stmt_token = $pdo1->prepare("SELECT token FROM `fillea-app`.users WHERE id = ?");
    $stmt_token->execute([$user_id]);
    $user_with_token = $stmt_token->fetch(PDO::FETCH_ASSOC);
    if ($user_with_token) {
        $token = $user_with_token['token'];
    }

} elseif ($token) {
    // VISTA UTENTE: L'utente è loggato tramite token.
    $sql_user = "SELECT id, nome, cognome, email, id_funzionario FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW() LIMIT 1";
    $stmt_user = $pdo1->prepare($sql_user);
    $stmt_user->execute([$token]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $user_info = $user;
        $user_id = $user['id'];
        $default_funzionario_id = $user['id_funzionario'];
    }
}

// 3. Se l'utente è stato identificato (sia come utente che come admin che visualizza), carica i dati.
if ($user_id) {
    // Cerca i dati del modulo salvato, se un form_name è specificato
    if ($form_name) {
        $sql_data = "SELECT * FROM `fillea-app`.`modulo1_richieste` WHERE user_id = ? AND form_name = ? LIMIT 1";
        $stmt_data = $pdo1->prepare($sql_data);
        $stmt_data->execute([$user_id, $form_name]);
        $result = $stmt_data->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $saved_data = $result;
            // Decodifica il campo JSON delle prestazioni per un facile accesso
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

    // Recupera tutti i form compilati dall'utente per popolare il menu a tendina
    // Modifica: filtra per la prestazione corrente
    $sql_forms = "SELECT form_name, studente_nome_cognome FROM `fillea-app`.`modulo1_richieste` WHERE user_id = ? AND status != 'abbandonato' AND prestazioni LIKE ? ORDER BY last_update DESC";
    $stmt_forms = $pdo1->prepare($sql_forms);
    $stmt_forms->execute([$user_id, '%"'.$prestazione_selezionata.'"%']);
    $user_forms = $stmt_forms->fetchAll(PDO::FETCH_ASSOC);

    // Recupera il numero di telefono del funzionario per il link WhatsApp
    $funzionario_telefono = null;
    if (!$is_admin_view) {
        $stmt_funz = $pdo1->prepare("
            SELECT f.telefono 
            FROM `fillea-app`.funzionari f
            JOIN `fillea-app`.users u ON f.id = u.id_funzionario
            WHERE u.id = ?
        ");
        $stmt_funz->execute([$user_id]);
        $funzionario_telefono = $stmt_funz->fetchColumn();
    }

    // Recupera l'elenco di tutti i funzionari per il dropdown
    $stmt_funzionari = $pdo1->prepare("SELECT id, funzionario, zona FROM `fillea-app`.funzionari WHERE is_super_admin = 0 ORDER BY funzionario ASC");
    $stmt_funzionari->execute();
    $funzionari_list = $stmt_funzionari->fetchAll(PDO::FETCH_ASSOC);

}

// 4. Se non è stato possibile identificare un utente valido, reindirizza al login.
if (!$user_id) {
    header("Location: ../../login.php");
    exit;
}

// 5. Funzione helper per stampare in modo sicuro i valori nei campi del form
function e($value) {
    echo htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modulo Richiesta Contributi di Studio</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#d0112b',
                        'secondary': '#f97316',
                        'light': '#fbe6e8',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body {
            background-color: #f8f9fa;
        }
        .form-section {
            background-color: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            margin-bottom: 2rem;
        }
        .form-section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            border-bottom: 2px solid #d1d5db;
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 600;
            color: #4b5563;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: #d0112b;
            box-shadow: 0 0 0 2px rgba(208, 17, 43, 0.2);
        }
        .info-box {
            background-color: #fef2f2;
            border-left: 4px solid #d0112b;
            padding: 1rem;
            border-radius: 0.375rem;
        }
        .checkbox-group {
            background-color: #f9fafb;
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
        }
        /* Stili per l'upload */
        .upload-box {
            border: 2px dashed #d1d5db;
            border-radius: 0.75rem;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s;
        }
        .upload-box:hover, .upload-box.dragover {
            background-color: #fef2f2;
            border-color: #d0112b;
        }
        .progress-bar-container {
            width: 100%;
            background-color: #e5e7eb;
            border-radius: 0.5rem;
            overflow: hidden;
            height: 1rem;
        }
        .progress-bar {
            height: 100%;
            width: 0;
            background-color: #16a34a; /* green-600 */
            border-radius: 0.5rem;
            transition: width 0.3s ease-in-out;
            text-align: center;
            color: white;
            font-size: 0.75rem;
            line-height: 1rem;
        }
        .file-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            margin-top: 0.5rem;
        }
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
                <a href="../../servizio_cassa_edile.php?token=<?php echo htmlspecialchars($token); ?>" class="inline-flex items-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg text-sm transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Torna Indietro
                </a>
            </div>
            <?php endif; ?>
            <?php if (!$is_admin_view): // Mostra il menu utente solo se non è un admin ?>
                <?php
                    // Definisce il prefisso del percorso per raggiungere la root del sito
                    // Dato che user_menu.php si trova nella stessa cartella, non serve un prefisso per l'include.
                    // Il prefisso $path_prefix serve solo per i link *all'interno* di user_menu.php.
                    $path_prefix = '../../';
                    include('user_menu.php');
                ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container mx-auto p-4 md:p-8 max-w-4xl">

    <header class="text-center mb-8">
        <h1 class="text-3xl md:text-4xl font-bold text-primary">Modulo Richiesta <?php echo htmlspecialchars($page_title); ?></h1>
        <p class="text-lg text-gray-600 mt-2">Compila i campi richiesti e carica gli allegati necessari.</p>
    </header>

    <?php
        // Mostra la notifica dell'admin all'utente, se presente. Spostato in cima per maggiore visibilità.
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


    <!-- Selezione Richiesta -->
    <div class="form-section">
        <label for="existing_form" class="form-label"><?php echo $form_name === null ? 'Inizia da qui: seleziona una richiesta o creane una nuova' : 'Puoi passare a un\'altra richiesta o crearne una nuova'; ?></label>
        <select id="existing_form" name="existing_form" class="form-input mt-2" onchange="handleFormSelection(this.value, '<?php echo $prestazione_selezionata; ?>')">
            <option value="" <?php if ($form_name === null) echo 'selected'; ?> disabled>-- Scegli una opzione --</option>
            <option value="new">+ Crea una nuova richiesta</option>
            <?php foreach ($user_forms as $form): ?>
                <?php
                    // Crea un'etichetta più descrittiva per l'opzione
                    $option_text = !empty($form['studente_nome_cognome'])
                        ? htmlspecialchars($form['studente_nome_cognome']) . ' (' . htmlspecialchars($form['form_name']) . ')'
                        : htmlspecialchars($form['form_name']);
                ?>
                <option value="<?php echo htmlspecialchars($form['form_name']); ?>" <?php if ($form['form_name'] == $form_name) echo 'selected'; ?>><?php echo $option_text; ?></option>
            <?php endforeach; ?>
        </select>
        <p class="text-sm text-gray-600 mt-4">
            Puoi compilare il modulo anche in più riprese.
            <br>
            Quando ritieni di aver compilato tutti i campi, puoi inviare il modulo al funzionario di riferimento che provvederà ad inoltrare la pratica alla Cassa Edile (se il form è stato compilato correttamente).
        </p>
    </div>

    <!-- Contenitore per il resto del form, visibile solo dopo la selezione -->
    <div id="form-content" class="<?php if ($form_name === null) echo 'hidden'; ?>">
    <?php if (isset($_GET['status']) && $_GET['status'] === 'saved'): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg shadow-md" role="alert">
        <p class="font-bold text-lg">Salvataggio completato!</p>
        <p>I tuoi dati sono stati salvati con successo.</p>
        <?php if (isset($_GET['action']) && $_GET['action'] === 'submit_official' && $funzionario_telefono): ?>
            <?php
                // Prepara il numero di telefono (rimuovendo spazi e aggiungendo il prefisso internazionale se non presente)
                $whatsapp_number = preg_replace('/[^0-9]/', '', $funzionario_telefono);
                if (substr($whatsapp_number, 0, 2) !== '39') {
                    $whatsapp_number = '39' . $whatsapp_number;
                }
                // Prepara il messaggio
                $whatsapp_message = urlencode("Ciao, ti ho appena inviato la pratica per i contributi di studio. Nome pratica: $form_name");
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


    <div class="info-box mb-8">
        <p class="font-semibold text-gray-800">Istruzioni per la compilazione:</p>
        <ul class="list-disc list-inside text-gray-700 mt-2 text-sm">
            <li>Per i soli **Contributi di Studio**, il modulo deve essere compilato e firmato dai figli dei lavoratori se maggiorenni.</li>
            <li>Nel caso di figli minorenni, deve essere compilato e firmato dal lavoratore.</li>
            <li>Per il **Bonus Centri Estivi**, il modulo deve essere compilato e firmato dal lavoratore.</li>
        </ul>
    </div>

    <!-- Aggiunto ID al form per il targeting con jQuery -->
    <form 
        id="modulo1-form" action="modulo1_save.php?token=<?php echo htmlspecialchars($token); ?>" method="POST" autocomplete="off">

    <?php
        $status = $saved_data['status'] ?? 'bozza';
        $is_submitted = ($status === 'inviato' || $status === 'inviato_in_cassa_edile');

        // Se è un admin, mostra sempre la sezione delle azioni admin.
        if ($is_admin_view):
    ?>
        <div class="form-section">
            <h2 class="form-section-title">Azioni Amministratore</h2>
            <?php if ($is_submitted): // Se la richiesta è stata inviata (o inoltrata), l'admin può modificare e sbloccare ?>
                <p class="text-gray-600 mb-4">Questa richiesta è stata inviata dall'utente. Puoi sbloccarla per consentire ulteriori modifiche.</p>
                <div class="mb-4">
                    <label for="admin_notification" class="form-label">Aggiungi una notifica per l'utente (opzionale)</label>
                    <textarea id="admin_notification" name="admin_notification" rows="2" class="form-input" placeholder="Es: Sbloccato. Per favore, correggi il tuo IBAN."></textarea>
                </div>
                <button type="submit" id="unlock-for-user-btn" class="w-full md:w-auto bg-yellow-500 text-black font-bold py-3 px-6 rounded-lg shadow-lg hover:bg-yellow-600 transition-colors duration-300" name="action" value="unlock">
                    <i class="fas fa-unlock mr-2"></i> Sblocca Modifiche per l'Utente
                </button>

            <?php else: // Se la richiesta è in bozza, l'admin non può fare nulla ?>
                <p class="text-gray-600 mb-4 bg-gray-100 p-3 rounded-md">
                    <i class="fas fa-info-circle me-2"></i>Questa richiesta è in stato di "Bozza". L'utente sta ancora compilando i dati. Non è possibile effettuare modifiche finché non verrà inviata.
                </p>
            <?php endif; ?> 
        </div>
    <?php elseif ($is_submitted && !$is_admin_view): // Se l'utente visualizza una richiesta inviata ?>
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded-md" role="alert">
            <p class="font-bold">In attesa di riscontro</p>
            <p>Questa richiesta è stata inviata al funzionario e non è più modificabile.</p>
        </div>
    <?php elseif ($status === 'abbandonato'): // Se la richiesta è stata abbandonata/archiviata ?>
        <div class="bg-gray-200 border-l-4 border-gray-500 text-gray-700 p-4 mb-6 rounded-md" role="alert">
            <p class="font-bold">Richiesta Archiviata</p>
            <p>Questa richiesta è stata archiviata e non è più accessibile o modificabile.</p>
        </div>
    <?php endif; ?>


        <!-- Sezione Dati Studente -->
        <div class="form-section">
            <h2 class="form-section-title">Dati dello Studente Richiedente</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="studente_nome_cognome" class="form-label">Nome e Cognome Studente</label>
                    <input type="text" id="studente_nome_cognome" name="studente_nome_cognome" class="form-input" value="<?php e($saved_data['studente_nome_cognome'] ?? ''); ?>">
                </div>
                <div>
                    <label class="form-label">Data di Nascita</label>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <select id="studente_data_nascita_giorno" class="form-input text-sm" aria-label="Giorno di nascita"><option value="">Giorno</option></select>
                        </div>
                        <div>
                            <select id="studente_data_nascita_mese" class="form-input text-sm" aria-label="Mese di nascita"><option value="">Mese</option></select>
                        </div>
                        <div>
                            <select id="studente_data_nascita_anno" class="form-input text-sm" aria-label="Anno di nascita"><option value="">Anno</option></select>
                        </div>
                    </div>
                    <input type="hidden" id="studente_data_nascita" name="studente_data_nascita" value="<?php e($saved_data['studente_data_nascita'] ?? ''); ?>">
                </div>
                <div>
                    <label for="studente_luogo_nascita" class="form-label">Luogo di Nascita</label>
                    <input type="text" id="studente_luogo_nascita" name="studente_luogo_nascita" class="form-input" value="<?php e($saved_data['studente_luogo_nascita'] ?? ''); ?>">
                </div>
                <div>
                    <label for="studente_codice_fiscale" class="form-label">Codice Fiscale</label>
                    <input type="text" id="studente_codice_fiscale" name="studente_codice_fiscale" class="form-input uppercase" maxlength="16" value="<?php e($saved_data['studente_codice_fiscale'] ?? ''); ?>">
                    <p class="text-red-500 text-xs mt-1 hidden" id="error-studente_codice_fiscale"></p>
                </div>
                <div class="md:col-span-2">
                    <label for="studente_indirizzo" class="form-label">Indirizzo di Domicilio (Via/Piazza)</label>
                    <input type="text" id="studente_indirizzo" name="studente_indirizzo" class="form-input" value="<?php e($saved_data['studente_indirizzo'] ?? ''); ?>">
                </div>
                <div>
                    <label for="studente_cap" class="form-label">CAP</label>
                    <input type="text" id="studente_cap" name="studente_cap" class="form-input" maxlength="5" value="<?php e($saved_data['studente_cap'] ?? ''); ?>">
                </div>
                <div>
                    <label for="studente_comune" class="form-label">Comune di Domicilio</label>
                    <input type="text" id="studente_comune" name="studente_comune" class="form-input" value="<?php e($saved_data['studente_comune'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Sezione Dati Lavoratore -->
        <div class="form-section">
            <h2 class="form-section-title">Dati del Lavoratore Iscritto</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="lavoratore_nome_cognome" class="form-label">Nome e Cognome Lavoratore</label>
                    <input type="text" id="lavoratore_nome_cognome" name="lavoratore_nome_cognome" class="form-input" value="<?php e($saved_data['lavoratore_nome_cognome'] ?? ''); ?>">
                </div>
                <div>
                    <label class="form-label">Data di Nascita</label>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <select id="lavoratore_data_nascita_giorno" class="form-input text-sm" aria-label="Giorno di nascita lavoratore"><option value="">Giorno</option></select>
                        </div>
                        <div>
                            <select id="lavoratore_data_nascita_mese" class="form-input text-sm" aria-label="Mese di nascita lavoratore"><option value="">Mese</option></select>
                        </div>
                        <div>
                            <select id="lavoratore_data_nascita_anno" class="form-input text-sm" aria-label="Anno di nascita lavoratore"><option value="">Anno</option></select>
                        </div>
                    </div>
                    <input type="hidden" id="lavoratore_data_nascita" name="lavoratore_data_nascita" value="<?php e($saved_data['lavoratore_data_nascita'] ?? ''); ?>">
                </div>
                <div>
                    <label for="lavoratore_codice_cassa" class="form-label">Codice Cassa Edile</label>
                    <input type="text" id="lavoratore_codice_cassa" name="lavoratore_codice_cassa" class="form-input" value="<?php e($saved_data['lavoratore_codice_cassa'] ?? ''); ?>">
                </div>
                <div>
                    <label for="lavoratore_telefono" class="form-label">Telefono</label>
                    <input type="tel" id="lavoratore_telefono" name="lavoratore_telefono" class="form-input" value="<?php e($saved_data['lavoratore_telefono'] ?? ''); ?>">
                </div>
                <div class="md:col-span-2">
                    <label for="lavoratore_impresa" class="form-label">Occupato presso l'impresa</label>
                    <input type="text" id="lavoratore_impresa" name="lavoratore_impresa" class="form-input" value="<?php e($saved_data['lavoratore_impresa'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Sezione Allegati -->
        <div class="form-section">
            <h2 class="form-section-title">Allegati Richiesti</h2>
            <p class="text-gray-600 mb-6">Carica qui i documenti necessari per le prestazioni che hai selezionato. I formati consentiti sono PDF, JPG, PNG. Dimensione massima 5MB.</p>

            <div id="upload-container" class="space-y-8">
                <!-- I box di upload verranno inseriti qui da JavaScript -->
                <?php
                function render_upload_box($doc_type, $title, $description, $token, $saved_files = []) {
                    ob_start();
                ?>
                

                    
           
                <div id="container-for-<?php echo $doc_type; ?>" class="upload-section-container hidden" data-doc-type="<?php echo $doc_type; ?>">

                    <h3 class="font-semibold text-lg text-gray-800 mb-2"><?php echo $title; ?></h3>
                    <p class="text-sm text-gray-500 mb-4"><?php echo $description; ?></p>
                    
                    <?php if ($doc_type === 'autocertificazione_famiglia' || $doc_type === 'dichiarazione_frequenza'): // Casi speciali per moduli da compilare ?>
                        <?php
                            $modal_title = ($doc_type === 'autocertificazione_famiglia') ? 'Autocertificazione Stato di Famiglia' : 'Dichiarazione Sostitutiva di Iscrizione e Frequenza';
                            $modal_icon = ($doc_type === 'autocertificazione_famiglia') ? 'fa-file-signature' : 'fa-user-check';
                            $modal_link = ($doc_type === 'autocertificazione_famiglia') ? 'modulo_autocertificazione_stato_famiglia.php' : 'modulo_dichiarazione_frequenza.php';
                        ?>
                        <div class="mb-2"> 
                            <a href="<?php echo $modal_link; ?>?token=<?php echo htmlspecialchars($token); ?>&origin_form_name=<?php echo htmlspecialchars($GLOBALS['form_name']); ?>&origin_prestazione=<?php echo htmlspecialchars($GLOBALS['prestazione_selezionata']); ?>&origin_module=modulo1" 
                               class="open-compilation-modal inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700"
                               data-modal-title="<?php echo htmlspecialchars($modal_title); ?>">
                                <i class="fas <?php echo $modal_icon; ?> mr-2"></i> Compila il modulo
                            </a>
                            <?php if (!empty($saved_files)): ?>
                                <span class="ml-3 text-green-500" title="Modulo compilato e salvato.">
                                    <i class="fas fa-check-circle fa-lg"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php else: // Box di upload standard per tutti gli altri documenti ?>
                    <div class="upload-box">
                        <input type="file" class="hidden file-input" multiple>
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400"></i>
                        <p class="mt-2 text-gray-600">Trascina i file qui o <span class="text-primary font-semibold">clicca per selezionare</span></p>
                    </div>

                    <div class="progress-container mt-4 hidden">
                        <div class="progress-bar-container">
                            <div class="progress-bar"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($doc_type !== 'autocertificazione_famiglia' && $doc_type !== 'dichiarazione_frequenza'): ?>
                    <div class="file-list mt-4">
                        <?php if (!empty($saved_files)): ?>
                            <?php foreach ($saved_files as $file): ?>
                                <div class="file-list-item" data-file-id="<?php echo $file['id']; ?>">
                                    <span class="truncate" title="<?php e($file['original_filename']); ?>"><i class="fas fa-file-alt text-gray-500 mr-2"></i><?php e($file['original_filename']); ?></span>
                                    <div class="flex items-center space-x-4 ml-2 flex-shrink-0">
                                        <a href="view_file.php?id=<?php echo $file['id']; ?>&token=<?php e($token); ?>" target="_blank" class="text-blue-500 hover:text-blue-700" title="Visualizza file">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="delete-file-btn text-red-500 hover:text-red-700" title="Elimina file"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php
                    return ob_get_clean();
                }

                $allegati = $saved_data['allegati'] ?? []; // Recupera gli allegati salvati
                echo render_upload_box('autocertificazione_famiglia', 'Autocertificazione Stato di Famiglia', 'Documento che attesta la composizione del nucleo familiare.', $token, $allegati['autocertificazione_famiglia'] ?? []);
                echo render_upload_box('certificato_iscrizione_nido', 'Certificato Iscrizione Asilo Nido', 'Certificato rilasciato dalla struttura.', $token, $allegati['certificato_iscrizione_nido'] ?? []);
                echo render_upload_box('dichiarazione_frequenza', 'Dichiarazione Sostitutiva di Iscrizione e Frequenza', 'Compila la dichiarazione di frequenza per l\'anno scolastico corrente.', $token, $allegati['dichiarazione_frequenza'] ?? []);
                echo render_upload_box('attestazione_spesa_centri_estivi', 'Attestazione Spesa Centri Estivi', 'Fattura o ricevuta che comprovi la spesa sostenuta.', $token, $allegati['attestazione_spesa_centri_estivi'] ?? []);
                echo render_upload_box('documentazione_universita', 'Documentazione Universitaria', 'Include certificato di iscrizione, piano di studi, superamento esami, ecc.', $token, $allegati['documentazione_universita'] ?? []);
                echo render_upload_box('documento_identita', 'Documento d\'Identità', 'Copia fronte/retro del documento del richiedente in corso di validità.', $token, $allegati['documento_identita'] ?? []);
                ?>
            </div>
        </div>


        <!-- Sezione Dati Pagamento -->
        <div class="form-section">
            <h2 class="form-section-title">Dati per il Pagamento</h2>
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label for="iban" class="form-label">Codice IBAN</label>
                    <input type="text" id="iban" name="iban" class="form-input uppercase" placeholder="IT00X0000000000000000000000" maxlength="27" value="<?php e($saved_data['iban'] ?? ''); ?>">
                    <p class="text-red-500 text-xs mt-1 hidden" id="error-iban"></p>
                </div>
                <div>
                    <label for="intestatario_conto" class="form-label">Intestatario del Conto Corrente</label>
                    <input type="text" id="intestatario_conto" name="intestatario_conto" class="form-input" value="<?php e($saved_data['intestatario_conto'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Sezione Dichiarazioni e Privacy -->
        <div class="form-section">
            <h2 class="form-section-title">Dichiarazioni e Consenso Privacy</h2>
            <div class="space-y-4 text-sm text-gray-700">
                <p>Il sottoscritto dichiara:</p>
                <ul class="list-decimal list-inside space-y-2">
                    <li>Di non aver percepito da altre Casse Edili quanto richiesto con la presente domanda.</li>
                    <li>Di aver diritto alla detrazione per redditi di lavoro dipendente (art. 13 TUIR).</li>
                    <li>Di non percepire redditi o di percepire i seguenti redditi (specificare natura e importo):
                        <input type="text" name="altri_redditi" class="form-input mt-1 text-sm" value="<?php e($saved_data['altri_redditi'] ?? ''); ?>">
                    </li>
                    <li>Di autorizzare il trattamento dei propri dati personali ai sensi del D.lgs 196/03 e del GDPR 2016/679.</li>
                </ul>
            </div>
            <div class="mt-6 border-t pt-6">
                <label class="flex items-start space-x-3">
                    <input type="checkbox" id="privacy_consent" name="privacy_consent" class="mt-1 h-5 w-5 text-primary rounded border-gray-300 focus:ring-primary" <?php if (!empty($saved_data['privacy_consent'])) echo 'checked'; ?>>
                    <div>
                        <span class="font-bold text-gray-800">Presa visione e consenso al trattamento dati</span>
                        <p class="text-sm text-gray-600 mt-1">
                            Dichiaro di aver preso visione e lettura dell'Informativa Privacy disponibile su <a href="http://www.cassaedilefirenze.it/privacy" target="_blank" class="text-primary hover:underline">www.cassaedilefirenze.it/privacy</a> e presto il consenso al trattamento dei dati personali, anche per il soggetto minorenne, per le finalità menzionate.
                        </p>
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
                // L'utente può firmare/modificare solo se la pratica è in stato di bozza.
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
                        <!-- L'immagine della firma viene sempre mostrata se esiste, ma potrebbe essere nascosta da JS -->
                        <img id="signature-image" src="<?php echo $has_signature ? $saved_data['firma_data'] : ''; ?>" alt="Firma salvata" class="w-full h-auto <?php if (!$has_signature) echo 'hidden'; ?>">
                        <!-- Il canvas è visibile solo se non c'è una firma e la pratica è in bozza -->
                        <canvas id="signature-pad" class="w-full h-48 <?php if ($has_signature || !$can_sign_or_modify) echo 'hidden'; ?>"></canvas>
                    </div>
                    <div id="signature-controls" class="flex justify-end mt-2">
                        <?php if ($can_sign_or_modify): ?>
                            <?php if ($has_signature): ?>
                                <button type="button" id="modify-signature" class="text-sm text-blue-600 hover:text-blue-800 font-semibold"><i class="fas fa-pencil-alt mr-1"></i> Modifica Firma</button>
                            <?php else: ?>
                                <div class="space-x-4">
                                    <button type="button" id="undo-signature" class="text-sm text-gray-600 hover:text-primary">Annulla tratto</button>
                                    <button type="button" id="clear-signature" class="text-sm text-gray-600 hover:text-primary">Pulisci</button>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($has_signature): ?>
                             <p class="text-xs text-gray-500 mt-1">Firma salvata. La pratica è stata inviata e non può essere modificata.</p>
                        <?php endif; ?>
                    </div>
                    <p id="signature-help-text" class="text-xs text-gray-500 mt-1 <?php if ($has_signature) echo 'hidden'; ?>">Disegna la tua firma nello spazio soprastante.</p>
                </div>
            </div>
        </div>

        <input type="hidden" name="form_name" value="<?php echo htmlspecialchars($form_name ?? uniqid()); ?>">
        <input type="hidden" name="prestazione" value="<?php echo htmlspecialchars($prestazione_selezionata); ?>">
        <input type="hidden" name="firma_data" id="firma_data" value="<?php e($saved_data['firma_data'] ?? ''); ?>">
        
        <!-- Campo nascosto per l'ID del funzionario già assegnato -->
        <input type="hidden" id="IDfunz" value="<?php echo htmlspecialchars($id_funzionario_assegnato ?? ''); ?>">

        <?php 
            // Logica per mostrare il pulsante "Salva Dati":
            // Per l'utente: il pulsante appare se la richiesta è in stato 'bozza' (sia nuova che sbloccata dall'admin).
            // Per l'admin: il pulsante appare se la richiesta è stata 'inviata' dall'utente, per permettere modifiche.
            $can_edit = false;
            if (!$is_admin_view && $status === 'bozza') $can_edit = true;
            if ($is_admin_view && ($status === 'inviato' || $status === 'inviato_in_cassa_edile')) $can_edit = true;

            if ($can_edit):            
        ?>
            <div class="mt-8 text-center">
                <button type="submit" id="save-btn" class="w-full md:w-auto bg-primary text-white font-bold py-3 px-8 rounded-lg shadow-lg hover:bg-red-700 transition-colors duration-300" name="action" value="save">
                    <i class="fas fa-save mr-2"></i> Salva Dati
                </button>
                <?php if (!$is_admin_view && $status === 'bozza'): // Il pulsante di invio è solo per l'utente in stato di bozza ?>
                <button type="button" id="submit-official-btn" class="w-full md:w-auto bg-green-600 text-white font-bold py-3 px-8 rounded-lg shadow-lg hover:bg-green-700 transition-colors duration-300 mt-4 md:mt-0 md:ml-4">
                    <i class="fas fa-paper-plane mr-2"></i> Invia dati al funzionario
                </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </form>
    </div>

</div>

<!-- Finestra Modale di Conferma -->
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

<!-- Toast Notification -->
<div id="toast-notification" class="fixed top-5 right-5 bg-green-500 text-white py-3 px-5 rounded-lg shadow-lg hidden transition-opacity duration-300" style="z-index: 1002;">
    <p id="toast-message"></p>
</div>


<!-- Modale per Iframe Autocertificazione -->
<div id="autocert-modal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center z-[1001] hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl h-[90vh] mx-4 flex flex-col">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 id="autocert-modal-title" class="text-xl font-bold text-gray-800">Compilazione Modulo</h3>
            <button id="autocert-modal-close-btn" class="text-gray-500 hover:text-gray-800 text-3xl leading-none">&times;</button>
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
<!-- Script di validazione custom -->
<script src="modulo1.js?v=<?php echo time(); ?>"></script>
<!-- Script per l'upload -->
<script src="modulo1_upload.js?v=<?php echo time(); ?>"></script>
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
    function handleFormSelection(selectedValue, prestazione) {
        const token = '<?php echo htmlspecialchars($token); ?>';
        const userId = '<?php echo $user_id; ?>'; // Recupera l'user_id per la generazione del nome
        let formName = '';

        if (selectedValue === 'new') {
            // Crea un ID univoco per il nuovo form, specifico per l'utente
        formName = `form1_${userId}_${Date.now()}`; 
        } else {
            formName = selectedValue;
        }

        // Se è un admin, mantiene i parametri da admin, altrimenti usa il token
        const isAdmin = <?php echo json_encode($is_admin_view); ?>;
        window.location.href = isAdmin ? `modulo1.php?user_id=${userId}&form_name=${formName}&prestazione=${prestazione}` : `modulo1.php?token=${token}&form_name=${formName}&prestazione=${prestazione}`;
    }

    // Imposta la data odierna nel campo data firma
    document.addEventListener('DOMContentLoaded', function() {
        const dataFirmaInput = document.getElementById('data_firma');
        if (!dataFirmaInput.value) {
            const today = new Date().toISOString().split('T')[0];
            dataFirmaInput.value = today;
        }

        // --- Logica per i selettori della data di nascita ---
        function setupDateInputs(prefix, savedDate) {
            const daySelect = document.getElementById(prefix + '_giorno');
            const monthSelect = document.getElementById(prefix + '_mese');
            const yearSelect = document.getElementById(prefix + '_anno');
            const hiddenInput = document.getElementById(prefix);

            // Popola anni (dal 1924 ad oggi)
            const currentYear = new Date().getFullYear();
            for (let i = currentYear; i >= 1924; i--) {
                yearSelect.add(new Option(i, i));
            }

            // Popola mesi
            const months = ["Gennaio", "Febbraio", "Marzo", "Aprile", "Maggio", "Giugno", "Luglio", "Agosto", "Settembre", "Ottobre", "Novembre", "Dicembre"];
            months.forEach((month, index) => {
                monthSelect.add(new Option(month, index + 1));
            });

            // Popola giorni
            for (let i = 1; i <= 31; i++) {
                daySelect.add(new Option(i, i));
            }

            // Funzione per aggiornare il campo nascosto
            function updateHiddenDate() {
                const year = yearSelect.value;
                const month = monthSelect.value;
                const day = daySelect.value;

                if (year && month && day) {
                    // Formatta mese e giorno con zero iniziale se necessario
                    const formattedMonth = month.toString().padStart(2, '0');
                    const formattedDay = day.toString().padStart(2, '0');
                    hiddenInput.value = `${year}-${formattedMonth}-${formattedDay}`;
                } else {
                    hiddenInput.value = '';
                }
            }

            // Pre-seleziona i valori se esiste una data salvata
            if (savedDate) {
                const dateParts = savedDate.split('-');
                yearSelect.value = parseInt(dateParts[0], 10);
                monthSelect.value = parseInt(dateParts[1], 10);
                daySelect.value = parseInt(dateParts[2], 10);
            }

            // Aggiungi event listeners
            [daySelect, monthSelect, yearSelect].forEach(select => {
                select.addEventListener('change', updateHiddenDate);
            });
        }

        function showRequiredUploads(prestazione) {

            // Mappa delle prestazioni ai documenti richiesti
            const uploadRequirements = {
                'asili_nido': ['certificato_iscrizione_nido', 'autocertificazione_famiglia'],
                'centri_estivi': ['attestazione_spesa_centri_estivi', 'autocertificazione_famiglia'],
                'scuole_elementari': ['dichiarazione_frequenza', 'autocertificazione_famiglia'],
                'scuole_medie_inferiori': ['dichiarazione_frequenza', 'autocertificazione_famiglia'],
                'superiori_iscrizione': ['dichiarazione_frequenza', 'autocertificazione_famiglia'],
                'universita_iscrizione': ['documentazione_universita', 'autocertificazione_famiglia']
            };

            // Nascondi tutti i contenitori prima di mostrare quelli necessari
            document.querySelectorAll('.upload-section-container').forEach(container => {
                container.classList.add('hidden');
            });
            
            // Mostra sempre il box del documento d'identità
            $('#container-for-documento_identita').removeClass('hidden');

            const requiredDocs = uploadRequirements[prestazione];
            
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
        const prestazioneSelezionata = '<?php echo htmlspecialchars($prestazione_selezionata ?? ''); ?>';
        if (prestazioneSelezionata) {
            showRequiredUploads(prestazioneSelezionata);

            // Inizializza i campi data
            setupDateInputs('studente_data_nascita', '<?php e($saved_data['studente_data_nascita'] ?? ''); ?>');
            setupDateInputs('lavoratore_data_nascita', '<?php e($saved_data['lavoratore_data_nascita'] ?? ''); ?>');
        }

        // Gestione dropdown utente
        const userMenuButton = document.getElementById('userMenuButton');
        const userDropdown = document.getElementById('userDropdown');
        
        // Aggiungi gli event listener solo se il pulsante del menu esiste
        if (userMenuButton) {
            userMenuButton.addEventListener('click', () => {
                userDropdown.classList.toggle('hidden');
            });
            
            // Chiudi il dropdown se si clicca fuori
            window.addEventListener('click', function(e) {
                if (userDropdown && !userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.add('hidden');
                }
            });
        }

        // Logica per modale autocertificazione
        const autocertModal = document.getElementById('autocert-modal');
        const autocertIframe = document.getElementById('autocert-iframe');
        const modalTitleElement = document.getElementById('autocert-modal-title');
        const openModalLinks = document.querySelectorAll('.open-compilation-modal');
        const closeModalBtn = document.getElementById('autocert-modal-close-btn');

        openModalLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.href;
                const modalTitle = this.dataset.modalTitle || 'Compilazione Modulo';
                modalTitleElement.textContent = modalTitle;
                autocertIframe.src = url;
                autocertModal.classList.remove('hidden');
            });
        });
        if(closeModalBtn) {
            closeModalBtn.addEventListener('click', () => autocertModal.classList.add('hidden'));
        }
        // Logica per disabilitare i campi in base allo stato e al ruolo
        <?php
            // Logica di modifica corretta:
            // L'utente può modificare solo se lo stato è 'bozza'.
            // L'admin può modificare solo se lo stato è 'inviato'.
            $can_edit = false;
            if (!$is_admin_view && $status === 'bozza') $can_edit = true;
            if ($is_admin_view && ($status === 'inviato' || $status === 'inviato_in_cassa_edile')) $can_edit = true; 

            if (!$can_edit):
        ?>
            // Disabilita tutti i campi tranne quelli necessari per la navigazione e le azioni admin
            $('#modulo1-form :input').not('#existing_form, #unlock-for-user-btn, #admin_notification, [name="action"]').prop('disabled', true);
        <?php endif; ?>

        // --- Logica per la firma digitale ---
        const canvas = document.getElementById('signature-pad');
        const signatureImage = document.getElementById('signature-image');
        const signatureControls = document.getElementById('signature-controls');
        const helpText = document.getElementById('signature-help-text');
        
        // Definisci signaturePad in un ambito più ampio per renderlo sempre accessibile.
        let signaturePad = null;

        // Funzione per inizializzare il pad.
        function initializeSignaturePad() {
            if (canvas && !signaturePad) {
                signaturePad = new SignaturePad(canvas, {
                    penColor: 'blue', // Imposta il colore della penna a blu
                    // Rimuovendo backgroundColor, il canvas diventa trasparente
                });
                window.signaturePadInstance = signaturePad; // Assegna all'istanza globale
                resizeCanvas();
            }
        }
            // Funzione per ridimensionare il canvas mantenendo il contenuto
            function resizeCanvas() { // Corretto: la funzione era definita ma non veniva chiamata su resize
                if (!signaturePad.isEmpty()) {
                    const data = signaturePad.toData();
                    const ratio =  Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext("2d").scale(ratio, ratio);
                    signaturePad.fromData(data);
                } else {
                    const ratio =  Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext("2d").scale(ratio, ratio);
                    signaturePad.clear();
                }
            }
            // Aggiungi l'event listener per il resize
            window.addEventListener("resize", resizeCanvas);
        
            // Gestione dinamica dei pulsanti
            signatureControls.addEventListener('click', function(event) {
                const target = event.target.closest('button');
                if (!target) return;

                if (target.id === 'clear-signature') {
                    // Assicurati che il pad sia inizializzato prima di pulirlo
                    if (!signaturePad) initializeSignaturePad();
                    event.preventDefault();
                    signaturePad.clear();
                }

                if (target.id === 'undo-signature') {
                    const data = signaturePad.toData();
                    if (data.length) {
                        data.pop(); // Rimuove l'ultimo tratto
                        signaturePad.fromData(data);
                    }
                }
                if (target.id === 'modify-signature') {
                    event.preventDefault();
                    // Nascondi l'immagine e il bottone "Modifica"
                    signatureImage.classList.add('hidden');
                    target.classList.add('hidden');

                    // Svuota il campo nascosto per cancellare la vecchia firma al salvataggio
                    $('#firma_data').val('');

                    // Mostra il canvas e il testo di aiuto
                    canvas.classList.remove('hidden');
                    helpText.classList.remove('hidden');

                    // Inizializza il pad e puliscilo
                    initializeSignaturePad();
                    signaturePad.clear();

                    // Crea e mostra il pulsante "Pulisci"
                    const clearButtonHTML = `<div class="space-x-4">
                                                <button type="button" id="undo-signature" class="text-sm text-gray-600 hover:text-primary">Annulla tratto</button>
                                                <button type="button" id="clear-signature" class="text-sm text-gray-600 hover:text-primary">Pulisci</button>
                                             </div>`;
                    signatureControls.innerHTML = clearButtonHTML;
                }
            });
        
        // Se il canvas è visibile al caricamento della pagina, inizializza subito il pad.
        if (canvas && !canvas.classList.contains('hidden')) {
            initializeSignaturePad();
        }
        
            // Prima di inviare il form, salva la firma nel campo nascosto
            $('#modulo1-form').on('submit', function() {
                // Usa la variabile locale 'signaturePad' che è più affidabile
                // e controlla che sia stata inizializzata.
                if (signaturePad && !signaturePad.isEmpty()) {
                    const signatureData = signaturePad.toDataURL('image/png');
                    $('#firma_data').val(signatureData);
                }
            });
    });
</script>

</body>
</html>
