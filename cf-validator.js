/**
 * Funzione per la validazione formale del Codice Fiscale italiano.
 * @param {string} cf Il codice fiscale da validare.
 * @returns {boolean} True se il codice fiscale è formalmente valido, altrimenti false.
 */
function validaCodiceFiscale(cf) {
    if (!cf || cf.length !== 16) {
        return false;
    }

    cf = cf.toUpperCase();

    if (!/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-EHLMPR-T][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/.test(cf)) {
        return false;
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
        if ((i + 1) % 2 === 0) { // Caratteri in posizione pari
            if (c >= '0' && c <= '9') {
                sum += parseInt(c, 10);
            } else {
                sum += c.charCodeAt(0) - 'A'.charCodeAt(0);
            }
        } else { // Caratteri in posizione dispari
            sum += oddMap[c];
        }
    }

    const expectedCheckDigit = String.fromCharCode('A'.charCodeAt(0) + (sum % 26));
    
    return expectedCheckDigit === cf[15];
}

// Aggiunge la validazione al campo del codice fiscale
document.addEventListener('DOMContentLoaded', function() {
    const codfiscInput = document.getElementById('codfisc');
    if (codfiscInput) {
        codfiscInput.addEventListener('input', function() {
            const isValid = validaCodiceFiscale(this.value);
            this.setCustomValidity(isValid || this.value.length === 0 ? '' : 'Codice fiscale non valido.');
        });
    }
});