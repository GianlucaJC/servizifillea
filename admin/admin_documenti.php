<?php
session_start();

// Proteggi la pagina: se l'utente non è loggato come admin, reindirizza al login.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Includi il file di connessione al database
// Il percorso `../` risale dalla cartella `admin` alla root `servizifillea`
include_once('../database.php');

// Ottieni l'istanza del database per l'app Fillea
$pdo1 = Database::getInstance('fillea');

// --- GESTIONE FILTRI, ORDINAMENTO E PAGINAZIONE ---

// 1. Filtri
$filter_service = $_GET['service_filter'] ?? 'all';
$filter_user = $_GET['user_filter'] ?? 'all';
$filter_status = $_GET['status_filter'] ?? 'all';

// 2. Ordinamento
$allowed_sort_columns = ['modulo_nome', 'form_name', 'data_invio', 'status', 'cognome'];
$sort_by = in_array($_GET['sort_by'] ?? '', $allowed_sort_columns) ? $_GET['sort_by'] : 'data_invio';
$sort_dir = (isset($_GET['sort_dir']) && strtoupper($_GET['sort_dir']) === 'ASC') ? 'ASC' : 'DESC';

// 3. Paginazione
const ITEMS_PER_PAGE = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * ITEMS_PER_PAGE;

// --- FINE GESTIONE ---

// Elenco dei servizi per il filtro
$servizi = [
    'Contributi di Studio' => "Contributi di Studio",
    // Aggiungi qui altri nomi di moduli quando saranno disponibili
];

// Elenco degli stati per il filtro
$stati = [
    'bozza' => 'Bozza',
    'inviato' => 'Inviato',
    'in_lavorazione' => 'In Lavorazione',
    'completato' => 'Completato',
    'abbandonato' => 'Abbandonato',
    'inviato_in_cassa_edile' => 'Inviato in Cassa Edile'
];

// Recupera l'elenco di tutti gli utenti che hanno inviato richieste per popolare il filtro
$sql_users = "SELECT DISTINCT u.id, u.cognome, u.nome FROM `fillea-app`.users u JOIN `fillea-app`.richieste_master rm ON u.id = rm.user_id ORDER BY u.cognome, u.nome";
$stmt_users = $pdo1->prepare($sql_users);
$stmt_users->execute();
$utenti_richiedenti = $stmt_users->fetchAll(PDO::FETCH_ASSOC);


// Costruzione della query base
$sql_base = "
    SELECT 
        rm.id,
        rm.user_id,
        rm.modulo_nome,
        rm.form_name,
        rm.data_invio,
        rm.status,
        u.nome,
        rm.is_new,
        u.cognome
    FROM `fillea-app`.richieste_master AS rm
    JOIN `fillea-app`.users AS u ON rm.user_id = u.id
";

// Aggiungiamo la condizione FONDAMENTALE per il funzionario loggato.
// Le richieste devono appartenere a utenti associati a questo funzionario.
$funzionario_id = $_SESSION['funzionario_id'] ?? 0; // Recupera l'ID del funzionario dalla sessione
$conditions = ["rm.id_funzionario = ?"];
$params = [];
$params[] = $funzionario_id;

if ($filter_service !== 'all') {
    $conditions[] = "rm.modulo_nome = ?";
    $params[] = $filter_service;
}
if ($filter_user !== 'all') {
    $conditions[] = "rm.user_id = ?";
    $params[] = $filter_user;
}
if ($filter_status !== 'all') {
    $conditions[] = "rm.status = ?";
    $params[] = $filter_status;
}

$where_clause = '';
if (!empty($conditions)) {
    $where_clause = " WHERE " . implode(' AND ', $conditions);
}

// Query per contare il totale dei record (per la paginazione)
$sql_count = "SELECT COUNT(rm.id) FROM `fillea-app`.richieste_master AS rm " . $where_clause;
$stmt_count = $pdo1->prepare($sql_count);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / ITEMS_PER_PAGE);

// Query per recuperare i record della pagina corrente
$sql = $sql_base . $where_clause;

// Aggiungi ordinamento. L'ordinamento per utente è gestito a livello di raggruppamento PHP.
if ($sort_by !== 'cognome') {
    $sql .= " ORDER BY " . ($sort_by === 'status' ? 'rm.status' : $sort_by) . " " . $sort_dir;
}

$sql .= " LIMIT " . ITEMS_PER_PAGE . " OFFSET " . $offset;

$stmt = $pdo1->prepare($sql);
$stmt->execute($params);
$richieste = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Se l'ordinamento è per utente, riordina l'array PHP
if ($sort_by === 'cognome') {
    usort($richieste, function($a, $b) use ($sort_dir) {
        return $sort_dir === 'ASC' ? strcmp($a['cognome'], $b['cognome']) : strcmp($b['cognome'], $a['cognome']);
    });
}

// Raggruppa le richieste per utente e conta le nuove notifiche
$richieste_per_utente = [];
$new_notifications_count = 0;
foreach ($richieste as $richiesta) {
    $richieste_per_utente[$richiesta['user_id']]['nome'] = $richiesta['cognome'] . ' ' . $richiesta['nome'];
    $richieste_per_utente[$richiesta['user_id']]['richieste'][] = $richiesta;
    if ($richiesta['is_new']) {
        $new_notifications_count++;
    }
}

// Funzione helper per generare i link di ordinamento
function get_sort_link($column, $current_sort_by, $current_sort_dir, $label) {
    $new_sort_dir = ($current_sort_by === $column && $current_sort_dir === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($current_sort_by === $column) {
        $icon = $current_sort_dir === 'ASC' ? '<i class="fas fa-sort-up ms-1"></i>' : '<i class="fas fa-sort-down ms-1"></i>';
    }

    // Preserva i filtri correnti
    $query_params = $_GET;
    $query_params['sort_by'] = $column;
    $query_params['sort_dir'] = $new_sort_dir;
    
    return '<a href="?' . http_build_query($query_params) . '">' . $label . $icon . '</a>';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Gestione Documenti</title>
    <link rel="icon" href="https://placehold.co/32x32/d0112b/ffffff?text=A">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --admin-primary: #d0112b;
            --admin-dark: #343a40;
            --admin-light: #f4f6f9;
        }
        body {
            background-color: var(--admin-light);
        }
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            z-index: 1030;
            top: 0;
            left: 0;
            background-color: var(--admin-dark);
            color: #fff;
            padding-top: 1rem;
            transition: transform 0.3s ease-in-out;
            transform: translateX(-100%);
        }
        .sidebar.collapsed {
        }
        .sidebar .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: #fff;
            text-decoration: none;
        }
        .sidebar .brand i {
            color: var(--admin-primary);
            margin-right: 0.5rem;
        }
        .sidebar .nav-link {
            color: #c2c7d0;
            padding: 0.75rem 1.5rem;
            display: block;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background-color: var(--admin-primary);
            color: #fff;
        }
        .content-wrapper {
            transition: margin-left 0.3s ease-in-out;
        }
        .main-content {
            padding: 2rem;
        }
        @media (min-width: 768px) {
            .content-wrapper {
                margin-left: 250px;
            }
            .sidebar {
                transform: translateX(0);
            }
        }
        .card-header.bg-primary {
            background-color: var(--admin-primary) !important;
        }
        .table-responsive .table thead {
            background-color: #e9ecef;
        }
        .user-group-header {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .user-group-header td {
            padding-top: 1.5rem !important;
            padding-bottom: 0.5rem !important;
            border-top: 2px solid #dee2e6;
        }
        .badge.bg-success { background-color: #28a745 !important; }
        .badge.bg-warning { background-color: #ffc107 !important; color: #212529 !important; }
        .badge.bg-info { background-color: #17a2b8 !important; }
        .badge.bg-dark { background-color: #6c757d !important; }
        .badge.bg-purple { background-color: #6f42c1 !important; }

        /* Stili per la navbar mobile */
        .navbar-mobile {
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,.1); z-index: 1020;
        }
        .topbar {
            height: 4.375rem;
        }
    </style>
</head>
<body>

<!-- Overlay per chiudere la sidebar su mobile -->
<div id="sidebarOverlay" style="
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1029;
    display: none;
"></div>

<aside class="sidebar">
    <a href="#" class="brand">
        <i class="fas fa-cogs"></i>
        <span>Admin</span>
    </a>
    <nav class="nav flex-column">
        <a class="nav-link active" href="admin_documenti.php"><i class="fas fa-file-alt me-2"></i> Gestione Documenti</a>
    </nav>
</aside>

<div class="content-wrapper">
    <!-- Navbar per mobile con bottone hamburger -->
    <nav class="navbar navbar-expand navbar-light bg-white topbar p-2 shadow-sm">
        <!-- Bottone Hamburger (visibile solo su mobile) -->
        <button id="sidebarToggle" class="btn btn-link text-dark d-md-none">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Navbar a destra -->
        <ul class="navbar-nav ms-auto">
            <!-- Campanella Notifiche -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-new-notifications="<?php echo $new_notifications_count; ?>">
                    <i class="fas fa-bell fa-fw text-gray-600"></i>
                    <!-- Contatore Notifiche -->
                    <?php if ($new_notifications_count > 0): ?>
                        <span class="badge bg-danger rounded-pill position-absolute top-0 start-75 translate-middle" style="font-size: 0.6em;">
                            <?php echo $new_notifications_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <!-- Menu a tendina delle notifiche -->
                <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="alertsDropdown">
                    <h6 class="dropdown-header" style="background-color: var(--admin-primary); color: white;">
                        Centro Notifiche
                    </h6>
                    <?php if ($new_notifications_count > 0): ?>
                        <?php foreach ($richieste as $req): if($req['is_new']): ?>
                            <a class="dropdown-item d-flex align-items-center" href="#">
                                <div class="me-3"><div class="icon-circle bg-primary"><i class="fas fa-file-alt text-white"></i></div></div>
                                <div><div class="small text-muted"><?php echo date('d/m/Y', strtotime($req['data_invio'])); ?></div><span class="font-weight-bold">Nuova richiesta da <?php echo htmlspecialchars($req['cognome'] . ' ' . $req['nome']); ?></span></div>
                            </a>
                        <?php endif; endforeach; ?>
                    <?php else: ?>
                        <a class="dropdown-item text-center small text-gray-500" href="#">Nessuna nuova notifica</a>
                    <?php endif; ?>
                    <a class="dropdown-item text-center small text-gray-500" href="#">Mostra tutte le notifiche</a>
                </div>
            </li>

            <div class="topbar-divider d-none d-sm-block mx-2 border-end"></div>

            <!-- Menu Utente Admin -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="me-2 d-none d-lg-inline text-gray-600 small">
                        <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                        <span class="badge bg-primary">Admin</span>
                    </span>
                    <i class="fas fa-user-shield text-gray-600"></i>
                </a>
                <!-- Menu a tendina Utente -->
                <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="userDropdown">
                    <a class="dropdown-item" href="admin_profilo.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profilo</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="logout.php">
                        <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="container-fluid">
            <h1 class="mb-4">Gestione Documenti Ricevuti</h1>

            <!-- Filtri -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtra Documenti</h5>
                </div>
                <div class="card-body">
                    <form action="admin_documenti.php" method="GET">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="serviceFilter" class="form-label">Filtra per Servizio</label>
                                <select id="serviceFilter" name="service_filter" class="form-select">
                                    <option value="all" <?php if ($filter_service === 'all') echo 'selected'; ?>>Tutti i servizi</option>
                                    <?php foreach ($servizi as $nome_modulo => $nome_visualizzato): ?>
                                        <option value="<?php echo htmlspecialchars($nome_modulo); ?>" <?php if ($filter_service === $nome_modulo) echo 'selected'; ?>><?php echo htmlspecialchars($nome_visualizzato); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mt-2 mt-md-0">
                                <label for="userFilter" class="form-label">Filtra per Lavoratore</label>
                                <select id="userFilter" name="user_filter" class="form-select">
                                    <option value="all" <?php if ($filter_user === 'all') echo 'selected'; ?>>Tutti i lavoratori</option>
                                    <?php foreach ($utenti_richiedenti as $utente): ?>
                                        <option value="<?php echo $utente['id']; ?>" <?php if ($filter_user == $utente['id']) echo 'selected'; ?>><?php echo htmlspecialchars($utente['cognome'] . ' ' . $utente['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mt-2 mt-md-0">
                                <label for="statusFilter" class="form-label">Filtra per Stato</label>
                                <select id="statusFilter" name="status_filter" class="form-select">
                                    <option value="all" <?php if ($filter_status === 'all') echo 'selected'; ?>>Tutti gli stati</option>
                                    <?php foreach ($stati as $val => $label): ?>
                                        <option value="<?php echo $val; ?>" <?php if ($filter_status === $val) echo 'selected'; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end mt-3 mt-md-0">
                                <button type="submit" class="btn" style="background-color: var(--admin-primary); color: white;">Applica Filtro</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabella Documenti -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Elenco Documenti</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo get_sort_link('modulo_nome', $sort_by, $sort_dir, 'Servizio'); ?></th>
                                    <th><?php echo get_sort_link('form_name', $sort_by, $sort_dir, 'Nome Documento'); ?></th>
                                    <th class="text-center"><?php echo get_sort_link('data_invio', $sort_by, $sort_dir, 'Data Invio'); ?></th>
                                    <th class="text-center"><?php echo get_sort_link('status', $sort_by, $sort_dir, 'Stato'); ?></th>
                                    <th class="text-center">Azioni</th> <!-- Le azioni non sono ordinabili -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($richieste)): ?>
                                    <tr><td colspan="5" class="text-center p-4">Nessuna richiesta trovata con i filtri applicati.</td></tr>
                                <?php else: ?>
                                <?php foreach ($richieste_per_utente as $user_id => $data): ?>
                                    <tr class="user-group-header">
                                        <td colspan="5" class="user-sortable-header">
                                            <i class="fas fa-user me-2"></i> Utente: <?php echo htmlspecialchars($data['nome']); ?> (ID: <?php echo $user_id; ?>)
                                        </td>
                                    </tr>
                                    <?php foreach ($data['richieste'] as $req): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($req['modulo_nome']); ?>
                                                <?php if ($req['is_new']): ?>
                                                    <span class="badge bg-danger ms-2">Nuovo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><i class="far fa-file-alt me-2 text-primary"></i>Richiesta (<?php echo htmlspecialchars($req['form_name']); ?>)</td>
                                            <td class="text-center"><?php echo date('d/m/Y H:i', strtotime($req['data_invio'])); ?></td>
                                            <td class="text-center">
                                                <?php
                                                    $status_class = 'bg-secondary';
                                                    if ($req['status'] === 'bozza') $status_class = 'bg-secondary';
                                                    if ($req['status'] === 'inviato') $status_class = 'bg-info';
                                                    if ($req['status'] === 'in_lavorazione') $status_class = 'bg-warning';
                                                    if ($req['status'] === 'completato') $status_class = 'bg-success';
                                                    if ($req['status'] === 'abbandonato') $status_class = 'bg-dark';
                                                    if ($req['status'] === 'inviato_in_cassa_edile') $status_class = 'bg-purple';
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $req['status'])); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="action-dropdown-<?php echo $req['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="action-dropdown-<?php echo $req['id']; ?>">
                                                        <?php
                                                            $module_path = '../servizi/moduli/modulo1.php';
                                                            $view_link = "{$module_path}?form_name=" . urlencode($req['form_name']) . "&user_id=" . urlencode($req['user_id']);
                                                        ?>
                                                        <li><a class="dropdown-item" href="<?php echo $view_link; ?>"><i class="fas fa-eye me-2"></i>Visualizza Dettagli</a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <?php if ($req['status'] !== 'bozza'): ?>
                                                            <form action="update_request_status.php" method="POST" class="d-inline">
                                                                <input type="hidden" name="form_name" value="<?php echo htmlspecialchars($req['form_name']); ?>">
                                                                <input type="hidden" name="new_status" value="inviato_in_cassa_edile">
                                                                <li><button type="submit" class="dropdown-item"><i class="fas fa-university me-2"></i>Invia a Cassa Edile</button></li>
                                                            </form>
                                                            <form action="update_request_status.php" method="POST" class="d-inline">
                                                                <input type="hidden" name="form_name" value="<?php echo htmlspecialchars($req['form_name']); ?>">
                                                                <input type="hidden" name="new_status" value="abbandonato">
                                                                <li><button type="submit" class="dropdown-item text-danger"><i class="fas fa-times-circle me-2"></i>Imposta come Abbandonato</button></li>
                                                            </form>
                                                        <?php else: ?>
                                                            <li><span class="dropdown-item disabled text-muted" title="Questa azione non è disponibile per le bozze"><i class="fas fa-university me-2"></i>Invia a Cassa Edile</span></li>
                                                            <li><span class="dropdown-item disabled text-muted" title="Questa azione non è disponibile per le bozze"><i class="fas fa-times-circle me-2"></i>Imposta come Abbandonato</span></li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Paginazione documenti">
                        <ul class="pagination justify-content-center mb-0">
                            <?php
                                // Preserva tutti i parametri GET tranne 'page'
                                $query_params = $_GET;
                                unset($query_params['page']);
                                $base_url = '?' . http_build_query($query_params);
                            ?>
                            <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                                <a class="page-link" href="<?php echo $base_url . '&page=' . ($page - 1); ?>">Precedente</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php if ($page == $i) echo 'active'; ?>"><a class="page-link" href="<?php echo $base_url . '&page=' . $i; ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                                <a class="page-link" href="<?php echo $base_url . '&page=' . ($page + 1); ?>">Successiva</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Logica per la sidebar mobile
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const sidebarToggle = document.getElementById('sidebarToggle');

        function toggleSidebar() {
            sidebar.classList.toggle('show');
            overlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
        }

        sidebarToggle.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // Logica per azzerare le notifiche al click sulla campanella
        $('#alertsDropdown').on('click', function() {
            const newNotifications = $(this).data('new-notifications');
            const badge = $(this).find('.badge');

            if (newNotifications > 0 && badge.length) {
                // Chiamata AJAX per marcare le notifiche come lette
                $.post('mark_notifications_read.php')
                    .done(function(response) {
                        if (response.status === 'success') {
                            // Rimuovi il badge visivamente
                            badge.remove();
                        }
                    });
            }
        });
    });
</script>
</body>
</html>