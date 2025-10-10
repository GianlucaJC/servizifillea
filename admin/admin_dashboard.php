<?php
session_start();

// Proteggi la pagina: solo i super-admin possono accedervi.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
    header('Location: index.php');
    exit;
}

include_once('../database.php');
$pdo1 = Database::getInstance('fillea');

// Query per ottenere le statistiche
// Conta le richieste per ogni funzionario, raggruppandole per stato.
$sql = "
    SELECT 
        f.id AS funzionario_id,
        f.funzionario AS nome_funzionario,
        rm.status AS stato_richiesta,
        COUNT(rm.id) AS numero_pratiche
    FROM 
        `fillea-app`.funzionari f
    JOIN 
        `fillea-app`.richieste_master rm ON f.id = rm.id_funzionario
    WHERE
        f.is_super_admin = 0 -- Escludi i super-admin stessi dalle statistiche
    GROUP BY 
        f.id, f.funzionario, rm.status
    ORDER BY 
        f.funzionario, rm.status;
";

$stmt = $pdo1->prepare($sql);
$stmt->execute();
$stats_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizza i dati in una struttura più comoda per la visualizzazione
$stats_by_funzionario = [];
foreach ($stats_raw as $row) {
    $stats_by_funzionario[$row['nome_funzionario']][$row['stato_richiesta']] = $row['numero_pratiche'];
}

// Elenco di tutti gli stati possibili per avere colonne consistenti
$stati_possibili = [
    'bozza',
    'inviato',
    'in_lavorazione',
    'completato',
    'abbandonato',
    'inviato_in_cassa_edile'
];

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
    return "<span class=\"badge {$class}\" style=\"font-size: 0.9em;\">" . htmlspecialchars($display_text) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Dashboard Statistiche</title>
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
        <a class="nav-link active" href="admin_dashboard.php"><i class="fas fa-chart-bar me-2"></i> Dashboard</a>
        <a class="nav-link" href="admin_reassign.php"><i class="fas fa-random me-2"></i> Riassegna Pratiche</a>
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
            <h1 class="mb-4">Dashboard Statistiche</h1>

            <div class="card">
                <div class="card-header" style="background-color: var(--admin-primary); color: white;">
                    <h5 class="mb-0">Riepilogo Pratiche per Funzionario</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Funzionario</th>
                                    <?php foreach ($stati_possibili as $stato): ?>
                                        <th class="text-center"><?php echo get_status_badge($stato); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($stats_by_funzionario)): ?>
                                    <tr><td colspan="<?php echo count($stati_possibili) + 1; ?>" class="text-center p-4">Nessun dato da mostrare.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($stats_by_funzionario as $nome_funzionario => $stati): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($nome_funzionario); ?></strong></td>
                                            <?php foreach ($stati_possibili as $stato_colonna): ?>
                                                <td class="text-center"><?php echo $stati[$stato_colonna] ?? 0; ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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