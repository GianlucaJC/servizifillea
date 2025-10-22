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

if (!$token || !$origin_form_name) {
    die("Accesso non autorizzato o parametri mancanti.");
}

// Salva il token in sessione per coerenza
$_SESSION['user_token'] = $token;

include_once("../../database.php");
$pdo1 = Database::getInstance('fillea');

// Recupera user_id dal token
$stmt_user = $pdo1->prepare("SELECT id, codfisc FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW()");
$stmt_user->execute([$token]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utente non valido o sessione scaduta.");
}
$user_id = $user['id'];


// Cerca dati salvati per questa autocertificazione
$json_filename = 'autocert_' . $origin_form_name . '.json';
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
            SELECT NOME, VIA, LOC, DATANASC, COMUNENASC 
            FROM anagrafe.t2_tosc_a 
            WHERE codfisc = ? LIMIT 1
        ");
        $stmt_anagrafe->execute([$codfisc]);
        $anagrafe_data = $stmt_anagrafe->fetch(PDO::FETCH_ASSOC);

        if ($anagrafe_data) {
            $saved_data['sottoscrittore_nome_cognome'] = $anagrafe_data['NOME'];
            $saved_data['sottoscrittore_luogo_nascita'] = $anagrafe_data['COMUNENASC'];
            $saved_data['sottoscrittore_data_nascita'] = $anagrafe_data['DATANASC'];
            $saved_data['sottoscrittore_residenza_comune'] = $anagrafe_data['LOC'];
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
    <title>Autocertificazione Stato di Famiglia</title>
    
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
        /* Stili ripresi da modulo1.php per coerenza */
        body { background-color: #f8f9fa; }
        .form-section { background-color: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); margin-bottom: 2rem; }
        .form-section-title { font-size: 1.25rem; font-weight: 700; color: #1f2937; border-bottom: 2px solid #d1d5db; padding-bottom: 0.75rem; margin-bottom: 1.5rem; }
        .form-label { font-weight: 600; color: #4b5563; }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; transition: border-color 0.2s, box-shadow 0.2s; }
        .form-input:focus { outline: none; border-color: #d0112b; box-shadow: 0 0 0 2px rgba(208, 17, 43, 0.2); }
        .btn-primary { background-color: #d0112b; color: white; font-weight: bold; padding: 0.75rem 1.5rem; border-radius: 0.5rem; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #a80e23; }
        .btn-secondary { background-color: #6c757d; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: background-color 0.3s; }
        .btn-secondary:hover { background-color: #5a6268; }
    </style>
</head>
<body class="p-4 md:p-6">

<div class="max-w-3xl mx-auto">
    <header class="text-center mb-8">
        <h1 class="text-2xl md:text-3xl font-bold text-primary">Autocertificazione Stato di Famiglia</h1>
        <p class="text-md text-gray-600 mt-2">Compila i dati richiesti per generare il documento.</p>
    </header>

    <form id="autocert-form" action="modulo_autocertificazione_stato_famiglia_save.php" method="POST">
        <!-- Campi nascosti per il salvataggio -->
        <input type="hidden" name="token" value="<?php e($token); ?>">
        <input type="hidden" name="origin_form_name" value="<?php e($origin_form_name); ?>">
        <input type="hidden" name="origin_prestazione" value="<?php e($origin_prestazione); ?>">
        <input type="hidden" name="origin_module" value="<?php e($origin_module); ?>">
        <input type="hidden" name="firma_data" id="firma_data" value="<?php e($saved_data['firma_data'] ?? ''); ?>">
        <input type="hidden" name="membri_famiglia_json" id="membri_famiglia_json">

        <!-- Sezione Dati Sottoscrittore -->
        <div class="form-section">
            <h2 class="form-section-title">Dati del Dichiarante</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="sottoscrittore_nome_cognome" class="form-label">Nome e Cognome</label>
                    <input type="text" id="sottoscrittore_nome_cognome" name="sottoscrittore_nome_cognome" class="form-input" value="<?php e($saved_data['sottoscrittore_nome_cognome'] ?? ''); ?>" required <?php if ($is_admin_view) echo 'disabled'; ?>>
                </div>
                <div>
                    <label for="sottoscrittore_luogo_nascita" class="form-label">Luogo di Nascita</label>
                    <input type="text" id="sottoscrittore_luogo_nascita" name="sottoscrittore_luogo_nascita" class="form-input" value="<?php e($saved_data['sottoscrittore_luogo_nascita'] ?? ''); ?>" required <?php if ($is_admin_view) echo 'disabled'; ?>>
                </div>
                <div>
                    <label for="sottoscrittore_data_nascita" class="form-label">Data di Nascita</label>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <select id="sottoscrittore_data_nascita_giorno" class="form-input text-sm" aria-label="Giorno di nascita" <?php if ($is_admin_view) echo 'disabled'; ?>><option value="">Giorno</option></select>
                        </div>
                        <div>
                            <select id="sottoscrittore_data_nascita_mese" class="form-input text-sm" aria-label="Mese di nascita" <?php if ($is_admin_view) echo 'disabled'; ?>><option value="">Mese</option></select>
                        </div>
                        <div>
                            <select id="sottoscrittore_data_nascita_anno" class="form-input text-sm" aria-label="Anno di nascita" <?php if ($is_admin_view) echo 'disabled'; ?>><option value="">Anno</option></select>
                        </div>
                    </div>
                    <input type="hidden" id="sottoscrittore_data_nascita" name="sottoscrittore_data_nascita" value="<?php e($saved_data['sottoscrittore_data_nascita'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="sottoscrittore_residenza_comune" class="form-label">Comune di Residenza</label>
                    <input type="text" id="sottoscrittore_residenza_comune" name="sottoscrittore_residenza_comune" class="form-input" value="<?php e($saved_data['sottoscrittore_residenza_comune'] ?? ''); ?>" required <?php if ($is_admin_view) echo 'disabled'; ?>>
                </div>
                <div class="md:col-span-2">
                    <label for="sottoscrittore_residenza_indirizzo" class="form-label">Indirizzo di Residenza (Via/Piazza e n.)</label>
                    <input type="text" id="sottoscrittore_residenza_indirizzo" name="sottoscrittore_residenza_indirizzo" class="form-input" value="<?php e($saved_data['sottoscrittore_residenza_indirizzo'] ?? ''); ?>" required <?php if ($is_admin_view) echo 'disabled'; ?>>
                </div>
            </div>
        </div>

        <!-- Sezione Membri Famiglia -->
        <div class="form-section">
            <div class="flex justify-between items-center mb-4">
                <h2 class="form-section-title !border-0 !mb-0">Composizione Nucleo Familiare</h2>
                <?php if (!$is_admin_view): ?>
                <button type="button" id="add-member-btn" class="btn-secondary text-sm"><i class="fas fa-plus mr-2"></i>Aggiungi Membro</button>
                <?php endif; ?>
            </div>
            <p class="text-sm text-gray-600 mb-6">Elenca tutti i componenti del nucleo familiare, incluso il dichiarante.</p>
            <div id="family-members-container" class="space-y-4">
                <!-- Le card dei membri verranno inserite qui da JS -->
            </div>
        </div>

        <!-- Sezione Firma -->
        <div class="form-section">
            <h2 class="form-section-title">Luogo, Data e Firma</h2>
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
                <i class="fas fa-save mr-2"></i> Salva e Genera Autocertificazione
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- Template per la card di un membro della famiglia -->
<template id="family-member-template">
    <div class="family-member-card border border-gray-200 rounded-lg p-4 bg-gray-50 relative">
        <button type="button" class="remove-member-btn absolute top-2 right-2 text-red-500 hover:text-red-700" title="Rimuovi membro">
            <?php if (!$is_admin_view): ?>
            <i class="fas fa-trash-alt"></i>
            <?php endif; ?>
        </button>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label text-sm">Nome e Cognome</label>
                <input type="text" class="form-input member-nome" placeholder="Mario Rossi" required <?php if ($is_admin_view) echo 'disabled'; ?>>
            </div>
            <div>
                <label class="form-label text-sm">Parentela</label>
                <input type="text" class="form-input member-parentela" placeholder="Padre, Figlio, ecc." required <?php if ($is_admin_view) echo 'disabled'; ?>>
            </div>
            <div>
                <label class="form-label text-sm">Luogo di Nascita</label>
                <input type="text" class="form-input member-luogo-nascita" placeholder="Firenze" required <?php if ($is_admin_view) echo 'disabled'; ?>>
            </div>
            <div>
                <label class="form-label text-sm">Data di Nascita</label>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <select class="form-input text-sm member-data-nascita-giorno" aria-label="Giorno di nascita membro" <?php if ($is_admin_view) echo 'disabled'; ?>><option value="">Giorno</option></select>
                    </div>
                    <div>
                        <select class="form-input text-sm member-data-nascita-mese" aria-label="Mese di nascita membro" <?php if ($is_admin_view) echo 'disabled'; ?>><option value="">Mese</option></select>
                    </div>
                    <div>
                        <select class="form-input text-sm member-data-nascita-anno" aria-label="Anno di nascita membro" <?php if ($is_admin_view) echo 'disabled'; ?>><option value="">Anno</option></select>
                    </div>
                </div>
                <input type="hidden" class="member-data-nascita" required>
            </div>
        </div>
    </div>
</template>


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
    function setupDateInputs(daySelect, monthSelect, yearSelect, hiddenInput, savedDate) {
        if (!daySelect || !monthSelect || !yearSelect || !hiddenInput) return;

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

        function updateHiddenDate() {
            const year = yearSelect.value;
            const month = monthSelect.value;
            const day = daySelect.value;

            if (year && month && day) {
                const formattedMonth = month.toString().padStart(2, '0');
                const formattedDay = day.toString().padStart(2, '0');
                hiddenInput.value = `${year}-${formattedMonth}-${formattedDay}`;
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

    // --- GESTIONE MEMBRI FAMIGLIA ---
    const container = document.getElementById('family-members-container');
    const addButton = document.getElementById('add-member-btn');
    const template = document.getElementById('family-member-template');
    const hiddenJsonInput = document.getElementById('membri_famiglia_json');

    function addMember(data = {}) {
        const clone = template.content.cloneNode(true);
        const card = clone.querySelector('.family-member-card');
        
        card.querySelector('.member-nome').value = data.nome_cognome || '';
        card.querySelector('.member-parentela').value = data.parentela || '';
        card.querySelector('.member-luogo-nascita').value = data.luogo_nascita || '';
        
        // Inizializza i selettori data per la nuova card
        const daySelect = card.querySelector('.member-data-nascita-giorno');
        const monthSelect = card.querySelector('.member-data-nascita-mese');
        const yearSelect = card.querySelector('.member-data-nascita-anno');
        const hiddenInput = card.querySelector('.member-data-nascita');
        hiddenInput.value = data.data_nascita || ''; // Imposta il valore iniziale del campo nascosto

        setupDateInputs(daySelect, monthSelect, yearSelect, hiddenInput, data.data_nascita || '');

        const removeBtn = card.querySelector('.remove-member-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => card.remove());
        }

        container.appendChild(card);
    }

    addButton.addEventListener('click', () => addMember());

    // Inizializza i campi data per il sottoscrittore
    setupDateInputs(
        document.getElementById('sottoscrittore_data_nascita_giorno'),
        document.getElementById('sottoscrittore_data_nascita_mese'),
        document.getElementById('sottoscrittore_data_nascita_anno'),
        document.getElementById('sottoscrittore_data_nascita'),
        '<?php e($saved_data['sottoscrittore_data_nascita'] ?? ''); ?>');

    // Popola con dati salvati
    const savedMembers = <?php echo json_encode($saved_data['membri_famiglia'] ?? []); ?>;
    if (savedMembers.length > 0) {
        savedMembers.forEach(member => addMember(member));
    } else {
        // Aggiungi un membro vuoto se non ci sono dati salvati
        addMember();
    }

    function collectFamilyData() {
        const members = [];
        container.querySelectorAll('.family-member-card').forEach(card => {
            const memberData = {
                nome_cognome: card.querySelector('.member-nome').value,
                parentela: card.querySelector('.member-parentela').value,
                luogo_nascita: card.querySelector('.member-luogo-nascita').value,
                data_nascita: card.querySelector('.member-data-nascita').value,
            };
            members.push(memberData);
        });
        hiddenJsonInput.value = JSON.stringify(members);
    }

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
            $('#firma_data').val('');
            canvas.classList.remove('hidden');
            initializeSignaturePad();
            
            // Sostituisce il contenuto del div dei controlli con i nuovi pulsanti, rendendoli visibili.
            signatureControls.innerHTML = `<div class="space-x-4">
                                            <button type="button" id="undo-signature" class="text-sm text-gray-600 hover:text-primary">Annulla tratto</button>
                                            <button type="button" id="clear-signature" class="text-sm text-gray-600 hover:text-primary">Pulisci</button>
                                          </div>`;
        }
    });

    // --- GESTIONE VISTA ADMIN ---
    // Se è la vista admin, nascondi i controlli non necessari.
    // La disabilitazione degli input è già gestita in PHP.
    <?php if ($is_admin_view): ?>
        $('#add-member-btn, #signature-controls, #save-btn').hide();
    <?php endif; ?>

    // --- GESTIONE SUBMIT ---
    $('#autocert-form').on('submit', function(e) {
        e.preventDefault();

        // 1. Raccogli i dati dei membri della famiglia e li inserisce nel campo nascosto
        collectFamilyData();

        // 2. Salva la firma dal canvas al campo nascosto, se è stata disegnata
        if (signaturePad && !signaturePad.isEmpty()) {
            $('#firma_data').val(signaturePad.toDataURL('image/png'));
        }

        // 3. Validazione: controlla che la firma esista e non sia un'immagine vuota
        const firmaData = $('#firma_data').val();
        if (!firmaData || firmaData === 'data:,') {
            alert('La firma è obbligatoria.');
            return;
        }

        // 4. Prepara e invia i dati con AJAX
        const formData = new FormData(this);
        const saveBtn = $('#save-btn');
        saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Salvataggio in corso...');

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json', // Specifica che ci aspettiamo una risposta JSON
            success: function(response) {
                if (response.status === 'success') {
                    // Comunica alla finestra genitore di mostrare il toast e ricaricare
                    if (window.parent && typeof window.parent.showToast === 'function') {
                        window.parent.showToast('Autocertificazione salvata con successo!');
                    }
                     // Ricarica la pagina del modulo principale per vedere l'allegato aggiornato e chiudere la modale
                    setTimeout(() => {
                        if (window.parent) {
                           window.parent.location.reload();
                        }
                    }, 1000);
                } else {
                    alert('Errore: ' + response.message);
                    saveBtn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Salva e Genera Autocertificazione');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                let errorMsg = 'Si è verificato un errore durante il salvataggio.';
                alert(errorMsg + "\nDettagli: " + (jqXHR.responseJSON ? jqXHR.responseJSON.message : errorThrown));
                saveBtn.prop('disabled', false).html('<i class="fas fa-save mr-2"></i> Salva e Genera Autocertificazione');
            }
        });
    });
});
</script>

</body>
</html>