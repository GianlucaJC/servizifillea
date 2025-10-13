document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('modulo2-form');
    const submitOfficialBtn = document.getElementById('submit-official-btn');
    const modalCancelBtn = document.getElementById('modal-cancel-btn');
    const modalCloseBtn = document.getElementById('modal-close-btn');

    // Funzione per mostrare/nascondere errori
    function toggleError(fieldId, message) {
        const errorElement = document.getElementById(`error-${fieldId}`);
        const fieldElement = document.getElementById(fieldId);
        if (!errorElement || !fieldElement) return;

        if (message) {
            errorElement.textContent = message;
            errorElement.classList.remove('hidden');
            fieldElement.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-200');
        } else {
            errorElement.textContent = '';
            errorElement.classList.add('hidden');
            fieldElement.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-200');
        }
    }

    // Funzione per validare il Codice Fiscale italiano
    function validaCodiceFiscale(cf) {
        // Il campo non è obbligatorio, quindi se è vuoto, è valido.
        if (!cf) {
            return true;
        }

        if (cf.length !== 16) {
            return "Il codice fiscale deve essere di 16 caratteri.";
        }

        cf = cf.toUpperCase();

        // Regex più precisa per la struttura del codice fiscale
        if (!/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-EHLMPR-T][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/.test(cf)) {
            return "Il formato del codice fiscale non è valido.";
        }

        return true; // Per ora solo controllo formale come richiesto
    }

    // --- Gestione Modale di Conferma Invio ---
    const confirmationModal = document.getElementById('confirmation-modal');
    const modalConfirmBtn = document.getElementById('modal-confirm-btn');

    if (submitOfficialBtn) {
        submitOfficialBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Eseguiamo la validazione manualmente prima di mostrare la modale.
            let isValid = true;
            const cfInput = document.getElementById('codice_fiscale');
            if (cfInput.value) {
                const cfResult = validaCodiceFiscale(cfInput.value);
                if (cfResult !== true) {
                    isValid = false;
                    toggleError('codice_fiscale', cfResult);
                } else {
                    toggleError('codice_fiscale', null);
                }
            }

            if (isValid) {
                if (confirmationModal) confirmationModal.classList.remove('hidden');
            } else {
                // Se non è valido, scrolla al campo con errore
                const firstErrorField = form.querySelector('.border-red-500');
                if (firstErrorField) firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    if (modalConfirmBtn) {
        modalConfirmBtn.addEventListener('click', function() {
            const hiddenActionInput = document.createElement('input');
            hiddenActionInput.type = 'hidden';
            hiddenActionInput.name = 'action';
            hiddenActionInput.value = 'submit_official';
            form.appendChild(hiddenActionInput);
            form.submit();
        });
    }

    function closeModal() {
        if (confirmationModal) confirmationModal.classList.add('hidden');
    }

    if (modalCancelBtn) modalCancelBtn.addEventListener('click', closeModal);
    if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);

    // Rimuovi l'errore mentre l'utente digita
    const cfInput = document.getElementById('codice_fiscale');
    if (cfInput) {
        cfInput.addEventListener('input', function() {
            // Converte in maiuscolo e rimuove caratteri non validi
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            const cfResult = validaCodiceFiscale(this.value);
            toggleError('codice_fiscale', cfResult === true ? null : cfResult);
        });
    }

    // --- Logica per la firma digitale ---
    const canvas = document.getElementById('signature-pad');
    const signatureImage = document.getElementById('signature-image');
    const signatureControls = document.getElementById('signature-controls');
    const helpText = document.getElementById('signature-help-text');
    let signaturePad = null;

    function initializeSignaturePad() {
        if (canvas && !signaturePad) {
            signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(249, 250, 251)' });
            resizeCanvas();
        }
    }

    function resizeCanvas() {
        if (!signaturePad) return;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        const data = signaturePad.toData();
        signaturePad.clear();
        signaturePad.fromData(data);
    }

    window.addEventListener("resize", resizeCanvas);

    if (signatureControls) {
        signatureControls.addEventListener('click', function(event) {
            const target = event.target.closest('button');
            if (!target) return;

            event.preventDefault();
            if (target.id === 'clear-signature') {
                if (!signaturePad) initializeSignaturePad();
                signaturePad.clear();
            }

            if (target.id === 'modify-signature') {
                signatureImage.classList.add('hidden');
                target.classList.add('hidden');
                $('#firma_data').val('');
                canvas.classList.remove('hidden');
                if(helpText) helpText.classList.remove('hidden');
                initializeSignaturePad();
                signaturePad.clear();
                signatureControls.innerHTML = '<button type="button" id="clear-signature" class="text-sm text-gray-600 hover:text-primary">Pulisci</button>';
            }
        });
    }

    if (canvas && !canvas.classList.contains('hidden')) {
        initializeSignaturePad();
    }

    if (form) {
        form.addEventListener('submit', function(event) {            
            // Controlla se ci sono file in coda per l'upload.
            // La variabile `uploadQueue` è definita in modulo2_upload.js
            if (typeof uploadQueue !== 'undefined' && uploadQueue.length > 0) {
                event.preventDefault(); // Blocca l'invio del form
                // Avvia l'upload. La funzione `processQueue` si occuperà di inviare il form
                // una volta che tutti i file sono stati caricati.
                processQueue();
                return;
            }
            let isValid = true;

            // Validazione Codice Fiscale (se compilato)
            const cfInput = document.getElementById('codice_fiscale');
            const cfValue = cfInput.value;
            if (cfValue) { // Valida solo se il campo non è vuoto
                const cfResult = validaCodiceFiscale(cfValue);
                if (cfResult !== true) {
                    isValid = false;
                    toggleError('codice_fiscale', cfResult);
                } else {
                    toggleError('codice_fiscale', null); // Pulisce l'errore se valido
                }
            } else {
                toggleError('codice_fiscale', null); // Pulisce l'errore se vuoto
            }

            if (!isValid) {
                event.preventDefault(); // Blocca il salvataggio/invio se il CF non è valido

                // Scrolla fino al primo campo con errore per attirare l'attenzione dell'utente.
                const firstErrorField = form.querySelector('.border-red-500');
                if (firstErrorField) {
                    firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                return;
            }

            if (signaturePad && !signaturePad.isEmpty()) {
                document.getElementById('firma_data').value = signaturePad.toDataURL('image/png');
            }
        });
    }
});