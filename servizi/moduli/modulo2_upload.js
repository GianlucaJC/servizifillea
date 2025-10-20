$(document).ready(function() {
    const formName = $('input[name="form_name"]').val();
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');
    const prestazione = params.get('prestazione');
    const userIdForUpload = params.get('user_id'); // Per la vista admin
    const isAdmin = !!userIdForUpload; // True se user_id è presente

    // Mappa delle prestazioni ai documenti richiesti (già presente in modulo2.php, la replichiamo qui per coerenza)
    const uploadRequirements = {
        'premio_matrimoniale': ['certificato_matrimonio', 'documento_identita'],
        'premio_giovani': ['documento_identita'],
        'bonus_nascita': ['certificato_nascita', 'documento_identita'],
        'donazioni_sangue': ['attestazione_donazione', 'documento_identita'],
        'contributo_affitto': ['contratto_affitto', 'documento_identita'],
        'contributo_sfratto': ['documentazione_sfratto', 'documento_identita'],
        'contributo_disabilita': ['certificazione_disabilita', 'documento_identita'],
        'post_licenziamento': ['lettera_licenziamento', 'documento_identita'],
        'permesso_soggiorno': ['ricevute_soggiorno', 'documento_identita'],
        'attivita_sportive': ['ricevuta_attivita_sportiva', 'documento_identita']
    };

    function updateUploadSections() {
        // Nascondi tutti i box di upload per sicurezza
        $('.upload-section-container').addClass('hidden');

        // Mostra i box corretti in base alla prestazione letta dall'URL
        const requiredDocs = uploadRequirements[prestazione];
        if (requiredDocs) {
            requiredDocs.forEach(docType => {
                $(`#container-for-${docType}`).removeClass('hidden');
            });
        }
    }
    // La logica di visualizzazione è già gestita in modulo2.php, quindi questa chiamata non è più necessaria
    // e potrebbe causare conflitti. La commentiamo.
    // updateUploadSections();

    // --- Logica di Upload ---
    $('.upload-box').on('dragover', function(e) { e.preventDefault(); e.stopPropagation(); $(this).addClass('dragover'); });
    $('.upload-box').on('dragleave', function(e) { e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragover'); });
    $('.upload-box').on('drop', function(e) {
        e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragover');
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) handleFiles(files, $(this).closest('.upload-section-container'));
    });
    $('.upload-box').on('click', function(e) {
        if ($(e.target).is('.upload-box, .upload-box p, .upload-box i, .upload-box span')) {
            $(this).find('.file-input').click();
        }
    });
    $('.file-input').on('change', function() {
        if (this.files.length > 0) handleFiles(this.files, $(this).closest('.upload-section-container'));
    });

    function handleFiles(files, section) {
        const docType = section.data('doc-type');
        const progressContainer = section.find('.progress-container');
        const progressBar = section.find('.progress-bar');
        const fileList = section.find('.file-list');

        const formData = new FormData();
        formData.append('form_name', formName);
        formData.append('document_type', docType);
        if (isAdmin) formData.append('user_id', userIdForUpload);
        else formData.append('token', token); // Aggiungi il token per l'utente normale
        
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        progressContainer.removeClass('hidden');
        progressBar.width('0%').text('0%');

        $.ajax({
            url: 'upload_handler.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        progressBar.width(percentComplete + '%').text(percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.status === 'success') {
                    response.files.forEach(file => {
                        const fileHtml = `
                            <div class="file-list-item" data-file-id="${file.id}">
                                <span class="truncate" title="${escapeHtml(file.original_filename)}"><i class="fas fa-file-alt text-gray-500 mr-2"></i>${escapeHtml(file.original_filename)}</span>
                                <div class="flex items-center space-x-4 ml-2 flex-shrink-0">
                                    <a href="view_file.php?id=${file.id}&token=${isAdmin ? '' : token}" target="_blank" class="text-blue-500 hover:text-blue-700" title="Visualizza file"><i class="fas fa-eye"></i></a>
                                    <button type="button" class="delete-file-btn text-red-500 hover:text-red-700" title="Elimina file"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>`;
                        fileList.append(fileHtml);
                    });
                } else {
                    alert('Errore: ' + response.message);
                }
                setTimeout(() => progressContainer.addClass('hidden'), 1000);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert('Errore durante l\'upload: ' + errorThrown);
                progressContainer.addClass('hidden');
            }
        });
    }

    // --- Logica Eliminazione File ---
    $('#upload-container').on('click', '.delete-file-btn', function() {
        const fileItem = $(this).closest('.file-list-item');
        const fileId = fileItem.data('file-id');

        if (!confirm('Sei sicuro di voler eliminare questo file?')) return;

        const deleteData = { 
            action: 'delete', 
            file_id: fileId 
        };
        // Aggiungi il token o l'user_id per l'autorizzazione
        if (isAdmin) { deleteData.user_id = userIdForUpload; }
        else { deleteData.token = token; }

        $.ajax({
            url: 'upload_handler.php',
            type: 'POST',
            data: deleteData,
            success: function(response) {
                if (response.status === 'success') {
                    fileItem.fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert('Errore: ' + response.message);
                }
            },
            error: function() {
                alert('Errore durante la comunicazione con il server.');
            }
        });
    });

    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        if (text == null) return '';
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});