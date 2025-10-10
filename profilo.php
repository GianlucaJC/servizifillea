<?php
session_start();

// 1. Recupera il token dall'URL
$token = $_GET['token'] ?? '';
$user_data = null;
$funzionario_info = "Non specificato";

include_once("push_config.php"); // Includi per accedere alla chiave pubblica VAPID
// 2. Se il token è presente, verifica la sua validità e recupera i dati dell'utente
if (!empty($token)) {
    // Includiamo C_register.php per avere accesso all'array dei funzionari
    include_once("database.php");
    // Otteniamo l'istanza del database 'fillea'
    $pdo1 = Database::getInstance('fillea');

    // Query per recuperare i dati dell'utente e del funzionario associato con un token valido e non scaduto
    $sql = "SELECT 
                u.nome, u.cognome, u.email, u.telefono, u.data_nascita, u.codfisc, u.settore,
                f.funzionario, f.zona
            FROM `fillea-app`.users AS u
            LEFT JOIN `fillea-app`.funzionari AS f ON u.id_funzionario = f.id
            WHERE u.token = ? AND u.token_expiry > NOW()
            LIMIT 1";
    $stmt = $pdo1->prepare($sql);
    $stmt->execute([$token]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se l'utente è stato trovato e ha un funzionario, formatta la stringa
    if ($user_data) {
        if (!empty($user_data['funzionario'])) {
            $funzionario_info = $user_data['funzionario'] . ' (' . $user_data['zona'] . ')';
        }
    }

    // Salva il token nella sessione per usarlo negli script WebAuthn
    $_SESSION['user_token'] = $token;

    $pdo1 = null; // Chiudi la connessione
}

// 3. Se non ci sono dati utente (token mancante, invalido o scaduto), reindirizza al login
if (!$user_data) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Il Tuo Profilo</title>
    <link rel="icon" href="https://placehold.co/32x32/d0112b/ffffff?text=P">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .profile-card {
            max-width: 800px;
            background-color: #fff;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        .form-control[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        .profile-header {
            color: #d0112b;
        }
    </style>
</head>
<body>

<div class="container profile-card">
    <div class="mb-4">
        <a href="servizi.php?token=<?php echo htmlspecialchars($token); ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Torna ai Servizi
        </a>
    </div>
    <div class="text-center mb-4">
        <i class="fas fa-user-circle fa-4x profile-header"></i>
        <h2 class="mt-3">Profilo Utente</h2>
        <p class="text-muted">Ecco i dati della tua registrazione.</p>
    </div>

    <form>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nome</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['nome']); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Cognome</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['cognome']); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Telefono</label>
                <input type="tel" class="form-control" value="<?php echo htmlspecialchars($user_data['telefono']); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Data di Nascita</label>
                <input type="date" class="form-control" value="<?php echo htmlspecialchars($user_data['data_nascita']); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Codice Fiscale</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['codfisc']); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Settore</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst($user_data['settore'])); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Funzionario di Riferimento</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($funzionario_info); ?>" readonly>
            </div>
        </div>

    </form>
</div>
<!-- Sezione per la gestione della sicurezza -->
<div class="container profile-card" id="security-section" style="display: none;">
    <div class="text-center mb-4">
        <i class="fas fa-fingerprint fa-4x profile-header"></i>
        <h2 class="mt-3">Accesso Biometrico</h2>
        <p class="text-muted">Abilita l'accesso con impronta digitale o riconoscimento facciale su questo dispositivo.</p>
    </div>
    <div class="d-grid">
        <button id="register-device-btn" class="btn btn-dark">Abilita accesso con impronta su questo dispositivo</button>
    </div>
</div>

<!-- Sezione per le notifiche push -->
<div class="container profile-card" id="push-section">
    <div class="text-center mb-4">
        <i class="fas fa-bell fa-4x profile-header"></i>
        <h2 class="mt-3">Notifiche Push</h2>
        <p class="text-muted">Ricevi aggiornamenti in tempo reale sullo stato delle tue pratiche.</p>
    </div>
    <div class="d-grid">
        <div class="d-grid gap-2">
            <button id="manage-push-btn" class="btn btn-primary" disabled>
                <i class="fas fa-spinner fa-spin me-2"></i>Caricamento...
            </button>
            <button id="simulate-push-btn" class="btn btn-info" disabled><i class="fas fa-paper-plane me-2"></i>Simula Ricezione Notifica</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="webauthn_helpers.js"></script>
</body>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        /*
         * --- REGISTRAZIONE BIOMETRICA TEMPORANEAMENTE DISABILITATA ---
         * // Mostra la sezione di sicurezza solo se WebAuthn è supportato
         * if (window.PublicKeyCredential) {
         *     document.getElementById('security-section').style.display = 'block';
         * }
        */

        $('#register-device-btn').on('click', async function() {
            try {
                // 1. Richiedi le opzioni di creazione della credenziale al server
                const response = await fetch('webauthn_register_start.php');
                let options = await response.json();

                if (options.error) {
                    throw new Error(options.error);
                }

                // 2. (CORREZIONE CRITICA) Converti i campi necessari da Base64URL a ArrayBuffer.
                // A differenza di navigator.credentials.get(), il metodo navigator.credentials.create()
                // richiede esplicitamente che 'challenge', 'user.id' e gli ID delle credenziali
                // da escludere siano in formato ArrayBuffer.
                options.challenge = base64UrlToBuffer(options.challenge);
                options.user.id = base64UrlToBuffer(options.user.id);
                // CRITICO: Esegui il ciclo solo se l'array excludeCredentials esiste e non è vuoto.
                if (options.excludeCredentials && options.excludeCredentials.length > 0) {
                    options.excludeCredentials.forEach(cred => cred.id = base64UrlToBuffer(cred.id));
                }


                // 3. Chiedi al browser di creare una nuova credenziale
                const credential = await navigator.credentials.create({
                    publicKey: options
                });

                // 4. Invia la nuova credenziale al server per la verifica e il salvataggio
                const verificationResponse = await fetch('webauthn_register_finish.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: credential.id,
                        rawId: bufferToBase64Url(credential.rawId),
                        type: credential.type,
                        response: {
                            clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                            attestationObject: bufferToBase64Url(credential.response.attestationObject),
                        },
                        // Invia un nome descrittivo per il dispositivo (opzionale)
                        deviceName: `Dispositivo ${new Date().toLocaleString()}`
                    })
                });

                const verificationResult = await verificationResponse.json();
                if (verificationResult.status === 'success') {
                    alert('Dispositivo registrato con successo! Ora puoi usarlo per accedere.');
                } else {
                    throw new Error(verificationResult.message || 'Registrazione fallita.');
                }
            } catch (err) {
                console.error('Errore durante la registrazione del dispositivo:', err);
                alert('Registrazione fallita: ' + err.message);
            }
        });

        // --- LOGICA NOTIFICHE PUSH ---
        const pushButton = document.getElementById('manage-push-btn');
        const VAPID_PUBLIC_KEY = '<?php echo VAPID_PUBLIC_KEY; ?>';

        // --- Funzioni di Utilità ---
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        // --- Comunicazione con il Server ---
        async function sendSubscriptionToServer(subscription) {
            // Aggiunge il token dell'utente al payload JSON inviato al server.
            // Questo è essenziale per autenticare l'utente nello script 'save_subscription.php'.
            const payload = { 
                ...subscription.toJSON(), 
                token: '<?php echo htmlspecialchars($token); ?>' };
            const response = await fetch('save_subscription.php', {
                method: 'POST',
                body: JSON.stringify(payload),
                headers: { 'Content-Type': 'application/json' }
            });
            if (!response.ok) {
                let errorText = `Errore HTTP: ${response.status}`;
                try {
                    const errorData = await response.json();
                    errorText = errorData.message || errorText;
                } catch (e) {}
                throw new Error(errorText);
            }
            const result = await response.json();
            if (result.status !== 'success') {
                throw new Error(result.message || 'Salvataggio fallito.');
            }
        }

        async function removeSubscriptionFromServer(subscription) {
            const payload = { endpoint: subscription.endpoint, token: '<?php echo htmlspecialchars($token); ?>' };
            return await fetch('remove_subscription.php', {
                method: 'POST', body: JSON.stringify(payload), headers: { 'Content-Type': 'application/json' }
            }).then(response => {
                if (!response.ok) {
                    throw new Error(`Errore HTTP durante la rimozione: ${response.status}`);
                }
                return response.json();
            }).then(result => {
                if (result.status !== 'success') {
                    throw new Error(result.message || 'Rimozione fallita.');
                }
            });
        }

        // --- Logica Principale Notifiche Push ---
        async function renewSubscription() {
            console.log('Tentativo di rinnovo della sottoscrizione...');
            const registration = await navigator.serviceWorker.ready;
            const existingSubscription = await registration.pushManager.getSubscription();
            if (existingSubscription) {
                await existingSubscription.unsubscribe();
                console.log('Sottoscrizione precedente rimossa per rinnovo.');
            }
            // Dopo aver rimosso la vecchia, tentiamo una nuova iscrizione.
            // subscribeUser gestirà la creazione e il salvataggio.
            await subscribeUser(); // subscribeUser lancerà un errore se fallisce
        }

        async function subscribeUser() {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                console.warn('Permesso per le notifiche non concesso.');
                throw new Error('Permesso notifiche non concesso dall\'utente.');
            }

            try {
                const registration = await navigator.serviceWorker.ready; // Assicurati che SW sia pronto
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                });
                console.log('Iscrizione push avvenuta:', subscription);
                await sendSubscriptionToServer(subscription);
            } catch (err) {
                console.error('Iscrizione push fallita:', err);
                if (err.name === 'InvalidStateError' || err.message.includes('application server key')) {
                    console.warn('Sottoscrizione con chiave VAPID diversa. Tento il rinnovo automatico.');
                    await renewSubscription(); // renewSubscription lancerà un errore se fallisce
                } else {
                    alert('ERRORE (Sottoscrizione): ' + err.message);
                    throw err; // Propaga l'errore per la gestione centralizzata
                }
            }
        }

        async function unsubscribeUser() {
            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();
                if (subscription) {
                    await removeSubscriptionFromServer(subscription);
                    await subscription.unsubscribe(); // Rimuove la sottoscrizione dal browser
                    console.log('Sottoscrizione rimossa con successo.');
                }
            } catch (err) {
                console.error('Errore durante la rimozione della sottoscrizione:', err);
                alert('Errore durante la disattivazione delle notifiche: ' + err.message);
                throw err; // Propaga l'errore per la gestione centralizzata
            }
        }

        async function handleSimulatePushClick() {
            console.log('Simulazione notifica utente avviata...');
            if (!('serviceWorker' in navigator)) {
                alert('Service Worker non supportato.');
                return;
            }
            try {
                const registration = await navigator.serviceWorker.ready;
                const title = 'Simulazione Notifica Utente';
                const options = {
                    body: 'Questa è una notifica di test. Se la vedi, il Service Worker funziona!',
                    icon: 'https://placehold.co/192x192/d0112b/ffffff?text=F',
                    badge: 'https://placehold.co/96x96/d0112b/ffffff?text=F',
                    data: {
                        url: 'https://www.filleaoffice.it:8013/servizifillea/servizi.php?token=<?php echo htmlspecialchars($token); ?>'
                    }
                };
                await registration.showNotification(title, options);
                console.log('Comando showNotification inviato al Service Worker.');
            } catch (err) {
                console.error('Errore durante la simulazione della notifica:', err);
                alert('Simulazione fallita: ' + err.message);
            }
        }

        // --- Gestione Interfaccia Utente ---
        function updateButtonUI({ isSubscribed, permission, isLoading = false, hasError = false }) {
            pushButton.disabled = isLoading;

            if (isLoading) {
                pushButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Caricamento...';
                pushButton.className = 'btn btn-secondary';
                return;
            }
            if (permission === 'denied') { // Permesso negato dall'utente
                pushButton.innerHTML = '<i class="fas fa-ban me-2"></i>Notifiche Bloccate';
                pushButton.className = 'btn btn-secondary';
                pushButton.disabled = true;
                return;
            }
            if (hasError) { // Errore generico nell'operazione
                pushButton.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Operazione fallita';
                pushButton.className = 'btn btn-warning';
                // Lascia il pulsante cliccabile per riprovare
                pushButton.disabled = false;
                return;
            }
            if (isSubscribed) { // Utente iscritto
                pushButton.innerHTML = '<i class="fas fa-bell-slash me-2"></i>Disabilita Notifiche';
                pushButton.className = 'btn btn-danger';
            } else {
                pushButton.innerHTML = '<i class="fas fa-bell me-2"></i>Abilita Notifiche su questo dispositivo';
                pushButton.className = 'btn btn-primary';
            }
            // Se non è in stato di caricamento, errore o negato, il pulsante è sempre abilitato
            pushButton.disabled = false;
            document.getElementById('simulate-push-btn').disabled = !isSubscribed;
        }

        // Gestore click del pulsante
        async function handlePushButtonClick() {
            console.log('handlePushButtonClick: Pulsante cliccato, impostazione stato di caricamento.');
            updateButtonUI({ isLoading: true });
            let finalHasError = false;
            try {
                console.log('handlePushButtonClick: In attesa che il Service Worker sia pronto...');
                const registration = await navigator.serviceWorker.ready;
                console.log('handlePushButtonClick: Service Worker pronto.', registration);

                const subscription = await registration.pushManager.getSubscription();
                console.log('handlePushButtonClick: Stato attuale della sottoscrizione:', subscription);

                if (subscription) {
                    console.log('handlePushButtonClick: Disiscrizione utente.');
                    await unsubscribeUser();
                } else {
                    console.log('handlePushButtonClick: Iscrizione utente.');
                    await subscribeUser();
                }
                console.log('handlePushButtonClick: Operazione completata.');
            } catch (error) {
                console.error('handlePushButtonClick: Errore durante l\'operazione push:', error);
                alert('Errore durante l\'operazione di notifica: ' + error.message);
                finalHasError = true;
            } finally {
                // Assicurati che l'UI sia aggiornata allo stato finale, indipendentemente dal successo o fallimento.
                // Ricontrolla lo stato effettivo della sottoscrizione per essere sicuro.
                try {
                    const registration = await navigator.serviceWorker.ready;
                    const currentSubscription = await registration.pushManager.getSubscription();
                    updateButtonUI({ isSubscribed: !!currentSubscription, permission: Notification.permission, hasError: finalHasError });
                } catch (e) {
                    console.error('handlePushButtonClick: Errore nel blocco finally durante il ricontrollo della sottoscrizione:', e);
                    updateButtonUI({ hasError: true }); // Se anche il ricontrollo fallisce, mostra errore
                }
            }
        }

        // Inizializza l'interfaccia utente del pulsante
        async function initializeUI(registration) { // `registration` è l'oggetto ServiceWorkerRegistration già pronto
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                pushButton.textContent = 'Notifiche non supportate';
                pushButton.disabled = true;
                pushButton.className = 'btn btn-secondary';
                return;
            }

            try {
                const subscription = await registration.pushManager.getSubscription();
                updateButtonUI({ isSubscribed: !!subscription, permission: Notification.permission }); // Aggiorna l'UI con lo stato reale
            } catch (e) { // Questo catch gestisce errori durante il recupero della sottoscrizione
                console.error("Errore nel controllo dello stato iniziale", e);
                updateButtonUI({ hasError: true });
            }
        }

        // --- Inizializzazione ---
        // Imposta lo stato di caricamento iniziale del pulsante immediatamente
        updateButtonUI({ isLoading: true });

        // Controlla se il browser supporta il Service Worker
        if ('serviceWorker' in navigator) {
            // 1. Registra il Service Worker.
            navigator.serviceWorker.register('service-worker.js').then(reg => {
                console.log('SW registrato con successo. Ora attendo che sia attivo...');
                
                // 2. Attendi che il Service Worker sia completamente attivo e pronto.
                // Questo previene race conditions dove l'utente clicca prima che il SW sia pronto.
                return navigator.serviceWorker.ready;
            }).then(registration => {
                console.log('Service Worker è attivo e pronto.', registration);
                
                // 3. Ora che il SW è pronto, possiamo abilitare l'interazione dell'utente.
                pushButton.addEventListener('click', handlePushButtonClick);
                document.getElementById('simulate-push-btn').addEventListener('click', handleSimulatePushClick);
                initializeUI(registration);
                
            }).catch(error => {
                console.error('Registrazione o attivazione del Service Worker fallita:', error);
                updateButtonUI({ isSubscribed: false, hasError: true });
            });
        }
    });
</script>
</html>