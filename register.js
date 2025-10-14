$(document).ready(function() {
    const form = $('.needs-validation');
    const verifyBtn = $('#verify-btn');
    const registerBtn = $('#register-btn');
    const passwordSection = $('#password-section');
    const divWait = $('#div_wait');
    const divResp = $('#div_resp');

    // Funzione per mostrare/nascondere la password
    $('.toggle-password').on('click', function() {
        const targetId = $(this).data('target');
        const targetInput = $('#' + targetId);
        const icon = $(this).find('i');

        if (targetInput.attr('type') === 'password') {
            targetInput.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            targetInput.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // --- GESTIONE VERIFICA (FASE 1) ---
    verifyBtn.on('click', function() {
        form.addClass('was-validated');
        
        // Validazione custom per email e codice fiscale
        if (!validateEmail() || !validateCodiceFiscale()) {
            return;
        }

        // Controlla se i campi base sono compilati
        let isInitialFormValid = true;
        form.find('input:not([type=password]), select').each(function() {
            if (!this.checkValidity()) {
                isInitialFormValid = false;
            }
        });

        if (!isInitialFormValid) {
            return;
        }

        // Se il form è valido, procedi con la chiamata AJAX
        const formData = form.serializeArray();
        formData.push({ name: 'action', value: 'verify' });

        divWait.show();
        divResp.html('').hide();
        passwordSection.hide();
        registerBtn.hide();

        $.ajax({
            url: 'C_register.php',
            type: 'POST',
            data: $.param(formData),
            dataType: 'json',
            success: function(response) {
                divWait.hide();
                if (response.header === 'OK') {
                    if (response.info && response.info.sindacato === '1') {
                        divResp.html('<div class="alert alert-success">Verifica superata! Sei un nostro iscritto. Per favore, crea una password per completare la registrazione.</div>').show();
                        passwordSection.show();
                        verifyBtn.hide();
                        registerBtn.show();
                    } else {
                        divResp.html('<div class="alert alert-warning">Non risulti attualmente iscritto al sindacato tramite i dati forniti. Non è possibile creare un account.</div>').show();
                    }
                } else {
                    divResp.html(`<div class="alert alert-danger">${response.message || 'Si è verificato un errore.'}</div>`).show();
                }
            },
            error: function() {
                divWait.hide();
                divResp.html('<div class="alert alert-danger">Errore di comunicazione con il server.</div>').show();
            }
        });
    });

    // --- GESTIONE REGISTRAZIONE (FASE 2) ---
    form.on('submit', function(event) {
        event.preventDefault(); // Impedisce l'invio tradizionale

        // Esegui la validazione completa, inclusa la password
        if (!validateFormOnSubmit()) {
            return;
        }

        const formData = form.serializeArray();
        formData.push({ name: 'action', value: 'register' });

        divWait.show();
        registerBtn.prop('disabled', true);

        $.ajax({
            url: 'C_register.php',
            type: 'POST',
            data: $.param(formData),
            dataType: 'json',
            success: function(response) {
                divWait.hide();
                if (response.header === 'OK') {
                    divResp.html(`<div class="alert alert-success">${response.message}</div>`).show();
                    passwordSection.hide();
                    registerBtn.hide();
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 3000);
                } else {
                    divResp.html(`<div class="alert alert-danger">${response.message || 'Errore durante la registrazione.'}</div>`).show();
                    registerBtn.prop('disabled', false);
                }
            },
            error: function() {
                divWait.hide();
                divResp.html('<div class="alert alert-danger">Errore di comunicazione con il server.</div>').show();
                registerBtn.prop('disabled', false);
            }
        });
    });

    // --- FUNZIONI DI VALIDAZIONE ---
    function validateEmail() {
        const email = $('#email').val();
        const email1 = $('#email1').val();
        if (email !== email1) {
            $('#email1')[0].setCustomValidity('Le email non coincidono.');
            return false;
        } else {
            $('#email1')[0].setCustomValidity('');
            return true;
        }
    }

    function validateCodiceFiscale() {
        const cf = $('#codfisc').val();
        if (cf.length > 0 && !validaCodiceFiscale(cf)) {
            $('#codfisc')[0].setCustomValidity('Codice fiscale non valido.');
            return false;
        } else {
            $('#codfisc')[0].setCustomValidity('');
            return true;
        }
    }

    function validatePassword() {
        const password = $('#password').val();
        const passwordConfirm = $('#password_confirm').val();
        if (password.length > 0 && password !== passwordConfirm) {
            $('#password_confirm')[0].setCustomValidity('Le password non coincidono.');
            return false;
        } else {
            $('#password_confirm')[0].setCustomValidity('');
            return true;
        }
    }

    function validateFormOnSubmit() {
        form.addClass('was-validated');
        const isEmailValid = validateEmail();
        const isCfValid = validateCodiceFiscale();
        const isPasswordValid = validatePassword();
        return form[0].checkValidity() && isEmailValid && isCfValid && isPasswordValid;
    }
});