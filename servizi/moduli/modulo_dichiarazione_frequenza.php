<?php
session_start();

// 1. Inizializzazione e recupero dati
$token = $_GET['token'] ?? null;
$origin_form_name = $_GET['origin_form_name'] ?? null;
$origin_prestazione = $_GET['origin_prestazione'] ?? null;
$origin_module = $_GET['origin_module'] ?? null;
$is_admin_view = isset($_GET['is_admin_view']) && $_GET['is_admin_view'] == 1; // Rileva la modalità admin

$user_id = null;
$saved_data = [];

if ((!$token && !$is_admin_view) || !$origin_form_name) {
    die("Accesso non autorizzato o parametri mancanti.");
}

if ($token) {
    $_SESSION['user_token'] = $token;
}

include_once("../../database.php");
$pdo1 = Database::getInstance('fillea');

if ($is_admin_view && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // VISTA ADMIN: L'admin è loggato. Recuperiamo l'ID utente dal form_name.
    $stmt_get_user_from_form = $pdo1->prepare("
        SELECT u.id, u.codfisc 
        FROM `fillea-app`.users u
        JOIN `fillea-app`.richieste_master rm ON u.id = rm.user_id
        WHERE rm.form_name = ?
    ");
    $stmt_get_user_from_form->execute([$origin_form_name]);
    $user = $stmt_get_user_from_form->fetch(PDO::FETCH_ASSOC);
} else {
    // VISTA UTENTE: L'utente è loggato tramite token.
    $stmt_user = $pdo1->prepare("SELECT id, codfisc FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW()");
    $stmt_user->execute([$token]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
}

if (!$user) {
    die("Utente non valido o sessione scaduta. Impossibile identificare il proprietario della pratica.");
}
$user_id = $user['id'];

$json_filename = 'dich_frequenza_' . $origin_form_name . '.json';
$json_filepath = __DIR__ . '/autocertificazioni_data/' . $json_filename;

if (file_exists($json_filepath)) {
    $json_content = file_get_contents($json_filepath);
    $saved_data = json_decode($json_content, true);
} else {
    // Se non ci sono dati salvati, pre-compila con i dati dell'anagrafe
    $codfisc = $user['codfisc'] ?? null;
    if ($codfisc) {
        $pdo_anagrafe = Database::getInstance('anagrafe');
        $stmt_anagrafe = $pdo_anagrafe->prepare("
            SELECT NOME, VIA, LOC, DATANASC, COMUNENASC, PRO 
            FROM anagrafe.t2_tosc_a 
            WHERE codfisc = ? LIMIT 1
        ");
        $stmt_anagrafe->execute([$codfisc]);
        $anagrafe_data = $stmt_anagrafe->fetch(PDO::FETCH_ASSOC);

        if ($anagrafe_data) {
            $saved_data['sottoscrittore_nome_cognome'] = $anagrafe_data['NOME'];
            $saved_data['sottoscrittore_luogo_nascita'] = $anagrafe_data['COMUNENASC'];
            $saved_data['sottoscrittore_prov_nascita'] = $anagrafe_data['PRO'];
            $saved_data['sottoscrittore_data_nascita'] = $anagrafe_data['DATANASC'];
            $saved_data['sottoscrittore_residenza_comune'] = $anagrafe_data['LOC'];
            $saved_data['sottoscrittore_residenza_prov'] = $anagrafe_data['PRO'];
            $saved_data['sottoscrittore_residenza_indirizzo'] = $anagrafe_data['VIA'];
        }
    }
}

function e($value) {
    echo htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dichiarazione Sostitutiva di Iscrizione e Frequenza</title>
    
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
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body { background-color: #f8f9fa; }
        .form-section { background-color: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); margin-bottom: 2rem; }
        .form-section-title { font-size: 1.25rem; font-weight: 700; color: #1f2937; border-bottom: 2px solid #d1d5db; padding-bottom: 0.75rem; margin-bottom: 1.5rem; }
        .form-label { font-weight: 600; color: #4b5563; }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-input:focus { outline: none; border-color: #d0112b; box-shadow: 0 0 0 2px rgba(208, 17, 43, 0.2); }
        .btn-primary { background-color: #d0112b; color: white; font-weight: bold; padding: 0.75rem 1.5rem; border-radius: 0.5rem; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #a80e23; }
        .radio-label { display: flex; align-items: center; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; cursor: pointer; }
        .radio-label:has(input:checked) { background-color: #fbe6e8; border-color: #d0112b; }
    </style>
</head>
<body class="p-4 md:p-6">

<div class="max-w-3xl mx-auto">
    <header class="text-center mb-8">
        <h1 class="text-2xl md:text-3xl font-bold text-primary">Dichiarazione Sostitutiva di Iscrizione e Frequenza</h1>
        <p class="text-md text-gray-600 mt-2">(Art. 46 D.P.R. 28 dicembre 2000, n. 445)</p>
    </header>

    <form id="dichiarazione-form" action="modulo_dichiarazione_frequenza_save.php" method="POST">
        <input type="hidden" name="token" value="<?php e($token); ?>">
        <input type="hidden" name="origin_form_name" value="<?php e($origin_form_name); ?>">
        <input type="hidden" name="origin_prestazione" value="<?php e($origin_prestazione); ?>">
        <input type="hidden" name="origin_module" value="<?php e($origin_module); ?>">
        <input type="hidden" name="firma_data" id="firma_data" value="<?php e($saved_data['firma_data'] ?? ''); ?>">

        <!-- Dati Sottoscrittore -->
        <div class="form-section">
            <h2 class="form-section-title">Il Sottoscritto</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="sottoscrittore_nome_cognome" class="form-label">Cognome e Nome</label>
                    <input type="text" id="sottoscrittore_nome_cognome" name="sottoscrittore_nome_cognome" class="form-input" value="<?php e($saved_data['sottoscrittore_nome_cognome'] ?? ''); ?>" required <?php if ($is_admin_view) echo 'disabled'; ?>>
                </div>
                <div>
                    <label class="form-label">Data di Nascita</label>
                    <div class="grid grid-cols-3 gap-2">
                        <select id="sottoscrittore_data_nascita_giorno" class="form-input text-sm" <?php if ($is_admin_view) echo 'disabled'; ?>><option value="">Giorno</option></select>
                        <select id="sottoscrittore_data_nascita_mese" class="form-input text-sm" <?php if ($is_admin_view) echo 'disabled'; ?>><option value="">Mese</option></select>
                        <select id="sottoscrittore_data_nascita_anno" class="form-input text-sm" <?php if ($is_admin_view) echo 'disabled'; ?>><option value="">Anno</option></select>
                    </div>
                    <input type="hidden" id="sottoscrittore_data_nascita" name="sottoscrittore_data_nascita" value="<?php e($saved_data['sottoscrittore_data_nascita'] ?? ''); ?>">
                </div>
                <div>
                    <label for="sottoscrittore_luogo_nascita" class="form-label">Luogo di Nascita</label>
                    <input type="text" id="sottoscrittore_luogo_nascita" name="sottoscrittore_luogo_nascita" class="form-input" value="<?php e($saved_data['sottoscrittore_luogo_nascita'] ?? ''); ?>" required <?php if ($is_admin_view) echo 'disabled'; ?>>
                </div>
                <div>
                    <label for="sottoscrittore_prov_nascita" class="form-label">Prov. di Nascita</label>
                    <input type="text" id="sottoscrittore_prov_nascita" name="sottoscrittore_prov_nascita" class="form-input" value="<?php e($saved_data['sottoscrittore_prov_nascita'] ?? ''); ?>" maxlength="2" required <?php if ($is_admin_view) echo 'disabled'; ?>>
                </div>
                <div>
                    <label for="sottoscrittore_residenza_comune" class="form-label">Comune di Residenza</label>
                    <input type="text" id="sottoscrittore_residenza_comune" name="sottoscrittore_residenza_comune" class="form-input" value="<?php e($saved_data['sottoscrittore_residenza_comune'] ?? ''); ?>" required <?php if ($is_admin_view) echo 'disabled'; ?>>
                </div>
                <div>
                    <label for="sottoscrittore_residenza_prov" class="form-label">Prov. di Residenza</label>
                    <input type="text" id="sottoscrittore_residenza_prov" name="sottoscrittore_residenza_prov" class="form-input" value="<?php e($saved_data['sottoscrittore_residenza_prov'] ?? ''); ?>" maxlength="2" required <?php if ($is_admin_view) echo 'disabled'; ?>>
                </div>
                <div class="md:col-span-2">
                    <label for="sottoscrittore_residenza_indirizzo" class="form-label">Indirizzo e n. civico</label>
                    <input type="text" id="sottoscrittore_residenza_indirizzo" name="sottoscrittore_residenza_indirizzo" class="form-input" value="<?php e($saved_data['sottoscrittore_residenza_indirizzo'] ?? ''); ?>" required <?php if ($is_admin_view) echo 'disabled'; ?>>
                </div>
            </div>
        </div>

        <!-- Dichiarazione -->
        <div class="form-section">
            <h2 class="form-section-title">Dichiara</h2>
            <div class="space-y-6">
                <!-- Qualità del dichiarante -->
                <div>
                    <label class="form-label">In qualità di:</label>
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <label class="radio-label"><input type="radio" name="qualita_dichiarante" value="Dichiarante" class="mr-2" <?php if (($saved_data['qualita_dichiarante'] ?? '') === 'Dichiarante') echo 'checked'; ?> <?php if ($is_admin_view) echo 'disabled'; ?>>Dichiarante</label>
                        <label class="radio-label"><input type="radio" name="qualita_dichiarante" value="Genitore" class="mr-2" <?php if (($saved_data['qualita_dichiarante'] ?? 'Genitore') === 'Genitore') echo 'checked'; ?> <?php if ($is_admin_view) echo 'disabled'; ?>>Genitore</label>
                        <label class="radio-label"><input type="radio" name="qualita_dichiarante" value="Altro" class="mr-2" <?php if (($saved_data['qualita_dichiarante'] ?? '') === 'Altro') echo 'checked'; ?> <?php if ($is_admin_view) echo 'disabled'; ?>>Altro</label>
                    </div>
                    <div id="qualita_altro_container" class="mt-4 <?php if (($saved_data['qualita_dichiarante'] ?? '') !== 'Altro') echo 'hidden'; ?>">
                        <label for="qualita_altro_specifica" class="form-label">Specificare (es. Tutore, Legale Rapp.)</label>
                        <input type="text" id="qualita_altro_specifica" name="qualita_altro_specifica" class="form-input" value="<?php e($saved_data['qualita_altro_specifica'] ?? ''); ?>" <?php if ($is_admin_view) echo 'disabled'; ?>>
                    </div>
                </div>

                <!-- Dati del minore -->
                <div id="dati_minore_container" class="<?php if (($saved_data['qualita_dichiarante'] ?? 'Genitore') === 'Dichiarante') echo 'hidden'; ?>">
                    <label class="form-label">...del minore:</label>
                    <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-6 border p-4 rounded-md bg-gray-50">
                        <div>
                            <label for="minore_nome_cognome" class="form-label text-sm">Cognome e Nome</label>
                            <input type="text" id="minore_nome_cognome" name="minore_nome_cognome" class="form-input" value="<?php e($saved_data['minore_nome_cognome'] ?? ''); ?>" <?php if ($is_admin_view) echo 'disabled'; ?>>
                        </div>
                        <div>
                            <label class="form-label text-sm">Data di Nascita</label>
                            <div class="grid grid-cols-3 gap-2">
                                <select id="minore_data_nascita_giorno" class="form-input text-sm" <?php if ($is_admin_view) echo 'disabled'; ?>><option value="">Giorno</option></select>
                                <select id="minore_data_nascita_mese" class="form-input text-sm" <?php if ($is_admin_view) echo 'disabled'; ?>><option value="">Mese</option></select>
                                <select id="minore_data_nascita_anno" class="form-input text-sm" <?php if ($is_admin_view) echo 'disabled'; ?>><option value="">Anno</option></select>
                            </div>
                            <input type="hidden" id="minore_data_nascita" name="minore_data_nascita" value="<?php e($saved_data['minore_data_nascita'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="minore_luogo_nascita" class="form-label text-sm">Luogo di Nascita</label>
                            <input type="text" id="minore_luogo_nascita" name="minore_luogo_nascita" class="form-input" value="<?php e($saved_data['minore_luogo_nascita'] ?? ''); ?>" <?php if ($is_admin_view) echo 'disabled'; ?>>
                        </div>
                        <div>
                            <label for="minore_prov_nascita" class="form-label text-sm">Prov. di Nascita</label>
                            <input type="text" id="minore_prov_nascita" name="minore_prov_nascita" class="form-input" value="<?php e($saved_data['minore_prov_nascita'] ?? ''); ?>" maxlength="2" <?php if ($is_admin_view) echo 'disabled'; ?>>
                        </div>
                    </div>
                </div>

                <!-- Ciclo di studi -->
                <div>
                    <label class="form-label">Che lo/a stesso/a è iscritto/a, per il corrente anno scolastico, al ciclo di studi previsto per l’Istruzione:</label>
                    <div class="mt-2 space-y-3">
                        <?php
                        $cicli = [
                            'primaria' => 'Primaria (Scuola Primaria già Scuola Elementare)',
                            'secondaria_primo' => 'Secondaria di primo grado (Ex Scuola media inferiore)',
                            'secondaria_secondo' => 'Secondaria di secondo grado (Ex Scuola media superiore)',
                            'superiore' => 'Superiore (Università – Conservatori ed alta formazione artistica)'
                        ];
                        $selected_ciclo = $saved_data['ciclo_studi'] ?? '';
                        foreach ($cicli as $key => $label):
                        ?>
                        <label class="radio-label"><input type="radio" name="ciclo_studi" value="<?php echo $key; ?>" class="mr-2" <?php if ($selected_ciclo === $key) echo 'checked'; ?> <?php if ($is_admin_view) echo 'disabled'; ?>> <?php echo $label; ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Dettagli frequenza standard -->
                <div id="frequenza_standard_container">
                    <label class="form-label">...e che frequenta, regolarmente le lezioni:</label>
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 border p-4 rounded-md bg-gray-50">
                        <div>
                            <label for="frequenza_classe" class="form-label text-sm">della classe</label>
                            <input type="text" id="frequenza_classe" name="frequenza_classe" class="form-input" value="<?php e($saved_data['frequenza_classe'] ?? ''); ?>" <?php if ($is_admin_view) echo 'disabled'; ?>>
                        </div>
                        <div>
                            <label for="frequenza_sezione" class="form-label text-sm">Sezione</label>
                            <input type="text" id="frequenza_sezione" name="frequenza_sezione" class="form-input" value="<?php e($saved_data['frequenza_sezione'] ?? ''); ?>" <?php if ($is_admin_view) echo 'disabled'; ?>>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="frequenza_istituto" class="form-label text-sm">dell'Istituto</label>
                            <input type="text" id="frequenza_istituto" name="frequenza_istituto" class="form-input" value="<?php e($saved_data['frequenza_istituto'] ?? ''); ?>" <?php if ($is_admin_view) echo 'disabled'; ?>>
                        </div>
                        <div class="sm:col-span-2 md:col-span-4">
                            <label for="frequenza_comune_istituto" class="form-label text-sm">di (Comune ove è ubicato l'Istituto)</label>
                            <input type="text" id="frequenza_comune_istituto" name="frequenza_comune_istituto" class="form-input" value="<?php e($saved_data['frequenza_comune_istituto'] ?? ''); ?>" <?php if ($is_admin_view) echo 'disabled'; ?>>
                        </div>
                    </div>
                </div>

                <!-- Dettagli frequenza per triennio superiori -->
                <div id="frequenza_triennio_container" class="<?php if (($saved_data['ciclo_studi'] ?? '') !== 'secondaria_secondo') echo 'hidden'; ?>">
                    <label class="form-label">...per gli iscritti al triennio della Secondaria di secondo grado, che frequenta regolarmente le lezioni da almeno 2 mesi:</label>
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 border p-4 rounded-md bg-gray-50">
                        <div>
                            <label for="frequenza_triennio_classe" class="form-label text-sm">della classe</label>
                            <input type="text" id="frequenza_triennio_classe" name="frequenza_triennio_classe" class="form-input" value="<?php e($saved_data['frequenza_triennio_classe'] ?? ''); ?>" <?php if ($is_admin_view) echo 'disabled'; ?>>
                        </div>
                        <div>
                            <label for="frequenza_triennio_sezione" class="form-label text-sm">Sezione</label>
                            <input type="text" id="frequenza_triennio_sezione" name="frequenza_triennio_sezione" class="form-input" value="<?php e($saved_data['frequenza_triennio_sezione'] ?? ''); ?>" <?php if ($is_admin_view) echo 'disabled'; ?>>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="frequenza_triennio_istituto" class="form-label text-sm">dell'Istituto</label>
                            <input type="text" id="frequenza_triennio_istituto" name="frequenza_triennio_istituto" class="form-input" value="<?php e($saved_data['frequenza_triennio_istituto'] ?? ''); ?>" <?php if ($is_admin_view) echo 'disabled'; ?>>
                        </div>
                        <div class="sm:col-span-2 md:col-span-4">
                            <label for="frequenza_triennio_comune_istituto" class="form-label text-sm">di (Comune ove è ubicato l'Istituto)</label>
                            <input type="text" id="frequenza_triennio_comune_istituto" name="frequenza_triennio_comune_istituto" class="form-input" value="<?php e($saved_data['frequenza_triennio_comune_istituto'] ?? ''); ?>" <?php if ($is_admin_view) echo 'disabled'; ?>>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sezione Firma -->
        <div class="form-section">
            <h2 class="form-section-title">Luogo, Data e Firma del Dichiarante</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="luogo_firma" class="form-label">Luogo</label>
                    <input type="text" id="luogo_firma" name="luogo_firma" class="form-input" value="<?php e($saved_data['luogo_firma'] ?? 'Firenze'); ?>" required <?php if ($is_admin_view) echo 'disabled'; ?>>
                </div>
                <div>
                    <label for="data_firma" class="form-label">Data</label>
                    <input type="date" id="data_firma" name="data_firma" class="form-input" value="<?php e($saved_data['data_firma'] ?? ''); ?>" required <?php if ($is_admin_view) echo 'disabled'; ?>>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Firma Digitale</label>
                    <div id="signature-container" class="w-full mt-2 border border-gray-300 rounded-lg relative">
                        <?php $has_signature = !empty($saved_data['firma_data']); ?>
                        <img id="signature-image" src="<?php echo $has_signature ? $saved_data['firma_data'] : ''; ?>" alt="Firma salvata" class="w-full h-auto <?php if (!$has_signature) echo 'hidden'; ?>">
                        <canvas id="signature-pad" class="w-full h-48 <?php if ($has_signature) echo 'hidden'; ?>"></canvas>
                    </div>
                    <div id="signature-controls" class="flex justify-end mt-2">
                        <?php if (!$is_admin_view): ?>
                            <?php if ($has_signature): ?>
                                <button type="button" id="modify-signature" class="text-sm text-blue-600 hover:text-blue-800 font-semibold"><i class="fas fa-pencil-alt mr-1"></i> Modifica Firma</button>
                            <?php else: ?>
                                <div class="space-x-4">
                                    <button type="button" id="undo-signature" class="text-sm text-gray-600 hover:text-primary">Annulla tratto</button>
                                    <button type="button" id="clear-signature" class="text-sm text-gray-600 hover:text-primary">Pulisci</button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$is_admin_view): ?>
        <div class="mt-8 text-center">
            <button type="submit" id="save-btn" class="btn-primary w-full md:w-auto">
                <i class="fas fa-save mr-2"></i> Salva e Genera Dichiarazione
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- IMPOSTAZIONI INIZIALI ---
    const dataFirmaInput = document.getElementById('data_firma');
    if (!dataFirmaInput.value) {
        dataFirmaInput.value = new Date().toISOString().split('T')[0];
    }

    // --- GESTIONE DATA DI NASCITA A 3 CAMPI ---
    function setupDateInputs(prefix, savedDate) {
        const daySelect = document.getElementById(prefix + '_giorno');
        const monthSelect = document.getElementById(prefix + '_mese');
        const yearSelect = document.getElementById(prefix + '_anno');
        const hiddenInput = document.getElementById(prefix);
        if (!daySelect || !monthSelect || !yearSelect || !hiddenInput) return;

        const currentYear = new Date().getFullYear();
        for (let i = currentYear; i >= 1924; i--) { yearSelect.add(new Option(i, i)); }
        const months = ["Gennaio", "Febbraio", "Marzo", "Aprile", "Maggio", "Giugno", "Luglio", "Agosto", "Settembre", "Ottobre", "Novembre", "Dicembre"];
        months.forEach((month, index) => { monthSelect.add(new Option(month, index + 1)); });
        for (let i = 1; i <= 31; i++) { daySelect.add(new Option(i, i)); }

        function updateHiddenDate() {
            const year = yearSelect.value, month = monthSelect.value, day = daySelect.value;
            if (year && month && day) {
                hiddenInput.value = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
            } else {
                hiddenInput.value = '';
            }
        }

        if (savedDate) {
            const dateParts = savedDate.split('-');
            if (dateParts.length === 3) {
                yearSelect.value = parseInt(dateParts[0], 10);
                monthSelect.value = parseInt(dateParts[1], 10);
                daySelect.value = parseInt(dateParts[2], 10);
            }
        }
        [daySelect, monthSelect, yearSelect].forEach(select => select.addEventListener('change', updateHiddenDate));
    }

    setupDateInputs('sottoscrittore_data_nascita', '<?php e($saved_data['sottoscrittore_data_nascita'] ?? ''); ?>');
    setupDateInputs('minore_data_nascita', '<?php e($saved_data['minore_data_nascita'] ?? ''); ?>');

    // --- GESTIONE CAMPI CONDIZIONALI ---
    const qualitaRadios = document.querySelectorAll('input[name="qualita_dichiarante"]');
    const altroContainer = document.getElementById('qualita_altro_container');
    const minoreContainer = document.getElementById('dati_minore_container');

    qualitaRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            altroContainer.classList.toggle('hidden', this.value !== 'Altro');
            minoreContainer.classList.toggle('hidden', this.value === 'Dichiarante');
        });
    });

    const cicloStudiRadios = document.querySelectorAll('input[name="ciclo_studi"]');
    const frequenzaTriennioContainer = document.getElementById('frequenza_triennio_container');

    cicloStudiRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            frequenzaTriennioContainer.classList.toggle('hidden', this.value !== 'secondaria_secondo');
        });
    });

    // --- GESTIONE FIRMA ---
    const canvas = document.getElementById('signature-pad');
    const signatureImage = document.getElementById('signature-image');
    const signatureControls = document.getElementById('signature-controls');
    let signaturePad = null;


    function initializeSignaturePad() {
        if (canvas) {
            // Se l'istanza esiste già, la disattiviamo per ricrearla.
            if (signaturePad) {
                signaturePad.off();
            }
            signaturePad = new SignaturePad(canvas, { penColor: 'blue' });
            resizeCanvas();
        }
    }    

    function resizeCanvas() {
        if (!signaturePad) return;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        signaturePad.clear();
    }
    window.addEventListener("resize", resizeCanvas);

    if (canvas && !canvas.classList.contains('hidden')) {
        initializeSignaturePad();
    }

    signatureControls.addEventListener('click', function(event) {
        const target = event.target.closest('button');
        if (!target) return;

        if (!signaturePad) initializeSignaturePad();

        if (target.id === 'clear-signature') {
            signaturePad.clear();
        }
        if (target.id === 'undo-signature') {
            const data = signaturePad.toData();
            if (data.length) {
                data.pop();
                signaturePad.fromData(data);
            }
        }
        if (target.id === 'modify-signature') {
            event.preventDefault();
            signatureImage.classList.add('hidden');
            target.style.display = 'none';
            $('#firma_data').val('');
            canvas.classList.remove('hidden');
            initializeSignaturePad();
            signatureControls.innerHTML = `<div class="space-x-4">
                                            <button type="button" id="undo-signature" class="text-sm text-gray-600 hover:text-primary">Annulla tratto</button>
                                            <button type="button" id="clear-signature" class="text-sm text-gray-600 hover:text-primary">Pulisci</button>
                                          </div>`;
        }
    });

    // --- GESTIONE SUBMIT ---
    $('#dichiarazione-form').on('submit', function(e) {
        e.preventDefault();

        if (signaturePad && !signaturePad.isEmpty()) {
            $('#firma_data').val(signaturePad.toDataURL('image/png'));
        }

        if (!$('#firma_data').val()) {
            alert('La firma è obbligatoria.');
            return;
        }

        const formData = new FormData(this);
        const saveBtn = $('#save-btn');
        saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Salvataggio in corso...');

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.status === 'success') {
                    if (window.parent && typeof window.parent.showToast === 'function') {
                        window.parent.showToast('Dichiarazione salvata con successo!');
                    }
                    setTimeout(() => {
                        if (window.parent) {
                           window.parent.location.reload();
                        }
                    }, 1000);
                } else {
                    alert('Errore: ' + response.message);
                    saveBtn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Salva e Genera Dichiarazione');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                let errorMsg = 'Si è verificato un errore durante il salvataggio.';
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    errorMsg = jqXHR.responseJSON.message;
                }
                alert(errorMsg);
                saveBtn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Salva e Genera Dichiarazione');
            }
        });
    });
});
</script>

</body>
</html>