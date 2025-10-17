document.addEventListener('DOMContentLoaded', function() {
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
                // backgroundColor rimosso per rendere il canvas trasparente
            });
            window.signaturePadInstance = signaturePad; // Assegna all'istanza globale
            resizeCanvas();
        }
    }

    // Funzione per ridimensionare il canvas mantenendo il contenuto
    function resizeCanvas() { 
        if (!signaturePad) return;

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
    if (signatureControls) {
        signatureControls.addEventListener('click', function(event) {
            const target = event.target.closest('button');
            if (!target) return;

            if (target.id === 'clear-signature') {
                // Assicurati che il pad sia inizializzato prima di pulirlo
                if (!signaturePad) initializeSignaturePad();
                event.preventDefault();
                signaturePad.clear();
            }

            if (target.id === 'modify-signature') {
                event.preventDefault();
                // Nascondi l'immagine e il bottone "Modifica"
                signatureImage.classList.add('hidden');
                target.classList.add('hidden');

                // Svuota il campo nascosto per cancellare la vecchia firma al salvataggio
                document.getElementById('firma_data').value = '';

                // Mostra il canvas e il testo di aiuto
                canvas.classList.remove('hidden');
                if (helpText) helpText.classList.remove('hidden');

                // Inizializza il pad e puliscilo
                initializeSignaturePad();
                signaturePad.clear();

                // Crea e mostra il pulsante "Pulisci"
                const clearButtonHTML = '<button type="button" id="clear-signature" class="text-sm text-gray-600 hover:text-primary">Pulisci</button>';
                signatureControls.innerHTML = clearButtonHTML;
            }
        });
    }

    // Se il canvas è visibile al caricamento della pagina, inizializza subito il pad.
    if (canvas && !canvas.classList.contains('hidden')) {
        initializeSignaturePad();
    }
    
    // Prima di inviare il form, salva la firma nel campo nascosto
    const form = canvas ? canvas.closest('form') : null;
    if (form) {
        form.addEventListener('submit', function() {
            if (signaturePad && !signaturePad.isEmpty()) {
                const signatureData = signaturePad.toDataURL('image/png');
                document.getElementById('firma_data').value = signatureData;
            }
        });
    }
});