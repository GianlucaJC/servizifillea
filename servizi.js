$(document).ready(function() {
    const SPLASH_DURATION = 3000; // 3 secondi per lo splash screen
    const serviceGrid = $('#service-grid');
    const splashScreen = $('#splash-screen');
    const mainApp = $('#main-app');
    const toast = $('#toast-message'); // Riferimento al toast
    
    // Mappatura delle icone Font Awesome per i 13 servizi
    // NUOVE ICONE SELEZIONATE PER ESSERE PIÙ RAPPRESENTATIVE
    const iconMap = [
        'fa-hand-holding-dollar', // 1. Cassa Edile (Prestazioni/Pagamenti)
        'fa-receipt',             // 2. Fai il 730 (Documento Fiscale)
        'fa-briefcase-medical',   // 3. Sanedil (Medicina/Assistenza Sanitaria)
        'fa-piggy-bank',          // 4. Fondo Pensione (Risparmio/Banca)
        'fa-chart-line',          // 5. Estratto Contributivo INPS (Grafico/Storico)
        'fa-hourglass-half',      // 6. Calcola Pensione (Tempo/Scadenza)
        'fa-gavel',               // 7. Dimissioni / Contestazione (Legale/Giustizia)
        'fa-calendar-check',      // 8. Appuntamento Patronato (Calendario/Prenotazione)
        'fa-user-tie',            // 9. Appuntamento U.V.L. (Professionista/Consulente)
        'fa-solid fa-flag',       // 10. Riscatta Servizio Militare (Elmetto/Militare)
        'fa-solid fa-bullhorn',       // 11. Iniziative e Manifestazioni (Evento/Celebrazione)
        'fa-newspaper',           // 12. Novità Normative (Giornale/Notizie)
        'fa-headset'              // 13. Supporto Extra Rapido (Assistenza/Cuffie)
    ];


    const services = [
        "Chiedi prestazioni di cassa edile",
        "Fai il 730",
        "Chiedi le prestazioni di sanedil",
        "Fondo pensione contrattuale",
        "Chiedi l’estratto contributivo inps",
        "Calcola quando andrai in pensione",
        "Presenta le dimissioni rispondi ad una contestazione disciplinare",
        "Fissa appuntamento con il patronato per inps e inail",
        "Fissa appuntamento con u.v.l.",
        "Riscatta il servizio militare",
        "Iniziative e manifestazioni",
        "Vedi le novita’ normative",
        "Supporto Extra Rapido"
    ];
    
    if (services.length < 13) {
        // Se l'elenco fosse più corto, il 13° servizio verrebbe aggiunto automaticamente
    }


    // Funzione per creare la card di un servizio
    function createServiceCard(serviceName, index) {
        const iconClass = iconMap[index % iconMap.length]; // Usa % per ciclare le icone se ce ne sono meno dei servizi
        
        const card = $('<div></div>');
        card.addClass(`service-card p-6 bg-white rounded-xl shadow-lg border border-gray-100 flex flex-col items-center text-center`);
        card.attr('data-service', serviceName);
        
        // Icona (utilizza il tag <i> e la classe Font Awesome)
        const iconContainer = $('<div></div>');
        iconContainer.addClass('w-12 h-12 mb-4 p-3 rounded-full bg-primary/10 text-primary flex items-center justify-center'); 
        
        const icon = $('<i></i>');
        icon.addClass(`fa-solid ${iconClass} text-2xl`); 
        
        iconContainer.append(icon);
        
        // Titolo - MODIFICATO: da text-lg a text-base e colore a text-gray-700
        const title = $('<h3></h3>');
        title.addClass('text-base font-semibold text-gray-700');
        title.text(serviceName);

        card.append(iconContainer);
        card.append(title);
        
        // Gestore click
        card.on('click', function() {
            const selectedService = $(this).attr('data-service'); // Ottieni il nome del servizio
            const serviceId = index + 1; // L'ID del servizio (basato sull'indice 0-based)
            handleServiceSelection(serviceId, selectedService); // Chiama la funzione esterna
        });

        return card;
    }
    
    // Funzione Semplice di Notifica (Toast)
    function showToast(message, type = 'error', duration = 3000, style = 'toast') { // Aggiunto 'style'
        // 1. Rimuovi classi di stile e colore precedenti
        toast.removeClass('bg-primary bg-green-500 bg-yellow-500 toast-style modal-style');

        // 2. Applica lo stile corretto (toast o modale)
        if (style === 'modal') {
            toast.addClass('modal-style'); // Stile modale: grande e centrato
        } else {
            toast.addClass('toast-style'); // Stile toast: piccolo in alto a destra
        }

        // 3. Aggiungi la classe di colore corretta in base al tipo
        if (type === 'success') {
            toast.addClass('bg-green-500'); // Verde per successo
        } else if (type === 'warning') {
            toast.addClass('bg-yellow-500'); // Giallo per avviso
        }  else {
            toast.addClass('bg-primary'); // Rosso per errore
        }

        // 4. Aggiorna il contenuto del messaggio (ora con icona)
        toast.html(`<i class="fas fa-exclamation-circle mr-2"></i> ${message}`);
        
        // 5. Mostra il toast con animazione
        toast.addClass('toast-visible');
        
        // 6. Nascondi il toast dopo la durata specificata
        setTimeout(() => {
            toast.removeClass('toast-visible');
        }, duration);
    }
    
    // Funzione esterna per gestire la selezione del servizio
    function handleServiceSelection(serviceId, serviceName) {
        // Disabilita tutti i bottoni dei servizi
        $('.service-card').off('click').addClass('disabled').css('opacity', '0.6').css('cursor', 'not-allowed');

        // Riabilita i bottoni dopo un certo periodo (es. 5 secondi)
        setTimeout(() => {
            $('.service-card').on('click', function() {
                const clickedService = $(this).attr('data-service');
                const clickedIndex = services.indexOf(clickedService);
                handleServiceSelection(clickedIndex + 1, clickedService);
            }).removeClass('disabled').css('opacity', '1').css('cursor', 'pointer');
        }, 2000); // 5000 millisecondi = 5 secondi
        token=$("#token").val()
        if (token.length!=0) {
            console.log(`Servizio ID: ${serviceId}, Nome: ${serviceName} selezionato.`);
            showToast(`Accesso al servizio: ${serviceName} in corso`, 'success'); // Mostra il toast VERDE per successo

            // Se viene selezionato il primo servizio (Cassa Edile), reindirizza alla pagina dedicata.
            if (serviceId === 1) {
                setTimeout(() => {
                    window.location.href = `servizio_cassa_edile.php?token=${token}`;
                }, 1500); // Attendi 1.5s per dare tempo all'utente di leggere il toast.
            } else {
                // Per tutti gli altri servizi, mostra un avviso.
                showToast(`Il servizio "${serviceName}" non è ancora disponibile.`, 'warning');
            }
        } else {
            showToast(`Utente non riconosciuto, reindirizzato al Login...`, 'error', 5000, 'modal'); // Usa lo stile 'modal'
            setTimeout(() => {
                window.location.href = `login.php?redirect_service_id=${serviceId}`;
            }, 5000); // Reindirizza dopo 5 secondi
        }
    }

    // Inietta i 13 servizi nella griglia
    services.forEach((service, index) => {
        const card = createServiceCard(service, index);
        serviceGrid.append(card);
    });
    
    // 3. Logica di Transizione Splash Screen
    setTimeout(() => {
        // Dissolvi lo splash screen
        splashScreen.css('opacity', '0');
        splashScreen.css('pointer-events', 'none'); // Impedisce allo splash di intercettare i click durante la transizione
        
        // Dopo la dissolvenza (0.5s), nascondilo completamente e mostra l'app principale
        setTimeout(() => {
            splashScreen.addClass('hidden');
            mainApp.removeClass('hidden');
            
            // Rimuovi la classe 'hidden' solo dopo che lo splash è stato rimosso
            mainApp.css('opacity', '0');
            setTimeout(() => {
                mainApp.css('transition', 'opacity 0.5s ease-in');
                mainApp.css('opacity', '1');
            }, 50);


        }, 500); // Durata della transizione CSS
    }, SPLASH_DURATION); // Durata del timer
    
    // 4. Simulazione Registrazione Service Worker (PWA)
    // Registra il Service Worker per abilitare le funzionalità PWA (es. offline)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('service-worker.js')
            .then(registration => {
                console.log('SW registrato:', registration);
                return registration; // Restituisci l'oggetto registration per la catena successiva
            })
            .then(reg => {
                reg.addEventListener('updatefound', () => {
                    const newWorker = reg.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // Nuovo Service Worker installato. Ricarica la pagina per usarlo subito.
                            console.log('Nuovo Service Worker trovato, ricarico la pagina.');
                            window.location.reload();
                        }
                    });
                });
            })
            .catch(error => console.error('SW registrazione fallita:', error));
    }

    // 5. Logica per il menu utente
    const userMenuButton = $('#user-menu-button');
    const userMenu = $('#user-menu');

    if (userMenuButton.length) {
        userMenuButton.on('click', function(event) {
            event.stopPropagation(); // Impedisce che il click si propaghi al documento
            userMenu.toggleClass('hidden'); // Mostra/nasconde il menu
        });

        // Chiude il menu se si clicca fuori
        $(document).on('click', function(event) {
            // Controlla se il click non è sul bottone o sul menu stesso
            if (!$(event.target).closest('#user-menu-button').length && !$(event.target).closest('#user-menu').length) {
                userMenu.addClass('hidden'); // Nasconde il menu
            }
        });
    }

    // --- NUOVA LOGICA PER MODALI PWA E NOTIFICHE ---

    let deferredInstallPrompt = null;

    // 1. Cattura l'evento di installazione del browser
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault(); // Impedisce al browser di mostrare il suo banner
        deferredInstallPrompt = event;
        console.log('Evento beforeinstallprompt catturato.');
        // Mostra il nostro modale personalizzato dopo un ritardo
        setTimeout(showInstallModal, 5000); // 5 secondi di ritardo
    });

    // 2. Funzione per mostrare il BANNER di installazione al posto del modale
    function showInstallModal() {
        if (!deferredInstallPrompt) return;

        const installBanner = $('#install-banner');
        installBanner.removeClass('hidden');

        $('#install-confirm-btn').on('click', async () => {
            deferredInstallPrompt.prompt(); // Mostra il prompt di installazione del browser
            const { outcome } = await deferredInstallPrompt.userChoice;
            if (outcome === 'accepted') {
                console.log('Utente ha accettato l\'installazione');
                installBanner.addClass('hidden'); // Nascondi il banner dopo l'installazione
            }
            deferredInstallPrompt = null;
        });

        $('#install-cancel-btn').on('click', () => {
            installBanner.addClass('hidden'); // Nascondi il banner se l'utente clicca 'chiudi'
        });
    }

    // 3. Logica per il modale delle notifiche (mostrato solo se non già gestito)
    function showNotificationModal() {
        // Non mostrare se l'utente ha esplicitamente bloccato le notifiche
        if (Notification.permission === 'denied') return;

        // Controlla se esiste già una sottoscrizione attiva
        navigator.serviceWorker.ready.then(reg => {
            reg.pushManager.getSubscription().then(subscription => {
                if (subscription) {
                    // L'utente è già iscritto, non mostrare il modale.
                    return;
                }
                // Se non c'è sottoscrizione e i permessi non sono negati, mostra il modale.
                displayNotificationModal();
            });
        });
    }

    function displayNotificationModal() {

        const token = $("#token").val();
        if (!token) return; // Non mostrare se l'utente non è loggato

        const modalHTML = `
            <div id="notification-modal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center z-[1002]">
                <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-sm mx-4 text-center">
                    <i class="fas fa-bell text-4xl text-primary mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Rimani Aggiornato</h3>
                    <p class="text-gray-600 mb-6">Vuoi ricevere notifiche push per sapere quando lo stato delle tue pratiche cambia?</p>
                    <div class="flex justify-center space-x-4">
                        <button onclick="$('#notification-modal').remove()" class="py-2 px-4 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">No, grazie</button>
                        <a href="profilo.php?token=${token}" class="py-2 px-4 bg-primary text-white rounded-lg hover:bg-red-700">Sì, attiva</a>
                    </div>
                </div>
            </div>
        `;
        $('body').append(modalHTML);
    }

    // Mostra il modale delle notifiche dopo un ritardo maggiore
    setTimeout(showNotificationModal, 10000); // 10 secondi di ritardo

    // --- NUOVA LOGICA PER GESTIONE NOTIFICHE ---
    $('#notification-bell').on('click', function() {
        const badge = $('#notification-badge');
        const token = $("#token").val();

        if (badge.length > 0 && token) {
            // Chiamata AJAX per marcare le notifiche come lette
            $.post('mark_notifications_read.php', { token: token })
                .done(function(response) {
                    if (response.status === 'success') {
                        // Rimuovi il badge e nascondi l'area alert con un'animazione
                        badge.remove();
                        $('#notification-alerts-container').slideUp();
                    } else {
                        console.error('Errore nel marcare le notifiche come lette.');
                    }
                });
        }
    });
});