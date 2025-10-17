<?php
session_start();

$token = $_SESSION['user_token'] ?? ($_GET['token'] ?? null);
if (isset($_GET['token'])) { $_SESSION['user_token'] = $_GET['token']; }

$form_name_origin = $_GET['origin_form_name'] ?? null;
$prestazione_origin = $_GET['origin_prestazione'] ?? null;
$origin_module = $_GET['origin_module'] ?? null;

include_once("../../database.php");
$pdo1 = Database::getInstance('fillea');

$user_id = null;
$user_info = [];
if ($token) {
    $stmt_user = $pdo1->prepare("SELECT id, nome, cognome FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW()");
    $stmt_user->execute([$token]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $user_id = $user['id'];
        $user_info = $user;
    }
}

if (!$user_id) {
    // Invece di reindirizzare, mostra un messaggio di errore all'interno della modale.
    // Questo perché la pagina è caricata in un iframe.
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Errore di Autenticazione</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 flex items-center justify-center h-screen">
        <div class="text-center p-8 bg-white rounded-lg shadow-md max-w-md mx-auto">
            <h1 class="text-2xl font-bold text-red-700 mb-4">Sessione Scaduta o Non Valida</h1>
            <p class="text-gray-700">La tua sessione di lavoro non è più valida.</p>
            <p class="text-gray-600 mt-2">Per favore, chiudi questa finestra e accedi nuovamente dalla pagina principale per continuare.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --- Logica di pre-caricamento dati da file JSON ---
$saved_data = [];
if ($form_name_origin) {
    $data_dir = __DIR__ . '/autocertificazioni_data/';
    $json_filename = 'autocert_' . $form_name_origin . '.json';
    $json_filepath = $data_dir . $json_filename;

    if (file_exists($json_filepath)) {
        $json_content = file_get_contents($json_filepath);
        $decoded_data = json_decode($json_content, true);
        if (is_array($decoded_data)) {
            $saved_data = $decoded_data;
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .form-section { background-color: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); margin-bottom: 2rem; }
        .form-section-title { font-size: 1.25rem; font-weight: 700; border-bottom: 2px solid #d1d5db; padding-bottom: 0.75rem; margin-bottom: 1.5rem; }
        .form-label { font-weight: 600; color: #4b5563; }
        .form-input { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; }
        .form-input:focus { outline: none; border-color: #d0112b; box-shadow: 0 0 0 2px rgba(208, 17, 43, 0.2); }
        .btn-primary { background-color: #d0112b; color: white; }
        .btn-primary:hover { background-color: #a80d22; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-secondary:hover { background-color: #5a6268; }
    </style>
</head>
<body class="bg-gray-50">

<div class="container mx-auto p-4 md:p-8 max-w-4xl">
    <header class="text-center mb-8">
        <h1 class="text-3xl md:text-4xl font-bold text-red-700">Autocertificazione Stato di Famiglia</h1>
        <p class="text-lg text-gray-600 mt-2">DICHIARAZIONE SOSTITUTIVA DI CERTIFICAZIONI</p>
        <p class="text-xs text-gray-500">(art.2 L. 15/68 come modificato dall’art. 3 Legge 15.5.97, n.127 ed integrato dall’ art. 1 DPR 403/1998 e succ.)</p>
    </header>

    <form id="stato-famiglia-form" action="modulo_autocertificazione_stato_famiglia_save.php" method="POST">
        <input type="hidden" name="token" value="<?php e($token); ?>">
        <input type="hidden" name="origin_form_name" value="<?php e($form_name_origin); ?>">
        <input type="hidden" name="origin_prestazione" value="<?php e($prestazione_origin); ?>">
        <input type="hidden" name="origin_module" value="<?php e($origin_module); ?>">

        <!-- Dati Dichiarante -->
        <div class="form-section">
            <h2 class="form-section-title">Dati del Dichiarante</h2>
            <p class="text-sm text-gray-600 mb-4">Da compilare a cura dei figli dei lavoratori se maggiorenni, oppure a cura dei lavoratori nel caso di figli minorenni.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="sottoscrittore_nome_cognome" class="form-label">Io sottoscritto/a (Nome e Cognome)</label>
                    <input type="text" id="sottoscrittore_nome_cognome" name="sottoscrittore_nome_cognome" class="form-input" value="<?php e($saved_data['sottoscrittore_nome_cognome'] ?? ($user_info['cognome'] . ' ' . $user_info['nome'])); ?>">
                </div>
                <div>
                    <label for="sottoscrittore_luogo_nascita" class="form-label">Nato/a a (Luogo di nascita)</label>
                    <input type="text" id="sottoscrittore_luogo_nascita" name="sottoscrittore_luogo_nascita" class="form-input" value="<?php e($saved_data['sottoscrittore_luogo_nascita'] ?? ''); ?>">
                </div>
                <div>
                    <label for="sottoscrittore_data_nascita" class="form-label">Il (Data di nascita)</label>
                    <input type="date" id="sottoscrittore_data_nascita" name="sottoscrittore_data_nascita" class="form-input" value="<?php e($saved_data['sottoscrittore_data_nascita'] ?? ''); ?>">
                </div>
                <div>
                    <label for="sottoscrittore_residenza_comune" class="form-label">Residente a (Comune)</label>
                    <input type="text" id="sottoscrittore_residenza_comune" name="sottoscrittore_residenza_comune" class="form-input" value="<?php e($saved_data['sottoscrittore_residenza_comune'] ?? ''); ?>">
                </div>
                <div>
                    <label for="sottoscrittore_residenza_indirizzo" class="form-label">In (Via/Piazza)</label>
                    <input type="text" id="sottoscrittore_residenza_indirizzo" name="sottoscrittore_residenza_indirizzo" class="form-input" value="<?php e($saved_data['sottoscrittore_residenza_indirizzo'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Composizione Nucleo Familiare -->
        <div class="form-section">
            <h2 class="form-section-title">Composizione del Nucleo Familiare</h2>
            <p class="text-sm text-gray-600 mb-4">Consapevole delle responsabilità penali in caso di false dichiarazioni, dichiaro che la famiglia anagrafica è composta dalle seguenti persone:</p>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-2 px-4 text-left text-sm font-semibold text-gray-600">Cognome e Nome</th>
                            <th class="py-2 px-4 text-left text-sm font-semibold text-gray-600">Data di Nascita</th>
                            <th class="py-2 px-4 text-left text-sm font-semibold text-gray-600">Luogo di Nascita</th>
                            <th class="py-2 px-4 text-left text-sm font-semibold text-gray-600">Rapporto di Parentela</th>
                            <th class="py-2 px-4 text-center text-sm font-semibold text-gray-600">Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="membri-famiglia-tbody">
                        <!-- Le righe verranno aggiunte qui da JS -->
                    </tbody>
                </table>
            </div>
            <button type="button" id="add-member-btn" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                <i class="fas fa-plus mr-2"></i> Aggiungi Membro
            </button>
        </div>

        <!-- Dichiarazioni e Firma -->
        <div class="form-section">
            <h2 class="form-section-title">Dichiarazioni e Firma</h2>
            <p class="text-sm text-gray-700 mb-6">Dichiaro altresì, in caso di false attestazioni, di impegnarmi a restituire le somme illecitamente percepite, autorizzando la Cassa a trattenere dette somme dalle eventuali altre spettanze a me dovute.</p>
            
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
                    <label class="form-label">Firma</label>
                    <div id="signature-container" class="w-full mt-2 border border-gray-300 rounded-lg relative">
                        <?php $has_signature = !empty($saved_data['firma_data']); ?>
                        <img id="signature-image" src="<?php echo $has_signature ? $saved_data['firma_data'] : ''; ?>" alt="Firma salvata" class="w-full h-auto <?php if (!$has_signature) echo 'hidden'; ?>">
                        <canvas id="signature-pad" class="w-full h-48 <?php if ($has_signature) echo 'hidden'; ?>"></canvas>
                    </div>
                    <div id="signature-controls" class="flex justify-end mt-2 space-x-4">
                        <?php if ($has_signature): ?>
                            <button type="button" id="modify-signature" class="text-sm text-blue-600 hover:text-blue-800 font-semibold"><i class="fas fa-pencil-alt mr-1"></i> Modifica Firma</button>
                        <?php else: ?>
                            <button type="button" id="undo-signature" class="text-sm text-gray-600 hover:text-red-700">Annulla tratto</button>
                            <button type="button" id="clear-signature" class="text-sm text-gray-600 hover:text-red-700">Pulisci</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" name="firma_data" id="firma_data" value="<?php e($saved_data['firma_data'] ?? ''); ?>">
        <input type="hidden" name="membri_famiglia_json" id="membri_famiglia_json">

        <div class="mt-8 flex justify-between items-center">
            <button type="button" id="close-modal-btn" class="btn-secondary font-bold py-3 px-6 rounded-lg">
                <i class="fas fa-times mr-2"></i> Chiudi
            </button>
            <button type="submit" id="save-btn" class="btn-primary font-bold py-3 px-6 rounded-lg shadow-lg">
                <i class="fas fa-save mr-2"></i> Salva Dati
            </button>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Logica per chiudere la modale
    document.getElementById('close-modal-btn').addEventListener('click', function() {
        // Cerca la modale nella finestra genitore e la chiude
        window.parent.document.getElementById('autocert-modal').classList.add('hidden');
    });

    // Funzione per aggiungere una riga alla tabella (modificata per accettare dati)
    function addMemberRow(data = {}) {
        const tbody = document.getElementById('membri-famiglia-tbody');
        const memberIndex = tbody.querySelectorAll('tr').length;
        const row = document.createElement('tr');
        row.classList.add('border-b');
        row.innerHTML = `
            <td class="py-2 px-4"><input type="text" name="membri[${memberIndex}][nome_cognome]" class="form-input text-sm p-1" value="${data.nome_cognome || ''}"></td>
            <td class="py-2 px-4"><input type="date" name="membri[${memberIndex}][data_nascita]" class="form-input text-sm p-1" value="${data.data_nascita || ''}"></td>
            <td class="py-2 px-4"><input type="text" name="membri[${memberIndex}][luogo_nascita]" class="form-input text-sm p-1" value="${data.luogo_nascita || ''}"></td>
            <td class="py-2 px-4"><input type="text" name="membri[${memberIndex}][parentela]" class="form-input text-sm p-1" value="${data.parentela || ''}"></td>
            <td class="py-2 px-4 text-center">
                <button type="button" class="text-red-500 hover:text-red-700 remove-member-btn"><i class="fas fa-trash"></i></button>
            </td>
        `;
        tbody.appendChild(row);
    }

    // Imposta data odierna
    const dataFirmaInput = document.getElementById('data_firma');
    if (!dataFirmaInput.value) {
        dataFirmaInput.value = new Date().toISOString().split('T')[0];
    }

    // Popola la tabella dei membri della famiglia con i dati pre-caricati
    const membriPrecaricati = <?php echo json_encode($saved_data['membri_famiglia'] ?? []); ?>;
    if (membriPrecaricati.length > 0) {
        const tbody = document.getElementById('membri-famiglia-tbody');
        tbody.innerHTML = ''; // Pulisce eventuali righe vuote
        membriPrecaricati.forEach(membro => {
            addMemberRow(membro);
        });
    }
    // Logica tabella dinamica
    const addMemberBtn = document.getElementById('add-member-btn');
    const tbody = document.getElementById('membri-famiglia-tbody');

    addMemberBtn.addEventListener('click', function() {
        addMemberRow(); // Chiama la funzione senza dati per aggiungere una riga vuota
    });

    tbody.addEventListener('click', function(e) {
        if (e.target.closest('.remove-member-btn')) {
            e.target.closest('tr').remove();
        }
    });

    // Logica Firma
    const canvas = document.getElementById('signature-pad');
    const signatureImage = document.getElementById('signature-image');
    const signatureControls = document.getElementById('signature-controls');
    const signaturePad = new SignaturePad(canvas, {
        penColor: 'blue'
    });
    // Se il canvas è visibile, ridimensionalo subito
    if (!canvas.classList.contains('hidden')) resizeCanvas();

    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        signaturePad.clear();
    }
    window.addEventListener("resize", resizeCanvas);
    resizeCanvas();
    
    signatureControls.addEventListener('click', function(event) {
        const target = event.target.closest('button');
        if (!target) return;

        if (target.id === 'clear-signature') {
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
            target.style.display = 'none';

            // Svuota il campo nascosto per cancellare la vecchia firma al salvataggio
            $('#firma_data').val('');

            // Mostra il canvas
            canvas.classList.remove('hidden');
            resizeCanvas(); // Ridimensiona e pulisce il canvas

            // Mostra i controlli per il disegno
            signatureControls.innerHTML = `
                <button type="button" id="undo-signature" class="text-sm text-gray-600 hover:text-red-700">Annulla tratto</button>
                <button type="button" id="clear-signature" class="text-sm text-gray-600 hover:text-red-700">Pulisci</button>`;
        }
    });

    // Logica di salvataggio
    const form = document.getElementById('stato-famiglia-form');
    form.addEventListener('submit', function(e) { 
        e.preventDefault(); // Impedisce l'invio tradizionale del form
        const saveBtn = document.getElementById('save-btn');
        const originalBtnContent = saveBtn.innerHTML;

        // 1. Salva la firma (se il canvas è stato usato)
        if (!signaturePad.isEmpty()) { document.getElementById('firma_data').value = signaturePad.toDataURL('image/png'); }

        // 2. Serializza i dati della tabella in JSON
        const membri = [];
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(row => {
            const inputs = row.querySelectorAll('input');
            const membro = {
                nome_cognome: inputs[0].value,
                data_nascita: inputs[1].value,
                luogo_nascita: inputs[2].value,
                parentela: inputs[3].value
            };
            // Aggiungi solo se la riga non è completamente vuota
            if (membro.nome_cognome || membro.data_nascita || membro.luogo_nascita || membro.parentela) {
                membri.push(membro);
            }
        });
        document.getElementById('membri_famiglia_json').value = JSON.stringify(membri);

        // Feedback visivo e disabilitazione del pulsante
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Salvataggio in corso...';

        // 3. Invia i dati tramite AJAX
        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Chiudi la modale e mostra una notifica toast sulla pagina principale
                const parentModal = window.parent.document.getElementById('autocert-modal');
                if (parentModal) parentModal.classList.add('hidden');
                window.parent.showToast('Autocertificazione salvata con successo!');
            } else {
                alert('Errore: ' + data.message);
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalBtnContent;
            }
        })
        .catch(error => {
            console.error('Errore AJAX:', error);
            alert('Si è verificato un errore di comunicazione. Riprova.');
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnContent;
        });
    });
});
</script>

</body>
</html>