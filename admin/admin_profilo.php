<?php
session_start();

// Proteggi la pagina: solo gli admin possono accedervi.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

include_once('../database.php');
include_once('../push_config.php'); // Includi per accedere alla chiave pubblica VAPID
$pdo1 = Database::getInstance('fillea');

// Recupera i dati del funzionario loggato
$funzionario_id = $_SESSION['funzionario_id'];
$stmt = $pdo1->prepare("SELECT funzionario, zona, username, telefono FROM `fillea-app`.funzionari WHERE id = ?");
$stmt->execute([$funzionario_id]);
$admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

$is_super_admin = $_SESSION['is_super_admin'] ?? false;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilo Funzionario</title>
    <link rel="icon" href="https://placehold.co/32x32/d0112b/ffffff?text=A">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root { --admin-primary: #d0112b; }
        body { background-color: #f4f6f9; }
        .sidebar { width: 250px; height: 100vh; position: fixed; top: 0; left: 0; background-color: #343a40; color: #fff; padding-top: 1rem; }
        .sidebar .brand { display: flex; align-items: center; justify-content: center; padding: 1rem; font-size: 1.5rem; font-weight: bold; color: #fff; text-decoration: none; }
        .sidebar .brand i { color: var(--admin-primary); margin-right: 0.5rem; }
        .sidebar .nav-link { color: #c2c7d0; padding: 0.75rem 1.5rem; display: block; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { background-color: var(--admin-primary); color: #fff; }
        .content-wrapper { transition: margin-left 0.3s ease-in-out; }
        .main-content { padding: 2rem; }
        .topbar { height: 4.375rem; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .profile-card { max-width: 800px; margin: auto; }
        .sidebar.show { transform: translateX(0); }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1029; }
        .overlay.show { display: block; }

        @media (max-width: 767.98px) {
            .sidebar { transform: translateX(-100%); z-index: 1030; }
            .content-wrapper { margin-left: 0; }
        }

        @media (min-width: 768px) {
            .sidebar { transform: translateX(0); }
            .content-wrapper { margin-left: 250px; }
            #sidebarToggle { display: none; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <a href="#" class="brand">
        <i class="fas fa-cogs"></i>
        <span><?php echo $is_super_admin ? 'Super Admin' : 'Admin'; ?></span>
    </a>
    <nav class="nav flex-column">
        <?php if ($is_super_admin): ?>
            <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-chart-bar me-2"></i> Dashboard</a>
            <a class="nav-link" href="admin_reassign.php"><i class="fas fa-random me-2"></i> Riassegna Pratiche</a>
        <?php else: ?>
            <a class="nav-link" href="admin_documenti.php"><i class="fas fa-file-alt me-2"></i> Gestione Documenti</a>
        <?php endif; ?>
    </nav>
</aside>

<div class="content-wrapper">
    <nav class="navbar navbar-expand navbar-light topbar p-2 shadow-sm">
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="me-2 d-none d-lg-inline text-gray-600 small">
                        <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                        <?php if ($is_super_admin): ?><span class="badge bg-danger">Super</span><?php else: ?><span class="badge bg-primary">Admin</span><?php endif; ?>
                    </span>
                    <i class="fas fa-user-shield text-gray-600"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                    <a class="dropdown-item" href="admin_profilo.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profilo</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout</a>
                </div>
            </li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="container-fluid">
            <h1 class="mb-4">Profilo Funzionario</h1>

            <div class="card profile-card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 col-md-6 mb-3">
                            <label class="form-label">Nome Funzionario</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin_data['funzionario']); ?>" readonly>
                        </div>
                        <div class="col-6 col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin_data['username']); ?>" readonly>
                        </div>
                        <div class="col-6 col-md-6 mb-3">
                            <label class="form-label">Zona di Competenza</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin_data['zona']); ?>" readonly>
                        </div>
                        <div class="col-6 col-md-6 mb-3">
                            <label class="form-label">Telefono</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin_data['telefono'] ?? 'Non impostato'); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$is_super_admin): ?>
                <div class="card profile-card" id="push-section">
                    <div class="card-body text-center">
                        <i class="fas fa-bell fa-3x text-primary mb-3"></i>
                        <h4 class="card-title">Notifiche Push</h4>
                        <p class="card-text text-muted">Ricevi aggiornamenti in tempo reale quando un utente invia una nuova pratica.</p>                    
                        <div class="d-grid gap-2">
                            <button id="manage-push-btn" class="btn btn-primary" disabled><i class="fas fa-spinner fa-spin me-2"></i>Caricamento...</button>
                            <button id="simulate-push-btn" class="btn btn-info" disabled><i class="fas fa-paper-plane me-2"></i>Simula Ricezione Notifica</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<!-- Overlay per chiudere la sidebar su mobile -->
<div id="sidebarOverlay" class="overlay"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const pushButton = document.getElementById('manage-push-btn');
    const VAPID_PUBLIC_KEY = '<?php echo VAPID_PUBLIC_KEY; ?>';

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

    async function sendSubscriptionToServer(subscription) {
        // CORREZIONE: Aggiungi l'ID del funzionario al payload per permettere
        // allo script 'save_subscription.php' di sapere a chi appartiene la sottoscrizione.
        const payload = { ...subscription.toJSON(), funzionario_id: '<?php echo $funzionario_id; ?>' };

        const response = await fetch('../save_subscription.php', {
            method: 'POST',
            body: JSON.stringify(payload),
            headers: { 'Content-Type': 'application/json' }
        });
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Salvataggio fallito.');
        }
    }

    async function removeSubscriptionFromServer(subscription) {
        // CORREZIONE: Aggiungi l'ID del funzionario anche alla richiesta di rimozione
        // per assicurarsi che lo script 'remove_subscription.php' possa identificare
        // correttamente quale sottoscrizione eliminare.
        const payload = { endpoint: subscription.endpoint, funzionario_id: '<?php echo $funzionario_id; ?>' };
        const response = await fetch('../remove_subscription.php', {
            method: 'POST',
            body: JSON.stringify(payload),
            headers: { 'Content-Type': 'application/json' }
        });
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Rimozione fallita.');
        }
    }

    async function unsubscribeUser() {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
            await removeSubscriptionFromServer(subscription);
            await subscription.unsubscribe();
            console.log('Sottoscrizione rimossa con successo.');
        }
    }

    // Funzione per gestire il rinnovo di una sottoscrizione esistente
    async function renewSubscription() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const existingSubscription = await registration.pushManager.getSubscription();
            
            if (existingSubscription) {
                await existingSubscription.unsubscribe();
                console.log('Sottoscrizione precedente rimossa con successo.');
            }
            
            // Tenta di nuovo la sottoscrizione dopo aver rimosso la vecchia
            const newSubscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
            });

            console.log('Nuova sottoscrizione creata dopo il rinnovo:', newSubscription);
            await sendSubscriptionToServer(newSubscription);

        } catch (renewErr) {
            console.error('Errore durante il rinnovo della sottoscrizione:', renewErr);
            pushButton.textContent = 'Abilitazione fallita';
        }
    }

    async function subscribeUser() {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            console.warn('Permesso per le notifiche non concesso.');
            throw new Error('Permesso notifiche non concesso dall\'utente.');
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
            });

            console.log('Iscrizione push avvenuta:', subscription);
            await sendSubscriptionToServer(subscription);
        } catch (err) {
            console.error('Iscrizione push fallita:', err);
            if (err.name === 'InvalidStateError' || err.message.includes('application server key')) {
                console.warn('Trovata una sottoscrizione con chiave diversa. Tento il rinnovo automatico.');
                await renewSubscription();
            } else {
                throw err;
            }
        }
    }

    function updateButtonUI({ isSubscribed, permission, isLoading = false, hasError = false }) {
        pushButton.disabled = isLoading;
        if (isLoading) {
            pushButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Elaborazione...';
            pushButton.className = 'btn btn-secondary';
        } else if (permission === 'denied') {
            pushButton.innerHTML = '<i class="fas fa-ban me-2"></i>Notifiche Bloccate';
            pushButton.className = 'btn btn-secondary';
            pushButton.disabled = true;
        } else if (hasError) {
            pushButton.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Operazione fallita';
            pushButton.className = 'btn btn-warning';
            pushButton.disabled = false;
        } else if (isSubscribed) {
            pushButton.innerHTML = '<i class="fas fa-bell-slash me-2"></i>Disabilita Notifiche';
            pushButton.className = 'btn btn-danger';
            pushButton.disabled = false;
        } else {
            pushButton.innerHTML = '<i class="fas fa-bell me-2"></i>Abilita Notifiche';
            pushButton.className = 'btn btn-primary';
            pushButton.disabled = false;
        }
        document.getElementById('simulate-push-btn').disabled = !isSubscribed;
    }

    async function handlePushButtonClick() {
        updateButtonUI({ isLoading: true });
        let finalHasError = false;
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await unsubscribeUser();
            } else {
                await subscribeUser();
            }
        } catch (error) {
            console.error('Errore durante l\'operazione push:', error);
            alert('Errore: ' + error.message);
            finalHasError = true;
        } finally {
            const registration = await navigator.serviceWorker.ready;
            const currentSubscription = await registration.pushManager.getSubscription();
            updateButtonUI({ isSubscribed: !!currentSubscription, permission: Notification.permission, hasError: finalHasError });
        }
    }

    async function handleSimulatePushClick() {
        console.log('Simulazione notifica avviata...');
        if (!('serviceWorker' in navigator)) {
            alert('Service Worker non supportato.');
            return;
        }
        try {
            const registration = await navigator.serviceWorker.ready;
            const title = 'Simulazione Notifica Admin';
            const options = {
                body: 'Questa è una notifica di test. Se la vedi, il Service Worker funziona!',
                icon: 'https://placehold.co/192x192/d0112b/ffffff?text=F',
                badge: 'https://placehold.co/96x96/d0112b/ffffff?text=F',
                data: {
                    url: 'https://www.filleaoffice.it:8013/servizifillea/admin/admin_documenti.php'
                }
            };
            await registration.showNotification(title, options);
            console.log('Comando showNotification inviato al Service Worker.');
        } catch (err) {
            console.error('Errore durante la simulazione della notifica:', err);
            alert('Simulazione fallita: ' + err.message);
        }
    }

    async function initializeUI(registration) {
        if (!('PushManager' in window)) {
            updateButtonUI({ hasError: true });
            pushButton.textContent = 'Notifiche non supportate';
            return;
        }
        try {
            const subscription = await registration.pushManager.getSubscription();
            updateButtonUI({ isSubscribed: !!subscription, permission: Notification.permission });
        } catch (e) {
            console.error("Errore nel controllo dello stato iniziale", e);
            updateButtonUI({ hasError: true });
        }
    }

    // --- Inizializzazione ---
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('../service-worker.js').then(registration => {
            console.log('Service Worker registrato, in attesa di essere pronto...');
            return navigator.serviceWorker.ready;
        }).then(readyRegistration => {
            console.log('Service Worker pronto.');
            pushButton.addEventListener('click', handlePushButtonClick);
            document.getElementById('simulate-push-btn').addEventListener('click', handleSimulatePushClick);
            initializeUI(readyRegistration);
        }).catch(error => {
            console.error('Registrazione o attivazione del Service Worker fallita:', error);
            updateButtonUI({ hasError: true });
        });
    }

    // Logica per la sidebar mobile
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebarToggle = document.getElementById('sidebarToggle');

    function toggleSidebar() {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    if (overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }
});
</script>
</body>
</html>