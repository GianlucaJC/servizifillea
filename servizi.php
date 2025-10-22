
<?php
    session_start();
    $token = $_GET['token'] ?? '';
    $is_user_logged_in = false;
    $unread_notifications = [];
    $unread_notifications_count = 0;

    // Array di configurazione delle prestazioni (necessario per gli alert)
    $prestazioni_config = [
        'premio_matrimoniale' => ['label' => 'Premio Matrimoniale / Unioni Civili'],
        'premio_giovani' => ['label' => 'Premio Giovani e Inserimento'],
        'bonus_nascita' => ['label' => 'Bonus Nascita o Adozione'],
        'donazioni_sangue' => ['label' => 'Donazioni del Sangue'],
        'contributo_affitto' => ['label' => 'Contributo Affitto Casa'],
        'contributo_sfratto' => ['label' => 'Contributo per Ingiunzione Sfratto'],
        'contributo_disabilita' => ['label' => 'Contributo Figli con Diversa Abilità'],
        'post_licenziamento' => ['label' => 'Contributo Post Licenziamento'],
        'centri_estivi' => ['label' => 'Bonus Centri Estivi'],
        'permesso_soggiorno' => ['label' => 'Rimborso Permesso di Soggiorno'],
        'attivita_sportive' => ['label' => 'Attività Sportive e Ricreative'],
        'asili_nido' => ['label' => 'Contributi Asilo Nido'],
        'scuole_elementari' => ['label' => 'Contributi Studio Scuole Elementari'],
        'scuole_medie_inferiori' => ['label' => 'Contributi Studio Scuole Medie'],
        'superiori_iscrizione' => ['label' => 'Contributi Studio Scuole Superiori'],
        'universita_iscrizione' => ['label' => 'Contributi Studio Università'],
    ];

    if (!empty($token)) {
        include("database.php");
        $pdo1 = Database::getInstance('fillea');
        
        $sql = "SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW() LIMIT 1";
        $stmt = $pdo1->prepare($sql);
        $stmt->execute([$token]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $is_user_logged_in = true;
            $user_id = $user['id'];

            // Recupera le notifiche non lette per l'utente
            $stmt_notifications = $pdo1->prepare("
                SELECT 
                    rm.form_name, 
                    rm.status, 
                    COALESCE(m1.prestazioni, m2.prestazioni) AS prestazioni,
                    rm.modulo_nome
                FROM `fillea-app`.richieste_master rm
                LEFT JOIN `fillea-app`.modulo1_richieste m1 ON rm.form_name = m1.form_name COLLATE utf8mb4_unicode_ci
                LEFT JOIN `fillea-app`.modulo2_richieste m2 ON rm.form_name = m2.form_name COLLATE utf8mb4_unicode_ci
                WHERE rm.user_id = :user_id AND rm.user_notification_unseen = 1
                ORDER BY rm.data_invio DESC
            ");
            $stmt_notifications->execute([':user_id' => $user_id]);
            $unread_notifications = $stmt_notifications->fetchAll(PDO::FETCH_ASSOC);
            $unread_notifications_count = count($unread_notifications);

        } else {
            header("Location: servizi.php");
        }
        $pdo1 = null; // Chiudi la connessione
    }    
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servizi Fillea</title>
    
    <!-- Meta tag essenziali per PWA e reattività -->
    <link rel="icon" href="https://placehold.co/32x32/d0112b/ffffff?text=S">
    <link rel="apple-touch-icon" href="https://placehold.co/192x192/d0112b/ffffff?text=S">
    <meta name="theme-color" content="#d0112b">
    
    <!-- Link al Web App Manifest per la PWA -->
    <link rel="manifest" href="manifest.json">

    <!-- Simulazione Manifest e Service Worker (DEV: da implementare in files separati) -->
    <!-- <link rel="manifest" href="/manifest.json"> -->

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- NUOVA INCLUSIONE: Font Awesome CDN (Versione 6) -->   
    

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" xintegrity="sha512-SnH5WK+bZxgPHs44uWIX+LLMDJ8Rj3URPImCNwI423lXQJ8j6tIq30k3oI6XpG8M8O2WJ6Y75Jz6T8Hw8tFw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        // PRIMARY ORA È #d0112b
                        'primary': '#d0112b', // Nuovo Rosso
                        'secondary': '#f97316', // Orange 500
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    
    <!-- Stili Custom (spostati in un file esterno) -->
    <link rel="stylesheet" href="style.css?ver=<?= time() ?>">
</head>
<body class="min-h-screen">

    <input type='hidden' name='token' id='token' value='<?php echo $token; ?>'>

    <!-- 1. SPLASH SCREEN -->
    <div id="splash-screen">
        <img src="splash1.png" alt="Fillea Service App" class="w-full max-w-md shadow-2xl rounded-lg">
        <div class="spinner mt-8"></div>
        <p class="mt-4 text-white/90 text-sm">Caricamento in corso...</p>
    </div>

    <!-- 2. MAIN APP CONTENT -->
    <div id="main-app" class="hidden">
        <!-- NUOVO: Banner di installazione PWA -->
        <div id="install-banner" class="hidden sticky top-0 bg-primary text-white p-3 text-center shadow-lg z-30">
            <div class="container mx-auto flex justify-between items-center">
                <span class="font-semibold">Installa l'app per un accesso rapido!</span>
                <div>
                    <button id="install-confirm-btn" class="bg-white text-primary font-bold py-1 px-3 rounded-md text-sm hover:bg-gray-200">Installa</button>
                    <button id="install-cancel-btn" class="ml-2 text-white text-xl">&times;</button>
                </div>
            </div>
        </div>
        <!-- Barra superiore fissa -->
        <div class="sticky top-0 bg-white/95 backdrop-blur-sm z-20 shadow-sm">
            <div class="container mx-auto px-4 py-4">
                <?php if ($is_user_logged_in==true): ?>
                <!-- Icone Utente e Notifiche -->
                <div class="absolute top-4 right-4 flex items-center space-x-4">
                    <!-- Icona Notifiche -->
                    <div id="notification-bell" class="relative cursor-pointer">
                        <i class="fas fa-bell fa-lg text-gray-600"></i>
                        <?php if ($unread_notifications_count > 0): ?>
                            <span id="notification-badge" class="absolute -top-2 -right-2 h-5 w-5 bg-red-600 text-white text-xs rounded-full flex items-center justify-center border-2 border-white"><?php echo $unread_notifications_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <!-- Icona Utente -->
                    <div class="relative">
                        <button id="user-menu-button" class="text-white bg-primary hover:bg-red-700 rounded-full w-10 h-10 shadow-lg flex items-center justify-center focus:outline-none">
                            <i class="fas fa-user"></i>
                        </button>
                        <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 ring-1 ring-black ring-opacity-5">
                            <a href="profilo.php?token=<?php echo htmlspecialchars($token);?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profilo</a>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <header class="text-center">
                    <!-- Logo aggiunto qui -->
                    <img src="logo.jpg" alt="Logo Fillea CGIL Firenze" class="mx-auto mb-4 w-full max-w-md">
                </header>
            </div>
        </div>

        <!-- Griglia dei 13 Servizi -->
        <div class="container mx-auto pt-8 pb-20"> <!-- Aggiunto padding per distanziare dalla barra fissa -->
            <!-- Area Alert Notifiche -->
            <div id="notification-alerts-container" class="max-w-3xl mx-auto px-4 mb-8 <?php if ($unread_notifications_count === 0) echo 'hidden'; ?>">
                <h3 class="text-xl font-bold text-primary mb-4"><i class="fas fa-bell mr-2"></i>Avvisi Importanti</h3>
                <div class="space-y-3">
                <?php foreach ($unread_notifications as $notification): ?>
                    <?php
                        // Logica per costruire il link corretto alla pratica
                        $module_file = ($notification['modulo_nome'] === 'Contributi di Studio') ? 'modulo1.php' : 'modulo2.php';
                        $prestazione_key = '';
                        $prestazione_label = 'N/D'; // Etichetta di default
                        if (!empty($notification['prestazioni'])) {
                            $prest_array = json_decode($notification['prestazioni'], true);
                            if (is_array($prest_array)) {
                                $prestazione_key = key($prest_array);
                                if (isset($prestazioni_config[$prestazione_key]['label'])) {
                                    $prestazione_label = $prestazioni_config[$prestazione_key]['label'];
                                }
                            }
                        }
                        $link = "servizi/moduli/{$module_file}?token=" . htmlspecialchars($token) . "&form_name=" . urlencode($notification['form_name']) . "&prestazione=" . urlencode($prestazione_key);
                        
                        $status_message = 'aggiornato';
                        switch ($notification['status']) {
                            case 'bozza': $status_message = 'stato <strong>sbloccato per modifiche</strong>'; break;
                            case 'inviato_in_cassa_edile': $status_message = 'stato <strong>inoltrato a Cassa Edile</strong>'; break;
                            case 'abbandonato': $status_message = 'stato <strong>archiviato</strong>'; break;
                        }
                    ?>
                    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 rounded-md shadow-sm" role="alert">
                        <p>La tua pratica per il servizio <a href="<?php echo $link; ?>" class="font-bold underline hover:text-yellow-900">"<?php echo htmlspecialchars($prestazione_label); ?>"</a> è <?php echo $status_message; ?>.</p>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <p class="text-center text-gray-600 mb-8 px-4">Scegli il servizio di cui hai bisogno. Clicca su una delle card per iniziare.</p>
            <!-- CLASSI AGGIORNATE: 
                 - grid-cols-2 (2 colonne su MOBILE - Nuovo Default)
                 - md:grid-cols-3 (3 colonne da schermi medi in su - Massimo)
                 - max-w-3xl mx-auto (Limita la larghezza per centrare bene 3 colonne) -->
            <div id="service-grid" class="grid gap-4 p-4 <!-- Aggiunto padding qui per lo spazio laterale -->
                grid-cols-2 
                md:grid-cols-3 
                max-w-3xl mx-auto">
                
                <!-- I dati dei servizi verranno iniettati qui da JavaScript -->
            </div>
        </div>
    </div>

    <!-- Contenitore per il Toast (Notifica) - Posizionato in alto per visibilità su mobile -->
    <!--
        MODIFICATO: Le classi di posizionamento (es. 'top-5 right-5') sono state rimosse.
        Verranno aggiunte dinamicamente da JavaScript per permettere stili diversi (toast o modale).
    -->
    <div id="toast-message" class="fixed p-4 rounded-lg shadow-xl text-white bg-primary transition-all duration-300 opacity-0 z-[1001] text-lg font-semibold">
        <!-- Il messaggio verrà inserito qui -->
    </div>

    <!-- Pulsante Accesso Admin (visibile solo in accesso pubblico) -->
    <?php if (!$is_user_logged_in): ?>
    <a href="admin/index.php" title="Accesso Admin" class="fixed bottom-4 left-4 bg-gray-700 text-white p-3 rounded-full shadow-lg hover:bg-gray-800 transition-colors z-30">
        <i class="fas fa-user-shield fa-lg"></i>
    </a>
    <?php endif; ?>

    <!-- JavaScript per la Logica PWA e lo Stepper (spostato in un file esterno) -->
    <script src="servizi.js?ver=<?= time() ?>""></script>

</body>
</html>
