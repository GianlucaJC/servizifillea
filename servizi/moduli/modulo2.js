document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('modulo2-form');
    const ibanInput = document.getElementById('iban');
    const cfStudenteInput = document.getElementById('codice_fiscale');
    const submitOfficialBtn = document.getElementById('submit-official-btn');
    const confirmationModal = document.getElementById('confirmation-modal');
    const modalConfirmBtn = document.getElementById('modal-confirm-btn');
    const modalCancelBtn = document.getElementById('modal-cancel-btn');
    const modalCloseBtn = document.getElementById('modal-close-btn');

    // --- Validazione IBAN ---
    if (ibanInput) {
        ibanInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            validateIBAN(this.value);
        });
    }

    function validateIBAN(iban) {
        const errorEl = document.getElementById('error-iban');
        if (!iban) {
            errorEl.classList.add('hidden');
            return;
        }
        if (iban.length !== 27 || !/^[A-Z]{2}[0-9]{2}[A-Z]{1}[0-9]{22}$/.test(iban)) {
            errorEl.textContent = 'Formato IBAN non valido. Deve essere di 27 caratteri (es. IT60X0542811101000000123456).';
            errorEl.classList.remove('hidden');
        } else {
            errorEl.classList.add('hidden');
        }
    }

    // --- Validazione Codice Fiscale ---
    if (cfStudenteInput) {
        cfStudenteInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            validateCF(this.value, 'studente_codice_fiscale');
        });
    }

    function validateCF(cf, elementId) {
        const errorEl = document.getElementById('error-' + elementId);
        if (!cf) {
            errorEl.classList.add('hidden');
            return;
        }
        if (!/^[A-Z]{6}[0-9]{2}[A-Z]{1}[0-9]{2}[A-Z]{1}[0-9]{3}[A-Z]{1}$/.test(cf)) {
            errorEl.textContent = 'Formato Codice Fiscale non valido.';
            errorEl.classList.remove('hidden');
        } else {
            errorEl.classList.add('hidden');
        }
    }

    // --- Gestione Modale di Conferma Invio ---
    if (submitOfficialBtn) {
        submitOfficialBtn.addEventListener('click', function(e) {
            e.preventDefault();
            confirmationModal.classList.remove('hidden');
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
        form.addEventListener('submit', function() {
            if (signaturePad && !signaturePad.isEmpty()) {
                document.getElementById('firma_data').value = signaturePad.toDataURL('image/png');
            }
        });
    }
});