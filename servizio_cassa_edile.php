<?php
session_start();

// 1. Recupera e verifica il token per la sicurezza
$token = $_GET['token'] ?? '';
$is_user_logged_in = false;

if (!empty($token)) {
    include_once("database.php");
    $pdo1 = Database::getInstance('fillea');

    $sql = "SELECT id FROM `fillea-app`.users WHERE token = ? AND token_expiry > NOW() LIMIT 1";
    $stmt = $pdo1->prepare($sql);
    $stmt->execute([$token]);

    if ($stmt->fetch()) {
        $is_user_logged_in = true;
    }
    $pdo1 = null;
}

// 2. Se l'utente non è loggato, reindirizza alla pagina di login
if (!$is_user_logged_in) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prestazioni Cassa Edile</title>
    <link rel="icon" href="https://placehold.co/32x32/d0112b/ffffff?text=C">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .service-container {
            max-width: 800px;
            background-color: #fff;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        .service-header {
            color: #d0112b;
        }
        /* Stili per la nuova visualizzazione a card */
        .service-card-link {
            text-decoration: none;
            color: inherit;
        }
        .service-card {
            border: 1px solid #e9ecef;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            height: 100%;
        }
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.08);
        }
        .service-card .card-body {
            display: flex;
            align-items: center;
        }
        .service-card .icon {
            font-size: 1.75rem;
            width: 50px;
            color: #d0112b;
        }
        .list-group-item-action:hover {
            background-color: #f1f1f1;
            color: #d0112b;
        }
        .list-group-item-action .icon {
            font-size: 1.75rem;
            width: 50px;
            color: #d0112b;
        }
        .service-card .content {
            margin-left: 1.5rem;
        }
        .service-card .content h5 {
            font-weight: 600;
        }
        /* Nuovi stili per le card delle prestazioni */
        .prestazione-card {
            display: flex;
            align-items: center;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            color: #212529;
            height: 100%;
        }
        .prestazione-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: #d0112b;
        }
        .prestazione-card.disabled {
            background-color: #f8f9fa;
            opacity: 0.6;
            cursor: not-allowed;
        }
        .prestazione-icon {
            font-size: 1.5rem;
            color: #d0112b;
            margin-right: 1rem;
            width: 30px;
            text-align: center;
        }
        .prestazione-text {
            font-weight: 500;
        }
    </style>
</head>
<body>

<!-- Barra superiore fissa -->
<div class="sticky-top bg-white shadow-sm">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center py-3">
            <div>
                <a href="servizi.php?token=<?php echo htmlspecialchars($token); ?>" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Torna ai Servizi</a>
            </div>
            <div>
                <div class="dropdown">
                    <button class="btn rounded-circle" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #d0112b; color: white; width: 48px; height: 48px;">
                        <i class="fas fa-user fa-lg"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuButton">
                        <li><a class="dropdown-item" href="profilo.php?token=<?php echo htmlspecialchars($token); ?>">Profilo</a></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="container service-container">
    <div class="text-center mb-5">
        <i class="fas fa-hand-holding-dollar fa-3x service-header"></i>
        <h2 class="mt-3">Prestazioni Cassa Edile</h2>
        <p class="text-muted">Seleziona il tipo di prestazione che desideri richiedere.</p>
    </div>

    <div class="alert alert-info border-start border-4 border-info">
        <p class="mb-2">La Cassa Edile effettua a favore degli operai regolarmente iscritti numerose prestazioni decise dalle Organizzazioni Territoriali dei Datori di Lavoro e dei lavoratori.</p>
        <p class="mb-0">Invitiamo gli interessati a prendere visione del Regolamento e stampare le domande da inviare alla Cassa Edile.</p>
    </div>

    <!-- Regolamento Attuale -->
    <h4 class="mt-5 service-header">REGOLAMENTO DELLE PRESTAZIONI VALIDITA’ DAL 10/2022 CON AGGIORNAMENTI 01/2024</h4>
    <div class="list-group mt-3">
        <a href="https://www.cassaedilefirenze.it/wp-content/uploads/2024/03/REGOLAMENTO-DEFINITIVO-PRESTAZIONI-3-2024-2.2.pdf" target='_blank' class="list-group-item list-group-item-action d-flex align-items-center">
            <div class="icon"><i class="fas fa-file-pdf fa-fw"></i></div>
            <span class="ms-3">Regolamento delle prestazioni</span>
        </a>
    </div>

    <!-- Modulistica -->
    <h4 class="mt-5 service-header">MODULISTICA PER RICHIEDERE LE PRINCIPALI PRESTAZIONI</h4>

    <!--inizio lista!-->
    <div class="my-5">
        <p class="text-muted mb-4">Seleziona una delle prestazioni attive per compilare la richiesta online. Le altre prestazioni saranno rese disponibili a breve.</p>
        
        <div class="row g-3">

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-ring"></i></div>
                    <div class="prestazione-text">PREMIO MATRIMONIALE O UNIONE CIVILE</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                    <div class="prestazione-text">PREMIO GIOVANI</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-baby"></i></div>
                    <div class="prestazione-text">BONUS NASCITA FIGLI O ADOZIONE</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                    <div class="prestazione-text">CONTRIBUTO AFFITTO CASA</div>
                </a>
            </div>
            
            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-bell"></i></div>
                    <div class="prestazione-text">CONTRIBUTO PER INGIUNZIONE SFATTO</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-house-chimney"></i></div>
                    <div class="prestazione-text">CONTRIBUTO MUTUO 1^ CASA</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-pills"></i></div>
                    <div class="prestazione-text">PREMIO PER DONAZIONE SANGUE</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-wheelchair"></i></div>
                    <div class="prestazione-text">CONTRIBUTO PER FIGLI PORTATORI DI DIVERSA ABILITA'</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-gavel"></i></div>
                    <div class="prestazione-text">CONTRIBUTO PER INSINUAZIONE ALLO STATO PASSIVO DI PROCEDURE CONCORSUALI</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-briefcase"></i></div>
                    <div class="prestazione-text">CONTRIBUTO LICENZIAMENTO PER SUPERAMENTO PERIODO DI COMPORTO</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="servizi/moduli/modulo1.php?token=<?php echo htmlspecialchars($token); ?>" class="prestazione-card">
                    <div class="prestazione-icon"><i class="fa-solid fa-children"></i></div>
                    <div class="prestazione-text">CONTRIBUTO PER CENTRI ESTIVI PER I FIGLI</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-plane-departure"></i></div>
                    <div class="prestazione-text">RIMBORSO PERMESSO DI SOGGIORNO</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-futbol"></i></div>
                    <div class="prestazione-text">CONTRIBUTO ATTIVITA' SPORTIVE</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="servizi/moduli/modulo1.php?token=<?php echo htmlspecialchars($token); ?>" class="prestazione-card">
                    <div class="prestazione-icon"><i class="fa-solid fa-school"></i></div>
                    <div class="prestazione-text">CONTRIBUTO PER ASILO NIDO</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="servizi/moduli/modulo1.php?token=<?php echo htmlspecialchars($token); ?>" class="prestazione-card">
                    <div class="prestazione-icon"><i class="fa-solid fa-book-open"></i></div>
                    <div class="prestazione-text">CONTRIBUTO STUDIO SCUOLE ELEMENTARI</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="servizi/moduli/modulo1.php?token=<?php echo htmlspecialchars($token); ?>" class="prestazione-card">
                    <div class="prestazione-icon"><i class="fa-solid fa-book"></i></div>
                    <div class="prestazione-text">CONTRIBUTO STUDIO SCUOLE MEDIE INFERIORI</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="servizi/moduli/modulo1.php?token=<?php echo htmlspecialchars($token); ?>" class="prestazione-card">
                    <div class="prestazione-icon"><i class="fa-solid fa-school-flag"></i></div>
                    <div class="prestazione-text">CONTRIBUTO ISCRIZIONE SCUOLE MEDIE SUPERIORI</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-star"></i></div>
                    <div class="prestazione-text">CONTRIBUTO PROFITTO SCUOLE MEDIE SUPERIORI</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="servizi/moduli/modulo1.php?token=<?php echo htmlspecialchars($token); ?>" class="prestazione-card">
                    <div class="prestazione-icon"><i class="fa-solid fa-building-columns"></i></div>
                    <div class="prestazione-text">CONTRIBUTO ISCRIZIONE UNIVERSITA'</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-award"></i></div>
                    <div class="prestazione-text">CONTRIBUTO PROFITTO UNIVERSITA'</div>
                </a>
            </div>
            
            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-bed"></i></div>
                    <div class="prestazione-text">CONTRIBUTO GIORNALIERO PER MALATTIA SUPERIORE A 271 GG</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-suitcase-medical"></i></div>
                    <div class="prestazione-text">INTEGRAZIONE MALATTIA PER RICOVERO OSPEDALIERO</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-file-invoice"></i></div>
                    <div class="prestazione-text">RIMBORSO SPESE PER 730 PRESSO CAF CGIL-CISL-UIL</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-person-digging"></i></div>
                    <div class="prestazione-text">CONTRIBUTO PER INABILITA' DA INFORTUNIO SUL LAVORO DAL 91° GG</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-skull-crossbones"></i></div>
                    <div class="prestazione-text">CONTRIBUTO PER MORTE DA INFORTUNIO SUL LAVORO O PER MALATTIA PROFESSIONALE</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-person-falling-burst"></i></div>
                    <div class="prestazione-text">CONTRIBUTO PER MORTE DA MALATTIA E INFORTUNIO EXTRA PROFESSIONALE</div>
                </a>
            </div>
            
            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-briefcase-medical"></i></div>
                    <div class="prestazione-text">CONTRIBUTO DI 6 MENSILITA' NASPI PER PERIODO ASPETTATIVA RETRIBUITA PER SUPERAMENTO PERIODO DI COMPORTO P</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-user-graduate"></i></div>
                    <div class="prestazione-text">RETTA MENSILE PER ASSISTENZA ALLO STUDIO DEI FIGLI DEI DECEDUTI SUL LAVORO</div>
                </a>
            </div>

            <div class="col-6 col-md-6">
                <a href="#" class="prestazione-card disabled">
                    <div class="prestazione-icon"><i class="fa-solid fa-credit-card"></i></div>
                    <div class="prestazione-text">COMUNICAZIONE IBAN BANCARIO E CORRETTI DATI ANAGRAFICI</div>
                </a>
            </div>

        </div>
    </div>    
    <!-- fine lista !-->

    <!-- Regolamenti Precedenti -->
    <h4 class="mt-5 service-header">REGOLAMENTI PRESTAZIONI PERIODI PRECEDENTI NON PIU’ IN VIGORE</h4>
    <div class="list-group mt-3">
        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">
            <div class="icon"><i class="fas fa-history fa-fw"></i></div>
            <span class="ms-3">Regolamento prestazioni da Gennaio 2021 a Settembre 2022</span>
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">
            <div class="icon"><i class="fas fa-history fa-fw"></i></div>
            <span class="ms-3">Regolamento prestazioni dal 01 Gennaio 2018</span>
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">
            <div class="icon"><i class="fas fa-history fa-fw"></i></div>
            <span class="ms-3">Regolamento prestazioni dal 1° luglio 2012</span>
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">
            <div class="icon"><i class="fas fa-history fa-fw"></i></div>
            <span class="ms-3">Regolamento prestazioni dal 1° gennaio 2006</span>
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">
            <div class="icon"><i class="fas fa-university fa-fw"></i></div>
            <span class="ms-3">Integrazioni regolamento prestazioni contributo università</span>
        </a>
    </div>

    <!-- Fornitura Vestiario -->
    <h4 class="mt-5 service-header">FORNITURA ESTIVA E INVERNALE VESTIARIO E SCARPE DA LAVORO</h4>
    <div class="list-group mt-3">
        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">
            <div class="icon"><i class="fas fa-tshirt fa-fw"></i></div>
            <span class="ms-3">Visualizza Fornitura Invernale 2022</span>
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">
            <div class="icon"><i class="fas fa-sun fa-fw"></i></div>
            <span class="ms-3">Visualizza Fornitura Estiva 2021</span>
        </a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>