$(document).ready(function() {
    const formName = $('input[name="form_name"]').val();
    const token = new URLSearchParams(window.location.search).get('token');
    const prestazione = new URLSearchParams(window.location.search).get('prestazione');

    // Mappa delle prestazioni e dei documenti richiesti
    const docMap = {
        'asili_nido': ['autocertificazione_famiglia', 'certificato_iscrizione_nido'],
        'centri_estivi': ['autocertificazione_famiglia', 'attestazione_spesa_centri_estivi'],
        'scuole_obbligo': ['autocertificazione_famiglia', 'autocertificazione_frequenza_obbligo'],
        'superiori_iscrizione': ['autocertificazione_famiglia', 'autocertificazione_frequenza_superiori'],
        'universita_iscrizione': ['autocertificazione_famiglia', 'documentazione_universita']
    };

    // Funzione per aggiornare la visibilità dei box di upload
    function updateUploadSections() {
        // Nascondi tutte le sezioni di upload
        $('.upload-section').addClass('hidden'); 

        // Mostra le sezioni per la prestazione selezionata dall'URL
        if (prestazione && docMap[prestazione]) {
            docMap[prestazione].forEach(docType => {
                $(`#upload-area-${docType}`).removeClass('hidden');
            });
        }
    }
    
    updateUploadSections();

    // --- Logica di Upload ---

    // Gestione Drag & Drop
    $('.upload-box').on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });

    $('.upload-box').on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });

    $('.upload-box').on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            handleFiles(files, $(this).closest('.upload-section'));
        }
    });

    // Gestione Click
    $('.upload-box').on('click', function(e) {
        // Controlla se l'elemento cliccato è esattamente l'upload-box
        // o un suo figlio diretto che non sia l'input stesso.
        // Questo previene il ciclo di recursione causato dall'evento click che "risale" dall'input.
        if ($(e.target).is('.upload-box, .upload-box p, .upload-box i, .upload-box span')) {
            $(this).find('.file-input').click();
        }
    });

    $('.file-input').on('change', function() {
        if (this.files.length > 0) {
            handleFiles(this.files, $(this).closest('.upload-section'));
        }
    });

    function handleFiles(files, section) {
        const docType = section.data('doc-type');
        const progressContainer = section.find('.progress-container');
        const progressBar = section.find('.progress-bar');
        const fileList = section.find('.file-list');

        // Prepara FormData
        const formData = new FormData();
        formData.append('form_name', formName);
        formData.append('token', token);
        formData.append('document_type', docType);
        
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        // Mostra la barra di progresso
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
                                    <a href="view_file.php?id=${file.id}&token=${token}" target="_blank" class="text-blue-500 hover:text-blue-700" title="Visualizza file">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="delete-file-btn text-red-500 hover:text-red-700" title="Elimina file"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        `;
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

        if (!confirm('Sei sicuro di voler eliminare questo file?')) {
            return;
        }

        $.ajax({
            url: 'upload_handler.php',
            type: 'POST',
            data: {
                action: 'delete',
                file_id: fileId,
                token: token
            },
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
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        // Se text è null o undefined, restituisci una stringa vuota
        if (text == null) {
            return '';
        }

        // Assicurati che text sia una stringa prima di chiamare replace
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});