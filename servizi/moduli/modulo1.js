$(document).ready(function() {

    // Funzione per validare il Codice Fiscale italiano
    function validaCodiceFiscale(cf) {
        if (!cf || cf.length !== 16) {
            return "Il codice fiscale deve essere di 16 caratteri.";
        }

        cf = cf.toUpperCase();

        if (!/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-EHLMPR-T][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/.test(cf)) {
            return "Il formato del codice fiscale non è valido.";
        }

        let sum = 0;
        const oddMap = {
            '0': 1, '1': 0, '2': 5, '3': 7, '4': 9, '5': 13, '6': 15, '7': 17, '8': 19, '9': 21,
            'A': 1, 'B': 0, 'C': 5, 'D': 7, 'E': 9, 'F': 13, 'G': 15, 'H': 17, 'I': 19, 'J': 21,
            'K': 2, 'L': 4, 'M': 18, 'N': 20, 'O': 11, 'P': 3, 'Q': 6, 'R': 8, 'S': 12, 'T': 14,
            'U': 16, 'V': 10, 'W': 22, 'X': 25, 'Y': 24, 'Z': 23
        };

        for (let i = 0; i < 15; i++) {
            const c = cf[i];
            if ((i + 1) % 2 === 0) { // Caratteri in posizione pari (2, 4, ...)
                if (c >= '0' && c <= '9') {
                    sum += parseInt(c, 10);
                } else {
                    sum += c.charCodeAt(0) - 'A'.charCodeAt(0);
                }
            } else { // Caratteri in posizione dispari (1, 3, ...)
                sum += oddMap[c];
            }
        }

        const expectedCheckDigit = String.fromCharCode('A'.charCodeAt(0) + (sum % 26));
        if (expectedCheckDigit !== cf[15]) {
            // Per debug, si potrebbe mostrare il carattere atteso
            // console.log(`Carattere di controllo calcolato: ${expectedCheckDigit}, carattere presente: ${cf[15]}`);
            return "Il codice fiscale non è valido (carattere di controllo errato).";
        }

        return true; // Valido
    }

    // Funzione per validare l'IBAN (formato italiano)
    function validaIBAN(iban) {
        if (!iban) return true; // Non obbligatorio, quindi valido se vuoto
        if (!/^IT\d{2}[A-Z]\d{10}[0-9A-Z]{12}$/i.test(iban)) {
            return "Il formato dell'IBAN non è valido. Deve iniziare con IT e avere 27 caratteri.";
        }
        return true;
    }

    // Funzione per mostrare/nascondere errori
    function toggleError(fieldId, message) {
        const errorElement = $(`#error-${fieldId}`);
        const fieldElement = $(`#${fieldId}`);
        
        if (message) {
            errorElement.text(message).removeClass('hidden');
            fieldElement.addClass('border-red-500 focus:border-red-500 focus:ring-red-200');
        } else {
            errorElement.text('').addClass('hidden');
            fieldElement.removeClass('border-red-500 focus:border-red-500 focus:ring-red-200');
        }
    }

    // Gestione della sottomissione del form
    $('#modulo1-form').on('submit', function(event) {        
        // Controlla se ci sono file in coda per l'upload.
        // La variabile `uploadQueue` è definita in modulo1_upload.js
        if (typeof uploadQueue !== 'undefined' && uploadQueue.length > 0) {
            event.preventDefault(); // Blocca l'invio del form
            // Avvia l'upload. La funzione `processQueue` si occuperà di inviare il form
            // una volta che tutti i file sono stati caricati.
            processQueue(); 
            return;
        }

        let isValid = true;

        // --- Validazione Campi Studente ---
        const cfStudente = $('#studente_codice_fiscale').val();
        if (cfStudente) { // Valida solo se il campo è compilato
            const cfStudenteResult = validaCodiceFiscale(cfStudente);
            if (cfStudenteResult !== true) {
                isValid = false;
                toggleError('studente_codice_fiscale', cfStudenteResult);
            } else {
                toggleError('studente_codice_fiscale', null);
            }
        } else {
            toggleError('studente_codice_fiscale', null); // Pulisci errore se vuoto
        }

        // --- Validazione Campi Lavoratore ---
        // (Aggiungere qui altre validazioni se necessario)

        // --- Validazione Dati Pagamento ---
        const iban = $('#iban').val();
        const ibanResult = validaIBAN(iban);
        if (ibanResult !== true) {
            isValid = false;
            toggleError('iban', ibanResult);
        } else {
            toggleError('iban', null);
        }

        // Se la validazione fallisce, blocca l'invio del form
        if (!isValid) {
            event.preventDefault();
            
            // Scrolla fino al primo errore per renderlo visibile
            const firstError = $('.border-red-500').first();
            if (firstError.length) {
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100 // -100 per dare spazio dalla barra superiore
                }, 500);
            }

            // Mostra un messaggio generale
            if (!$('#general-error-message').length) {
                $('#modulo1-form').prepend('<div id="general-error-message" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert"><p class="font-bold">Errore di validazione</p><p>Controlla i campi evidenziati in rosso.</p></div>');
            }
        } else {
            $('#general-error-message').remove();
        }
    });

    // Rimuovi l'errore mentre l'utente digita
    $('.form-input').on('input', function() {
        const fieldId = $(this).attr('id');
        toggleError(fieldId, null);
    });

    // --- Gestione Modale di Conferma ---
    const modal = $('#confirmation-modal');

    // Mostra la modale quando si clicca "Invia al funzionario"
    $('#submit-official-btn').on('click', function(event) {
        event.preventDefault(); // Impedisce l'invio immediato del form
        
        // Legge il campo nascosto per vedere se un funzionario è già stato assegnato.
        const isFunzionarioAssigned = $('#IDfunz').val() !== '';
        
        if (isFunzionarioAssigned) {
            $('#funzionario-selector-container').hide();
        } else {
            $('#funzionario-selector-container').show();
        }

        modal.removeClass('hidden');
        $('body').css('overflow', 'hidden'); // Blocca lo scroll della pagina di sfondo
    });

    // Nascondi la modale se si clicca "Annulla" o il tasto chiudi
    $('#modal-cancel-btn, #modal-close-btn').on('click', function() {
        modal.addClass('hidden');
        toggleError('id_funzionario_modal', null); // Pulisci eventuali errori
        $('body').css('overflow', 'auto'); // Ripristina lo scroll della pagina di sfondo
    });

    // Invia il form quando si clicca "Sì, invia" nella modale
    $('#modal-confirm-btn').on('click', function() {
        let funzionarioId = null;
        // Se il selettore è visibile, valida la scelta e prendi il valore
        if ($('#funzionario-selector-container').is(':visible')) { // Cerca il radio button selezionato
            funzionarioId = $('input[name="id_funzionario"]:checked').val();
            if (!funzionarioId) {
                toggleError('id_funzionario_modal', 'Per favore, seleziona un funzionario.');
                return; // Blocca l'invio
            }
            toggleError('id_funzionario_modal', null);
        } else {
            // Se non è visibile, il funzionario è già assegnato. Prendi il valore dal campo nascosto.
            funzionarioId = $('#IDfunz').val();
        }

        // Aggiungiamo un campo nascosto per assicurarci che l'azione corretta venga inviata
        // anche se la sottomissione è programmatica.
        if (!$('input[name="action"][value="submit_official"]').length) {
             $('<input>').attr({
                type: 'hidden',
                name: 'action',
                value: 'submit_official'
            }).appendTo('#modulo1-form');
        }

        // Rimuovi un eventuale campo 'id_funzionario' precedente per evitare duplicati
        $('#modulo1-form input[name="id_funzionario"]').remove();

        // Aggiungi il nuovo valore solo se ne abbiamo uno
        if (funzionarioId) {
            $('<input>').attr({ type: 'hidden', name: 'id_funzionario', value: funzionarioId }).appendTo('#modulo1-form');
        }

        $('body').css('overflow', 'auto'); // Ripristina lo scroll prima di inviare
        $('#modulo1-form').submit();
    });

});