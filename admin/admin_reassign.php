<?php
session_start();

// Proteggi la pagina: solo i super-admin possono accedervi.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
    header('Location: index.php');
    exit;
}

include_once('../database.php');
$pdo1 = Database::getInstance('fillea');

// --- GESTIONE FILTRI, ORDINAMENTO E PAGINAZIONE ---

// 1. Filtri
$filter_user = $_GET['user_filter'] ?? 'all';
$filter_service = $_GET['service_filter'] ?? 'all';
$filter_status = $_GET['status_filter'] ?? 'all';
$filter_funzionario = $_GET['funzionario_filter'] ?? 'all';

// 2. Ordinamento
$allowed_sort_columns = ['id', 'utente', 'modulo_nome', 'status', 'funzionario'];
$sort_by = in_array($_GET['sort_by'] ?? '', $allowed_sort_columns) ? $_GET['sort_by'] : 'id';
$sort_dir = (isset($_GET['sort_dir']) && strtoupper($_GET['sort_dir']) === 'ASC') ? 'ASC' : 'DESC';

// 3. Paginazione
const ITEMS_PER_PAGE = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * ITEMS_PER_PAGE;

// --- FINE GESTIONE ---

// Recupera dati per i filtri
$servizi = $pdo1->query("SELECT DISTINCT modulo_nome FROM `fillea-app`.richieste_master")->fetchAll(PDO::FETCH_COLUMN);
$stati = $pdo1->query("SELECT DISTINCT status FROM `fillea-app`.richieste_master")->fetchAll(PDO::FETCH_COLUMN);
$utenti_richiedenti = $pdo1->query("SELECT DISTINCT u.id, u.cognome, u.nome FROM `fillea-app`.users u JOIN `fillea-app`.richieste_master rm ON u.id = rm.user_id ORDER BY u.cognome, u.nome")->fetchAll(PDO::FETCH_ASSOC);
$funzionari_filtro = $pdo1->query("SELECT id, funzionario FROM `fillea-app`.funzionari WHERE is_super_admin = 0 ORDER BY funzionario ASC")->fetchAll(PDO::FETCH_ASSOC);

// Costruzione della query base
$sql_base = "
    SELECT 
        rm.id,
        rm.form_name,
        rm.modulo_nome,
        rm.status,
        rm.id_funzionario,
        CONCAT(u.cognome, ' ', u.nome) as utente,
        f.funzionario AS nome_funzionario
    FROM 
        `fillea-app`.richieste_master rm
    JOIN 
        `fillea-app`.users u ON rm.user_id = u.id
    LEFT JOIN
        `fillea-app`.funzionari f ON rm.id_funzionario = f.id
";

// Aggiunta filtri
$conditions = [];
$params = [];

if ($filter_user !== 'all') {
    $conditions[] = "rm.user_id = ?";
    $params[] = $filter_user;
}
if ($filter_service !== 'all') {
    $conditions[] = "rm.modulo_nome = ?";
    $params[] = $filter_service;
}
if ($filter_status !== 'all') {
    $conditions[] = "rm.status = ?";
    $params[] = $filter_status;
}
if ($filter_funzionario !== 'all') {
    $conditions[] = "rm.id_funzionario = ?";
    $params[] = $filter_funzionario;
}

$where_clause = !empty($conditions) ? " WHERE " . implode(' AND ', $conditions) : '';

// Query per contare il totale dei record (per la paginazione)
$sql_count = "SELECT COUNT(rm.id) FROM `fillea-app`.richieste_master AS rm " . $where_clause;
$stmt_count = $pdo1->prepare($sql_count);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / ITEMS_PER_PAGE);

// Query per recuperare i record della pagina corrente
$sql_requests = $sql_base . $where_clause;

// Mappatura colonne di ordinamento
$sort_column_map = [
    'id' => 'rm.id',
    'utente' => 'utente',
    'modulo_nome' => 'rm.modulo_nome',
    'status' => 'rm.status',
    'funzionario' => 'nome_funzionario'
];
$sql_requests .= " ORDER BY " . $sort_column_map[$sort_by] . " " . $sort_dir;
$sql_requests .= " LIMIT " . ITEMS_PER_PAGE . " OFFSET " . $offset;

$stmt_requests = $pdo1->prepare($sql_requests);
$stmt_requests->execute($params);
$requests = $stmt_requests->fetchAll(PDO::FETCH_ASSOC);

// Recupera tutti i funzionari (non super-admin) per il dropdown
$sql_funzionari = "SELECT id, funzionario FROM `fillea-app`.funzionari WHERE is_super_admin = 0 ORDER BY funzionario ASC";
$stmt_funzionari = $pdo1->query($sql_funzionari);
$funzionari = $stmt_funzionari->fetchAll(PDO::FETCH_ASSOC);

// Funzione helper per generare i link di ordinamento
function get_sort_link($column, $current_sort_by, $current_sort_dir, $label) {
    $new_sort_dir = ($current_sort_by === $column && $current_sort_dir === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($current_sort_by === $column) {
        $icon = $current_sort_dir === 'ASC' ? '<i class="fas fa-sort-up ms-1"></i>' : '<i class="fas fa-sort-down ms-1"></i>';
    }
    $query_params = $_GET;
    $query_params['sort_by'] = $column;
    $query_params['sort_dir'] = $new_sort_dir;
    return '<a href="?' . http_build_query($query_params) . '">' . $label . $icon . '</a>';
}

// Funzione helper per generare i badge colorati per lo stato
function get_status_badge($status) {
    $status_map = [
        'bozza' => ['class' => 'bg-secondary', 'text' => 'Bozza'],
        'inviato' => ['class' => 'bg-primary', 'text' => 'Inviato'],
        'in_lavorazione' => ['class' => 'bg-info text-dark', 'text' => 'In Lavorazione'],
        'completato' => ['class' => 'bg-success', 'text' => 'Completato'],
        'abbandonato' => ['class' => 'bg-warning text-dark', 'text' => 'Abbandonato'],
        'inviato_in_cassa_edile' => ['class' => 'bg-dark', 'text' => 'Inviato in Cassa Edile']
    ];
    $display_text = $status_map[$status]['text'] ?? ucfirst(str_replace('_', ' ', $status));
    $class = $status_map[$status]['class'] ?? 'bg-light text-dark';
    return "<span class=\"badge {$class}\" style=\"font-size: 0.85em;\">" . htmlspecialchars($display_text) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Riassegnazione Pratiche</title>
    <link rel="icon" href="https://placehold.co/32x32/d0112b/ffffff?text=S">
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
        .form-select-sm {
            width: 250px;
        }
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
        <span>Super Admin</span>
    </a>
    <nav class="nav flex-column">
        <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-chart-bar me-2"></i> Dashboard</a>
        <a class="nav-link active" href="admin_reassign.php"><i class="fas fa-random me-2"></i> Riassegna Pratiche</a>
    </nav>
</aside>

<div class="content-wrapper">
    <nav class="navbar navbar-expand navbar-light topbar p-2 shadow-sm">
        <button id="sidebarToggle" class="btn btn-link text-dark d-md-none">
            <i class="fas fa-bars"></i>
        </button>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="me-2 d-none d-lg-inline text-gray-600 small">
                        <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                        <span class="badge bg-danger">Super</span>
                    </span>
                    <i class="fas fa-user-shield text-gray-600"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
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
            <h1 class="mb-4">Riassegnazione Pratiche</h1>

            <!-- Filtri -->
            <div class="card mb-4">
                <div class="card-header" style="background-color: var(--admin-primary); color: white;">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtra Pratiche</h5>
                </div>
                <div class="card-body">
                    <form action="admin_reassign.php" method="GET">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="userFilter" class="form-label">Lavoratore</label>
                                <select id="userFilter" name="user_filter" class="form-select">
                                    <option value="all">Tutti</option>
                                    <?php foreach ($utenti_richiedenti as $utente): ?>
                                        <option value="<?php echo $utente['id']; ?>" <?php if ($filter_user == $utente['id']) echo 'selected'; ?>><?php echo htmlspecialchars($utente['cognome'] . ' ' . $utente['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="serviceFilter" class="form-label">Servizio</label>
                                <select id="serviceFilter" name="service_filter" class="form-select">
                                    <option value="all">Tutti</option>
                                    <?php foreach ($servizi as $servizio): ?>
                                        <option value="<?php echo htmlspecialchars($servizio); ?>" <?php if ($filter_service === $servizio) echo 'selected'; ?>><?php echo htmlspecialchars($servizio); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="statusFilter" class="form-label">Stato</label>
                                <select id="statusFilter" name="status_filter" class="form-select">
                                    <option value="all">Tutti</option>
                                    <?php foreach ($stati as $stato): ?>
                                        <option value="<?php echo htmlspecialchars($stato); ?>" <?php if ($filter_status === $stato) echo 'selected'; ?>><?php echo ucfirst(str_replace('_', ' ', $stato)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="funzionarioFilter" class="form-label">Funzionario</label>
                                <select id="funzionarioFilter" name="funzionario_filter" class="form-select">
                                    <option value="all">Tutti</option>
                                    <?php foreach ($funzionari_filtro as $funzionario): ?>
                                        <option value="<?php echo $funzionario['id']; ?>" <?php if ($filter_funzionario == $funzionario['id']) echo 'selected'; ?>><?php echo htmlspecialchars($funzionario['funzionario']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Applica</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Pratica riassegnata con successo!</div>
            <?php elseif (isset($_GET['error'])): ?>
                <div class="alert alert-danger">Si è verificato un errore durante la riassegnazione.</div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header" style="background-color: var(--admin-primary); color: white;">
                    <h5 class="mb-0">Elenco di tutte le pratiche</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th><?php echo get_sort_link('id', $sort_by, $sort_dir, 'ID'); ?></th>
                                    <th><?php echo get_sort_link('utente', $sort_by, $sort_dir, 'Utente'); ?></th>
                                    <th><?php echo get_sort_link('modulo_nome', $sort_by, $sort_dir, 'Servizio'); ?></th>
                                    <th><?php echo get_sort_link('status', $sort_by, $sort_dir, 'Stato'); ?></th>
                                    <th><?php echo get_sort_link('funzionario', $sort_by, $sort_dir, 'Funzionario Attuale'); ?></th>
                                    <th>Riassegna a</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr><td colspan="6" class="text-center p-4">Nessuna pratica trovata con i filtri applicati.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo $request['id']; ?></td>
                                        <td><?php echo htmlspecialchars($request['utente']); ?></td>
                                        <td><?php echo htmlspecialchars($request['modulo_nome']); ?></td>
                                        <td><?php echo get_status_badge($request['status']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($request['nome_funzionario'] ?? 'Non assegnato'); ?>
                                        </td>
                                        <td>
                                            <form action="admin_update_assignment.php" method="POST" class="d-flex align-items-center">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <select name="new_funzionario_id" class="form-select form-select-sm me-2">
                                                    <option value="">Seleziona...</option>
                                                    <?php foreach ($funzionari as $funzionario): ?>
                                                        <option value="<?php echo $funzionario['id']; ?>" <?php if ($funzionario['id'] == $request['id_funzionario']) echo 'selected'; ?>>
                                                            <?php echo htmlspecialchars($funzionario['funzionario']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-check"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Paginazione pratiche">
                        <ul class="pagination justify-content-center mb-0">
                            <?php
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

<!-- Overlay per chiudere la sidebar su mobile -->
<div id="sidebarOverlay" class="overlay"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
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


<!--
[PROMPT_SUGGESTION]Crea una pagina per permettere ai funzionari di cambiare la propria password.[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]Aggiungi la possibilità di filtrare le pratiche nella pagina di riassegnazione.[/PROMPT_SUGGESTION]
