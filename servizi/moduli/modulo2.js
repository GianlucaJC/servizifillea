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
                if (confirmationModal) {
                    // Legge il campo nascosto per vedere se un funzionario è già stato assegnato.
                    const isFunzionarioAssigned = document.getElementById('IDfunz').value !== '';
                    const funzionarioSelectorContainer = document.getElementById('funzionario-selector-container');
                    
                    funzionarioSelectorContainer.style.display = isFunzionarioAssigned ? 'none' : 'block';

                    confirmationModal.classList.remove('hidden');
                }
            } else {
                // Se non è valido, scrolla al campo con errore
                const firstErrorField = form.querySelector('.border-red-500');
                if (firstErrorField) firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    if (modalConfirmBtn) {
        modalConfirmBtn.addEventListener('click', function() {
            let isValid = true;
            let funzionarioId = null;

            const funzionarioSelectorContainer = document.getElementById('funzionario-selector-container');
            const funzionarioModalSelect = document.getElementById('id_funzionario_modal');

            // Se il selettore è visibile, valida la scelta
            if (funzionarioSelectorContainer && window.getComputedStyle(funzionarioSelectorContainer).display !== 'none') {
                funzionarioId = funzionarioModalSelect.value;
                if (!funzionarioId) {
                    toggleError('id_funzionario_modal', 'Per favore, seleziona un funzionario.');
                    isValid = false;
                } else {
                    toggleError('id_funzionario_modal', null);
                }
            } else {
                // Se non è visibile, il funzionario è già assegnato. Prendi il valore dal campo nascosto.
                funzionarioId = document.getElementById('IDfunz').value;
            }

            if (isValid) {
                // Rimuovi eventuali campi nascosti precedenti per evitare duplicati
                form.querySelectorAll('input[name="action"], input[name="id_funzionario"]').forEach(el => el.remove());

                // Aggiungi l'azione di invio
                const hiddenActionInput = document.createElement('input');
                hiddenActionInput.type = 'hidden';
                hiddenActionInput.name = 'action';
                hiddenActionInput.value = 'submit_official';
                form.appendChild(hiddenActionInput);

                // Aggiungi l'ID del funzionario
                const hiddenFunzionarioInput = document.createElement('input');
                hiddenFunzionarioInput.type = 'hidden';
                hiddenFunzionarioInput.name = 'id_funzionario';
                hiddenFunzionarioInput.value = funzionarioId;
                form.appendChild(hiddenFunzionarioInput);

                form.submit();
            }
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

        });
    }
});